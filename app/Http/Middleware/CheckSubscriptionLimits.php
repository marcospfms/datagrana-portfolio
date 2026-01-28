<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionLimitService;
use Closure;
use Illuminate\Http\Request;

class CheckSubscriptionLimits
{
    public function __construct(
        protected SubscriptionLimitService $limitService
    ) {}

    public function handle(Request $request, Closure $next, string $limitType)
    {
        if (!config('subscription.enforce_limits')) {
            return $next($request);
        }

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        switch ($limitType) {
            case 'portfolio':
                $this->limitService->ensureCanCreatePortfolio($user);
                break;
            case 'composition':
                $portfolio = $request->route('portfolio');
                $pending = count($request->input('compositions', []));

                if (!$pending) {
                    $pending = 1;
                }

                if ($portfolio && $portfolio->user_id === $user->id) {
                    $this->limitService->ensureCanAddComposition($user, $portfolio, $pending);
                } else {
                    $this->limitService->ensureCanAddComposition($user, null, $pending);
                }
                break;
            case 'account':
                $this->limitService->ensureCanCreateAccount($user);
                break;
            case 'position':
                $this->limitService->ensureCanCreatePosition($user);
                break;
            default:
                throw new \InvalidArgumentException("Invalid limit type: {$limitType}");
        }

        return $next($request);
    }
}
