<?php declare(strict_types=1);

namespace DataTypeEdtf\Service\DataType;

use DataTypeEdtf\DataType\Edtf;
use DataTypeEdtf\DataType\EdtfConvertible;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class EdtfFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        $class = interface_exists(\Omeka\DataType\ConversionTargetInterface::class)
            ? EdtfConvertible::class
            : Edtf::class;
        /** @var Edtf $dataType */
        $dataType = new $class();
        $dataType->setSettings($services->get('Omeka\Settings'));
        return $dataType;
    }
}
