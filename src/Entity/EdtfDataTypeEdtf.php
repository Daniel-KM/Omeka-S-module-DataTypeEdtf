<?php declare(strict_types=1);

namespace EdtfDataType\Entity;

use Omeka\Entity\AbstractEntity;
use Omeka\Entity\Property;
use Omeka\Entity\Resource;

/**
 * @Entity
 * @Table(
 *     indexes={
 *         @Index(name="property_value", columns={"property_id", "value"}),
 *         @Index(name="value", columns={"value"}),
 *     }
 * )
 */
class EdtfDataTypeEdtf extends AbstractEntity
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
     * @Column(type="string", length=255)
     */
    protected $value;

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

    public function setValue($value): void
    {
        $this->value = $value;
    }

    public function getValue()
    {
        return $this->value;
    }
}
