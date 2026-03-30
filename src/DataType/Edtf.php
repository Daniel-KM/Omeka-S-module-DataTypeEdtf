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
        if (!$this->isValid(['@value' => $value->value()])) {
            return ['@value' => $value->value()];
        }
        $date = $this->toEdtf($value);
        $type = "xsd:string";
        # @todo this could be made much more specific using
        # all of the qualitifications of https://github.com/ProfessionalWiki/EDTF
        # a bit of relevant discussion here: https://github.com/Islandora/documentation/issues/916
        // if (isset($date['month']) && isset($date['day']) && isset($date['hour']) && isset($date['minute']) && isset($date['second']) && isset($date['offset_value'])) {
        //     $type = 'http://www.w3.org/2001/XMLSchema#dateTime';
        // } elseif (isset($date['month']) && isset($date['day']) && isset($date['hour']) && isset($date['minute']) && isset($date['offset_value'])) {
        //     $type = 'http://www.w3.org/2001/XMLSchema#dateTime';
        // } elseif (isset($date['month']) && isset($date['day']) && isset($date['hour']) && isset($date['offset_value'])) {
        //     $type = 'http://www.w3.org/2001/XMLSchema#dateTime';
        // } elseif (isset($date['month']) && isset($date['day']) && isset($date['hour']) && isset($date['minute']) && isset($date['second'])) {
        //     $type = 'http://www.w3.org/2001/XMLSchema#dateTime';
        // } elseif (isset($date['month']) && isset($date['day']) && isset($date['hour']) && isset($date['minute'])) {
        //     $type = null; // XSD has no datatype for truncated seconds
        // } elseif (isset($date['month']) && isset($date['day']) && isset($date['hour'])) {
        //     $type = null; // XSD has no datatype for truncated minutes/seconds
        // } elseif (isset($date['month']) && isset($date['day'])) {
        //     $type = 'http://www.w3.org/2001/XMLSchema#date';
        // } elseif (isset($date['month'])) {
        //     $type = 'http://www.w3.org/2001/XMLSchema#gYearMonth';
        // } else {
        //     $type = 'http://www.w3.org/2001/XMLSchema#gYear';
        // }
        $jsonLd = ['@value' => $value->value()];
        if ($type) {
            $jsonLd['@type'] = $type;
        }
        return $jsonLd;
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
        // Parse only once: reuse the result for validity check and
        // humanization.
        $parsingResult = EdtfFactory::newParser()->parse($raw);
        if (!$parsingResult->isValid()) {
            return $raw;
        }
        $humanizer = EdtfFactory::newHumanizerForLanguage($view->lang() ?? 'en');
        $response = $humanizer->humanize($parsingResult->getEdtfValue());
        return $response !== '' ? $response : $raw;
    }

    public function getFulltextText(PhpRenderer $view, ValueRepresentation $value)
    {

        return sprintf('%s %s', $value->value(), $this->render($view, $value));
    }

    public function getEntityClass()
    {
        return 'DataTypeEdtf\Entity\Edtf';
    }

    public function setEntityValues(EdtfEntity $entity, Value $value): void
    {
        [$min, $max] = $this->getValueBounds($value->getValue());
        $entity->setValueMin($min);
        $entity->setValueMax($max);
    }

    /**
     * Compute the (min, max) Unix timestamp bounds of an EDTF string.
     *
     * For open intervals, PHP_INT_MIN / PHP_INT_MAX are used as
     * sentinels so range queries work without handling NULL.
     */
    public function getValueBounds(?string $edtfString): array
    {
        if ($edtfString === null || $edtfString === '') {
            return [PHP_INT_MIN, PHP_INT_MAX];
        }
        $result = EdtfFactory::newParser()->parse($edtfString);
        if (!$result->isValid()) {
            return [PHP_INT_MIN, PHP_INT_MAX];
        }
        $edtf = $result->getEdtfValue();
        if ($edtf instanceof \EDTF\Model\Interval) {
            $min = $edtf->hasStartDate() ? $edtf->getStartDate()->getMin() : PHP_INT_MIN;
            $max = $edtf->hasEndDate() ? $edtf->getEndDate()->getMax() : PHP_INT_MAX;
            return [$min, $max];
        }
        return [$edtf->getMin(), $edtf->getMax()];
    }

    /**
     * edtf => [
     *   date => [
     *     lt/lte => [val => <edtf string>, pid => <propertyId>],
     *     gt/gte => [val => <edtf string>, pid => <propertyId>],
     *   ],
     * ]
     *
     * Range queries use value_min and value_max on the specialized
     * entity table for efficient indexing.
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
            [$min, $max] = $this->getValueBounds($value);
            // For "less than" queries, filter on value_max of the
            // stored item: item ends before the query date.
            // For "greater than", filter on value_min: item starts
            // after the query date.
            if ($op === 'lt') {
                $this->addLessThanQuery($adapter, $qb, $propertyId, $min, 'valueMax');
            } elseif ($op === 'lte') {
                $this->addLessThanOrEqualToQuery($adapter, $qb, $propertyId, $max, 'valueMax');
            } elseif ($op === 'gt') {
                $this->addGreaterThanQuery($adapter, $qb, $propertyId, $max, 'valueMin');
            } elseif ($op === 'gte') {
                $this->addGreaterThanOrEqualToQuery($adapter, $qb, $propertyId, $min, 'valueMin');
            }
        }
    }

    public function sortQuery(AdapterInterface $adapter, QueryBuilder $qb, array $query, $type, $propertyId): void
    {
        if ('edtf' === $type) {
            $alias = $adapter->createAlias();
            $qb->addSelect("MIN($alias.valueMin) as HIDDEN edtf_sort");
            $qb->leftJoin(
                $this->getEntityClass(), $alias, 'WITH',
                $qb->expr()->andX(
                    $qb->expr()->eq("$alias.resource", 'omeka_root.id'),
                    $qb->expr()->eq("$alias.property", $propertyId)
                )
            );
            $qb->addOrderBy('edtf_sort', $query['sort_order']);
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
