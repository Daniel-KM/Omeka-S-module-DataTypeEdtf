<?php declare(strict_types=1);

namespace EdtfDataType\Service\Form\Element;

use EdtfDataType\Form\Element\ConvertToEdtf;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ConvertToEdtfFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $element = new ConvertToEdtf;
        $element->setFormElementManager($services->get('FormElementManager'));
        return $element;
    }
}
