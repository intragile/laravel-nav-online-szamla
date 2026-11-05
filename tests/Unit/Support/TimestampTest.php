<?php

declare(strict_types=1);

namespace Intragile\NavOnlineSzamla\Tests\Unit\Support;

use DateTimeImmutable;
use DateTimeZone;
use Intragile\NavOnlineSzamla\Support\Timestamp;
use Intragile\NavOnlineSzamla\Support\UlidRequestIdGenerator;
use PHPUnit\Framework\TestCase;

final class TimestampTest extends TestCase
{
    public function test_header_format_is_utc_with_milliseconds(): void
    {
        $winter = new DateTimeImmutable('2024-01-15 13:05:09.123456', new DateTimeZone('Europe/Budapest'));

        $this->assertSame('2024-01-15T12:05:09.123Z', Timestamp::header($winter));
        $this->assertSame('20240115120509', Timestamp::signatureMask($winter));
    }

    public function test_header_matches_the_xsd_pattern(): void
    {
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{1,3})?Z$/',
            Timestamp::header(Timestamp::now()),
        );
    }

    public function test_now_is_truncated_to_milliseconds(): void
    {
        $this->assertSame(0, (int) Timestamp::now()->format('u') % 1000);
    }

    public function test_request_ids_match_the_nav_pattern_and_are_unique(): void
    {
        $generator = new UlidRequestIdGenerator;
        $ids = array_map(fn () => $generator->generate(), range(1, 1000));

        foreach ($ids as $id) {
            $this->assertMatchesRegularExpression('/^[+a-zA-Z0-9_]{1,30}$/', $id);
        }
        $this->assertCount(1000, array_unique($ids));
    }
}
