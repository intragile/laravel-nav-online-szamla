<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Enums;

/**
 * Verification status of a technical annulment.
 */
enum AnnulmentVerificationStatus: string
{
    case NotVerifiable = 'NOT_VERIFIABLE';
    case VerificationPending = 'VERIFICATION_PENDING';
    case VerificationDone = 'VERIFICATION_DONE';
    case VerificationRejected = 'VERIFICATION_REJECTED';
}
