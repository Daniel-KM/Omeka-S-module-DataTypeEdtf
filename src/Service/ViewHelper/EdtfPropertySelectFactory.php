<?php declare(strict_types=1);

namespace DataTypeEdtf\Service\ViewHelper;

use DataTypeEdtf\View\Helper\EdtfPropertySelect;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class EdtfPropertySelectFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new EdtfPropertySelect($services->get('FormElementManager'));
    }
}
