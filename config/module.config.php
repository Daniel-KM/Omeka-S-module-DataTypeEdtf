<?php declare(strict_types=1);

namespace EdtfDataType;

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
        'invokables' => [
            'edtf:date' => interface_exists(\Omeka\DataType\ConversionTargetInterface::class)
                ? DataType\EdtfConvertible::class
                : DataType\Edtf::class,
        ],
        'value_annotating' => [
            'edtf:date',
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
        'factories' => [
            'Form\Element\EdtfPropertySelect' => Service\Form\Element\EdtfPropertySelectFactory::class,
            'Form\Element\ConvertToEdtf' => Service\Form\Element\ConvertToEdtfFactory::class,
        ],
    ],
    'controllers' => [
        'factories' => [
            'Controller\SiteAdmin\FacetedBrowse\Index' => Service\Controller\SiteAdmin\FacetedBrowse\IndexControllerFactory::class,
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
                                    'edtf-data-type-faceted-browse' => [
                                        'type' => \Laminas\Router\Http\Segment::class,
                                        'options' => [
                                            'route' => '/edtf-data-type-faceted-browse/:controller/:action',
                                            'constraints' => [
                                                'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                                'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                            ],
                                            'defaults' => [
                                                '__NAMESPACE__' => 'Controller\SiteAdmin\FacetedBrowse',
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
        'Add time', // @translate
        'At least one side of the interval must be specified', // @translate
        'April', // @translate
        'Apply', // @translate
        'Approximate (~)', // @translate
        'August', // @translate
        'Autumn', // @translate
        'Autumn - Northern', // @translate
        'Autumn - Southern', // @translate
        'Cancel', // @translate
        'Century (19XX)', // @translate
        'Close', // @translate
        'Copy', // @translate
        'Copy start to end', // @translate
        'Day', // @translate
        'December', // @translate
        'Decade (198X)', // @translate
        'EDTF Assistant', // @translate
        'February', // @translate
        'Hour', // @translate
        'Interval (two dates)', // @translate
        'Interval end must be on or after its start', // @translate
        'Invalid date', // @translate
        'Invalid day for this month', // @translate
        'Invalid hour', // @translate
        'Invalid minute', // @translate
        'Invalid month', // @translate
        'Invalid second', // @translate
        'Qualifiers (uncertain, approximate) cannot be used with reduced precision', // @translate
        'Qualifiers (uncertain, approximate) cannot be used with seasons', // @translate
        'Seasons and sub-year groupings cannot be used in intervals', // @translate
        'Swap', // @translate
        'Swap start and end', // @translate
        'Time is only allowed with day precision', // @translate
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
        'Quarters', // @translate
        'Seasons', // @translate
        'Seasons (Northern Hemisphere)', // @translate
        'Seasons (Southern Hemisphere)', // @translate
        'Second', // @translate
        'Semester 1', // @translate
        'Semester 2', // @translate
        'Semesters', // @translate
        'September', // @translate
        'Spring', // @translate
        'Spring - Northern', // @translate
        'Spring - Southern', // @translate
        'Summer', // @translate
        'Summer - Northern', // @translate
        'Summer - Southern', // @translate
        'Uncertain (?)', // @translate
        'Winter', // @translate
        'Winter - Northern', // @translate
        'Winter - Southern', // @translate
        'Year', // @translate
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
            'count_items_time_series' => Datavis\DatasetType\CountItemsTimeSeries::class,
            'count_items_property_values_time_series' => Datavis\DatasetType\CountItemsPropertyValuesTimeSeries::class,
        ],
    ],
    'datavis_diagram_types' => [
        'invokables' => [
            'line_chart_time_series' => Datavis\DiagramType\LineChartTimeSeries::class,
            'histogram_time_series' => Datavis\DiagramType\HistogramTimeSeries::class,
            'line_chart_time_series_grouped' => Datavis\DiagramType\LineChartTimeSeriesGrouped::class,
        ],
    ],
    'faceted_browse_facet_types' => [
        'factories' => [
            'date_after' => Service\FacetedBrowse\FacetType\DateAfterFactory::class,
            'date_before' => Service\FacetedBrowse\FacetType\DateBeforeFactory::class,
            'date_in_interval' => Service\FacetedBrowse\FacetType\DateInIntervalFactory::class,
        ],
    ],
];
