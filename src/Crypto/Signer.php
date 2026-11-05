<?php

declare(strict_types=1);

namespace Intragile\NavOnlineSzamla\Crypto;

use DateTimeInterface;
use Intragile\NavOnlineSzamla\Support\Timestamp;

/**
 * A NAV interfész-authentikációjának hash-számításai (spec v3.0, 1.3.2 és 1.5 fejezet).
 */
final class Signer
{
    /** A technikai felhasználó jelszavának nagybetűs SHA-512 hash-e. */
    public static function passwordHash(string $password): string
    {
        return strtoupper(hash('sha512', $password));
    }

    /**
     * A requestSignature értéke.
     *
     * Üres `$operations` esetén ez a "parciális" aláírás, amit minden lekérdező operáció használ.
     * manageInvoice / manageAnnulment esetén az `$operations` a kérés indexeinek sorrendjében
     * tartalmazza a műveletet (pl. CREATE) és a base64 tartalmat; a NAV az index-sorrendet is
     * beleszámolja, ezért a sorrend itt kötelezően azonos a kérésbelivel.
     *
     * @param  list<array{operation: string, data: string}>  $operations
     */
    public static function requestSignature(
        string $requestId,
        DateTimeInterface $timestamp,
        string $signKey,
        array $operations = [],
    ): string {
        $base = $requestId.Timestamp::signatureMask($timestamp).$signKey;

        foreach ($operations as $operation) {
            $base .= self::sha3($operation['operation'].$operation['data']);
        }

        return self::sha3($base);
    }

    /**
     * Elektronikus számla (completenessIndicator) hash-lenyomata.
     * completenessIndicator=true esetén csak SHA3-512 elfogadott (spec 2.x, electronicInvoiceHash).
     */
    public static function electronicInvoiceHash(string $content, HashAlgorithm $algorithm = HashAlgorithm::Sha3_512): string
    {
        return strtoupper(hash($algorithm->phpName(), $content));
    }

    private static function sha3(string $value): string
    {
        return strtoupper(hash('sha3-512', $value));
    }
}
