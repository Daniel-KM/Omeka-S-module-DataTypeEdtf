<?php declare(strict_types=1);

namespace DataTypeEdtf;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
 */
$plugins = $services->get('ControllerPluginManager');
$translate = $plugins->get('translate');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');

if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.84')) {
    $message = new \Omeka\Stdlib\Message(
        $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
        'Common', '3.4.84'
    );
    $messenger->addError($message);
    throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $translate('Missing requirement. Unable to upgrade.')); // @translate
}

if (version_compare((string) $oldVersion, '3.4.5', '<')) {
    // Align indexes on table data_type_edtf with the Doctrine-generated schema
    // and add a covering composite idx_prop_res (property_id, resource_id) for
    // DISTINCT property queries joining resource.
    //
    // Order: add new indexes first, then drop the legacy custom-named ones.
    // MySQL refuses to drop an index still required by a foreign key, so the FK
    // must already see another index covering its column before drop.
    $sm = $connection->getSchemaManager();
    $indexes = $sm->listTableIndexes('data_type_edtf');

    // Add the Doctrine FK-backing indexes if not already present (under any
    // name). The FK only requires an index whose first column is the FK column.
    $hasPropertyIdx = false;
    $hasResourceIdx = false;
    foreach ($indexes as $name => $idx) {
        if (in_array($name, ['idx_property', 'idx_resource'], true)) {
            continue;
        }
        $cols = $idx->getColumns();
        if (!empty($cols) && $cols[0] === 'property_id') {
            $hasPropertyIdx = true;
        }
        if (!empty($cols) && $cols[0] === 'resource_id') {
            $hasResourceIdx = true;
        }
    }
    if (!$hasPropertyIdx) {
        $connection->executeStatement('ALTER TABLE `data_type_edtf` ADD INDEX `IDX_D5404670549213EC` (`property_id`)');
    }
    if (!$hasResourceIdx) {
        $connection->executeStatement('ALTER TABLE `data_type_edtf` ADD INDEX `IDX_D540467089329D25` (`resource_id`)');
    }
    if (!isset($indexes['idx_prop_res'])) {
        $connection->executeStatement('ALTER TABLE `data_type_edtf` ADD INDEX `idx_prop_res` (`property_id`, `resource_id`)');
    }

    // Drop legacy custom-named indexes after FK-backing ones exist.
    if (isset($indexes['idx_property'])) {
        $connection->executeStatement('ALTER TABLE `data_type_edtf` DROP INDEX `idx_property`');
    }
    if (isset($indexes['idx_resource'])) {
        $connection->executeStatement('ALTER TABLE `data_type_edtf` DROP INDEX `idx_resource`');
    }

    $message = new \Omeka\Stdlib\Message(
        $translate('The indexes on database table were updated for faster lookups.') // @translate
    );
    $messenger->addSuccess($message);
}
