<?php declare(strict_types=1);

namespace DataTypeEdtf\DataType;

use Doctrine\ORM\QueryBuilder;
use EDTF\EdtfFactory;
use DataTypeEdtf\Entity\Edtf as EdtfEntity;
use DataTypeEdtf\Form\Element\Edtf as EdtfElement;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Adapter\AdapterInterface;
use Omeka\Api\Representation\ValueRepresentation;
use Omeka\DataType\ValueAnnotatingInterface;
use Omeka\Entity\Value;

class Edtf extends AbstractDataType implements ValueAnnotatingInterface
{
    public function getName()
    {
        return 'edtf';
    }

    public function getLabel()
    {
        return 'EDTF Date/Time'; // @translate
    }

    public function prepareForm(PhpRenderer $view): void
    {
    }

    public function getJsonLd(ValueRepresentation $value)
    {
        $raw = $value->value();
        if (!$this->isValid(['@value' => $raw])) {
            return ['@value' => $raw];
        }
        return [
            '@value' => $raw,
            '@type' => $this->getXsdOrEdtfType($raw),
        ];
    }

    /**
     * Return the most precise JSON-LD @type URI for an EDTF string.
     *
     * When the string is a "pure" form expressible in XML Schema (no qualifier,
     * no unspecified digit, no season, no interval, no set, no long year),
     * returns an xsd:* URI. Otherwise returns the Library of Congress EDTF
     * generic datatype URI.
     *
     * Official URIs and documentation:
     * @link https://id.loc.gov/datatypes/EDTFScheme.html
     * @link https://id.loc.gov/datatypes/edtf/EDTF.html
     * @link https://id.loc.gov/datatypes/edtf/EDTF-level0.html
     * @link https://id.loc.gov/datatypes/edtf/EDTF-level1.html
     * @link https://id.loc.gov/datatypes/edtf/EDTF-level2.html
     *
     * Related discussions:
     * @link https://github.com/ProfessionalWiki/WikibaseEdtf/issues/13
     * @link https://github.com/Islandora/documentation/issues/916
     *
     * @todo Differentiate EDTF Level 0 / 1 / 2 URIs instead of always returning the generic EDTF URI.
     */
    protected function getXsdOrEdtfType(string $edtfString): string
    {
        $edtfDatatypeUri = 'http://id.loc.gov/datatypes/edtf/EDTF';
        try {
            $edtf = EdtfFactory::newParser()->parse($edtfString)->getEdtfValue();
        } catch (\Throwable $e) {
            return $edtfDatatypeUri;
        }

        // Intervals, Seasons and Sets always use the EDTF type.
        if ($edtf instanceof \EDTF\Model\Interval
            || $edtf instanceof \EDTF\Model\Season
            || $edtf instanceof \EDTF\Model\Set
        ) {
            return $edtfDatatypeUri;
        }

        // ExtDate / ExtDateTime: check for EDTF-only features.
        if ($edtf instanceof \EDTF\Model\ExtDateTime) {
            // Qualifiers/unspecified on the underlying date → EDTF.
            $inner = $edtf->getDate();
            if ($inner->uncertain() || $inner->approximate() || $inner->unspecified()) {
                return $edtfDatatypeUri;
            }
            if (strpos($edtfString, 'Y') === 0 || strpos($edtfString, 'E') !== false) {
                return $edtfDatatypeUri;
            }
            return 'http://www.w3.org/2001/XMLSchema#dateTime';
        }

        if ($edtf instanceof \EDTF\Model\ExtDate) {
            if ($edtf->uncertain() || $edtf->approximate() || $edtf->unspecified()) {
                return $edtfDatatypeUri;
            }
            if (strpos($edtfString, 'Y') === 0 || strpos($edtfString, 'E') !== false) {
                return $edtfDatatypeUri;
            }
            if ($edtf->getDay() !== null) {
                return 'http://www.w3.org/2001/XMLSchema#date';
            }
            if ($edtf->getMonth() !== null) {
                return 'http://www.w3.org/2001/XMLSchema#gYearMonth';
            }
            if ($edtf->getYear() !== null) {
                return 'http://www.w3.org/2001/XMLSchema#gYear';
            }
        }

        return $edtfDatatypeUri;
    }

    public function form(PhpRenderer $view)
    {
        $element = new EdtfElement('edtf-value');
        $element->getValueElement()->setAttribute('data-value-key', '@value');
        return $view->formElement($element);
    }

    public function toEdtf(ValueRepresentation $value)
    {
        $parser = EdtfFactory::newParser();
        $parsingResult = $parser->parse($value->value());
        return $parsingResult;
    }

