<?php declare(strict_types=1);

namespace DataTypeEdtfTest\View\Helper;

use CommonTest\AbstractHttpControllerTestCase;
use DataTypeEdtf\Form\Element\ConvertToEdtf;

class ConvertToEdtfTest extends AbstractHttpControllerTestCase
{
    public function testElementIsRenderedWithSelectAndCheckbox(): void
    {
        $services = $this->getApplicationServiceLocator();
        $element = $services->get('FormElementManager')->get(ConvertToEdtf::class);

        $html = $services->get('ViewHelperManager')->get('formConvertToEdtf')($element);

        $this->assertStringContainsString('<select name="edtf_convert&#x5B;property&#x5D;" class="chosen-select"', $html);
        $this->assertStringContainsString('<input type="checkbox" name="edtf_convert&#x5B;type&#x5D;" value="edtf"', $html);
        $this->assertStringNotContainsString('type="hidden"', $html);
        $this->assertStringContainsString('Run the conversion process</label>', $html);
    }
}
