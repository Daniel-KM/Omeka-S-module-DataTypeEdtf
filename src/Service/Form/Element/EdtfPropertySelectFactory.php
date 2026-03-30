<?php declare(strict_types=1);

namespace EdtfDataType\Service\Form\Element;

use EdtfDataType\Form\Element\EdtfPropertySelect;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class EdtfPropertySelectFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        $element = new EdtfPropertySelect;
        $element->setEntityManager($services->get('Omeka\EntityManager'));
        return $element;
    }
}
