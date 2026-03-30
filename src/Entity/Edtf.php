<?php declare(strict_types=1);

namespace DataTypeEdtf\Entity;

use Omeka\Entity\AbstractEntity;
use Omeka\Entity\Property;
use Omeka\Entity\Resource;

/**
 * @Entity
 * @Table(
 *     name="data_type_edtf",
 *     indexes={
 *         @Index(name="idx_property_value_min", columns={"property_id", "value_min"}),
 *         @Index(name="idx_property_value_max", columns={"property_id", "value_max"}),
 *     }
 * )
 */
class Edtf extends AbstractEntity
{
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    protected $id;

    /**
     * @ManyToOne(targetEntity="Omeka\Entity\Resource")
     * @JoinColumn(nullable=false, onDelete="CASCADE")
     */
    protected $resource;

    /**
     * @ManyToOne(targetEntity="Omeka\Entity\Property")
     * @JoinColumn(nullable=false, onDelete="CASCADE")
     */
    protected $property;

    /**
     * Earliest possible Unix timestamp for the EDTF value. PHP_INT_MIN
     * for unknown/open start.
     *
     * @Column(type="bigint")
     */
    protected $valueMin;

    /**
     * Latest possible Unix timestamp for the EDTF value. PHP_INT_MAX
     * for unknown/open end.
     *
     * @Column(type="bigint")
     */
    protected $valueMax;

    public function getId()
    {
        return $this->id;
    }

    public function setResource(Resource $resource): void
    {
        $this->resource = $resource;
    }

    public function getResource()
    {
        return $this->resource;
    }

    public function setProperty(Property $property): void
    {
        $this->property = $property;
    }

    public function getProperty()
    {
        return $this->property;
    }

    public function setValueMin(int $valueMin): void
    {
        $this->valueMin = $valueMin;
    }

    public function getValueMin(): ?int
    {
        return $this->valueMin === null ? null : (int) $this->valueMin;
    }

    public function setValueMax(int $valueMax): void
    {
        $this->valueMax = $valueMax;
    }

    public function getValueMax(): ?int
    {
        return $this->valueMax === null ? null : (int) $this->valueMax;
    }
}
