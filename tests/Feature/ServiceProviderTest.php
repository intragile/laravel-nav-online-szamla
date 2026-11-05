<?php

declare(strict_types=1);

namespace Intragile\NavOnlineSzamla\Tests\Feature;

use Intragile\NavOnlineSzamla\Tests\TestCase;

final class ServiceProviderTest extends TestCase
{
    public function test_config_is_merged_and_defaults_to_test_environment(): void
    {
        $this->assertSame('test', config('nav-online-szamla.environment'));
        $this->assertStringStartsWith('https://api-test.', (string) config('nav-online-szamla.urls.test'));
    }
}
