<?php declare(strict_types=1);

namespace DataTypeEdtfTest\DataType;

use DataTypeEdtf\DataType\Edtf;
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
        $this->assertTrue($this->dataType->convert($value, 'edtf'));
    }

    public function testConvertAcceptsInterval(): void
    {
        $value = $this->createValueStub('1900/1999');
        $this->assertTrue($this->dataType->convert($value, 'edtf'));
    }

    public function testConvertAcceptsUncertain(): void
    {
        $value = $this->createValueStub('1984?');
        $this->assertTrue($this->dataType->convert($value, 'edtf'));
    }

    public function testConvertRejectsInvalid(): void
    {
        $value = $this->createValueStub('not-a-date');
        $this->assertFalse($this->dataType->convert($value, 'edtf'));
    }

    public function testConvertRejectsNull(): void
    {
        $value = $this->createValueStub(null);
        $this->assertFalse($this->dataType->convert($value, 'edtf'));
    }

    public function testConvertRejectsEmpty(): void
    {
        $value = $this->createValueStub('');
        $this->assertFalse($this->dataType->convert($value, 'edtf'));
    }

    public function testEdtfConvertibleExtendsEdtf(): void
    {
        if (!interface_exists(\Omeka\DataType\ConversionTargetInterface::class)) {
            $this->markTestSkipped('ConversionTargetInterface not available (Omeka S < 4.2).');
        }
        $convertible = new \DataTypeEdtf\DataType\EdtfConvertible();
        $this->assertInstanceOf(Edtf::class, $convertible);
        $this->assertInstanceOf(
            \Omeka\DataType\ConversionTargetInterface::class,
            $convertible
        );
    }

    public function testConfigRegistersEdtfFactory(): void
    {
        $module = new \DataTypeEdtf\Module();
        $config = $module->getConfig();
        $this->assertSame(
            \DataTypeEdtf\Service\DataType\EdtfFactory::class,
            $config['data_types']['factories']['edtf']
        );
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
