<?php

declare(strict_types=1);

// Deliberately faulty plugin: the generic plugin check must find every one of its problems.
require_once __DIR__ . '/backend/BrokenFactory.php';
require_once __DIR__ . '/backend/BrokenPlugin.php';

return new CommonSight\Tests\Fixtures\Plugins\Broken\BrokenFactory();
