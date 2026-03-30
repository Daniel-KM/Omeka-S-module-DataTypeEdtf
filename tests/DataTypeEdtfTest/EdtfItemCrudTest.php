<?php declare(strict_types=1);

namespace DataTypeEdtfTest;

use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Integration tests for creating/reading items with EDTF values.
 */
class EdtfItemCrudTest extends AbstractHttpControllerTestCase
{
    use DataTypeEdtfTestTrait;

    protected array $createdItemIds = [];

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
    }

    public function tearDown(): void
    {
        $api = $this->getApiManager();
        foreach ($this->createdItemIds as $id) {
            try {
                $api->delete('items', $id);
            } catch (\Exception $e) {
                // Ignore.
            }
        }
        $this->logout();
        parent::tearDown();
    }

    public function testCreateItemWithEdtfValue(): void
    {
        $api = $this->getApiManager();
        $response = $api->create('items', [
            'dcterms:date' => [
                [
                    'type' => 'edtf:date',
                    'property_id' => $this->getDatePropertyId(),
                    '@value' => '1984',
                ],
            ],
        ]);
        $item = $response->getContent();
        $this->createdItemIds[] = $item->id();

        $values = $item->value('dcterms:date', ['all' => true]);
        $this->assertCount(1, $values);
        $this->assertSame('1984', $values[0]->value());
        $this->assertSame('edtf:date', $values[0]->type());
    }

    public function testCreateItemWithEdtfInterval(): void
    {
        $api = $this->getApiManager();
        $response = $api->create('items', [
            'dcterms:date' => [
                [
                    'type' => 'edtf:date',
                    'property_id' => $this->getDatePropertyId(),
                    '@value' => '1900/1999',
                ],
            ],
        ]);
        $item = $response->getContent();
        $this->createdItemIds[] = $item->id();

        $values = $item->value('dcterms:date', ['all' => true]);
        $this->assertSame('1900/1999', $values[0]->value());
    }

    public function testCreateItemWithUncertainDate(): void
    {
        $api = $this->getApiManager();
        $response = $api->create('items', [
            'dcterms:date' => [
                [
                    'type' => 'edtf:date',
                    'property_id' => $this->getDatePropertyId(),
                    '@value' => '1984?',
                ],
            ],
        ]);
        $item = $response->getContent();
        $this->createdItemIds[] = $item->id();

        $values = $item->value('dcterms:date', ['all' => true]);
        $this->assertSame('1984?', $values[0]->value());
    }

    public function testCreateItemWithNullEdtfValueIsSkipped(): void
    {
        $api = $this->getApiManager();
        $response = $api->create('items', [
            'dcterms:date' => [
                [
                    'type' => 'edtf:date',
                    'property_id' => $this->getDatePropertyId(),
                    '@value' => null,
                ],
            ],
        ]);
        $item = $response->getContent();
        $this->createdItemIds[] = $item->id();

        $values = $item->value('dcterms:date', ['all' => true]);
        $this->assertCount(0, $values);
    }

    public function testEdtfEntityIsSavedOnCreate(): void
    {
        $api = $this->getApiManager();
        $response = $api->create('items', [
            'dcterms:date' => [
                [
                    'type' => 'edtf:date',
                    'property_id' => $this->getDatePropertyId(),
                    '@value' => '2024-03-15',
                ],
            ],
        ]);
        $item = $response->getContent();
        $this->createdItemIds[] = $item->id();

        $conn = $this->getConnection();
        $count = $conn->fetchOne(
            'SELECT COUNT(*) FROM edtf_data_type_edtf WHERE resource_id = ?',
            [$item->id()]
        );
        $this->assertEquals(1, $count);
    }

    public function testEdtfEntityIsRemovedOnDelete(): void
    {
        $api = $this->getApiManager();
        $response = $api->create('items', [
            'dcterms:date' => [
                [
                    'type' => 'edtf:date',
                    'property_id' => $this->getDatePropertyId(),
                    '@value' => '2024-03-15',
                ],
            ],
        ]);
        $item = $response->getContent();
        $itemId = $item->id();

        $api->delete('items', $itemId);

        $conn = $this->getConnection();
        $count = $conn->fetchOne(
            'SELECT COUNT(*) FROM edtf_data_type_edtf WHERE resource_id = ?',
            [$itemId]
        );
        $this->assertEquals(0, $count);
    }

    protected function getDatePropertyId(): int
    {
        $conn = $this->getConnection();
        return (int) $conn->fetchOne(
            "SELECT id FROM property WHERE local_name = 'date' AND vocabulary_id = (SELECT id FROM vocabulary WHERE prefix = 'dcterms')"
        );
    }
}
