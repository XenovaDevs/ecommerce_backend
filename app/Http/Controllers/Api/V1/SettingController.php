<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * @ai-context SettingController handles public settings API endpoint.
 */
class SettingController extends Controller
{
    use HasApiResponse;

    public function public(): JsonResponse
    {
        $settings = Setting::getPublic();

        return $this->success($settings);
    }
}
