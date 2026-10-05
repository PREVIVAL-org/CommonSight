<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

// The region assignment and contract tests need the generated master data.
if (!is_file(dirname(__DIR__) . '/generated/regions-CH.php')) {
    fwrite(STDERR, "Generating master data (bin/build-contract.php) ...\n");
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/bin/build-contract.php'), $code);
    if ($code !== 0) {
        exit($code);
    }
}
