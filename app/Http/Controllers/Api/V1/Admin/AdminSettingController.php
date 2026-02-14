<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SettingResource;
use App\Models\Setting;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * @ai-context AdminSettingController handles admin settings management.
 */
class AdminSettingController extends Controller
{
    use HasApiResponse;

    public function index(): JsonResponse
    {
        $settings = Setting::all();

        return $this->success(SettingResource::collection($settings));
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
        ]);
        $forcedGroup = $request->route('group');

        // Handle both formats:
        // Format 1: ['settings' => ['key' => 'value', ...]]
        // Format 2: ['settings' => [['key' => 'x', 'value' => 'y'], ...]]
        $settings = $validated['settings'];

        // Check if it's associative array format (key => value)
        $isAssociative = array_keys($settings) !== range(0, count($settings) - 1);

        if ($isAssociative) {
            // Format 1: associative array
            foreach ($settings as $key => $value) {
                $attributes = ['value' => is_array($value) ? json_encode($value) : (string) $value];
                if (is_string($forcedGroup) && $forcedGroup !== '') {
                    $attributes['group'] = $forcedGroup;
                }

                Setting::updateOrCreate(
                    ['key' => $key],
                    $attributes
                );
            }
        } else {
            // Format 2: array of objects
            foreach ($settings as $setting) {
                if (isset($setting['key']) && isset($setting['value'])) {
                    $attributes = [
                        'value' => is_array($setting['value']) ? json_encode($setting['value']) : (string) $setting['value'],
                    ];

                    if (is_string($forcedGroup) && $forcedGroup !== '') {
                        $attributes['group'] = $forcedGroup;
                    } elseif (!empty($setting['group'])) {
                        $attributes['group'] = (string) $setting['group'];
                    }

                    Setting::updateOrCreate(
                        ['key' => $setting['key']],
                        $attributes
                    );
                }
            }
        }

        // Clear settings cache
        Cache::forget('settings:public');
        Cache::forget('settings:all');

        return $this->success(['message' => 'Settings updated successfully']);
    }

    public function show(string $key): JsonResponse
    {
        $setting = Setting::where('key', $key)->firstOrFail();

        return $this->success(new SettingResource($setting));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'unique:settings,key'],
            'value' => ['required'],
            'group' => ['nullable', 'string'],
            'type' => ['required', 'string', 'in:string,integer,boolean,json'],
            'is_public' => ['nullable', 'boolean'],
        ]);

        $setting = Setting::create($validated);

        return $this->created(new SettingResource($setting));
    }

    public function destroy(string $key): JsonResponse
    {
        $setting = Setting::where('key', $key)->firstOrFail();
        $setting->delete();

        // Clear settings cache
        Cache::forget('settings:public');
        Cache::forget('settings:all');

        return $this->noContent();
    }

    public function getByGroup(string $group): JsonResponse
    {
        $settings = Setting::where('group', $group)->get();

        return $this->success(SettingResource::collection($settings));
    }
}
