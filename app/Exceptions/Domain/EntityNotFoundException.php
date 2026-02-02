<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use App\Exceptions\BaseException;
use App\Messages\ErrorMessages;

/**
 * @ai-context Exception thrown when a requested entity is not found in the database.
 *             Use this for any model/entity lookup that returns null.
 * @ai-flow Thrown by repositories/services -> Caught by exception handler -> Returns 404
 */
class EntityNotFoundException extends BaseException
{
    /**
     * Create a new exception instance.
     *
     * @param string $entity Entity type (e.g., 'User', 'Product', 'Order')
     * @param mixed $identifier The identifier used to search (ID, slug, etc.)
     */
    public function __construct(string $entity, mixed $identifier = null)
    {
        $metadata = ['entity' => $entity];

        if ($identifier !== null) {
            $metadata['identifier'] = $identifier;
        }

        parent::__construct(
            ErrorMessages::entityNotFound($entity),
            $metadata
        );
    }

    public function getErrorCode(): string
    {
        return 'ENTITY_NOT_FOUND';
    }

    public function getHttpStatus(): int
    {
        return 404;
    }

    public function isOperational(): bool
    {
        return true;
    }
}
