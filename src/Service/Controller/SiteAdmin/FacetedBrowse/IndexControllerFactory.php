<?php declare(strict_types=1);

namespace DataTypeEdtf\Service\Controller\SiteAdmin\FacetedBrowse;

use DataTypeEdtf\Controller\SiteAdmin\FacetedBrowse\IndexController;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class IndexControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new IndexController($services);
    }
}
