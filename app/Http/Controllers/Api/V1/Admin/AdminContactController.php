<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Contracts\Repositories\ContactMessageRepositoryInterface;
use App\Domain\Enums\ContactMessageStatus;
use App\Exceptions\Domain\EntityNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Resources\ContactMessageResource;
use App\Models\ContactMessage;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @ai-context AdminContactController handles contact message management for administrators.
 *             Provides endpoints to view, reply to, and manage customer inquiries.
 */
class AdminContactController extends Controller
{
    use HasApiResponse;

    public function __construct(
        private readonly ContactMessageRepositoryInterface $contactRepository
    ) {}

    /**
     * List all contact messages with pagination and filters.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);
        $query = ContactMessage::query();

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('email')) {
            $query->where('email', 'like', '%' . $request->query('email') . '%');
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $messages = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => ContactMessageResource::collection($messages)->resolve(),
            'meta' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
        ]);
    }

    /**
     * Show a specific contact message.
     */
    public function show(int $id): JsonResponse
    {
        $message = $this->contactRepository->findById($id);

        if (!$message) {
            throw new EntityNotFoundException('Contact message', (string) $id);
        }

        return $this->success(new ContactMessageResource($message));
    }

    /**
     * Reply to a contact message.
     */
    public function reply(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reply' => ['required', 'string', 'min:1'],
        ]);

        $message = $this->contactRepository->findById($id);

        if (!$message) {
            throw new EntityNotFoundException('Contact message', (string) $id);
        }

        if (!$message->status->allowsReply()) {
            return $this->error(
                'This message cannot be replied to because it is already closed.',
                'CANNOT_REPLY',
                422
            );
        }

        // Update with the reply content
        $message->update([
            'reply' => $validated['reply'],
            'status' => ContactMessageStatus::REPLIED,
        ]);

        return $this->success(
            new ContactMessageResource($message->fresh()),
            'Reply sent successfully.'
        );
    }

    /**
     * Update contact message status.
     */
    public function updateStatus(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:' . implode(',', ContactMessageStatus::values())],
        ]);

        $message = $this->contactRepository->findById($id);

        if (!$message) {
            throw new EntityNotFoundException('Contact message', (string) $id);
        }

        $updatedMessage = $this->contactRepository->update($message, [
            'status' => $validated['status'],
        ]);

        return $this->success(
            new ContactMessageResource($updatedMessage),
            'Status updated successfully.'
        );
    }
}
