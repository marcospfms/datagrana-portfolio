<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanConfig;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $configMeta = [
            'max_accounts' => [
                'name' => 'accounts',
                'slug' => 'Contas',
                'status' => true,
            ],
            'max_positions' => [
                'name' => 'positions',
                'slug' => 'Posições ativas',
                'status' => true,
            ],
            'max_portfolios' => [
                'name' => 'portfolios',
                'slug' => 'Carteiras',
                'status' => true,
            ],
            'max_compositions' => [
                'name' => 'compositions',
                'slug' => 'Ativos por carteira',
                'status' => true,
            ],
            'allow_full_crossing' => [
                'name' => 'full_crossing',
                'slug' => 'Comparação completa',
                'status' => true,
            ],
            'allow_composition_history' => [
                'name' => 'composition_history',
                'slug' => 'Histórico de composição',
                'status' => true,
            ],
        ];

        $plans = [
            [
                'name' => 'Gratuito',
                'slug' => 'free',
                'description' => 'Ideal para começar a organizar seus investimentos',
                'price_monthly' => 0,
                'is_active' => true,
                'display_order' => 1,
                'revenuecat_product_id' => null,
                'revenuecat_entitlement_id' => null,
                'configs' => [
                    'max_portfolios' => 1,
                    'max_compositions' => 5,
                    'max_positions' => 5,
                    'max_accounts' => 1,
                    'allow_full_crossing' => false,
                    'allow_composition_history' => false,
                ],
            ],
            [
                'name' => 'Investidor Iniciante',
                'slug' => 'starter',
                'description' => 'Para investidores começando a diversificar',
                'price_monthly' => 9.90,
                'is_active' => true,
                'display_order' => 2,
                'revenuecat_product_id' => 'datagrana_starter_monthly',
                'revenuecat_entitlement_id' => 'starter',
                'configs' => [
                    'max_portfolios' => 2,
                    'max_compositions' => 10,
                    'max_positions' => 10,
                    'max_accounts' => 2,
                    'allow_full_crossing' => true,
                    'allow_composition_history' => true,
                ],
            ],
            [
                'name' => 'Investidor Pro',
                'slug' => 'pro',
                'description' => 'Para investidores ativos com múltiplas estratégias',
                'price_monthly' => 14.90,
                'is_active' => true,
                'display_order' => 3,
                'revenuecat_product_id' => 'datagrana_pro_monthly',
                'revenuecat_entitlement_id' => 'pro',
                'configs' => [
                    'max_portfolios' => 4,
                    'max_compositions' => 25,
                    'max_positions' => 25,
                    'max_accounts' => 4,
                    'allow_full_crossing' => true,
                    'allow_composition_history' => true,
                ],
            ],
            [
                'name' => 'Premium',
                'slug' => 'premium',
                'description' => 'Recursos ilimitados para investidores profissionais',
                'price_monthly' => 24.90,
                'is_active' => true,
                'display_order' => 4,
                'revenuecat_product_id' => 'datagrana_premium_monthly',
                'revenuecat_entitlement_id' => 'premium',
                'configs' => [
                    'max_portfolios' => null,
                    'max_compositions' => null,
                    'max_positions' => null,
                    'max_accounts' => null,
                    'allow_full_crossing' => true,
                    'allow_composition_history' => true,
                ],
            ],
        ];

        foreach ($plans as $planData) {
            $configs = $planData['configs'];
            unset($planData['configs']);

            $plan = SubscriptionPlan::updateOrCreate(
                ['slug' => $planData['slug']],
                $planData
            );

            foreach ($configs as $key => $value) {
                $isLimit = str_starts_with($key, 'max_');
                $meta = $configMeta[$key] ?? [
                    'name' => $key,
                    'slug' => $key,
                    'status' => true,
                ];
                SubscriptionPlanConfig::updateOrCreate(
                    ['subscription_plan_id' => $plan->id, 'config_key' => $key],
                    [
                        'name' => $meta['name'],
                        'slug' => $meta['slug'],
                        'status' => (bool) $meta['status'],
                        'config_value' => $isLimit ? $value : null,
                        'is_enabled' => $isLimit ? false : (bool) $value,
                    ]
                );
            }
        }
    }
}
