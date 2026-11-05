<?php

declare(strict_types=1);

namespace Intragile\NavOnlineSzamla\Exceptions;

/** A NAV által adott token nem dekódolható a megadott cserekulccsal. */
final class TokenDecodingException extends NavException {}
