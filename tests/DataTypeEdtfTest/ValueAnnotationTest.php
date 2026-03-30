<?php declare(strict_types=1);

namespace DataTypeEdtfTest;

use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Integration tests for EDTF values inside value annotations (Omeka core).
 */
class ValueAnnotationTest extends AbstractHttpControllerTestCase
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

    public function testEdtfInValueAnnotationIsSavedInIndex(): void
    {
        $api = $this->getApiManager();
        $propertyId = $this->getDatePropertyId();

        $response = $api->create('items', [
            'dcterms:title' => [
                [
                    'type' => 'literal',
                    'property_id' => $this->getTitlePropertyId(),
                    '@value' => 'Item with annotated date',
                ],
            ],
            'dcterms:date' => [
                [
                    'type' => 'literal',
                    'property_id' => $propertyId,
                    '@value' => 'circa 1900',
                    '@annotation' => [
                        'dcterms:date' => [
                            [
                                'type' => 'edtf:date',
                                'property_id' => $propertyId,
                                '@value' => '1900~',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $item = $response->getContent();
        $this->createdItemIds[] = $item->id();

        // Verify the EDTF entity was created for the value annotation.
        // 1900~ bounds: 1900-01-01 to 1900-12-31.
        $conn = $this->getConnection();
        $count = $conn->fetchOne(
            'SELECT COUNT(*) FROM data_type_edtf WHERE value_min >= ? AND value_max <= ?',
            [
                (new \DateTime('1900-01-01'))->getTimestamp(),
                (new \DateTime('1900-12-31 23:59:59'))->getTimestamp(),
            ]
        );
        $this->assertGreaterThanOrEqual(
            1,
            $count,
            'EDTF entity should be indexed for value annotation'
        );
    }

    protected function getDatePropertyId(): int
    {
        return (int) $this->getConnection()->fetchOne(
            "SELECT id FROM property WHERE local_name = 'date' AND vocabulary_id = (SELECT id FROM vocabulary WHERE prefix = 'dcterms')"
        );
    }

    protected function getTitlePropertyId(): int
    {
        return (int) $this->getConnection()->fetchOne(
            "SELECT id FROM property WHERE local_name = 'title' AND vocabulary_id = (SELECT id FROM vocabulary WHERE prefix = 'dcterms')"
        );
    }
}
