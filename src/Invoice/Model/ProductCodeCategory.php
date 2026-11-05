<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice\Model;

/**
 * The type used to mark the kind of product code.
 *
 * Generated from ProductCodeCategoryType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
enum ProductCodeCategory: string
{
    /** Customs tariff number CTN. */
    case Vtsz = 'VTSZ';

    /** Business service list number BSL. */
    case Szj = 'SZJ';

    /** CN code (Combined Nomenclature, 2658/87/ECC Annex I). */
    case Kn = 'KN';

    /** Administrative reference code of e-TKO defined in the Act LXVIII of 2016 on Excise Duties. */
    case Ahk = 'AHK';

    /** Packaging product catalogue code of the product according to the Title A) in the Schedule No.1 of the Government Decree No. 343/2011. (XII. 29.). */
    case Csk = 'CSK';

    /** Environmental protection product code of the product according to the Title B) in the Schedule No.1 of the Government Decree No. 343/2011. (XII. 29.). */
    case Kt = 'KT';

    /** Classification of Inventory of Construction. */
    case Ej = 'EJ';

    /** Product code according to the TESZOR (Hungarian Classification of Goods and Services), Classification of Product by Activity, CPA - regulation 451/2008/EC. */
    case Teszor = 'TESZOR';

    /** The own product code of the enterprise. */
    case Own = 'OWN';

    /** Other product code. */
    case Other = 'OTHER';
}
