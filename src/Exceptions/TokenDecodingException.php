<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Exceptions;

/**
 * Thrown when an exchange token cannot be decoded with the configured exchange key.
 */
final class TokenDecodingException extends NavException {}
