<?php

declare(strict_types=1);

namespace Intragile\NavOnlineSzamla\Tests\Unit\Crypto;

use DateTimeImmutable;
use Intragile\NavOnlineSzamla\Crypto\HashAlgorithm;
use Intragile\NavOnlineSzamla\Crypto\Signer;
use PHPUnit\Framework\TestCase;

/** Az elvárt értékek a NAV specifikáció (v3.0, 2026.02.12) 1.5.1 fejezetének példájából. */
final class SignerTest extends TestCase
{
    private const REQUEST_ID = 'TSTKFT1222564';

    private const SIGN_KEY = 'ce-8f5e-215119fa7dd621DLMRHRLH2S';

    public function test_manage_invoice_signature_matches_the_specification_example(): void
    {
        $signature = Signer::requestSignature(
            self::REQUEST_ID,
            new DateTimeImmutable('2017-12-30T18:25:45.000Z'),
            self::SIGN_KEY,
            [
                ['operation' => 'CREATE', 'data' => 'QWJjZDEyMzQ='],
                ['operation' => 'MODIFY', 'data' => 'RGNiYTQzMjE='],
            ],
        );

        $this->assertSame(
            '60BC80609EE3B8F42FE904200A49A1921A1DADA08D55319ACD40C59F626514B74EEA49011D372600A10DBCF8199D590DA9C2841D987308F2D83DAE17C2470C42',
            $signature,
        );
    }

    public function test_index_hashes_match_the_specification_example(): void
    {
        $this->assertSame(
            '4317798460962869BC67F07C48EA7E4A3AFA301513CEB87B8EB94ECF92BC220A89C480F87F0860E85E29A3B6C0463D4F29712C5AD48104A6486CE839DC2F24CB',
            strtoupper(hash('sha3-512', 'CREATEQWJjZDEyMzQ=')),
        );
    }

    public function test_query_signature_is_the_hash_of_the_partial_authentication(): void
    {
        $signature = Signer::requestSignature(self::REQUEST_ID, new DateTimeImmutable('2017-12-30T18:25:45.000Z'), self::SIGN_KEY);

        $this->assertSame(
            strtoupper(hash('sha3-512', 'TSTKFT122256420171230182545ce-8f5e-215119fa7dd621DLMRHRLH2S')),
            $signature,
        );
    }

    public function test_local_time_is_converted_to_utc_before_masking(): void
    {
        // Budapest, nyári időszámítás: GMT+2 → 2017-07-01 10:00:00 helyi = 08:00:00 UTC.
        $local = new DateTimeImmutable('2017-07-01 10:00:00', new \DateTimeZone('Europe/Budapest'));
        $utc = new DateTimeImmutable('2017-07-01T08:00:00Z');

        $this->assertSame(
            Signer::requestSignature('X', $utc, 'k'),
            Signer::requestSignature('X', $local, 'k'),
        );
    }

    public function test_password_hash_is_uppercase_sha512(): void
    {
        $this->assertSame(strtoupper(hash('sha512', 'secret')), Signer::passwordHash('secret'));
        $this->assertMatchesRegularExpression('/^[0-9A-F]{128}$/', Signer::passwordHash('secret'));
    }

    public function test_electronic_invoice_hash_defaults_to_uppercase_sha3_512(): void
    {
        $this->assertSame(strtoupper(hash('sha3-512', 'pdf')), Signer::electronicInvoiceHash('pdf'));
        $this->assertSame(strtoupper(hash('sha256', 'pdf')), Signer::electronicInvoiceHash('pdf', HashAlgorithm::Sha256));
    }
}
