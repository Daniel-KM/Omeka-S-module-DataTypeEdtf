<?php declare(strict_types=1);

namespace DataTypeEdtf\View\Helper;

use Laminas\Form\ElementInterface;
use Laminas\Form\View\Helper\AbstractHelper;

class ConvertToEdtf extends AbstractHelper
{
    public function __invoke(ElementInterface $element)
    {
        return $this->render($element);
    }

    public function render(ElementInterface $element)
    {
        $view = $this->getView();
        $typeElement = $element->getTypeElement();
        return sprintf(
            '%s<label>%s %s</label>',
            $view->formSelect($element->getPropertyElement()),
            $view->formCheckbox($typeElement),
            $view->escapeHtml($view->translate($typeElement->getLabel()))
        );
    }
}
