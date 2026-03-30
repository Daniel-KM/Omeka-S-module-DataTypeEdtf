<?php declare(strict_types=1);

namespace DataTypeEdtf\Job;

use DataTypeEdtf\DataType\Edtf as EdtfDataType;
use Omeka\Job\AbstractJob;
use Omeka\Module\Manager as ModuleManager;

/**
 * Migrate data from the legacy "EdtfDataType" module to "DataTypeEdtf".
 *
 * The stored EDTF strings in the Omeka "value" column are NOT modified: items
 * keep their exact metadata. This job only updates internal indexes, the
 * declared data type id ("edtf:date" => "edtf") and uninstalls the legacy
 * module.
 */
class MigrateFromLegacy extends AbstractJob
{
    public function perform()
    {
        $services = $this->getServiceLocator();
        $conn = $services->get('Omeka\Connection');
        $logger = $services->get('Omeka\Logger');
        $moduleManager = $services->get('Omeka\ModuleManager');
        $dataType = new EdtfDataType();

        $legacy = $moduleManager->getModule('EdtfDataType');
        if (!$legacy
            || $legacy->getState() === ModuleManager::STATE_NOT_INSTALLED
            || $legacy->getState() === ModuleManager::STATE_NOT_FOUND
        ) {
            $logger->notice('DataTypeEdtf migration: legacy module not installed, nothing to do.'); // @translate
            return;
        }

        // Update stored data type id in the value table.
        $updated = $conn->executeStatement("UPDATE value SET type = 'edtf' WHERE type = 'edtf:date'");
        $logger->info(sprintf('DataTypeEdtf migration: %d value(s) updated from "edtf:date" to "edtf".', $updated)); // @translate

        // Update the data type id referenced by resource templates.
        // resource_template_property.data_type stores a JSON array of
        // data type ids. The module AdvancedResourceTemplate (ART)
        // also keeps a JSON copy of the data_type array inside its
        // own resource_template_property_data.data and
        // resource_template_data.data longtext columns. A literal
        // REPLACE on the quoted token is safe and exact for all of
        // them because the quotes anchor the match. The ART tables
        // may not exist; swallow the error in that case.
        $tplTables = [
            'resource_template_property' => 'data_type',
            'resource_template_property_data' => 'data',
            'resource_template_data' => 'data',
        ];
        foreach ($tplTables as $table => $col) {
            try {
                $n = $conn->executeStatement(
                    "UPDATE {$table} SET {$col} = REPLACE({$col}, '\"edtf:date\"', '\"edtf\"') "
                    . "WHERE {$col} LIKE '%\"edtf:date\"%'"
                );
                $logger->info(sprintf('DataTypeEdtf migration: %d row(s) updated in %s.%s.', $n, $table, $col)); // @translate
            } catch (\Throwable $e) {
                // Table absent (ART not installed) — ignore silently.
            }
        }

        // Start from an empty target table so the migration is idempotent
        // after an interrupted previous run.
        $conn->executeStatement('DELETE FROM data_type_edtf');

        // Iterate legacy index rows in batches and compute bounds.
        // Each batch is inserted via a single multi-row INSERT inside a
        // transaction to amortize round-trips on large datasets.
        $batchSize = 500;
        $offset = 0;
        $migrated = 0;
        $failed = 0;
        while (!$this->shouldStop()) {
            $rows = $conn->fetchAllAssociative(
                'SELECT resource_id, property_id, value FROM edtf_data_type_edtf ORDER BY id LIMIT ? OFFSET ?',
                [$batchSize, $offset],
                [\Doctrine\DBAL\ParameterType::INTEGER, \Doctrine\DBAL\ParameterType::INTEGER]
            );
            if (empty($rows)) {
                break;
            }
            $insertRows = [];
            foreach ($rows as $row) {
                try {
                    [$minDate, $minTime, $maxDate, $maxTime] = $dataType->getValueBounds($row['value']);
                } catch (\Throwable $e) {
                    $failed++;
                    $logger->notice(sprintf(
                        'DataTypeEdtf migration: failed to parse value "%s" (resource %d): %s', // @translate
                        $row['value'], $row['resource_id'], $e->getMessage()
                    ));
                    continue;
                }
                $insertRows[] = [
                    (int) $row['resource_id'],
                    (int) $row['property_id'],
                    $minDate,
                    $minTime,
                    $maxDate,
                    $maxTime,
                ];
            }
            if ($insertRows) {
                $placeholders = implode(', ', array_fill(0, count($insertRows), '(?, ?, ?, ?, ?, ?)'));
                $params = array_merge(...$insertRows);
                $conn->beginTransaction();
                try {
                    $conn->executeStatement(
                        'INSERT INTO data_type_edtf (resource_id, property_id, value_min_date, value_min_time, value_max_date, value_max_time) VALUES ' . $placeholders,
                        $params
                    );
                    $conn->commit();
                } catch (\Throwable $e) {
                    $conn->rollBack();
                    throw $e;
                }
                $migrated += count($insertRows);
            }
            $offset += $batchSize;
            $logger->info(sprintf('DataTypeEdtf migration: %d row(s) processed.', $migrated)); // @translate
        }

        if ($this->shouldStop()) {
            $logger->warn('DataTypeEdtf migration: interrupted; run the job again to finish.'); // @translate
            return;
        }

        // Drop legacy table and uninstall legacy module.
        $conn->executeStatement('DROP TABLE IF EXISTS edtf_data_type_edtf');
        $conn->executeStatement('DELETE FROM module WHERE id = ?', ['EdtfDataType']);

        $logger->info(sprintf(
            'DataTypeEdtf migration complete: %d row(s) migrated, %d failure(s). The legacy module "EdtfDataType" has been uninstalled.', // @translate
            $migrated, $failed
        ));
    }
}
