<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\ContactMessage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @ai-context Repository interface for ContactMessage operations.
 *             Defines contract for data access to contact messages.
 */
interface ContactMessageRepositoryInterface
{
    /**
     * Find a contact message by ID.
     */
    public function findById(int $id): ?ContactMessage;

    /**
     * Get paginated list of contact messages with optional filters.
     *
     * @param array<string, mixed> $filters
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator;

    /**
     * Create a new contact message.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): ContactMessage;

    /**
     * Update an existing contact message.
     *
     * @param array<string, mixed> $data
     */
    public function update(ContactMessage $message, array $data): ContactMessage;

    /**
     * Delete a contact message.
     */
    public function delete(ContactMessage $message): void;
}
