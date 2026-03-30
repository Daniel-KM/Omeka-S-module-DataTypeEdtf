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

    /**
     * Add a comparison query on a column of the specialized entity.
     *
     * @param string $op One of 'lt', 'lte', 'gt', 'gte'.
     * @param string $column Doctrine field name (default 'valueMin').
     */
    protected function addComparisonQuery(
        AdapterInterface $adapter,
        QueryBuilder $qb,
        $propertyId,
        $number,
        string $op,
        string $column = 'valueMin'
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
        $qb->andWhere($qb->expr()->$op(
            "$alias.$column",
            $adapter->createNamedParameter($qb, $number)
        ));
    }

    public function addLessThanQuery(AdapterInterface $adapter, QueryBuilder $qb, $propertyId, $number, string $column = 'valueMin'): void
    {
        $this->addComparisonQuery($adapter, $qb, $propertyId, $number, 'lt', $column);
    }

    public function addGreaterThanQuery(AdapterInterface $adapter, QueryBuilder $qb, $propertyId, $number, string $column = 'valueMin'): void
    {
        $this->addComparisonQuery($adapter, $qb, $propertyId, $number, 'gt', $column);
    }

    public function addLessThanOrEqualToQuery(AdapterInterface $adapter, QueryBuilder $qb, $propertyId, $number, string $column = 'valueMin'): void
    {
        $this->addComparisonQuery($adapter, $qb, $propertyId, $number, 'lte', $column);
    }

    public function addGreaterThanOrEqualToQuery(AdapterInterface $adapter, QueryBuilder $qb, $propertyId, $number, string $column = 'valueMin'): void
    {
        $this->addComparisonQuery($adapter, $qb, $propertyId, $number, 'gte', $column);
    }
}
