<?php declare(strict_types=1);

/**
 * Migrate data from the legacy "EdtfDataType" module to "DataTypeEdtf".
 *
 * The same script is available as job in the config form of the module in admin
 * interface.
 *
 * Run this script from the Omeka S root directory:
 *
 * ```
 * cd /path/to/omeka-s
 * php modules/DataTypeEdtf/data/scripts/migrate-from-legacy.php
 * ```
 *
 * The stored EDTF strings in the Omeka "value" column are NOT modified. Items
 * are unchanged.
 *
 * Three things change:
 * - data type id in the "value" table: "edtf:date" => "edtf"
 * - JSON-LD @type returned by the API: "xsd:string" => precise XSD types or LoC EDTF URI
 * - advanced-search query format: "edtf[date][gte][<pid>]" => "edtf[gte][<pid>]"
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

// Locate Omeka S root.
$root = realpath(__DIR__ . '/../../../..');
if (!$root || !file_exists($root . '/bootstrap.php')) {
    fwrite(STDERR, "Cannot locate Omeka S root directory.\n");
    exit(1);
}
chdir($root);
require $root . '/bootstrap.php';

$application = \Omeka\Mvc\Application::init(require $root . '/application/config/application.config.php');
$services = $application->getServiceManager();

$conn = $services->get('Omeka\Connection');
$logger = $services->get('Omeka\Logger');
$moduleManager = $services->get('Omeka\ModuleManager');

$legacy = $moduleManager->getModule('EdtfDataType');
if (!$legacy
    || $legacy->getState() === \Omeka\Module\Manager::STATE_NOT_FOUND
    || $legacy->getState() === \Omeka\Module\Manager::STATE_NOT_INSTALLED
) {
    echo "The legacy module \"EdtfDataType\" is not installed; nothing to migrate.\n";
    exit(0);
}

// Load the new module's EDTF data type class directly so we can compute min/max
// bounds (the class is not autoloaded while the module is not installed).
require_once $root . '/modules/DataTypeEdtf/vendor/autoload.php';
$classLoader = new \Composer\Autoload\ClassLoader();
$classLoader->addPsr4('DataTypeEdtf\\', $root . '/modules/DataTypeEdtf/src/');
$classLoader->register();

$dataType = new \DataTypeEdtf\DataType\Edtf();

$countQuery = $conn->fetchOne('SELECT COUNT(*) FROM edtf_data_type_edtf');
$total = (int) $countQuery;
echo "Starting migration of {$total} row(s) from edtf_data_type_edtf.\n";

// Step 1: update data type id in the value table.
$updated = $conn->executeStatement("UPDATE value SET type = 'edtf' WHERE type = 'edtf:date'");
echo "Updated {$updated} value(s): data type id 'edtf:date' -> 'edtf'.\n";

// Step 2: create the new index table if it does not exist.
try {
    $conn->executeStatement(<<<'SQL'
        CREATE TABLE data_type_edtf (
            id INT AUTO_INCREMENT NOT NULL,
            resource_id INT NOT NULL,
            property_id INT NOT NULL,
            value_min BIGINT NOT NULL,
            value_max BIGINT NOT NULL,
            INDEX idx_resource (resource_id),
            INDEX idx_property (property_id),
            INDEX idx_property_value_min (property_id, value_min),
            INDEX idx_property_value_max (property_id, value_max),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;
        SQL);
    $conn->executeStatement('ALTER TABLE data_type_edtf ADD CONSTRAINT fk_edtf_resource FOREIGN KEY (resource_id) REFERENCES resource (id) ON DELETE CASCADE;');
    $conn->executeStatement('ALTER TABLE data_type_edtf ADD CONSTRAINT fk_edtf_property FOREIGN KEY (property_id) REFERENCES property (id) ON DELETE CASCADE;');
} catch (\Exception $e) {
    // Already created.
}

// Step 3: iterate legacy rows in batches and compute bounds.
$batchSize = 500;
$offset = 0;
$migrated = 0;
$failed = 0;
while (true) {
    $rows = $conn->fetchAllAssociative(
        'SELECT resource_id, property_id, value FROM edtf_data_type_edtf ORDER BY id LIMIT ? OFFSET ?',
        [$batchSize, $offset],
        [\Doctrine\DBAL\ParameterType::INTEGER, \Doctrine\DBAL\ParameterType::INTEGER]
    );
    if (empty($rows)) {
        break;
    }
    foreach ($rows as $row) {
        try {
            [$min, $max] = $dataType->getValueBounds($row['value']);
        } catch (\Throwable $e) {
            $failed++;
            fwrite(STDERR, sprintf(
                "  failed to parse value %s (resource %d): %s\n",
                var_export($row['value'], true), $row['resource_id'], $e->getMessage()
            ));
            continue;
        }
        $conn->insert('data_type_edtf', [
            'resource_id' => $row['resource_id'],
            'property_id' => $row['property_id'],
            'value_min' => $min,
            'value_max' => $max,
        ]);
        $migrated++;
    }
    $offset += $batchSize;
    echo "  {$migrated}/{$total} rows processed.\n";
}

// Step 4: drop legacy table (foreign keys already added in step 2).
$conn->executeStatement('DROP TABLE IF EXISTS edtf_data_type_edtf');

// Step 5: mark legacy module as uninstalled.
$conn->executeStatement('DELETE FROM module WHERE id = ?', ['EdtfDataType']);
echo "Legacy module EdtfDataType uninstalled.\n";

// Step 6: install DataTypeEdtf (its install() will succeed now that the legacy
// module is gone and the table already exists).
$new = $moduleManager->getModule('DataTypeEdtf');
if ($new && $new->getState() !== \Omeka\Module\Manager::STATE_ACTIVE) {
    // The install() method would try to CREATE TABLE again; since the table
    // already exists from step 2, we insert the module row directly and mark it
    // active.
    $conn->executeStatement(
        'INSERT INTO module (id, is_active, version) VALUES (?, 1, ?) ON DUPLICATE KEY UPDATE is_active = 1, version = VALUES(version)',
        ['DataTypeEdtf', $new->getIni('version')]
    );
    echo "Module DataTypeEdtf installed and activated.\n";
}

echo "Migration complete: {$migrated} row(s) migrated, {$failed} failure(s).\n";
exit($failed > 0 ? 1 : 0);
