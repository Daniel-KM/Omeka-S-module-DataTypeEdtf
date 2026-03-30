<?php declare(strict_types=1);

namespace DataTypeEdtf\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;

class SettingsFieldset extends Fieldset
{
    protected $label = 'Extended Date/Time Format'; // @translate

    protected $elementGroups = [
        'edtf' => 'Extended Date/Time Format', // @translate
    ];

    public function init(): void
    {
        $this
            ->setAttribute('id', 'datatypeedtf')
            ->setOption('element_groups', $this->elementGroups)

            ->add([
                'name' => 'datatypeedtf_humanizer',
                'type' => CommonElement\OptionalSelect::class,
                'options' => [
                    'element_group' => 'edtf',
                    'label' => 'Humanizer style', // @translate
                    'info' => 'Controls how EDTF values are rendered in human-readable form. This is the default for all sites.', // @translate
                    'value_options' => [
                        'library' => 'Bundled library (ProfessionalWiki/EDTF, multilingual)', // @translate
                        'fr_usage' => 'Usage courant en français', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'datatypeedtf_humanizer',
                ],
            ])
            ->add([
                'name' => 'datatypeedtf_calendar_mode',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'element_group' => 'edtf',
                    'label' => 'Calendar for pre-reform dates', // @translate
                    'info' => 'EDTF stores dates in proleptic Gregorian. For display, dates before the reform date can be converted to Julian, which matches historical sources.', // @translate
                    'value_options' => [
                        'gregorian' => 'Proleptic Gregorian (as stored)', // @translate
                        'julian' => 'Convert to Julian before reform date', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'datatypeedtf_calendar_mode',
                ],
            ])
            ->add([
                'name' => 'datatypeedtf_show_calendar',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'edtf',
                    'label' => 'Show calendar indicator for pre-reform dates', // @translate
                    'info' => 'Appends [grég.] or [jul.] to dates before the reform date.', // @translate
                ],
                'attributes' => [
                    'id' => 'datatypeedtf_show_calendar',
                ],
            ])
            ->add([
                'name' => 'datatypeedtf_reform_date',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'edtf',
                    'label' => 'Gregorian reform date', // @translate
                    'info' => 'Date from which the Gregorian calendar is used (ISO 8601 format). Dates before this are considered pre-reform. Default: 1582-10-15 (papal reform). Some countries adopted it later: England 1752-09-14, Russia 1918-02-14.', // @translate
                ],
                'attributes' => [
                    'id' => 'datatypeedtf_reform_date',
                    'placeholder' => '1582-10-15',
                ],
            ])
        ;
    }
}
