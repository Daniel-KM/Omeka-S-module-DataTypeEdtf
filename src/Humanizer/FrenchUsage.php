<?php declare(strict_types=1);

namespace DataTypeEdtf\Humanizer;

use EDTF\Model\ExtDate;
use EDTF\Model\ExtDateTime;
use EDTF\Model\Interval;
use EDTF\Model\Season;
use EDTF\Model\Set;

/**
 * Humanize an EDTF value using current French usage conventions.
 *
 * Follows AFNOR Z 44-050 within its scope (uncertainty brackets, approximate
 * "vers"/"ca."), typographic conventions from the French "Lexique de l'Imprimerie nationale"
 * (abbreviated months, en-dash for ranges, non-breaking spaces) and common
 * editorial usage for the rest (arabic ordinal centuries, BCE years with
 * astronomical numbering, seasons, decades).
 */
class FrenchUsage
{
    // No-break space = "\u{00A0}".
    private const NBSP = ' ';

    // Tiret demi-cadratin = "\u{2013}".
    private const DASH = '–';

    /** @var string 'gregorian' or 'julian' */
    private $calendarMode;

    /** @var bool Whether to append [grég.]/[jul.] before reform */
    private $showCalendar;

    /**
     * Gregorian reform date as [year, month, day].
     * Default: 15 October 1582 (papal bull Inter gravissimas).
     * @var array{int, int, int}
     */
    private $reformDate;

    public function __construct(
        string $calendarMode = 'gregorian',
        bool $showCalendar = false,
        string $reformDate = '1582-10-15'
    ) {
        $this->calendarMode = $calendarMode;
        $this->showCalendar = $showCalendar;
        $this->reformDate = $this->parseReformDate($reformDate);
    }

