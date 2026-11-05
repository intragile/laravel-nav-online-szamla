<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Tests;

use IntrAgile\NavOnlineSzamla\NavOnlineSzamlaServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [NavOnlineSzamlaServiceProvider::class];
    }
}