    public function isValid(array $valueObject)
    {
        if (!isset($valueObject['@value']) || !is_string($valueObject['@value']) || $valueObject['@value'] === '') {
            return false;
        }
        $parser = EdtfFactory::newParser();
        $parsingResult = $parser->parse($valueObject['@value']);
        return $parsingResult->isValid();
    }

    public function hydrate(array $valueObject, Value $value, AbstractEntityAdapter $adapter): void
    {
        // Store the datetime as a string
        $edtfDate = $valueObject['@value'];
        $value->setValue($edtfDate);
        $value->setLang(null);
        $value->setUri(null);
        $value->setValueResource(null);
    }

    public function render(PhpRenderer $view, ValueRepresentation $value, $options = [])
    {
        $raw = $value->value();
        if (!is_string($raw) || $raw === '') {
            return (string) $raw;
        }
        // Parse only once: reuse result for validity check and humanization.
        $parsingResult = EdtfFactory::newParser()->parse($raw);
        if (!$parsingResult->isValid()) {
            return $raw;
        }
        // Site setting overrides general setting for each option.
        $style = $this->resolveEdtfSetting($view, 'datatypeedtf_humanizer', 'library');
        if ($style === 'fr_usage') {
            $calendarMode = $this->resolveEdtfSetting($view, 'datatypeedtf_calendar_mode', 'gregorian');
            $showCalendar = (bool) $this->resolveEdtfSetting($view, 'datatypeedtf_show_calendar', '0');
            $reformDate = $this->resolveEdtfSetting($view, 'datatypeedtf_reform_date', '1582-10-15');
            $humanizer = new \DataTypeEdtf\Humanizer\FrenchUsage($calendarMode, $showCalendar, $reformDate);
            $response = $humanizer->humanize($raw, $parsingResult->getEdtfValue());
        } else {
            $humanizer = EdtfFactory::newHumanizerForLanguage($view->lang() ?? 'en');
            $response = $humanizer->humanize($parsingResult->getEdtfValue());
        }
        return $response !== '' ? $response : $raw;
    }

    public function getFulltextText(PhpRenderer $view, ValueRepresentation $value)
    {

        return sprintf('%s %s', $value->value(), $this->render($view, $value));
    }

    /**
     * Read a module setting with site-level override. Returns the site
     * setting if set and non-empty, else the general setting.
     */
    protected function resolveEdtfSetting(PhpRenderer $view, string $key, string $default): string
    {
        $value = null;
        try {
            $value = $view->siteSetting($key);
        } catch (\Throwable $e) {
            // Not in a site context.
        }
        if ($value !== null && $value !== '') {
            return (string) $value;
        }
        try {
            return (string) $view->setting($key, $default);
        } catch (\Throwable $e) {
            return $default;
        }
    }

    public function getEntityClass()
    {
        return 'DataTypeEdtf\Entity\Edtf';
    }

    /**
     * Year offset used so the packed date column is always positive when
     * year  >= -OFFSET. Must be large enough to cover the age of the universe
     * (≈1.4·10^10) and leave room for sentinels.
     */
    public const YEAR_OFFSET = 100000000000000; // 10^14

    public const DATE_MIN = PHP_INT_MIN;
    public const DATE_MAX = PHP_INT_MAX;
    public const TIME_MIN = 0;
    public const TIME_MAX = 235959;

    public function setEntityValues(EdtfEntity $entity, Value $value): void
    {
        [$minDate, $minTime, $maxDate, $maxTime] = $this->getValueBounds($value->getValue());
        $entity->setValueMinDate($minDate);
        $entity->setValueMinTime($minTime);
        $entity->setValueMaxDate($maxDate);
        $entity->setValueMaxTime($maxTime);
    }

    /**
     * Compute packed bounds (minDate, minTime, maxDate, maxTime) of EDTF string.
     *
     * Date part is encoded as (year + YEAR_OFFSET) * 10000 + month * 100 + day,
     * giving a natively sortable signed BIGINT.
     * Time part is encoded as hour * 10000 + minute * 100 + second.
     *
     * For open intervals and invalid strings, DATE_MIN / DATE_MAX are used as
     * sentinels so range queries work without handling NULL.
     */
    public function getValueBounds(?string $edtfString): array
    {
        if ($edtfString === null || $edtfString === '') {
            return [self::DATE_MIN, self::TIME_MIN, self::DATE_MAX, self::TIME_MAX];
        }
        $result = EdtfFactory::newParser()->parse($edtfString);
        if (!$result->isValid()) {
            return [self::DATE_MIN, self::TIME_MIN, self::DATE_MAX, self::TIME_MAX];
        }
        $edtf = $result->getEdtfValue();

        if ($edtf instanceof \EDTF\Model\Interval) {
            if ($edtf->hasStartDate()) {
                [$minDate, $minTime, ,] = $this->boundsOf($edtf->getStartDate());
            } else {
                $minDate = self::DATE_MIN;
                $minTime = self::TIME_MIN;
            }
            if ($edtf->hasEndDate()) {
                [, , $maxDate, $maxTime] = $this->boundsOf($edtf->getEndDate());
            } else {
                $maxDate = self::DATE_MAX;
                $maxTime = self::TIME_MAX;
            }
            return [$minDate, $minTime, $maxDate, $maxTime];
        }

        return $this->boundsOf($edtf);
    }

