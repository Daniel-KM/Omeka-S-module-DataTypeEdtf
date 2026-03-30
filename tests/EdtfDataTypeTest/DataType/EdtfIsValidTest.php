<?php declare(strict_types=1);

namespace EdtfDataTypeTest\DataType;

use EdtfDataType\DataType\Edtf;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Edtf::isValid().
 *
 * Does not require the Omeka application (pure unit test).
 */
class EdtfIsValidTest extends TestCase
{
    protected Edtf $dataType;

    public function setUp(): void
    {
        $this->dataType = new Edtf();
    }

    /**
     * @dataProvider validEdtfProvider
     */
    public function testIsValidWithValidEdtf(string $value): void
    {
        $this->assertTrue(
            $this->dataType->isValid(['@value' => $value]),
            "Expected '$value' to be valid EDTF"
        );
    }

    public function validEdtfProvider(): array
    {
        return [
            'full date' => ['2024-03-15'],
            'year-month' => ['2024-03'],
            'year only' => ['2024'],
            'negative year' => ['-0500'],
            'uncertain' => ['1984?'],
            'approximate' => ['1984~'],
            'uncertain and approximate' => ['1984%'],
            'interval' => ['1964/2008'],
            'open interval start' => ['../1985'],
            'open interval end' => ['1985/..'],
            'unspecified decade' => ['198X'],
            'unspecified century' => ['19XX'],
            'season spring' => ['2001-21'],
            'season summer' => ['2001-22'],
            'season autumn' => ['2001-23'],
            'season winter' => ['2001-24'],
            'set one of' => ['[1667,1668,1670..1672]'],
            'long year' => ['Y17000'],
            'date time' => ['2024-03-15T10:30:00'],
        ];
    }

    /**
     * @dataProvider invalidEdtfProvider
     */
    public function testIsValidWithInvalidEdtf($value): void
    {
        $this->assertFalse(
            $this->dataType->isValid(['@value' => $value]),
            "Expected value to be invalid EDTF"
        );
    }

    public function invalidEdtfProvider(): array
    {
        return [
            'plain text' => ['not-a-date'],
            'empty string' => [''],
            'partial nonsense' => ['2024-13-45'],
            'month 13' => ['2024-13'],
            'day 32' => ['2024-01-32'],
        ];
    }

    public function testIsValidWithNull(): void
    {
        $this->assertFalse($this->dataType->isValid(['@value' => null]));
    }

    public function testIsValidWithMissingKey(): void
    {
        $this->assertFalse($this->dataType->isValid([]));
    }

    public function testIsValidWithInteger(): void
    {
        $this->assertFalse($this->dataType->isValid(['@value' => 2024]));
    }

    public function testIsValidWithEmptyArray(): void
    {
        $this->assertFalse($this->dataType->isValid(['@value' => []]));
    }
}
