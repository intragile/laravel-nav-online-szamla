<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Tests\Support;

use IntrAgile\NavOnlineSzamla\Enums\InvoiceAppearance;
use IntrAgile\NavOnlineSzamla\Enums\PaymentMethod;
use IntrAgile\NavOnlineSzamla\Invoice\InvoiceBuilder;
use IntrAgile\NavOnlineSzamla\Invoice\LineItem;
use IntrAgile\NavOnlineSzamla\Invoice\Model\UnitOfMeasure;
use IntrAgile\NavOnlineSzamla\Invoice\Party;
use IntrAgile\NavOnlineSzamla\Invoice\Vat;

final class InvoiceFactory
{
    public static function domestic(string $number = 'INV-1', string $date = '2026-11-05'): InvoiceBuilder
    {
        return InvoiceBuilder::create($number)
            ->issueDate($date)
            ->appearance(InvoiceAppearance::Electronic)
            ->payment(PaymentMethod::Transfer, '2026-11-20')
            ->supplier(Party::supplier('12345678-2-41', 'Eladó Kft.', Party::address('HU', '1011', 'Budapest', 'Fő utca 1.'), '11111111-22222222-33333333'))
            ->customer(Party::domesticCustomer('87654321-2-13', 'Vevő Kft.', Party::address('HU', '2000', 'Szentendre', 'Kossuth tér 2.')));
    }

    public static function product(string $quantity = '2', string $price = '1000', int|string $rate = 27): LineItem
    {
        return LineItem::product('Termék')->quantity($quantity, UnitOfMeasure::Piece)->unitPrice($price)->vat(Vat::rate($rate));
    }
}
