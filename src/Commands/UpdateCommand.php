<?php

namespace RahatulRabbi\TalkBridge\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use RahatulRabbi\TalkBridge\Support\ComposerRunner;

class UpdateCommand extends Command
{
    protected $signature = 'talkbridge:update
                            {--version=      : Install a specific version e.g. --version=1.0.1}
                            {--force         : Overwrite published config, migrations, and stubs}
                            {--skip-composer : Skip running composer update (useful in CI)}';

    protected $description = 'Update TalkBridge to the latest version and re-publish assets';

    protected string $packageName = 'rahatulrabbi/talkbridge';

    public function handle(): int
    {
        $this->printHeader();

        $before = $this->getInstalledVersion();
        $this->line("  Current version : <info>{$before}</info>");
        $this->newLine();

        // Step 1 — composer update
        if (! $this->option('skip-composer')) {
            $this->step(1, 'Running composer update');
            $this->runComposerUpdate();
        } else {
            $this->line('  [1] Skipping composer update (--skip-composer)');
        }

        $after = $this->getInstalledVersion();

        // Step 2 — Re-publish config (never overwrite by default — only if --force)
        $this->step(2, 'Re-publishing config');
        $this->republishConfig();

        // Step 3 — Re-publish migrations (always safe — new migrations only)
        $this->step(3, 'Re-publishing migrations');
        $this->republishMigrations();

        // Step 4 — Re-publish stubs (only if --force)
        $this->step(4, 'Re-publishing stubs');
        $this->republishStubs();

        // Step 5 — Run new migrations
        $this->step(5, 'Running migrations');
        $this->runMigrations();

        // Step 6 — Re-check User model trait (in case new methods were added)
        $this->step(6, 'Verifying User model patch');
        $this->verifyUserModel();

        // Step 7 — Clear caches
        $this->step(7, 'Clearing caches');
        $this->clearCaches();

        $this->printSuccess($before, $after);

        return self::SUCCESS;
    }

    // =========================================================================
    // Steps
    // =========================================================================

    protected function runComposerUpdate(): void
    {
        $version = $this->option('version');
        $runner  = new ComposerRunner();

        if ($version) {
            $cmd = "composer require {$this->packageName}:{$version} --no-interaction --prefer-dist 2>&1";
            $this->line("  Running: composer require {$this->packageName}:{$version} ...");
        } else {
            $cmd = "composer update {$this->packageName} --no-interaction --prefer-dist 2>&1";
            $this->line("  Running: composer update {$this->packageName} (latest stable) ...");
        }

        [$ok, $out] = $runner->run($cmd);

        if ($ok) {
            $label = $version ? "Installed: {$version}" : 'Updated to latest.';
            $this->line("  {$label}");
            $runner->dumpAutoload();
            $this->line('  Autoload rebuilt.');
        } else {
            $this->warn('  Command failed. Output:');
            $this->line($out);
            if ($version) {
                $this->warn("  Run manually: composer require {$this->packageName}:{$version}");
            } else {
                $this->warn("  Run manually: composer update {$this->packageName}");
            }
        }
    }

    protected function republishConfig(): void
    {
        if ($this->option('force')) {
            Artisan::call('vendor:publish', [
                '--tag'   => 'talkbridge-config',
                '--force' => true,
            ]);
            $this->line('  Force-published: config/talkbridge.php');
            $this->warn('  Review config/talkbridge.php — your custom values may have been overwritten.');
        } else {
            $this->line('  Skipped (use --force to overwrite config/talkbridge.php).');
            $this->line('  Review the package CHANGELOG for new config keys to add manually.');
        }
    }

    protected function republishMigrations(): void
    {
        // Always publish migrations — new files will be added, existing ones are skipped
        // unless --force is passed
        Artisan::call('vendor:publish', [
            '--tag'   => 'talkbridge-migrations',
            '--force' => $this->option('force'),
        ]);
        $this->line('  Migrations published: database/migrations/');
    }

