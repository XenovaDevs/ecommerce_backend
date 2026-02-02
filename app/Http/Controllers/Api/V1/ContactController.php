<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Repositories\ContactMessageRepositoryInterface;
use App\Domain\Enums\ContactMessageStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contact\CreateContactMessageRequest;
use App\Http\Resources\ContactMessageResource;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * @ai-context ContactController handles public contact form submissions.
 *             Allows customers to send inquiries without authentication.
 */
class ContactController extends Controller
{
    use HasApiResponse;

    public function __construct(
        private readonly ContactMessageRepositoryInterface $contactRepository
    ) {}

    /**
     * Submit a contact message (public endpoint).
     */
    public function store(CreateContactMessageRequest $request): JsonResponse
    {
        $message = $this->contactRepository->create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'subject' => $request->validated('subject'),
            'message' => $request->validated('message'),
            'status' => ContactMessageStatus::PENDING,
        ]);

        return $this->success(
            new ContactMessageResource($message),
            'Your message has been sent successfully. We will get back to you soon.',
            201
        );
    }
}
