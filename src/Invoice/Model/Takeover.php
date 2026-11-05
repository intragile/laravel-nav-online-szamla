<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice\Model;

/**
 * Field type for data of takeover.
 *
 * Generated from TakeoverType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
enum Takeover: string
{
    /** The supplier takes over buyer’s product fee liability on the basis of Paragraph (4), Section 14 of the Act LXXXV of 2011. */
    case Value01 = '01';

    /** On the basis of contract, the buyer takes over supplier’s product fee liability on the basis of sub-point aa), Paragraph (5), Section 14 of the Act LXXXV of 2011. */
    case Value02Aa = '02_aa';

    case Value02Ab = '02_ab';

    case Value02B = '02_b';

    case Value02C = '02_c';

    case Value02D = '02_d';

    case Value02Ea = '02_ea';

    case Value02Eb = '02_eb';

    case Value02Fa = '02_fa';

    case Value02Fb = '02_fb';

    case Value02Ga = '02_ga';

    case Value02Gb = '02_gb';
}
