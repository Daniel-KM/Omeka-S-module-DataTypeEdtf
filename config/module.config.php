<?php declare(strict_types=1);

return [
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
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
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
                ? EdtfDataType\DataType\EdtfConvertible::class
                : EdtfDataType\DataType\Edtf::class,
        ],
        'value_annotating' => [
            'edtf:date',
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            'formEdtf' => EdtfDataType\View\Helper\Edtf::class,
            'formConvertToEdtf' => EdtfDataType\View\Helper\ConvertToEdtf::class,
        ],
        'factories' => [
            'edtfPropertySelect' => EdtfDataType\Service\ViewHelper\EdtfPropertySelectFactory::class,
        ],
        'delegators' => [
            'Laminas\Form\View\Helper\FormElement' => [
                EdtfDataType\Service\Delegator\FormElementDelegatorFactory::class,
            ],
        ],
    ],
    'form_elements' => [
        'factories' => [
            'EdtfDataType\Form\Element\EdtfPropertySelect' => EdtfDataType\Service\Form\Element\EdtfPropertySelectFactory::class,
            'EdtfDataType\Form\Element\ConvertToEdtf' => EdtfDataType\Service\Form\Element\ConvertToEdtfFactory::class,
        ],
    ],
    'csv_import' => [
        'data_types' => [
            'edtf' => [
                'label' => 'Extended Date Time Format', // @translate
                'adapter' => 'literal',
            ],
        ],
    ],
    'controllers' => [
        'factories' => [
            'EdtfDataType\Controller\SiteAdmin\FacetedBrowse\Index' => EdtfDataType\Service\Controller\SiteAdmin\FacetedBrowse\IndexControllerFactory::class,
        ],
    ],
    'faceted_browse_facet_types' => [
        'factories' => [
            'date_after' => EdtfDataType\Service\FacetedBrowse\FacetType\DateAfterFactory::class,
            'date_before' => EdtfDataType\Service\FacetedBrowse\FacetType\DateBeforeFactory::class,
            'date_in_interval' => EdtfDataType\Service\FacetedBrowse\FacetType\DateInIntervalFactory::class,
        ],
    ],
    'datavis_dataset_types' => [
        'invokables' => [
            'count_items_time_series' => EdtfDataType\Datavis\DatasetType\CountItemsTimeSeries::class,
            'count_items_property_values_time_series' => EdtfDataType\Datavis\DatasetType\CountItemsPropertyValuesTimeSeries::class,
        ],
    ],
    'datavis_diagram_types' => [
        'invokables' => [
            'line_chart_time_series' => EdtfDataType\Datavis\DiagramType\LineChartTimeSeries::class,
            'histogram_time_series' => EdtfDataType\Datavis\DiagramType\HistogramTimeSeries::class,
            'line_chart_time_series_grouped' => EdtfDataType\Datavis\DiagramType\LineChartTimeSeriesGrouped::class,
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
                                                '__NAMESPACE__' => 'EdtfDataType\Controller\SiteAdmin\FacetedBrowse',
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
];
