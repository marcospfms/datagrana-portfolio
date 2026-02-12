<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use Illuminate\Database\Seeder;

class SubscriptionSnapshotSeeder extends Seeder
{
    public function run(): void
    {
        $plans = SubscriptionPlan::with('configs')->get()->keyBy('id');

        UserSubscription::query()
            ->chunkById(200, function ($subscriptions) use ($plans) {
                foreach ($subscriptions as $subscription) {
                    $plan = $plans->get($subscription->subscription_plan_id);

                    if (! $plan) {
                        continue;
                    }

                    $subscription->update([
                        'limits_snapshot' => $plan->getLimitsArray(),
                        'features_snapshot' => $plan->getFeaturesArray(),
                    ]);
                }
            });
    }
}
