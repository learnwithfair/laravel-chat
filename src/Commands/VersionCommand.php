<?php

namespace RahatulRabbi\TalkBridge\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class VersionCommand extends Command
{
    protected $signature = 'talkbridge:version
                            {--check : Check Packagist for a newer version}';

    protected $description = 'Show the installed TalkBridge version and recent changelog';

    protected string $packageName  = 'rahatulrabbi/talkbridge';
    protected string $packagistUrl = 'https://repo.packagist.org/p2/rahatulrabbi/talkbridge.json';
    protected string $githubUrl    = 'https://github.com/learnwithfair/talkbridge';

    public function handle(): int
    {
        $installed = $this->getInstalledVersion();

        $this->newLine();
        $this->line('  +----------------------------------------------------+');
        $this->line('  |   TalkBridge — Version Info                        |');
        $this->line('  +----------------------------------------------------+');
        $this->newLine();
        $this->line("  Installed : <info>{$installed}</info>");
        $this->line("  Package   : {$this->packageName}");
        $this->line("  Docs      : {$this->githubUrl}");
        $this->newLine();

        if ($this->option('check')) {
            $this->checkForUpdates($installed);
        } else {
            $this->line('  Tip: run <comment>php artisan talkbridge:version --check</comment> to check for updates.');
            $this->newLine();
        }

        $this->showRecentChangelog();

        return self::SUCCESS;
    }

    protected function getInstalledVersion(): string
    {
        // 1. Check composer.lock — most reliable
        $lockFile = base_path('composer.lock');

        if (File::exists($lockFile)) {
            $lock     = json_decode(File::get($lockFile), true);
            $packages = array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []);

            foreach ($packages as $package) {
                if ($package['name'] === $this->packageName) {
                    return $package['version'] ?? 'unknown';
                }
            }
        }

        // 2. Fallback: read from vendor/rahatulrabbi/talkbridge/composer.json
        $vendorJson = base_path("vendor/{$this->packageName}/composer.json");

        if (File::exists($vendorJson)) {
            $data = json_decode(File::get($vendorJson), true);
            return $data['version'] ?? 'unknown';
        }

        return 'unknown';
    }

    protected function checkForUpdates(string $installed): void
    {
        $this->line('  Checking Packagist for latest version...');

        try {
            $context = stream_context_create(['http' => ['timeout' => 5]]);
            $json    = @file_get_contents($this->packagistUrl, false, $context);

            if ($json === false) {
                $this->warn('  Could not reach Packagist. Check your internet connection.');
                return;
            }

            $data     = json_decode($json, true);
            $packages = $data['packages'][$this->packageName] ?? [];

            if (empty($packages)) {
                $this->warn('  Package not yet on Packagist or no versions found.');
                return;
            }

            // Latest stable version
            $latest = collect($packages)
                ->filter(fn($v) => ! str_contains($v['version'] ?? '', 'dev'))
                ->sortByDesc(fn($v) => $v['version_normalized'] ?? '0.0.0.0')
                ->first();

            $latestVersion = $latest['version'] ?? 'unknown';

            $this->newLine();

            if ($latestVersion === 'unknown') {
                $this->warn('  Could not determine latest version.');
            } elseif (ltrim($installed, 'v') === ltrim($latestVersion, 'v')) {
                $this->info("  You are on the latest version ({$installed}).");
            } else {
                $this->newLine();
                $this->warn("  Update available: {$installed} -> <comment>{$latestVersion}</comment>");
                $this->newLine();
                $this->line('  Update with:');
                $this->line('    <comment>php artisan talkbridge:update</comment>');
                $this->line('  Or manually:');
                $this->line("    <comment>composer update {$this->packageName}</comment>");
            }

            $this->newLine();

        } catch (\Throwable $e) {
            $this->warn('  Could not check for updates: ' . $e->getMessage());
        }
    }

    protected function showRecentChangelog(): void
    {
        // Look in vendor first, fallback to package source
        $candidates = [
            base_path("vendor/{$this->packageName}/CHANGELOG.md"),
            __DIR__ . '/../../CHANGELOG.md',
        ];

        $changelogPath = null;
        foreach ($candidates as $candidate) {
            if (File::exists($candidate)) {
                $changelogPath = $candidate;
                break;
            }
        }

        if (! $changelogPath) {
            return;
        }

        $content = File::get($changelogPath);
        $lines   = explode("\n", $content);

        $this->line('  Recent changes:');
        $this->line('  ' . str_repeat('-', 50));

        $inBlock  = false;
        $shown    = 0;
        $maxLines = 18;

        foreach ($lines as $line) {
            // Version header
            if (preg_match('/^## \[/', $line)) {
                if ($inBlock) break; // Stop after first version block
                $inBlock = true;
                $this->line("  <info>{$line}</info>");
                $shown++;
                continue;
            }

            if ($inBlock && $shown < $maxLines) {
                if (trim($line) !== '') {
                    $this->line("  {$line}");
                    $shown++;
                }
            }
        }

        $this->newLine();
        $this->line("  Full changelog: {$changelogPath}");
        $this->newLine();
    }
}
