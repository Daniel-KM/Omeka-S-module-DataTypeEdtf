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
 *         @Index(name="idx_property_value_min", columns={"property_id", "value_min_date", "value_min_time"}),
 *         @Index(name="idx_property_value_max", columns={"property_id", "value_max_date", "value_max_time"}),
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
     * Earliest date component of the EDTF value, packed as
     * (year + 10^14) * 10000 + month * 100 + day. PHP_INT_MIN is used
     * as sentinel for an open/unknown start.
     *
     * @Column(type="bigint")
     */
    protected $valueMinDate;

    /**
     * Earliest time-of-day component, packed as
     * hour * 10000 + minute * 100 + second (0..235959). 0 when no
     * time is provided.
     *
     * @Column(type="integer")
     */
    protected $valueMinTime;

    /**
     * Latest date component of the EDTF value, same encoding as
     * valueMinDate. PHP_INT_MAX is used as sentinel for an open end.
     *
     * @Column(type="bigint")
     */
    protected $valueMaxDate;

    /**
     * Latest time-of-day component, same encoding as valueMinTime.
     * 235959 when no time is provided.
     *
     * @Column(type="integer")
     */
    protected $valueMaxTime;

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

    public function setValueMinDate(int $valueMinDate): void
    {
        $this->valueMinDate = $valueMinDate;
    }

    public function getValueMinDate(): ?int
    {
        return $this->valueMinDate === null ? null : (int) $this->valueMinDate;
    }

    public function setValueMinTime(int $valueMinTime): void
    {
        $this->valueMinTime = $valueMinTime;
    }

    public function getValueMinTime(): ?int
    {
        return $this->valueMinTime === null ? null : (int) $this->valueMinTime;
    }

    public function setValueMaxDate(int $valueMaxDate): void
    {
        $this->valueMaxDate = $valueMaxDate;
    }

    public function getValueMaxDate(): ?int
    {
        return $this->valueMaxDate === null ? null : (int) $this->valueMaxDate;
    }

    public function setValueMaxTime(int $valueMaxTime): void
    {
        $this->valueMaxTime = $valueMaxTime;
    }

    public function getValueMaxTime(): ?int
    {
        return $this->valueMaxTime === null ? null : (int) $this->valueMaxTime;
    }
}
