<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice;

use IntrAgile\NavOnlineSzamla\Invoice\Model\DetailedReason;
use IntrAgile\NavOnlineSzamla\Invoice\Model\MarginScheme;
use IntrAgile\NavOnlineSzamla\Invoice\Model\VatAmountMismatch;
use IntrAgile\NavOnlineSzamla\Invoice\Model\VatRate;
use IntrAgile\NavOnlineSzamla\Support\Decimal;
use InvalidArgumentException;

/**
 * Factories for the VAT classification of a line or a VAT rate summary.
 */
final class Vat
{
    /**
     * VAT content (VAT / gross) used on simplified invoices, keyed by the VAT rate. The
     * values are the ones prescribed by NAV, not computed ratios.
     */
    private const CONTENT_BY_RATE = [
        '0.27' => '0.2126',
        '0.18' => '0.1525',
        '0.05' => '0.0476',
        '0' => '0',
    ];

    /**
     * Normal VAT rate given as a fraction ("0.27") or a percentage (27, "27").
     */
    public static function rate(Decimal|int|string $rate): VatRate
    {
        return new VatRate(vatPercentage: self::fraction($rate));
    }

    /**
     * VAT content of a simplified invoice, given directly ("0.2126").
     */
    public static function content(Decimal|string $content): VatRate
    {
        return new VatRate(vatContent: Decimal::of($content));
    }

    /**
     * VAT content of a simplified invoice derived from the VAT rate (27 → 0.2126).
     */
    public static function contentForRate(Decimal|int|string $rate): VatRate
    {
        $fraction = self::fraction($rate);
        foreach (self::CONTENT_BY_RATE as $known => $content) {
            if ($fraction->equals((string) $known)) {
                return self::content($content);
            }
        }

        throw new InvalidArgumentException(sprintf('No VAT content is defined for rate %s.', $fraction));
    }

    /**
     * Tax exempt supply with its NAV code (AAM, TAM, KBAET, KBAUK, EAM, NAM) and the reason shown on the invoice.
     */
    public static function exempt(string $case, string $reason): VatRate
    {
        return new VatRate(vatExemption: new DetailedReason($case, $reason));
    }

    /**
     * Supply outside the scope of the VAT law with its NAV code (ATK, EUFAD37, EUFADE, EUE, HO) and the reason shown on the invoice.
     */
    public static function outOfScope(string $case, string $reason): VatRate
    {
        return new VatRate(vatOutOfScope: new DetailedReason($case, $reason));
    }

    public static function domesticReverseCharge(): VatRate
    {
        return new VatRate(vatDomesticReverseCharge: true);
    }

    public static function marginScheme(MarginScheme $scheme): VatRate
    {
        return new VatRate(marginSchemeIndicator: $scheme);
    }

    /**
     * Tax base and levied tax differ, e.g. Vat::amountMismatch(27, 'REFUNDABLE_VAT').
     */
    public static function amountMismatch(Decimal|int|string $rate, string $case): VatRate
    {
        return new VatRate(vatAmountMismatch: new VatAmountMismatch(self::fraction($rate), $case));
    }

    /**
     * No VAT charged under section 17 of the VAT law.
     */
    public static function noCharge(): VatRate
    {
        return new VatRate(noVatCharge: true);
    }

    /**
     * The rate used to compute the VAT amount of a normal line, or null when no VAT is charged.
     */
    public static function chargedRate(VatRate $rate): ?Decimal
    {
        return $rate->vatPercentage ?? $rate->vatAmountMismatch?->vatRate;
    }

    /**
     * Stable identity of a classification, used to group lines into VAT rate summaries.
     * Exemptions group by their code; the reason is free text and may vary between lines.
     */
    public static function key(VatRate $rate): string
    {
        return match (true) {
            $rate->vatPercentage !== null => 'percentage:'.$rate->vatPercentage->round(4)->value,
            $rate->vatContent !== null => 'content:'.$rate->vatContent->round(4)->value,
            $rate->vatExemption !== null => 'exemption:'.$rate->vatExemption->case,
            $rate->vatOutOfScope !== null => 'outOfScope:'.$rate->vatOutOfScope->case,
            $rate->vatDomesticReverseCharge !== null => 'reverseCharge',
            $rate->marginSchemeIndicator !== null => 'margin:'.$rate->marginSchemeIndicator->value,
            $rate->vatAmountMismatch !== null => 'mismatch:'.$rate->vatAmountMismatch->case.':'.$rate->vatAmountMismatch->vatRate->round(4)->value,
            default => 'noCharge',
        };
    }

    private static function fraction(Decimal|int|string $rate): Decimal
    {
        $rate = Decimal::of($rate);
        if ($rate->isNegative()) {
            throw new InvalidArgumentException('A VAT rate cannot be negative.');
        }

        // Values above 1 are percentages: 27 means 27 %.
        return $rate->compare(1) > 0 ? $rate->div(100, 4) : $rate;
    }
}
