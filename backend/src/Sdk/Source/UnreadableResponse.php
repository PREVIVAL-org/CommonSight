<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Source;

/** A response cannot be evaluated as a whole (wrong format, broken JSON or XML). */
final class UnreadableResponse extends \RuntimeException {}
