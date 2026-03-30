<?php declare(strict_types=1);

namespace DataTypeEdtfTest\DataType;

use DataTypeEdtf\DataType\Edtf;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Edtf data type metadata.
 */
class DataTypeEdtfTest extends TestCase
{
    protected Edtf $dataType;

    public function setUp(): void
    {
        $this->dataType = new Edtf();
    }

    public function testGetName(): void
    {
        $this->assertSame('edtf:date', $this->dataType->getName());
    }

    public function testGetLabel(): void
    {
        $this->assertSame('EDTF Date/Time', $this->dataType->getLabel());
    }

    public function testGetEntityClass(): void
    {
        $this->assertSame(
            'DataTypeEdtf\Entity\Edtf',
            $this->dataType->getEntityClass()
        );
    }
}