    protected function republishStubs(): void
    {
        if ($this->option('force')) {
            Artisan::call('vendor:publish', [
                '--tag'   => 'talkbridge-stubs',
                '--force' => true,
            ]);
            $this->line('  Force-published: stubs/talkbridge/');
        } else {
            $this->line('  Skipped (use --force to overwrite stubs/talkbridge/).');
        }
    }

    protected function runMigrations(): void
    {
        if ($this->confirm('  Run php artisan migrate now?', true)) {
            Artisan::call('migrate', [], $this->output);
            $this->line('  Migrations complete.');
        } else {
            $this->line('  Skipped. Run php artisan migrate when ready.');
        }
    }

    protected function verifyUserModel(): void
    {
        $path = $this->resolveUserModelPath();

        if (! $path) {
            $this->warn('  User model not found — skipping.');
            return;
        }

        $modifier = new \RahatulRabbi\TalkBridge\Support\UserModelModifier($path);

        if ($modifier->isAlreadyInjected()) {
            $this->line('  HasTalkBridgeFeatures already present — no changes needed.');
        } else {
            $this->warn('  HasTalkBridgeFeatures not found in User model.');
            $this->line('  Re-injecting...');
            $modifier->inject();
            $relative = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path);
            $this->line("  Re-injected -> {$relative}");
        }
    }

    protected function clearCaches(): void
    {
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');
        $this->line('  config, route, view caches cleared.');
    }

    // =========================================================================
    // Utilities
    // =========================================================================

    protected function getInstalledVersion(): string
    {
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

        $vendorJson = base_path("vendor/{$this->packageName}/composer.json");
        if (File::exists($vendorJson)) {
            $data = json_decode(File::get($vendorJson), true);
            return $data['version'] ?? 'unknown';
        }

        return 'unknown';
    }

    protected function resolveUserModelPath(): ?string
    {
        $userModel    = config('talkbridge.user_model', 'App\\Models\\User');
        $relativePath = ltrim(str_replace(['App\\', '\\'], ['app/', '/'], $userModel), '/') . '.php';
        $fullPath      = base_path($relativePath);

        if (File::exists($fullPath)) {
            return $fullPath;
        }

        foreach ([app_path('Models/User.php'), app_path('User.php')] as $fallback) {
            if (File::exists($fallback)) {
                return $fallback;
            }
        }

        return null;
    }

    protected function printHeader(): void
    {
        $this->newLine();
        $this->line('  +----------------------------------------------------+');
        $this->line('  |   TalkBridge  -  Updater                            |');
        $this->line('  |   github.com/learnwithfair/talkbridge               |');
        $this->line('  +----------------------------------------------------+');
        $this->newLine();
    }

    protected function printSuccess(string $before, string $after): void
    {
        $this->newLine();
        $this->line('  +----------------------------------------------------+');
        $this->info('  |   Update complete.                                 |');
        $this->line('  +----------------------------------------------------+');
        $this->newLine();

        $version = $this->option('version');

        if ($version) {
            $this->line("  Requested version : <comment>{$version}</comment>");
            $this->line("  Installed version : <info>{$after}</info>");
        } elseif ($before !== $after && $before !== 'unknown' && $after !== 'unknown') {
            $this->line("  Updated : {$before} -> <info>{$after}</info>");
        } else {
            $this->line("  Version : <info>{$after}</info>");
        }

        $this->newLine();
        $this->line('  If you updated config manually, review CHANGELOG for new keys:');
        $this->line('    vendor/' . $this->packageName . '/CHANGELOG.md');
        $this->newLine();
        $this->line('  If using Reverb, restart the WebSocket server:');
        $this->line('    php artisan reverb:start --debug');
        $this->newLine();
    }

    protected function step(int $n, string $label): void
    {
        $this->newLine();
        $this->info("  [{$n}] {$label}");
        $this->line('  ' . str_repeat('-', 54));
    }
}
