<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Domain\EntityNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerAddressResource;
use App\Models\CustomerAddress;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @ai-context CustomerAddressController handles customer address API endpoints.
 */
class CustomerAddressController extends Controller
{
    use HasApiResponse;

    public function index(Request $request): JsonResponse
    {
        $addresses = CustomerAddress::where('user_id', $request->user()->id)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success(CustomerAddressResource::collection($addresses));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:50'],
            'type' => ['required', 'string', 'in:shipping,billing'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:2'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $address = CustomerAddress::create([
            ...$validated,
            'user_id' => $request->user()->id,
            'country' => $validated['country'] ?? 'AR',
        ]);

        if ($validated['is_default'] ?? false) {
            $address->setAsDefault();
        }

        return $this->created(new CustomerAddressResource($address));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $address = CustomerAddress::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$address) {
            throw new EntityNotFoundException('Address', $id);
        }

        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:50'],
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['sometimes', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['sometimes', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:2'],
        ]);

        $address->update($validated);

        return $this->success(new CustomerAddressResource($address->fresh()));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $address = CustomerAddress::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$address) {
            throw new EntityNotFoundException('Address', $id);
        }

        $address->delete();

        return $this->noContent();
    }

    public function setDefault(Request $request, int $id): JsonResponse
    {
        $address = CustomerAddress::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$address) {
            throw new EntityNotFoundException('Address', $id);
        }

        $address->setAsDefault();

        return $this->success(new CustomerAddressResource($address->fresh()));
    }
}
