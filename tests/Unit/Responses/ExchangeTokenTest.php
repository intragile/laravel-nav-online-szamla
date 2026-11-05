<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Tests\Unit\Responses;

use DateTimeImmutable;
use IntrAgile\NavOnlineSzamla\Responses\ExchangeToken;
use PHPUnit\Framework\TestCase;

final class ExchangeTokenTest extends TestCase
{
    private function token(): ExchangeToken
    {
        return new ExchangeToken('t', new DateTimeImmutable('2026-11-05T11:17:55.161Z'), new DateTimeImmutable('2026-11-05T11:22:55.162Z'));
    }

    public function test_a_token_starting_slightly_after_the_local_clock_is_valid(): void
    {
        $this->assertTrue($this->token()->isValidAt(new DateTimeImmutable('2026-11-05T11:17:55.083Z')));
    }

    public function test_validity_keeps_the_margin_before_expiry(): void
    {
        $this->assertTrue($this->token()->isValidAt(new DateTimeImmutable('2026-11-05T11:22:24Z')));
        $this->assertFalse($this->token()->isValidAt(new DateTimeImmutable('2026-11-05T11:22:26Z')));
    }

    public function test_a_token_far_in_the_future_is_not_valid_yet(): void
    {
        $this->assertFalse($this->token()->isValidAt(new DateTimeImmutable('2026-11-05T11:16:00Z')));
    }

    public function test_secrets_are_masked_in_debug_output(): void
    {
        $this->assertSame('***', $this->token()->__debugInfo()['token']);
    }
}
