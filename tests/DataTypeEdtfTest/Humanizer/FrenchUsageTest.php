<?php declare(strict_types=1);

namespace DataTypeEdtfTest\Humanizer;

use DataTypeEdtf\Humanizer\FrenchUsage;
use EDTF\EdtfFactory;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the French usage humanizer.
 */
class FrenchUsageTest extends TestCase
{
    const NBSP = "\u{00A0}";

    /**
     * @dataProvider humanizeProvider
     */
    public function testHumanize(string $edtf, string $expected): void
    {
        $result = EdtfFactory::newParser()->parse($edtf);
        $this->assertTrue($result->isValid(), $edtf);
        $humanized = (new FrenchUsage())->humanize($edtf, $result->getEdtfValue());
        // Compare with regular spaces for readability.
        $this->assertSame($expected, strtr($humanized, [self::NBSP => ' ']));
    }

    public function humanizeProvider(): array
    {
        return [
            // Simple dates.
            ['1970', '1970'],
            ['1980-03', 'mars 1980'],
            ['1789-23', 'automne 1789'],
            ['1960/1965', '1960 – 1965'],
            // Unspecified digits.
            ['192X', 'années 1920'],
            ['19XX', '20ᵉ siècle'],
            ['17XX', '18ᵉ siècle'],
            ['1XXX', '2ᵉ millénaire'],
            // Open and unknown sides.
            ['1871/..', 'depuis 1871'],
            ['../1871', 'jusqu’en 1871'],
            ['1871/', 'après 1871'],
            ['/1871', 'avant 1871'],
            // Open sides with unspecified digits.
            ['19XX/..', 'début du 20ᵉ siècle'],
            ['../19XX', 'fin du 20ᵉ siècle'],
            ['192X/..', 'début des années 1920'],
            ['../192X', 'fin des années 1920'],
            // Thirds of century (base 0).
            ['1900/1933', 'début du 20ᵉ siècle'],
            ['1934/1966', 'milieu du 20ᵉ siècle'],
            ['1967/1999', 'fin du 20ᵉ siècle'],
            ['1800/1833', 'début du 19ᵉ siècle'],
            // Quarters of century.
            ['1900/1924', '1ᵉʳ quart du 20ᵉ siècle'],
            ['1925/1949', '2ᵉ quart du 20ᵉ siècle'],
            ['1950/1974', '3ᵉ quart du 20ᵉ siècle'],
            ['1975/1999', '4ᵉ quart du 20ᵉ siècle'],
            // Halves of century.
            ['1900/1949', '1ʳᵉ moitié du 20ᵉ siècle'],
            ['1950/1999', '2ᵈᵉ moitié du 20ᵉ siècle'],
            ['1850/1899', '2ᵈᵉ moitié du 19ᵉ siècle'],
            // Not a subdivision: base 1 is not used.
            ['1901/1950', '1901 – 1950'],
        ];
    }
}
