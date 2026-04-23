<?php

namespace Foundry\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class InstallCommand extends Command
{
    protected $signature = 'foundry:install
        {--force : Overwrite existing published files}
        {--migrate : Run database migrations after publishing}
        {--seed : Seed essential data (roles, permissions, plans, etc.)}';

    protected $description = 'Install Foundry: publish config, routes, views, run migrations, and configure the package';

    /**
     * Modules the user chose to enable during interactive install.
     *
     * @var array<string>
     */
    private array $enabledModules = [];

    public function handle(): int
    {
        $this->printBanner();

        $force = (bool) $this->option('force');
        $shouldMigrate = (bool) $this->option('migrate');
        $shouldSeed = (bool) $this->option('seed');

        if (! $this->option('no-interaction') && $this->input->isInteractive()) {
            [$shouldMigrate, $shouldSeed] = $this->gatherInteractiveOptions($shouldMigrate, $shouldSeed);
        } else {
            $this->enabledModules = ['subscriptions', 'payments'];
        }

        // ── Publishing ──────────────────────────────────────────────────────
        $this->publishAssets($force);

        // ── Provider registration ────────────────────────────────────────────
        $this->registerProviders();

        // ── Fortify configuration ────────────────────────────────────────────
        $this->configureFortify();

        // ── Migrations ───────────────────────────────────────────────────────
        if ($shouldMigrate) {
            $this->runMigrations();
        }

        // ── Seeding ──────────────────────────────────────────────────────────
        if ($shouldSeed) {
            $this->runSeeders();
        }

        // ── Module-specific setup ─────────────────────────────────────────────
        $this->setupSelectedModules();

        // ── Installation flag ─────────────────────────────────────────────────
        $this->markInstalled();

        $this->printSummary($shouldMigrate, $shouldSeed);

        return self::SUCCESS;
    }

    // ── Interactive helpers ──────────────────────────────────────────────────

    private function gatherInteractiveOptions(bool $migrate, bool $seed): array
    {
        note('Welcome to the Foundry interactive installer.');

        if (! $migrate) {
            $migrate = confirm('Run database migrations now?', default: true);
        }

        if (! $seed) {
            $seed = confirm('Seed essential data (groups, plans, payment methods, etc.)?', default: true);
        }

        $this->enabledModules = multiselect(
            label: 'Which optional modules would you like to enable?',
            options: [
                'subscriptions' => 'Subscriptions & Plans',
                'payments' => 'Payment Gateways',
                'wallet' => 'Wallet System',
                'blog' => 'Blog & Content',
                'reports' => 'Analytics & Reports',
            ],
            default: ['subscriptions', 'payments'],
            hint: 'Space to select, Enter to confirm.',
        );

        return [$migrate, $seed];
    }

    // ── Publishing ───────────────────────────────────────────────────────────

    private function publishAssets(bool $force): void
    {
        $publishOptions = $force ? ['--force' => true] : [];

        $steps = [
            'Configuration' => 'foundry-config',
            'Migrations' => 'foundry-migrations',
            'Routes' => 'foundry-assets',   // routes are part of assets tag
            'Views' => 'foundry-assets',
            'Controllers' => 'foundry-assets',
            'Models' => 'foundry-assets',
            'Policies' => 'foundry-assets',
        ];

        // Deduplicate tags
        $tags = array_unique(array_values($steps));

        foreach ($tags as $tag) {
            $label = match ($tag) {
                'foundry-config' => 'Configuration',
                'foundry-migrations' => 'Migrations',
                'foundry-assets' => 'Views, routes, controllers, models & policies',
                default => $tag,
            };

            $this->comment("Publishing {$label}...");
            $this->callSilent('vendor:publish', array_merge(['--tag' => $tag], $publishOptions));
        }

        // Publish Fortify provider stub
        $this->comment('Publishing Fortify service provider...');
        $this->callSilent('vendor:publish', array_merge(['--tag' => 'foundry-fortify-provider'], $publishOptions));
    }

    // ── Provider registration ────────────────────────────────────────────────

    private function registerProviders(): void
    {
        $namespace = Str::replaceLast('\\', '', $this->laravel->getNamespace());
        $providersPath = base_path('bootstrap/providers.php');

        if (! file_exists($providersPath)) {
            warning('bootstrap/providers.php not found. Register providers manually.');

            return;
        }

        $content = file_get_contents($providersPath);

        $toRegister = [
            "{$namespace}\\Providers\\FoundryServiceProvider::class",
            "{$namespace}\\Providers\\FortifyServiceProvider::class",
        ];

        $newProviders = array_filter($toRegister, fn ($p) => ! Str::contains($content, $p));

        if (empty($newProviders)) {
            info('All providers already registered.');

            return;
        }

        $providers = include $providersPath;
        if (! is_array($providers)) {
            $providers = [];
        }

        foreach ($newProviders as $provider) {
            $providers[] = $provider;
        }

        $lines = implode("\n", array_map(fn ($p) => "    {$p},", $providers));
        file_put_contents($providersPath, "<?php\n\nreturn [\n{$lines}\n];\n");

        foreach ($newProviders as $provider) {
            $name = Str::between($provider, "{$namespace}\\Providers\\", '::class');
            $this->updateProviderNamespace($namespace, $name);
        }

        info('Providers registered in bootstrap/providers.php');
    }

