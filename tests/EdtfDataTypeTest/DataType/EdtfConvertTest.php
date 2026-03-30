<?php declare(strict_types=1);

namespace EdtfDataTypeTest\DataType;

use EdtfDataType\DataType\Edtf;
use Omeka\Entity\Value;
use PHPUnit\Framework\TestCase;

/**
 * Tests for convert() method and conditional ConversionTargetInterface.
 */
class EdtfConvertTest extends TestCase
{
    protected Edtf $dataType;

    public function setUp(): void
    {
        $this->dataType = new Edtf();
    }

    public function testConvertAcceptsValidEdtf(): void
    {
        $value = $this->createValueStub('1984');
        $this->assertTrue($this->dataType->convert($value, 'edtf:date'));
    }

    public function testConvertAcceptsInterval(): void
    {
        $value = $this->createValueStub('1900/1999');
        $this->assertTrue($this->dataType->convert($value, 'edtf:date'));
    }

    public function testConvertAcceptsUncertain(): void
    {
        $value = $this->createValueStub('1984?');
        $this->assertTrue($this->dataType->convert($value, 'edtf:date'));
    }

    public function testConvertRejectsInvalid(): void
    {
        $value = $this->createValueStub('not-a-date');
        $this->assertFalse($this->dataType->convert($value, 'edtf:date'));
    }

    public function testConvertRejectsNull(): void
    {
        $value = $this->createValueStub(null);
        $this->assertFalse($this->dataType->convert($value, 'edtf:date'));
    }

    public function testConvertRejectsEmpty(): void
    {
        $value = $this->createValueStub('');
        $this->assertFalse($this->dataType->convert($value, 'edtf:date'));
    }

    public function testEdtfConvertibleExtendsEdtf(): void
    {
        if (!interface_exists(\Omeka\DataType\ConversionTargetInterface::class)) {
            $this->markTestSkipped('ConversionTargetInterface not available (Omeka S < 4.2).');
        }
        $convertible = new \EdtfDataType\DataType\EdtfConvertible();
        $this->assertInstanceOf(Edtf::class, $convertible);
        $this->assertInstanceOf(
            \Omeka\DataType\ConversionTargetInterface::class,
            $convertible
        );
    }

    public function testConfigSwapsClassWhenInterfaceExists(): void
    {
        $module = new \EdtfDataType\Module();
        $config = $module->getConfig();
        $class = $config['data_types']['invokables']['edtf:date'];
        if (interface_exists(\Omeka\DataType\ConversionTargetInterface::class)) {
            $this->assertSame(\EdtfDataType\DataType\EdtfConvertible::class, $class);
        } else {
            $this->assertSame(Edtf::class, $class);
        }
    }

    protected function createValueStub($val): Value
    {
        $value = new Value();
        $ref = new \ReflectionProperty($value, 'value');
        $ref->setAccessible(true);
        $ref->setValue($value, $val);
        return $value;
    }
}
