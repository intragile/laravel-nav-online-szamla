<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Console;

use Illuminate\Console\Command;
use IntrAgile\NavOnlineSzamla\Config\Software;
use IntrAgile\NavOnlineSzamla\Exceptions\ConfigurationException;

final class SoftwareIdCommand extends Command
{
    protected $signature = 'nav:software-id
        {tax-number? : Tax number of the software developer; defaults to software.dev_tax_number, then credentials.tax_number}
        {--write : Store the ID as NAV_SOFTWARE_ID in the .env file}
        {--force : Replace an existing NAV_SOFTWARE_ID when writing}';

    protected $description = 'Generate the 18-character NAV softwareId of this application';

    public function handle(): int
    {
        $taxNumber = $this->argument('tax-number')
            ?? config('nav-online-szamla.software.dev_tax_number')
            ?? config('nav-online-szamla.credentials.tax_number');

        try {
            $id = Software::generateId(is_string($taxNumber) ? $taxNumber : '');
        } catch (ConfigurationException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if (! $this->option('write')) {
            $this->line($id);
            $this->components->info('Generate the ID once and keep it: it identifies the software in every installation. Store it as NAV_SOFTWARE_ID.');

            return self::SUCCESS;
        }

        return $this->write($id);
    }

    private function write(string $id): int
    {
        $path = $this->laravel->environmentFilePath();
        if (! is_file($path)) {
            $this->components->error("The environment file does not exist: {$path}");

            return self::FAILURE;
        }

        $contents = (string) file_get_contents($path);
        $pattern = '/^NAV_SOFTWARE_ID=(.*)$/m';

        if (preg_match($pattern, $contents, $match) === 1) {
            if (trim($match[1], " \t\"'") !== '' && ! $this->option('force')) {
                $this->components->error('NAV_SOFTWARE_ID is already set. Changing it makes this installation a different software for NAV; use --force to replace it anyway.');

                return self::FAILURE;
            }
            $contents = (string) preg_replace($pattern, 'NAV_SOFTWARE_ID='.$id, $contents);
        } else {
            $contents = rtrim($contents, "\n")."\nNAV_SOFTWARE_ID={$id}\n";
        }

        file_put_contents($path, $contents);
        $this->components->info("NAV_SOFTWARE_ID={$id} written to {$path}.");

        return self::SUCCESS;
    }
}
