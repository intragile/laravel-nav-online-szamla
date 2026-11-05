<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Tests\Unit\Support;

use IntrAgile\NavOnlineSzamla\Support\Decimal;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DecimalTest extends TestCase
{
    public function test_keeps_lexical_form(): void
    {
        $this->assertSame('1000.00', Decimal::of('1000.00')->value);
        $this->assertSame('-0.5', Decimal::of(' -0.5 ')->value);
        $this->assertSame('12', Decimal::of(12)->value);
        $this->assertSame('0.1', Decimal::of(0.1)->value);
    }

    /**
     * @return iterable<array{string}>
     */
    public static function invalid(): iterable
    {
        yield ['1e5'];
        yield ['abc'];
        yield [''];
        yield ['1,5'];
        yield ['--1'];
    }

    #[DataProvider('invalid')]
    public function test_rejects_non_decimal_input(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        Decimal::of($value);
    }

    public function test_arithmetic_is_exact(): void
    {
        $this->assertSame('0.3', Decimal::of('0.1')->add('0.2')->value);
        $this->assertSame('-0.1', Decimal::of('0.1')->sub('0.2')->value);
        $this->assertSame('12.345', Decimal::of('1.2345')->mul(10)->value);
        $this->assertSame('3.33', Decimal::of(10)->div(3, 2)->value);
        $this->assertSame('6.67', Decimal::of(20)->div(3, 2)->value);
    }

    /**
     * @return iterable<array{string, int, string}>
     */
    public static function rounding(): iterable
    {
        yield ['2.5', 0, '3'];
        yield ['-2.5', 0, '-3'];
        yield ['2.4999', 0, '2'];
        yield ['1.005', 2, '1.01'];
        yield ['-1.005', 2, '-1.01'];
        yield ['1270.4999999', 2, '1270.5'];
        yield ['-0.004', 2, '0'];
        yield ['99.995', 2, '100'];
    }

    #[DataProvider('rounding')]
    public function test_rounds_half_away_from_zero(string $value, int $scale, string $expected): void
    {
        $this->assertSame($expected, Decimal::of($value)->round($scale)->value);
    }

    public function test_to_scale_pads_fraction_digits(): void
    {
        $this->assertSame('1270.00', Decimal::of('1270')->toScale(2)->value);
        $this->assertSame('0.27', Decimal::of('0.2650')->toScale(2)->value);
    }

    public function test_comparison_ignores_scale(): void
    {
        $this->assertTrue(Decimal::of('1.50')->equals('1.5'));
        $this->assertSame(-1, Decimal::of('1.49')->compare('1.5'));
        $this->assertTrue(Decimal::of('-0.00')->isZero());
        $this->assertSame('5', Decimal::of('-5')->abs()->value);
        $this->assertSame('0', Decimal::of('0')->negate()->value);
    }

    public function test_division_by_zero_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Decimal::of(1)->div(0);
    }
}
