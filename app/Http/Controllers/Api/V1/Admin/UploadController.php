<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @ai-context UploadController handles generic file uploads.
 */
class UploadController extends Controller
{
    use HasApiResponse;

    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:5120'], // 5MB max
        ]);

        $file = $request->file('image');
        $path = $file->store('uploads', 'public');
        $url = asset('storage/' . $path);

        return $this->success([
            'url' => $url,
            'path' => $path,
        ]);
    }

    public function uploadImages(Request $request): JsonResponse
    {
        $request->validate([
            'images' => ['required', 'array'],
            'images.*' => ['image', 'max:5120'], // 5MB max per image
        ]);

        $uploadedImages = [];

        foreach ($request->file('images') as $file) {
            $path = $file->store('uploads', 'public');
            $uploadedImages[] = [
                'url' => asset('storage/' . $path),
                'path' => $path,
            ];
        }

        return $this->success([
            'images' => $uploadedImages,
        ]);
    }
}
