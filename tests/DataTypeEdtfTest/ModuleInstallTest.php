<?php declare(strict_types=1);

namespace DataTypeEdtfTest;

use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Integration tests for module install/config.
 *
 * Requires the Omeka application (database, service manager).
 */
class ModuleInstallTest extends AbstractHttpControllerTestCase
{
    use DataTypeEdtfTestTrait;

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
        $module = $moduleManager->getModule('DataTypeEdtf');
        $this->assertNotNull($module, 'DataTypeEdtf module should be installed');
        $this->assertSame(
            'active',
            $module->getState(),
            'DataTypeEdtf module should be active'
        );
    }

    public function testDataTypeEdtfIsRegistered(): void
    {
        $services = $this->getApplication()->getServiceManager();
        $dataTypeManager = $services->get('Omeka\DataTypeManager');
        $this->assertTrue(
            $dataTypeManager->has('edtf'),
            'Data type edtf:date should be registered'
        );
    }

    public function testEdtfTableExists(): void
    {
        $conn = $this->getConnection();
        $sm = $conn->getSchemaManager();
        $this->assertTrue(
            $sm->tablesExist(['data_type_edtf']),
            'Table data_type_edtf should exist'
        );
    }

    public function testEdtfTableHasExpectedColumns(): void
    {
        $conn = $this->getConnection();
        $sm = $conn->getSchemaManager();
        $columns = $sm->listTableColumns('data_type_edtf');
        $columnNames = array_keys($columns);
        $this->assertContains('id', $columnNames);
        $this->assertContains('resource_id', $columnNames);
        $this->assertContains('property_id', $columnNames);
        $this->assertContains('value_min_date', $columnNames);
        $this->assertContains('value_min_time', $columnNames);
        $this->assertContains('value_max_date', $columnNames);
        $this->assertContains('value_max_time', $columnNames);
        $this->assertSame('bigint', $columns['value_min_date']->getType()->getName());
        $this->assertSame('integer', $columns['value_min_time']->getType()->getName());
        $this->assertSame('bigint', $columns['value_max_date']->getType()->getName());
        $this->assertSame('integer', $columns['value_max_time']->getType()->getName());
    }

    public function testViewHelpersRegistered(): void
    {
        $services = $this->getApplication()->getServiceManager();
        $viewHelpers = $services->get('ViewHelperManager');
        $this->assertTrue($viewHelpers->has('formEdtf'));
        $this->assertTrue($viewHelpers->has('formConvertToEdtf'));
        $this->assertTrue($viewHelpers->has('edtfPropertySelect'));
    }

    public function testBatchUpdateFormHasEdtfGroup(): void
    {
        $services = $this->getApplication()->getServiceManager();
        $form = $services->get('FormElementManager')
            ->get(\Omeka\Form\ResourceBatchUpdateForm::class, ['resource_type' => 'item']);

        $this->assertSame('EDTF', $form->getOption('element_groups')['edtf'] ?? null);
        $this->assertSame('edtf', $form->get('edtf_convert')->getOption('element_group'));

        $html = $services->get('ViewHelperManager')->get('formCollectionElementGroups')($form, false);
        $this->assertStringContainsString('<fieldset id="edtf"><legend><h2 class="fieldsets-heading">EDTF</h2></legend>', $html);
    }
}
