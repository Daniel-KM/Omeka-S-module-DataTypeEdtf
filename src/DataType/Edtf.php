<?php
namespace EdtfDataType\DataType;

use Doctrine\ORM\QueryBuilder;
use EdtfDataType\Entity\EdtfDataTypeEdtf;
use EdtfDataType\Form\Element\Edtf as EdtfElement;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Adapter\AdapterInterface;
use Omeka\Api\Representation\ValueRepresentation;
use Omeka\DataType\ValueAnnotatingInterface;
use Omeka\Entity\Value;
use Laminas\View\Renderer\PhpRenderer;
use \EDTF\EdtfFactory;

class Edtf extends AbstractDateTimeDataType implements ValueAnnotatingInterface
{
    public function getName()
    {
        return 'edtf:date';
    }

    public function getLabel()
    {
        return 'EDTF Date/Time'; // @translate
    }

    public function prepareForm(PhpRenderer $view)
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
        # @todo for some reason even if this is invalid, it saves.
        $parser = EdtfFactory::newParser();
        $parsingResult = $parser->parse($valueObject['@value']);

        if(!$parsingResult->isValid()) {
            return (bool) false;
        }
        return (bool) true;
    }

    public function hydrate(array $valueObject, Value $value, AbstractEntityAdapter $adapter)
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
        if (!$this->isValid(['@value' => $value->value()])) {
            return $value->value();
        }

        $humanizer = EdtfFactory::newHumanizerForLanguage(
            $view->lang() ?? 'en',
        );
        $response = $humanizer->humanize(
            $this->toEdtf($value)->getEdtfValue()
        );

        // Handles valid dates that do not return a humanized value.
        return $response !== ''
            ? $response
            : $value->value();

    }

    public function getFulltextText(PhpRenderer $view, ValueRepresentation $value)
    {

        return sprintf('%s %s', $value->value(), $this->render($view, $value));
    }

    public function getEntityClass()
    {
        return 'EdtfDataType\Entity\EdtfDataTypeEdtf';
    }

    public function setEntityValues(EdtfDataTypeEdtf $entity, Value $value)
    {
      
        // Set the datetime as a string
        $edtfDate = $value->getValue();
        $entity->setValue($edtfDate);
    }

    /**
     * edtf => [
     *   date => [
     *     lt/lte => [val => <date>, pid => <propertyID>],
     *     gt/gte => [val => <date>, pid => <propertyID>],
     *   ],
     * ]
     */
    public function buildQuery(AdapterInterface $adapter, QueryBuilder $qb, array $query)
    {
        if (isset($query['edtf']['date']['lt']['val'])) {
            $value = $query['edtf']['date']['lt']['val'];
            $propertyId = $query['edtf']['date']['lt']['pid'] ?? null;
            if ($this->isValid(['@value' => $value])) {
                $this->addLessThanQuery($adapter, $qb, $propertyId, $value);
            }
        }
        if (isset($query['edtf']['date']['gt']['val'])) {
            $value = $query['edtf']['date']['gt']['val'];
            $propertyId = $query['edtf']['date']['gt']['pid'] ?? null;
            if ($this->isValid(['@value' => $value])) {
                $this->addGreaterThanQuery($adapter, $qb, $propertyId, $value);
            }
        }
        if (isset($query['edtf']['date']['lte']['val'])) {
            $value = $query['edtf']['date']['lte']['val'];
            $propertyId = $query['edtf']['date']['lte']['pid'] ?? null;
            if ($this->isValid(['@value' => $value])) {
                $this->addLessThanOrEqualToQuery($adapter, $qb, $propertyId, $value);
            }
        }
        if (isset($query['edtf']['date']['gte']['val'])) {
            $value = $query['edtf']['date']['gte']['val'];
            $propertyId = $query['edtf']['date']['gte']['pid'] ?? null;
            if ($this->isValid(['@value' => $value])) {
                $this->addGreaterThanOrEqualToQuery($adapter, $qb, $propertyId, $value);
            }
        }
    }

    public function sortQuery(AdapterInterface $adapter, QueryBuilder $qb, array $query, $type, $propertyId)
    {
        if ('date' === $type) {
            $alias = $adapter->createAlias();
            $qb->addSelect("MIN($alias.value) as HIDDEN edtf_value");
            $qb->leftJoin(
                $this->getEntityClass(), $alias, 'WITH',
                $qb->expr()->andX(
                    $qb->expr()->eq("$alias.resource", 'omeka_root.id'),
                    $qb->expr()->eq("$alias.property", $propertyId)
                )
            );
            $qb->addOrderBy('edtf_value', $query['sort_order']);
        }
    }

    public function valueAnnotationPrepareForm(PhpRenderer $view)
    {
    }

    public function valueAnnotationForm(PhpRenderer $view)
    {
        return $this->form($view);
    }
}