    /**
     * Compute packed bounds for a non-Interval EDTF node (ExtDate, ExtDateTime,
     * Season, Set).
     */
    protected function boundsOf($node): array
    {
        if ($node instanceof \EDTF\Model\ExtDateTime) {
            $year = $node->getYear();
            $month = $node->getMonth();
            $day = $node->getDay();
            $h = $node->getHour();
            $mi = $node->getMinute();
            $s = $node->getSecond();
            $packedDate = $this->packDate($year, $month, $day);
            $packedTime = $h * 10000 + $mi * 100 + $s;
            return [$packedDate, $packedTime, $packedDate, $packedTime];
        }
        if ($node instanceof \EDTF\Model\ExtDate) {
            $year = $node->getYear();
            if ($year === null) {
                return [self::DATE_MIN, self::TIME_MIN, self::DATE_MAX, self::TIME_MAX];
            }
            $month = $node->getMonth();
            $day = $node->getDay();
            $minDate = $this->packDate($year, $month ?? 1, $day ?? 1);
            $maxMonth = $month ?? 12;
            $maxDate = $this->packDate($year, $maxMonth, $day ?? $this->lastDayOfMonth($year, $maxMonth));
            return [$minDate, self::TIME_MIN, $maxDate, self::TIME_MAX];
        }
        if ($node instanceof \EDTF\Model\Season) {
            $year = $node->getYear();
            $startMonth = $node->getStartMonth();
            $endMonth = $node->getEndMonth();
            // Winter (Dec-Feb) and similar wrap across years.
            if ($endMonth < $startMonth) {
                $minDate = $this->packDate($year, $startMonth, 1);
                $maxDate = $this->packDate($year + 1, $endMonth, $this->lastDayOfMonth($year + 1, $endMonth));
            } else {
                $minDate = $this->packDate($year, $startMonth, 1);
                $maxDate = $this->packDate($year, $endMonth, $this->lastDayOfMonth($year, $endMonth));
            }
            return [$minDate, self::TIME_MIN, $maxDate, self::TIME_MAX];
        }
        if ($node instanceof \EDTF\Model\Set) {
            $minDate = self::DATE_MAX;
            $minTime = self::TIME_MAX;
            $maxDate = self::DATE_MIN;
            $maxTime = self::TIME_MIN;
            foreach ($node->getElements() as $element) {
                $inner = method_exists($element, 'getDate') ? $element->getDate() : $element;
                [$lo, $loT, $hi, $hiT] = $this->boundsOf($inner);
                if ($lo < $minDate || ($lo === $minDate && $loT < $minTime)) {
                    $minDate = $lo;
                    $minTime = $loT;
                }
                if ($hi > $maxDate || ($hi === $maxDate && $hiT > $maxTime)) {
                    $maxDate = $hi;
                    $maxTime = $hiT;
                }
            }
            if ($minDate === self::DATE_MAX) {
                return [self::DATE_MIN, self::TIME_MIN, self::DATE_MAX, self::TIME_MAX];
            }
            return [$minDate, $minTime, $maxDate, $maxTime];
        }
        return [self::DATE_MIN, self::TIME_MIN, self::DATE_MAX, self::TIME_MAX];
    }

    /**
     * Pack (year, month, day) into a signed BIGINT:
     * (year + YEAR_OFFSET) * 10000 + month * 100 + day.
     */
    public function packDate(int $year, int $month, int $day): int
    {
        return ($year + self::YEAR_OFFSET) * 10000 + $month * 100 + $day;
    }

    public function packTime(int $hour, int $minute, int $second): int
    {
        return $hour * 10000 + $minute * 100 + $second;
    }