    private function updateProviderNamespace(string $namespace, string $providerName): void
    {
        $path = app_path("Providers/{$providerName}.php");

        if (! file_exists($path)) {
            return;
        }

        file_put_contents(
            $path,
            str_replace(
                'namespace App\\Providers;',
                "namespace {$namespace}\\Providers;",
                file_get_contents($path)
            )
        );
    }

    // ── Fortify configuration ────────────────────────────────────────────────

    private function configureFortify(): void
    {
        $fortifyConfig = config_path('fortify.php');

        if (! file_exists($fortifyConfig)) {
            $this->comment('Publishing Fortify config...');
            $this->callSilent('vendor:publish', ['--tag' => 'fortify-config']);
        }

        // Ensure our Fortify config points to the correct guard
        if (file_exists($fortifyConfig)) {
            $content = file_get_contents($fortifyConfig);

            if (! Str::contains($content, "'guard' => 'user'")) {
                $content = str_replace(
                    "'guard' => 'web'",
                    "'guard' => 'user'",
                    $content
                );
                file_put_contents($fortifyConfig, $content);
            }
        }

        info('Fortify configured with user guard.');
    }

    // ── Migrations ───────────────────────────────────────────────────────────

    private function runMigrations(): void
    {
        $this->comment('Running migrations...');

        spin(
            fn () => $this->callSilent('migrate', ['--force' => true]),
            'Running database migrations…'
        );

        info('Migrations completed.');
    }

    // ── Seeding ──────────────────────────────────────────────────────────────

    private function runSeeders(): void
    {
        $seeders = [
            'GroupSeeder' => 'Groups',
            'ModuleSeeder' => 'Modules',
            'NotificationSeeder' => 'Notification templates',
            'SettingsSeeder' => 'Application settings',
            'PaymentMethodSeeder' => 'Payment methods',
            'FeatureSeeder' => 'Plan features',
            'PlanSeeder' => 'Default plans',
        ];

        foreach ($seeders as $seeder => $label) {
            $fqn = "Database\\Seeders\\{$seeder}";

            if (! class_exists($fqn)) {
                continue;
            }

            $this->comment("Seeding {$label}...");

            try {
                $this->callSilent('db:seed', ['--class' => $fqn, '--force' => true]);
                info("{$label} seeded.");
            } catch (\Throwable $e) {
                warning("Skipped {$label}: {$e->getMessage()}");
            }
        }
    }

    // ── Module setup ─────────────────────────────────────────────────────────

    private function setupSelectedModules(): void
    {
        if (in_array('wallet', $this->enabledModules)) {
            $this->ensureEnvValue('WALLET_ENABLED', 'true');
        }

        if (in_array('payments', $this->enabledModules)) {
            $this->ensureEnvValue('STRIPE_KEY', '');
        }
    }

    private function ensureEnvValue(string $key, string $default): void
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            return;
        }

        $content = file_get_contents($envPath);

        if (! Str::contains($content, $key)) {
            file_put_contents($envPath, "\n{$key}={$default}", FILE_APPEND);
        }
    }

    // ── Installation flag ─────────────────────────────────────────────────────

    private function markInstalled(): void
    {
        $flag = base_path('storage/.installed');

        if (! file_exists($flag)) {
            file_put_contents($flag, date('Y-m-d H:i:s'));
        }
    }

    // ── Summary ───────────────────────────────────────────────────────────────

    private function printBanner(): void
    {
        $this->newLine();
        $this->line('  <fg=blue;options=bold>  Foundry Package Installer</>');
        $this->newLine();
    }

    private function printSummary(bool $migrated, bool $seeded): void
    {
        $this->newLine();
        $this->info('Foundry installed successfully.');
        $this->newLine();
        $this->line('  <info>Completed steps:</info>');
        $this->line('  - Config, migrations, routes, views, controllers, models, policies published');
        $this->line('  - Providers registered in bootstrap/providers.php');
        $this->line('  - Fortify configured with user guard');

        if ($migrated) {
            $this->line('  - Database migrations ran');
        }

        if ($seeded) {
            $this->line('  - Essential data seeded');
        }

        if (! empty($this->enabledModules)) {
            $this->line('  - Modules enabled: '.implode(', ', $this->enabledModules));
        }

        $this->newLine();
        $this->comment('Next steps:');
        $this->line('  1. Update your .env with APP_ADMIN_EMAIL and payment gateway keys');
        $this->line('  2. Run: npm run dev  (or composer run dev)');
        $this->line('  3. Visit your application at: '.config('app.url'));
        $this->newLine();
    }
}
