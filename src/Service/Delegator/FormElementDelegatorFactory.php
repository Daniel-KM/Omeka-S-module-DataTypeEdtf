<?php declare(strict_types=1);

namespace DataTypeEdtf\Service\Delegator;

use Laminas\ServiceManager\Factory\DelegatorFactoryInterface;
use Psr\Container\ContainerInterface;

class FormElementDelegatorFactory implements DelegatorFactoryInterface
{
    public function __invoke(ContainerInterface $container, $name,
        callable $callback, ?array $options = null
    ) {
        $formElement = $callback();
        $formElement->addClass(
            \DataTypeEdtf\Form\Element\Edtf::class,
            'formEdtf'
        );
        $formElement->addClass(
            \DataTypeEdtf\Form\Element\ConvertToEdtf::class,
            'formConvertToEdtf'
        );
        return $formElement;
    }
}
