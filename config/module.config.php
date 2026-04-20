<?php declare(strict_types=1);

namespace DataTypeEdtf;

return [
    'entity_manager' => [
        'mapping_classes_paths' => [
            dirname(__DIR__) . '/src/Entity',
        ],
        'proxy_paths' => [
            dirname(__DIR__) . '/data/doctrine-proxies',
        ],
    ],
    'data_types' => [
        'factories' => [
            'edtf' => Service\DataType\EdtfFactory::class,
        ],
        'value_annotating' => [
            'edtf',
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            'formEdtf' => View\Helper\Edtf::class,
            'formConvertToEdtf' => View\Helper\ConvertToEdtf::class,
        ],
        'factories' => [
            'edtfPropertySelect' => Service\ViewHelper\EdtfPropertySelectFactory::class,
        ],
        'delegators' => [
            'Laminas\Form\View\Helper\FormElement' => [
                Service\Delegator\FormElementDelegatorFactory::class,
            ],
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\ConfigForm::class => Form\ConfigForm::class,
            Form\SettingsFieldset::class => Form\SettingsFieldset::class,
            Form\SiteSettingsFieldset::class => Form\SiteSettingsFieldset::class,
        ],
        'factories' => [
            'DataTypeEdtf\Form\Element\EdtfPropertySelect' => Service\Form\Element\EdtfPropertySelectFactory::class,
            'DataTypeEdtf\Form\Element\ConvertToEdtf' => Service\Form\Element\ConvertToEdtfFactory::class,
        ],
    ],
    'controllers' => [
        'factories' => [
            'DataTypeEdtf\Controller\SiteAdmin\FacetedBrowse\Index' => Service\Controller\SiteAdmin\FacetedBrowse\IndexControllerFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'site' => [
                        'child_routes' => [
                            'slug' => [
                                'child_routes' => [
                                    'faceted-browse-edtf' => [
                                        'type' => \Laminas\Router\Http\Segment::class,
                                        'options' => [
                                            'route' => '/faceted-browse-edtf/:controller/:action',
                                            'constraints' => [
                                                'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                                'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                            ],
                                            'defaults' => [
                                                '__NAMESPACE__' => 'DataTypeEdtf\Controller\SiteAdmin\FacetedBrowse',
                                                'controller' => 'index',
                                                'action' => 'index',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'js_translate_strings' => [
        '%1$s to %2$s', // @translate
        '%s century', // @translate
        '%s millennium', // @translate
        '%ss', // @translate
        'About calendar and year numbering', // @translate
        'Add time', // @translate
        'After %1$s', // @translate
        'Apply', // @translate
        'Approximate (~)', // @translate
        'April', // @translate
        'Assistant for Extended Date/Time Format', // @translate
        'At least one side of the interval must be specified', // @translate
        'August', // @translate
        'Autumn', // @translate
        'Autumn - Northern', // @translate
        'Autumn - Southern', // @translate
        'BCE', // @translate
        'Before %1$s', // @translate
        'Cancel', // @translate
        'Date before the Gregorian reform (4th/15th October 1582). Enter the value in proleptic Gregorian.', // @translate
        'Century (19XX)', // @translate
        'Checked: the empty side means unknown. Unchecked: it means open, extending indefinitely (..)', // @translate
        'Close', // @translate
        'Copy', // @translate
        'Copy start to end', // @translate
        'Day', // @translate
        'Decade (198X)', // @translate
        'December', // @translate
        'February', // @translate
        'Help', // @translate
        'Hour', // @translate
        'Interval (two dates)', // @translate
        'Interval end must be on or after its start', // @translate
        'Invalid date', // @translate
        'Invalid day for this month', // @translate
        'Invalid hour', // @translate
        'Invalid minute', // @translate
        'Invalid month', // @translate
        'Invalid second', // @translate
        'January', // @translate
        'July', // @translate
        'June', // @translate
        'March', // @translate
        'May', // @translate
        'Millennium (1XXX)', // @translate
        'Minute', // @translate
        'Month', // @translate
        'Months', // @translate
        'November', // @translate
        'October', // @translate
        'Offset', // @translate
        'Precision', // @translate
        'Q1', // @translate
        'Q2', // @translate
        'Q3', // @translate
        'Q4', // @translate
        'Quadrimester 1', // @translate
        'Quadrimester 2', // @translate
        'Quadrimester 3', // @translate
        'Quadrimesters', // @translate
        'Qualifiers (uncertain, approximate) cannot be used with reduced precision', // @translate
        'Qualifiers (uncertain, approximate) cannot be used with seasons', // @translate
        'Quarters', // @translate
        'Seasons', // @translate
        'Seasons (Northern Hemisphere)', // @translate
        'Seasons (Southern Hemisphere)', // @translate
        'Seasons and sub-year groupings cannot be used in intervals', // @translate
        'Second', // @translate
        'Semester 1', // @translate
        'Semester 2', // @translate
        'Semesters', // @translate
        'September', // @translate
        'Since %1$s', // @translate
        'Spring', // @translate
        'Spring - Northern', // @translate
        'Spring - Southern', // @translate
        'Summer', // @translate
        'Summer - Northern', // @translate
        'Summer - Southern', // @translate
        'Swap', // @translate
        'Swap start and end', // @translate
        'The extended format uses the proleptic Gregorian calendar with astronomical year numbering (year 0 = 1 BCE). Historical dates before the Gregorian reform of 1582 (or later in some countries) are usually recorded in the Julian calendar in sources and must be converted before entry. For instance:', // @translate
        'Time is only allowed with day precision', // @translate
        'Toggle humanized view', // @translate
        'Uncertain (?)', // @translate
        'Unknown side', // @translate
        'Until %1$s', // @translate
        'Winter', // @translate
        'Winter - Northern', // @translate
        'Winter - Southern', // @translate
        'Year', // @translate
        'approximate', // @translate
        'at', // @translate
        'the Battle of Lepanto (7 October 1571 Julian) must be entered as 1571-10-17;', // @translate
        'the Battle of Marathon (12 September 490 BCE Julian) as -0489-09-07.', // @translate
        'uncertain', // @translate
        'uncertain and approximate', // @translate
    ],
    'csv_import' => [
        'data_types' => [
            'edtf' => [
                'label' => 'Extended Date Time Format', // @translate
                'adapter' => 'literal',
            ],
        ],
    ],
    'datavis_dataset_types' => [
        'invokables' => [
            'count_items_edtf' => Datavis\DatasetType\CountItemsTimeSeries::class,
            'count_items_property_values_edtf' => Datavis\DatasetType\CountItemsPropertyValuesTimeSeries::class,
        ],
    ],
    'datavis_diagram_types' => [
        'invokables' => [
            'line_chart_edtf' => Datavis\DiagramType\LineChartTimeSeries::class,
            'histogram_edtf' => Datavis\DiagramType\HistogramTimeSeries::class,
            'line_chart_edtf_grouped' => Datavis\DiagramType\LineChartTimeSeriesGrouped::class,
        ],
    ],
    'faceted_browse_facet_types' => [
        'factories' => [
            'edtf_after' => Service\FacetedBrowse\FacetType\DateAfterFactory::class,
            'edtf_before' => Service\FacetedBrowse\FacetType\DateBeforeFactory::class,
            'edtf_in_interval' => Service\FacetedBrowse\FacetType\DateInIntervalFactory::class,
        ],
    ],
    'datatypeedtf' => [
        'config' => [
        ],
        'settings' => [
            'datatypeedtf_humanizer' => 'library',
            'datatypeedtf_calendar_mode' => 'gregorian',
            'datatypeedtf_show_calendar' => false,
            'datatypeedtf_reform_date' => '1582-10-15',
        ],
        'site_settings' => [
            'datatypeedtf_humanizer' => '',
            'datatypeedtf_calendar_mode' => '',
            'datatypeedtf_show_calendar' => '',
            'datatypeedtf_reform_date' => '',
        ],
    ],
];
