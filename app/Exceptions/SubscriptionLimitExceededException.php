<?php

namespace App\Exceptions;

use Exception;

class SubscriptionLimitExceededException extends Exception
{
    protected $code = 403;

    public function render($request)
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'error_code' => 'SUBSCRIPTION_LIMIT_EXCEEDED',
        ], 403);
    }
}
