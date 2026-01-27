<?php

namespace App\Providers;

use App\Models\Account;
use App\Models\Composition;
use App\Models\Consolidated;
use App\Models\Portfolio;
use App\Models\User;
use App\Observers\AccountObserver;
use App\Observers\CompositionObserver;
use App\Observers\ConsolidatedObserver;
use App\Observers\PortfolioObserver;
use App\Observers\UserObserver;
use App\Policies\AccountPolicy;
use App\Policies\ConsolidatedPolicy;
use App\Policies\PortfolioPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Account::class, AccountPolicy::class);
        Gate::policy(Consolidated::class, ConsolidatedPolicy::class);
        Gate::policy(Portfolio::class, PortfolioPolicy::class);
        User::observe(UserObserver::class);
        Portfolio::observe(PortfolioObserver::class);
        Composition::observe(CompositionObserver::class);
        Account::observe(AccountObserver::class);
        Consolidated::observe(ConsolidatedObserver::class);
        $this->configureDefaults();
        $this->loadMigrations();
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }

    /**
     * Configurar caminhos das migrations organizadas por módulo.
     * Ordem cronológica pelos nomes dos arquivos, respeitando as dependências.
     *
     * Nota: gateway e subscriptions excluídos por incompatibilidade com SQLite em testes.
     * Serão incluídos quando o projeto usar MySQL em produção.
     */
    protected function loadMigrations(): void
    {
        $this->loadMigrationsFrom([
            database_path('migrations/core'),             // Tabelas base (users, banks, accounts, etc.)
            database_path('migrations/personal_finance'), // Finanças pessoais
            database_path('migrations/companies'),        // Empresas (antes de consolidated)
            database_path('migrations/treasures'),        // Títulos (antes de consolidated)
            database_path('migrations/consolidated'),     // Consolidação (após companies/treasures)
            database_path('migrations/earnings'),         // Proventos (após consolidated)
            database_path('migrations/portfolio'),        // Portfólios (após companies/treasures)
            database_path('migrations/subscription_limits'), // Assinaturas simplificadas (V7)
        ]);
    }
}
