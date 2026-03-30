<?php
namespace EdtfDataType\View\Helper;

use EdtfDataType\Form\Element\EdtfPropertySelect as Select;
use Laminas\Form\Factory;
use Laminas\View\Helper\AbstractHelper;
use Laminas\ServiceManager\ServiceLocatorInterface;

class EdtfPropertySelect extends AbstractHelper
{
    /**
     * @var ServiceLocatorInterface
     */
    protected $formElementManager;

    /**
     * Construct the helper.
     *
     * @param ServiceLocatorInterface $formElementManager
     */
    public function __construct(ServiceLocatorInterface $formElementManager)
    {
        $this->formElementManager = $formElementManager;
    }

    /**
     * Render a select menu containing edtf properties.
     *
     * @param array $spec
     * @return string|null Returns null if the element has no property value options
     */
    public function __invoke(array $spec = [])
    {
        $spec['type'] = Select::class;
        if (!isset($spec['options']['empty_option'])) {
            $spec['options']['empty_option'] = 'Select property…'; // @translate
        }
        $factory = new Factory($this->formElementManager);
        $element = $factory->createElement($spec);
        return $element->getValueOptions() ? $this->getView()->formSelect($element) : null;
    }
}
