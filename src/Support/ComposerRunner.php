<?php

namespace RahatulRabbi\TalkBridge\Support;

use Illuminate\Console\Command;

/**
 * ComposerRunner
 *
 * Executes `composer require` and `composer remove` in the host application
 * context during install and uninstall, so users never have to run any
 * Composer command manually.
 */
class ComposerRunner
{
    protected string $workingDir;

    public function __construct(string $workingDir = null)
    {
        $this->workingDir = $workingDir ?? base_path();
    }

    /**
     * Install a Composer package.
     * Returns [success, output].
     */
    public function require(string $package, bool $dev = false): array
    {
        $flag    = $dev ? ' --dev' : '';
        $command = "composer require {$package}{$flag} --no-interaction --prefer-dist 2>&1";
        return $this->run($command);
    }

    /**
     * Remove a Composer package.
     * Returns [success, output].
     */
    public function remove(string $package): array
    {
        $command = "composer remove {$package} --no-interaction 2>&1";
        return $this->run($command);
    }

    /**
     * Check if a package is installed in vendor.
     */
    public function isInstalled(string $vendorClass): bool
    {
        return class_exists($vendorClass);
    }

    protected function run(string $command): array
    {
        $output     = [];
        $returnCode = 0;

        $prevDir = getcwd();
        chdir($this->workingDir);

        exec($command, $output, $returnCode);

        chdir($prevDir);

        return [$returnCode === 0, implode("\n", $output)];
    }
}
