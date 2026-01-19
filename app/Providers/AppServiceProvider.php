<?php

namespace App\Providers;

use App\Models\Account;
use App\Policies\AccountPolicy;
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
        ]);
    }
}
