<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Exceptions;

/**
 * Thrown for missing or malformed configuration, always before any request is sent.
 */
final class ConfigurationException extends NavException {}
