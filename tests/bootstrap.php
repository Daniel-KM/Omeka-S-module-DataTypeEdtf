<?php declare(strict_types=1);

/**
 * Bootstrap file for EdtfDataType module tests.
 *
 * @see \CommonTest\Bootstrap
 */

require dirname(__DIR__, 3) . '/modules/Common/tests/Bootstrap.php';

\CommonTest\Bootstrap::bootstrap(
    [
        'Common',
        'EdtfDataType',
    ],
    'EdtfDataTypeTest',
    __DIR__ . '/EdtfDataTypeTest'
);
