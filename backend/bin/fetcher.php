<?php

declare(strict_types=1);

// Entry point of the fetcher (Architecture 4.12). Calling it without arguments shows the help.

use CommonSight\Entry\FetcherCli;
use CommonSight\Model\Decoded;

require dirname(__DIR__) . '/vendor/autoload.php';

exit((new FetcherCli(dirname(__DIR__)))->main(Decoded::of($_SERVER['argv'] ?? [])->strings()));
