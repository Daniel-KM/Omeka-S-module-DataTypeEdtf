<?php declare(strict_types=1);

namespace EdtfDataType\Entity;

/**
 * @Entity
 */
class EdtfDataTypeEdtf extends EdtfDataTypeSuper
{
    /**
     * @Column(type="string", length=255)
     */
    protected $value;

    public function setValue($value): void
    {
        $this->value = $value;
    }

    public function getValue()
    {
        return $this->value;
    }
}
