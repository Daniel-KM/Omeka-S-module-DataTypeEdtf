<?php declare(strict_types=1);

namespace EdtfDataType\Service\ViewHelper;

use EdtfDataType\View\Helper\EdtfPropertySelect;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class EdtfPropertySelectFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new EdtfPropertySelect($services->get('FormElementManager'));
    }
}
