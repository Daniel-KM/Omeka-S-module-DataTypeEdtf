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
        return sprintf(
            '%s%s',
            $view->formText($element->getPropertyElement()),
            $view->formText($element->getTypeElement())
        );
    }
}
