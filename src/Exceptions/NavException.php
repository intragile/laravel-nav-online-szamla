<?php

declare(strict_types=1);

namespace Intragile\NavOnlineSzamla\Exceptions;

use RuntimeException;

/** A csomag összes kivételének közös őse: egy `catch` elég mindenre. */
class NavException extends RuntimeException {}
