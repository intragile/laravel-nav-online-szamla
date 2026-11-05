<?php

declare(strict_types=1);

namespace Intragile\NavOnlineSzamla\Tests;

use Intragile\NavOnlineSzamla\NavOnlineSzamlaServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [NavOnlineSzamlaServiceProvider::class];
    }
}
