<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Support;

use InvalidArgumentException;
use Stringable;

/**
 * Arbitrary-precision decimal number backed by bcmath.
 *
 * The lexical form is kept exactly as given, so values read from XML are written back
 * unchanged. Addition, subtraction and multiplication are exact and keep the scale of
 * their operands (like bcmath); round() drops insignificant zeros, toScale() pads.
 */
final readonly class Decimal implements Stringable
{
    private const INTERNAL_SCALE = 20;

    /** @var numeric-string */
    public string $value;

    private function __construct(string $value)
    {
        // is_numeric() alone would also accept exponents and surrounding whitespace.
        if (preg_match('/^[+-]?(\d+(\.\d*)?|\.\d+)$/', $value) !== 1 || ! is_numeric($value)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a decimal number.', $value));
        }

        $this->value = $value;
    }

    /**
     * Floats are formatted with up to 10 fraction digits; pass strings for exact values.
     */
    public static function of(self|int|float|string $value): self
    {
        return match (true) {
            $value instanceof self => $value,
            is_int($value) => new self((string) $value),
            is_float($value) => new self(self::trim(sprintf('%.10F', $value))),
            default => new self(trim($value)),
        };
    }

    public static function zero(): self
    {
        return new self('0');
    }

    public function add(self|int|string $other): self
    {
        $other = self::of($other);

        return new self(self::normalizeZero(bcadd($this->value, $other->value, max($this->scale(), $other->scale()))));
    }

    public function sub(self|int|string $other): self
    {
        $other = self::of($other);

        return new self(self::normalizeZero(bcsub($this->value, $other->value, max($this->scale(), $other->scale()))));
    }

    public function mul(self|int|string $other): self
    {
        $other = self::of($other);

        return new self(self::normalizeZero(bcmul($this->value, $other->value, $this->scale() + $other->scale())));
    }

    /**
     * Divides and rounds the quotient half away from zero to the given scale.
     */
    public function div(self|int|string $other, int $scale = self::INTERNAL_SCALE): self
    {
        $other = self::of($other);
        if ($other->isZero()) {
            throw new InvalidArgumentException('Division by zero.');
        }

        return (new self(bcdiv($this->value, $other->value, self::INTERNAL_SCALE + 1)))->round($scale);
    }

    /**
     * Rounds half away from zero, the commercial rounding used on Hungarian invoices.
     */
    public function round(int $scale = 0): self
    {
        $half = bcdiv('5', bcpow('10', (string) ($scale + 1)), $scale + 1);
        $shifted = $this->isNegative() ? bcsub($this->value, $half, $scale + 1) : bcadd($this->value, $half, $scale + 1);

        return new self(self::trim(bcadd($shifted, '0', $scale)));
    }

    /**
     * The same value with exactly the given number of fraction digits, rounded if needed.
     */
    public function toScale(int $scale): self
    {
        return new self(self::normalizeZero(bcadd($this->round($scale)->value, '0', $scale)));
    }

    public function negate(): self
    {
        return $this->isZero() ? $this : new self(self::normalizeZero(bcmul($this->value, '-1', $this->scale())));
    }

    public function abs(): self
    {
        return $this->isNegative() ? $this->negate() : $this;
    }

    public function compare(self|int|string $other): int
    {
        $other = self::of($other);

        return bccomp($this->value, $other->value, max($this->scale(), $other->scale()));
    }

    public function equals(self|int|string $other): bool
    {
        return $this->compare($other) === 0;
    }

    public function isZero(): bool
    {
        return $this->compare('0') === 0;
    }

    public function isNegative(): bool
    {
        return $this->compare('0') < 0;
    }

    public function isPositive(): bool
    {
        return $this->compare('0') > 0;
    }

    public function scale(): int
    {
        $dot = strpos($this->value, '.');

        return $dot === false ? 0 : strlen($this->value) - $dot - 1;
    }

    public function toFloat(): float
    {
        return (float) $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Removes insignificant trailing zeros and a negative sign on zero.
     */
    private static function trim(string $value): string
    {
        if (str_contains($value, '.')) {
            $value = rtrim(rtrim($value, '0'), '.');
        }

        return self::normalizeZero($value === '' ? '0' : $value);
    }

    /**
     * bcmath may return "-0.00" for negative operands that round to zero.
     */
    private static function normalizeZero(string $value): string
    {
        return preg_match('/^-0*(\.0*)?$/', $value) === 1 ? substr($value, 1) : $value;
    }
}
