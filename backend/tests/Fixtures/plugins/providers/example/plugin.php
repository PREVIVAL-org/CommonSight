<?php

declare(strict_types=1);

// Manifest of the example plugin for the tests. Real plugins in plugins/ are autoloaded; this one loads its classes itself.
require_once __DIR__ . '/backend/ExampleFactory.php';
require_once __DIR__ . '/backend/ExamplePlugin.php';

return new CommonSight\Tests\Fixtures\Plugins\Example\ExampleFactory();
