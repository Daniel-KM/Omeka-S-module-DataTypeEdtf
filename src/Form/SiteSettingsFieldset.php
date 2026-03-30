<?php declare(strict_types=1);

namespace DataTypeEdtf\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;

class SiteSettingsFieldset extends Fieldset
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
                    'info' => 'Controls how EDTF values are rendered on this site. Leave empty to use the general setting.', // @translate
                    'empty_option' => 'Use general setting', // @translate
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
                    'info' => 'Leave empty to use the general setting.', // @translate
                    'value_options' => [
                        '' => 'Use general setting', // @translate
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
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'element_group' => 'edtf',
                    'label' => 'Show calendar indicator for pre-reform dates', // @translate
                    'info' => 'Appends [grég.] or [jul.] to dates before the reform date.', // @translate
                    'value_options' => [
                        '' => 'Use general setting', // @translate
                        '0' => 'No', // @translate
                        '1' => 'Yes', // @translate
                    ],
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
                    'info' => 'Leave empty to use the general setting. Default: 1582-10-15.', // @translate
                ],
                'attributes' => [
                    'id' => 'datatypeedtf_reform_date',
                    'placeholder' => '1582-10-15',
                ],
            ])
        ;
    }
}