    protected function lastDayOfMonth(int $year, int $month): int
    {
        static $days = [1 => 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        if ($month === 2 && $this->isLeapYear($year)) {
            return 29;
        }
        return $days[$month] ?? 31;
    }

    protected function isLeapYear(int $year): bool
    {
        return ($year % 4 === 0 && $year % 100 !== 0) || $year % 400 === 0;
    }

    /**
     * edtf => [
     *   lt/lte/gt/gte => [val => <edtf string>, pid => <propertyId>],
     * ]
     *
     * Range queries compare lexicographically on the (date, time) pair using
     * the composite indexes on value_min_date/time and value_max_date/time.
     */
    public function buildQuery(AdapterInterface $adapter, QueryBuilder $qb, array $query): void
    {
        $ops = ['lt', 'lte', 'gt', 'gte'];
        foreach ($ops as $op) {
            if (!isset($query['edtf'][$op]['val'])) {
                continue;
            }
            $value = $query['edtf'][$op]['val'];
            $propertyId = $query['edtf'][$op]['pid'] ?? null;
            if (!$this->isValid(['@value' => $value])) {
                continue;
            }
            [$minDate, $minTime, $maxDate, $maxTime] = $this->getValueBounds($value);
            // "less than" => the item ends before the query value:
            // compare on value_max_* with lower bound.
            // "greater than" => the item starts after the query value:
            // compare on value_min_* with upper bound.
            if ($op === 'lt') {
                $this->addCompositeCompare($adapter, $qb, $propertyId, 'valueMax', '<', $minDate, $minTime);
            } elseif ($op === 'lte') {
                $this->addCompositeCompare($adapter, $qb, $propertyId, 'valueMax', '<=', $maxDate, $maxTime);
            } elseif ($op === 'gt') {
                $this->addCompositeCompare($adapter, $qb, $propertyId, 'valueMin', '>', $maxDate, $maxTime);
            } elseif ($op === 'gte') {
                $this->addCompositeCompare($adapter, $qb, $propertyId, 'valueMin', '>=', $minDate, $minTime);
            }
        }
    }

    /**
     * Add a lexicographic (date, time) comparison against the given column
     * pair, joined on property.
     *
     * @param string $columnPrefix 'valueMin' or 'valueMax'.
     * @param string $op '<', '<=', '>', '>='.
     */
    protected function addCompositeCompare(
        AdapterInterface $adapter,
        QueryBuilder $qb,
        $propertyId,
        string $columnPrefix,
        string $op,
        int $date,
        int $time
    ): void {
        $alias = $adapter->createAlias();
        $with = $qb->expr()->eq("$alias.resource", 'omeka_root.id');
        if (is_numeric($propertyId)) {
            $with = $qb->expr()->andX(
                $with,
                $qb->expr()->eq("$alias.property", (int) $propertyId)
            );
        }
        $qb->leftJoin($this->getEntityClass(), $alias, 'WITH', $with);

        $dateField = "$alias.{$columnPrefix}Date";
        $timeField = "$alias.{$columnPrefix}Time";
        $pDate = $adapter->createNamedParameter($qb, $date);
        $pTime = $adapter->createNamedParameter($qb, $time);

        // (date, time) OP (:d, :t) expanded for DQL:
        // strict:  date OP :d OR (date = :d AND time OP :t)
        // inclusive: date strictOP :d OR (date = :d AND time OP :t)
        $strict = rtrim($op, '=');
        $expr = $qb->expr()->orX(
            "$dateField $strict $pDate",
            $qb->expr()->andX(
                "$dateField = $pDate",
                "$timeField $op $pTime"
            )
        );
        $qb->andWhere($expr);
    }

    public function sortQuery(AdapterInterface $adapter, QueryBuilder $qb, array $query, $type, $propertyId): void
    {
        if ('edtf' === $type) {
            $alias = $adapter->createAlias();
            $qb->addSelect("MIN($alias.valueMinDate) as HIDDEN edtf_sort_date");
            $qb->addSelect("MIN($alias.valueMinTime) as HIDDEN edtf_sort_time");
            $qb->leftJoin(
                $this->getEntityClass(), $alias, 'WITH',
                $qb->expr()->andX(
                    $qb->expr()->eq("$alias.resource", 'omeka_root.id'),
                    $qb->expr()->eq("$alias.property", $propertyId)
                )
            );
            $qb->addOrderBy('edtf_sort_date', $query['sort_order']);
            $qb->addOrderBy('edtf_sort_time', $query['sort_order']);
        }
    }

    public function valueAnnotationPrepareForm(PhpRenderer $view): void
    {
    }

    public function valueAnnotationForm(PhpRenderer $view)
    {
        return $this->form($view);
    }

    /**
     * Check whether a value can be converted to this data type.
     *
     * Implements ConversionTargetInterface::convert() when available
     * (Omeka S 4.2+). The method is always defined so the subclass
     * EdtfConvertible can rely on it.
     */
    public function convert(Value $value, string $dataTypeTarget): bool
    {
        $v = $value->getValue();
        return is_string($v) && $this->isValid(['@value' => $v]);
    }
}