    private function parseReformDate(string $iso): array
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $m)) {
            return [(int) $m[1], (int) $m[2], (int) $m[3]];
        }
        return [1582, 10, 15];
    }

    /**
     * Is the given date strictly before the reform date?
     */
    private function isPreReform(int $year, ?int $month, ?int $day): bool
    {
        [$rY, $rM, $rD] = $this->reformDate;
        if ($year !== $rY) {
            return $year < $rY;
        }
        $m = $month ?? 1;
        if ($m !== $rM) {
            return $m < $rM;
        }
        return ($day ?? 1) < $rD;
    }

    private const MONTHS = [
        1 => 'janvier',
        'février',
        'mars',
        'avril',
        'mai',
        'juin',
        'juillet',
        'août',
        'septembre',
        'octobre',
        'novembre',
        'décembre',
    ];

    private const SEASONS = [
        21 => 'printemps',
        22 => 'été',
        23 => 'automne',
        24 => 'hiver',
        25 => 'printemps (hémisphère nord)',
        26 => 'été (hémisphère nord)',
        27 => 'automne (hémisphère nord)',
        28 => 'hiver (hémisphère nord)',
        29 => 'printemps (hémisphère sud)',
        30 => 'été (hémisphère sud)',
        31 => 'automne (hémisphère sud)',
        32 => 'hiver (hémisphère sud)',
        33 => 'premier trimestre',
        34 => 'deuxième trimestre',
        35 => 'troisième trimestre',
        36 => 'quatrième trimestre',
        37 => 'premier quadrimestre',
        38 => 'deuxième quadrimestre',
        39 => 'troisième quadrimestre',
        40 => 'premier semestre',
        41 => 'second semestre',
    ];

    public function humanize(string $raw, $parsed): string
    {
        // Unspecified digits (X) are flattened by the parser; handle them from
        // the raw string first.
        if (strpos($raw, 'X') !== false && strpos($raw, '/') === false) {
            $r = $this->humanizeUnspecified($raw);
            if ($r !== null) {
                return $r;
            }
        }
        if ($parsed instanceof Interval) {
            return $this->humanizeInterval($parsed, $raw);
        }
        if ($parsed instanceof Season) {
            return $this->humanizeSeason($parsed);
        }
        if ($parsed instanceof Set) {
            return $this->humanizeSet($parsed, $raw);
        }
        if ($parsed instanceof ExtDateTime) {
            return $this->humanizeDateTime($parsed);
        }
        if ($parsed instanceof ExtDate) {
            return $this->humanizeDate($parsed);
        }
        return $raw;
    }

    private function humanizeDate(ExtDate $d): string
    {
        $year = $d->getYear();
        if ($year === null) {
            return '';
        }
        $core = $this->formatYMD($year, $d->getMonth(), $d->getDay());
        return $this->applyQualifiers($d, $core);
    }

    private function humanizeDateTime(ExtDateTime $dt): string
    {
        $date = $this->formatYMD($dt->getYear(), $dt->getMonth(), $dt->getDay());
        $time = sprintf('%d%sh%s%02d', $dt->getHour(), self::NBSP, self::NBSP, $dt->getMinute());
        if ($dt->getSecond() > 0) {
            $time .= sprintf('%s%02d', self::NBSP, $dt->getSecond()) . self::NBSP . 's';
        }
        return $this->applyQualifiers($dt->getDate(), $date . ',' . self::NBSP . $time);
    }

    private function formatYMD(int $year, ?int $month, ?int $day): string
    {
        // Convert proleptic Gregorian to Julian if requested and before the
        // reform date.
        $isPreReform = $this->isPreReform($year, $month, $day);
        if ($isPreReform && $this->calendarMode === 'julian' && $day !== null && $month !== null) {
            [$year, $month, $day] = $this->gregorianToJulian($year, $month, $day);
        }

        $yearStr = $this->formatYear($year);
        if ($month === null) {
            $result = $yearStr;
        } elseif ($day === null) {
            $result = (self::MONTHS[$month] ?? (string) $month) . self::NBSP . $yearStr;
        } else {
            $result = $day . self::NBSP . (self::MONTHS[$month] ?? (string) $month) . self::NBSP . $yearStr;
        }

        // Append calendar indicator before the reform.
        if ($isPreReform && $this->showCalendar && $month !== null) {
            $cal = $this->calendarMode === 'julian' ? 'jul.' : 'grég.';
            $result .= self::NBSP . '[' . $cal . ']';
        }

        return $result;
    }

    /**
     * Format an astronomical year into usage-style French.
     *
     * Year 0 = 1 BCE, -1 = 2 BCE, etc.
     */
    private function formatYear(int $year): string
    {
        if ($year > 0) {
            return (string) $year;
        }
        $bce = 1 - $year;
        return $bce . self::NBSP . 'av.' . self::NBSP . 'J.-C.';
    }

    /**
     * Convert a proleptic Gregorian date to a proleptic Julian date via the
     * Julian Day Number (JDN), which is calendar-independent.
     *
     * @return array{int, int, int} [year, month, day] in Julian
     */
    private function gregorianToJulian(int $year, int $month, int $day): array
    {
        $jd = $this->gregorianToJD($year, $month, $day);
        return $this->jdToJulian($jd);
    }

    /**
     * Proleptic Gregorian date => Julian Day Number.
     *
     * @see Meeus, "Astronomical Algorithms", 2nd ed., ch. 7.
     */
    private function gregorianToJD(int $y, int $m, int $d): int
    {
        if ($m <= 2) {
            $y--;
            $m += 12;
        }
        $a = (int) floor($y / 100);
        $b = 2 - $a + (int) floor($a / 4);
        return (int) floor(365.25 * ($y + 4716))
             + (int) floor(30.6001 * ($m + 1))
             + $d + $b - 1524;
    }

    /**
     * Julian Day Number => proleptic Julian calendar date.
     *
     * No century correction (difference from the Gregorian inverse).
     *
     * @see Meeus, "Astronomical Algorithms", 2nd ed., ch. 7.
     */
    private function jdToJulian(int $jd): array
    {
        $b = $jd + 1524;
        $c = (int) floor(($b - 122.1) / 365.25);
        $d = (int) floor(365.25 * $c);
        $e = (int) floor(($b - $d) / 30.6001);
        $day = $b - $d - (int) floor(30.6001 * $e);
        $month = $e < 14 ? $e - 1 : $e - 13;
        $year = $month > 2 ? $c - 4716 : $c - 4715;
        return [$year, $month, $day];
    }

    /**
     * Return the French ordinal suffix in Unicode superscript.
     *
     * 1 => "ᵉʳ" (1er), 2+ => "ᵉ" (2e, 3e…).
     */
    private function ordinalSuffix(int $n): string
    {
        return $n === 1 ? 'ᵉʳ' : 'ᵉ';
    }

    private function ordinalSuffixFem(int $n): string
    {
        return $n === 1 ? 'ʳᵉ' : 'ᵉ';
    }

    private function applyQualifiers(ExtDate $d, string $text): string
    {
        $uncertain = $d->uncertain();
        $approximate = $d->approximate();
        if ($approximate && !$uncertain) {
            return 'vers' . self::NBSP . $text;
        }
        if ($uncertain && !$approximate) {
            return '[' . $text . self::NBSP . '?]';
        }
        if ($uncertain && $approximate) {
            return 'vers' . self::NBSP . '[' . $text . self::NBSP . '?]';
        }
        return $text;
    }

    private function humanizeSeason(Season $s): string
    {
        $label = self::SEASONS[$s->getSeason()] ?? null;
        if ($label === null) {
            return '';
        }
        return $label . self::NBSP . $this->formatYear($s->getYear());
    }

    private function humanizeInterval(Interval $i, string $raw): string
    {
        $parts = explode('/', $raw, 2);
        $left = $parts[0] ?? '';
        $right = $parts[1] ?? '';

        $leftOpen = $left === '..';
        $leftUnknown = $left === '';
        $rightOpen = $right === '..';
        $rightUnknown = $right === '';

        // Handle "début/fin du Xe siècle" for unspecified digits with open
        // intervals (19XX/.., ../19XX).
        $leftUnspec = strpos($left, 'X') !== false
            ? $this->humanizeUnspecified($left)
            : null;
        $rightUnspec = strpos($right, 'X') !== false
            ? $this->humanizeUnspecified($right)
            : null;
        if ($leftUnspec !== null && ($rightOpen || $rightUnknown)) {
            return 'début'
                . self::NBSP . $this->prepositionDu($leftUnspec)
                . self::NBSP . $leftUnspec;
        }
        if ($rightUnspec !== null && ($leftOpen || $leftUnknown)) {
            return 'fin'
                . self::NBSP . $this->prepositionDu($rightUnspec)
                . self::NBSP . $rightUnspec;
        }

        // Detect century subdivisions (thirds, halves) from year intervals like
        // 1900/1933, 1934/1966, 1900/1949.
        $centuryPart = $this->humanizeCenturyPart($left, $right);
        if ($centuryPart !== null) {
            return $centuryPart;
        }

        $leftStr = null;
        if (!$leftOpen && !$leftUnknown && $i->hasStartDate()) {
            $leftStr = $leftUnspec
                ?? $this->humanizeNode($i->getStartDate(), $left);
        }
        $rightStr = null;
        if (!$rightOpen && !$rightUnknown && $i->hasEndDate()) {
            $rightStr = $rightUnspec
                ?? $this->humanizeNode($i->getEndDate(), $right);
        }

        // /1985 : unknown start.
        if ($leftUnknown && $rightStr !== null) {
            return 'avant' . self::NBSP . $rightStr;
        }
        // 1985/ : unknown end.
        if ($rightUnknown && $leftStr !== null) {
            return 'après' . self::NBSP . $leftStr;
        }
        // ../1985 : open start.
        if ($leftOpen && $rightStr !== null) {
            return 'jusqu’en' . self::NBSP . $rightStr;
        }
        // 1985/.. : open end.
        if ($rightOpen && $leftStr !== null) {
            return 'depuis' . self::NBSP . $leftStr;
        }
        if ($leftStr !== null && $rightStr !== null) {
            // Factor out shared "vers" prefix when both endpoints are
            // approximate-only (no uncertainty brackets), so an interval like
            // "vers 1890 – vers 1900" reads "vers 1890–1900".
            $versPrefix = 'vers' . self::NBSP;
            if (strpos($leftStr, $versPrefix) === 0
                && strpos($rightStr, $versPrefix) === 0
                && strpos($leftStr, '[') === false
                && strpos($rightStr, '[') === false
            ) {
                return $versPrefix
                    . substr($leftStr, strlen($versPrefix))
                    . self::DASH
                    . substr($rightStr, strlen($versPrefix));
            }
            return $leftStr . self::NBSP . self::DASH . self::NBSP . $rightStr;
        }
        return $raw;
    }

    private function humanizeNode($node, string $raw): string
    {
        if ($node instanceof Season) {
            return $this->humanizeSeason($node);
        }
        if ($node instanceof ExtDateTime) {
            return $this->humanizeDateTime($node);
        }
        if ($node instanceof ExtDate) {
            return $this->humanizeDate($node);
        }
        return $raw;
    }

    /**
     * Handle EDTF "unspecified digits" patterns (X) that the parser flattens to
     * NULL components.
     */
    private function humanizeUnspecified(string $raw): ?string
    {
        // NNNN-MM-XX : unknown day.
        if (preg_match('/^(-?\d{4})-(\d{2})-XX$/', $raw, $m)) {
            $year = (int) $m[1];
            $month = (int) $m[2];
            return (self::MONTHS[$month] ?? $m[2]) . self::NBSP . $this->formatYear($year);
        }
        // NNNN-XX : unknown month.
        if (preg_match('/^(-?\d{4})-XX$/', $raw, $m)) {
            return $this->formatYear((int) $m[1]);
        }
        // NNNX / NNXX / NXXX: decade, century, millennium.
        if (preg_match('/^(-?)(\d+)(X+)$/', $raw, $m)) {
            $sign = $m[1];
            $digits = $m[2];
            $xcount = strlen($m[3]);
            if ($xcount === 1) {
                $base = (int) $digits * 10;
                return 'années' . self::NBSP . $base . ($sign === '-' ? self::NBSP . 'av.' . self::NBSP . 'J.-C.' : '');
            }
            if ($xcount === 2) {
                $c = (int) $digits + 1;
                $suffix = $this->ordinalSuffix($c);
                return $c . $suffix . self::NBSP . 'siècle' . ($sign === '-' ? self::NBSP . 'av.' . self::NBSP . 'J.-C.' : '');
            }
            if ($xcount === 3) {
                $mi = (int) $digits + 1;
                $suffix = $this->ordinalSuffix($mi);
                return $mi . $suffix . self::NBSP . 'millénaire' . ($sign === '-' ? self::NBSP . 'av.' . self::NBSP . 'J.-C.' : '');
            }
        }
        return null;
    }

    private function humanizeSet(Set $s, string $raw): string
    {
        $allMembers = strpos($raw, '{') === 0;
        $parts = [];
        foreach ($s->getElements() as $el) {
            $node = method_exists($el, 'getDate') ? $el->getDate() : $el;
            $parts[] = $this->humanizeNode($node, '');
        }
        $parts = array_filter($parts, 'strlen');
        if (empty($parts)) {
            return $raw;
        }
        if (count($parts) === 1) {
            return reset($parts);
        }
        $last = array_pop($parts);
        $sep = $allMembers ? ' et ' : ' ou ';
        return implode(', ', $parts) . $sep . $last;
    }

    /**
     * Detect century subdivisions: thirds, quarters, halves.
     *
     * Base-0 convention (Joconde, p. 24):
     * - century: 1700–1799 = 18e siècle (= 17XX)
     * - thirds:  1700/1733, 1734/1766, 1767/1799
     * - quarters: 1700/1724, 1725/1749, 1750/1774, 1775/1799
     * - halves:  1700/1749, 1750/1799
     *
     * @see https://www.culture.gouv.fr/content/download/197593/file/methode.pdf
     */
    private function humanizeCenturyPart(
        string $left,
        string $right
    ): ?string {
        if (!preg_match('/^-?\d{4}$/', $left)
            || !preg_match('/^-?\d{4}$/', $right)
        ) {
            return null;
        }
        $y1 = (int) $left;
        $y2 = (int) $right;
        if ($y1 >= $y2 || $y1 < 0) {
            return null;
        }
        // Base-0: 1900–1999 = 20e siècle.
        $centuryStart = (int) (floor($y1 / 100) * 100);
        $centuryEnd = $centuryStart + 99;
        $c = (int) ($centuryStart / 100) + 1;
        $suffix = $this->ordinalSuffix($c);
        $century = $c . $suffix . self::NBSP . 'siècle';

        // Thirds: 34/33/33 years.
        $t1End = $centuryStart + 33;
        $t2Start = $t1End + 1;
        $t2End = $t2Start + 32;
        $t3Start = $t2End + 1;

        if ($y1 === $centuryStart && $y2 === $t1End) {
            return 'début du'
                . self::NBSP . $century;
        }
        if ($y1 === $t2Start && $y2 === $t2End) {
            return 'milieu du'
                . self::NBSP . $century;
        }
        if ($y1 === $t3Start && $y2 === $centuryEnd) {
            return 'fin du'
                . self::NBSP . $century;
        }

        // Quarters: 25 years each.
        $q1End = $centuryStart + 24;
        $q2Start = $q1End + 1;
        $q2End = $q2Start + 24;
        $q3Start = $q2End + 1;
        $q3End = $q3Start + 24;
        $q4Start = $q3End + 1;

        if ($y1 === $centuryStart && $y2 === $q1End) {
            return '1' . $this->ordinalSuffix(1)
                . self::NBSP . 'quart du'
                . self::NBSP . $century;
        }
        if ($y1 === $q2Start && $y2 === $q2End) {
            return '2' . $this->ordinalSuffix(2)
                . self::NBSP . 'quart du'
                . self::NBSP . $century;
        }
        if ($y1 === $q3Start && $y2 === $q3End) {
            return '3' . $this->ordinalSuffix(3)
                . self::NBSP . 'quart du'
                . self::NBSP . $century;
        }
        if ($y1 === $q4Start && $y2 === $centuryEnd) {
            return '4' . $this->ordinalSuffix(4)
                . self::NBSP . 'quart du'
                . self::NBSP . $century;
        }

        // Halves: 50 years each.
        $halfEnd = $centuryStart + 49;
        $half2Start = $halfEnd + 1;

        if ($y1 === $centuryStart && $y2 === $halfEnd) {
            return '1ʳᵉ'
                . self::NBSP . 'moitié du'
                . self::NBSP . $century;
        }
        if ($y1 === $half2Start && $y2 === $centuryEnd) {
            return '2ᵈᵉ'
                . self::NBSP . 'moitié du'
                . self::NBSP . $century;
        }

        return null;
    }

    /**
     * Return "du", "des" or "de la" depending on the label.
     */
    private function prepositionDu(string $label): string
    {
        if (strpos($label, 'années') === 0) {
            return 'des';
        }
        return 'du';
    }
}
