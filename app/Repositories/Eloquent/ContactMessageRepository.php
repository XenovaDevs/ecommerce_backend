<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\ContactMessageRepositoryInterface;
use App\Domain\Enums\ContactMessageStatus;
use App\Models\ContactMessage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @ai-context Eloquent implementation of ContactMessageRepositoryInterface.
 *             Handles data access for contact messages with filtering and pagination.
 */
class ContactMessageRepository implements ContactMessageRepositoryInterface
{
    public function __construct(
        private readonly ContactMessage $model
    ) {}

    public function findById(int $id): ?ContactMessage
    {
        return $this->model->find($id);
    }

    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->query()->latest();

        if (isset($filters['status']) && in_array($filters['status'], ContactMessageStatus::values(), true)) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['email']) && !empty($filters['email'])) {
            $query->where('email', 'like', '%' . $filters['email'] . '%');
        }

        if (isset($filters['search']) && !empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('email', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('subject', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('message', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $query->paginate($perPage);
    }

    public function create(array $data): ContactMessage
    {
        return $this->model->create($data);
    }

    public function update(ContactMessage $message, array $data): ContactMessage
    {
        $message->update($data);
        return $message->fresh();
    }

    public function delete(ContactMessage $message): void
    {
        $message->delete();
    }
}
