<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Tests\Unit\Support;

use IntrAgile\NavOnlineSzamla\Config\Software;
use IntrAgile\NavOnlineSzamla\Exceptions\ConfigurationException;
use PHPUnit\Framework\TestCase;

final class SoftwareTest extends TestCase
{
    public function test_generated_id_has_the_nav_format(): void
    {
        $id = Software::generateId('12345678-2-42');

        $this->assertMatchesRegularExpression('/^HU12345678-[0-9A-Z]{7}$/', $id);
        $this->assertSame(18, strlen($id));
        $this->assertSame($id, (new Software($id, 'n', 'ONLINE_SERVICE', '1', 'd', 'c'))->id);
    }

    public function test_invalid_id_is_rejected(): void
    {
        $this->expectException(ConfigurationException::class);
        new Software('too-short', 'n', 'ONLINE_SERVICE', '1', 'd', 'c');
    }
}
