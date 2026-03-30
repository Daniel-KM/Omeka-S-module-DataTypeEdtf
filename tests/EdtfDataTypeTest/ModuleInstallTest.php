<?php declare(strict_types=1);

namespace EdtfDataTypeTest;

use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Integration tests for module install/config.
 *
 * Requires the Omeka application (database, service manager).
 */
class ModuleInstallTest extends AbstractHttpControllerTestCase
{
    use EdtfDataTypeTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
    }

    public function tearDown(): void
    {
        $this->logout();
        parent::tearDown();
    }

    public function testModuleIsActive(): void
    {
        $services = $this->getApplication()->getServiceManager();
        $moduleManager = $services->get('Omeka\ModuleManager');
        $module = $moduleManager->getModule('EdtfDataType');
        $this->assertNotNull($module, 'EdtfDataType module should be installed');
        $this->assertSame(
            'active',
            $module->getState(),
            'EdtfDataType module should be active'
        );
    }

    public function testEdtfDataTypeIsRegistered(): void
    {
        $services = $this->getApplication()->getServiceManager();
        $dataTypeManager = $services->get('Omeka\DataTypeManager');
        $this->assertTrue(
            $dataTypeManager->has('edtf:date'),
            'Data type edtf:date should be registered'
        );
    }

    public function testEdtfTableExists(): void
    {
        $conn = $this->getConnection();
        $sm = $conn->getSchemaManager();
        $this->assertTrue(
            $sm->tablesExist(['edtf_data_type_edtf']),
            'Table edtf_data_type_edtf should exist'
        );
    }

    public function testEdtfTableHasExpectedColumns(): void
    {
        $conn = $this->getConnection();
        $sm = $conn->getSchemaManager();
        $columns = $sm->listTableColumns('edtf_data_type_edtf');
        $columnNames = array_keys($columns);
        $this->assertContains('id', $columnNames);
        $this->assertContains('resource_id', $columnNames);
        $this->assertContains('property_id', $columnNames);
        $this->assertContains('value', $columnNames);
        $this->assertSame('string', $columns['value']->getType()->getName());
    }

    public function testViewHelpersRegistered(): void
    {
        $services = $this->getApplication()->getServiceManager();
        $viewHelpers = $services->get('ViewHelperManager');
        $this->assertTrue($viewHelpers->has('formEdtf'));
        $this->assertTrue($viewHelpers->has('formConvertToEdtf'));
        $this->assertTrue($viewHelpers->has('edtfPropertySelect'));
    }
}
