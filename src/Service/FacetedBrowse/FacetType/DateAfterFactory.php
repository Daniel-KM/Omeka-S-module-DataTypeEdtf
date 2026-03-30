<?php declare(strict_types=1);

namespace DataTypeEdtf\Service\FacetedBrowse\FacetType;

use DataTypeEdtf\FacetedBrowse\FacetType\DateAfter;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class DateAfterFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new DateAfter($services->get('FormElementManager'));
    }
}
