<?php declare(strict_types=1);

namespace EdtfDataType\Form\Element;

use Laminas\Form\Element;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Form\Element\PropertySelect;

class ConvertToEdtf extends Element
{
    protected $formElements;
    protected $propertyElement;
    protected $typeElement;

    public function setFormElementManager(ServiceLocatorInterface  $formElements): void
    {
        $this->formElements = $formElements;
    }

    public function init(): void
    {
        $this->setAttribute('data-collection-action', 'replace');
        $this->setLabel('Convert to EDTF'); // @translate
        $this->propertyElement = $this->formElements->get(PropertySelect::class)
            ->setName('edtf_convert[property]')
            ->setEmptyOption('Select property') // @translate
            ->setAttributes([
                'class' => 'chosen-select',
                'data-placeholder' => 'Select property', // @translate
            ]);
        $this->typeElement = (new Element\Select('edtf_convert[type]'))
            ->setEmptyOption('[No change]') // @translate
            ->setValueOptions([
                'edtf:date' => 'Convert to EDTF', // @translate
            ]);
    }

    public function getPropertyElement()
    {
        return $this->propertyElement;
    }

    public function getTypeElement()
    {
        return $this->typeElement;
    }
}
