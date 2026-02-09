<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\EarningTypeResource;
use App\Models\EarningType;
use Illuminate\Http\JsonResponse;

class EarningTypeController extends BaseController
{
    public function index(): JsonResponse
    {
        $types = EarningType::query()
            ->orderBy('name')
            ->get();

        return $this->sendResponse(EarningTypeResource::collection($types));
    }
}
