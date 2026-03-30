<?php declare(strict_types=1);

namespace DataTypeEdtf\DataType;

use Doctrine\ORM\QueryBuilder;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Adapter\AdapterInterface;
use Omeka\Api\Representation\ValueRepresentation;
use Omeka\DataType\DataTypeWithOptionsInterface;

abstract class AbstractDataType implements DataTypeWithOptionsInterface, DataTypeInterface
{
    public function getOptgroupLabel()
    {
        return 'Edtf'; // @translate
    }

    public function prepareForm(PhpRenderer $view): void
    {
    }

    public function toString(ValueRepresentation $value)
    {
        return (string) $value->value();
    }

    public function getFulltextText(PhpRenderer $view, ValueRepresentation $value)
    {
        return $value->value();
    }

    public function buildQuery(AdapterInterface $adapter, QueryBuilder $qb, array $query): void
    {
    }

    public function sortQuery(AdapterInterface $adapter, QueryBuilder $qb, array $query, $type, $propertyId): void
    {
    }

}
