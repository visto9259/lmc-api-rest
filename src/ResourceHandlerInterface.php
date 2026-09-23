<?php

declare(strict_types=1);

namespace Lmc\Api\Rest;

use Laminas\InputFilter\InputFilterInterface;
use Laminas\Paginator\Paginator;
use Lmc\Api\Auth\Identity\IdentityInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Interface describing operations for a given resource.
 */
interface ResourceHandlerInterface
{
    public function setIdentity(IdentityInterface $identity): self;

    public function setInputFilter(?InputFilterInterface $inputFilter = null): self;

    public function create(mixed $data): array|object;

   /**
    * Update (replace) an existing record
    */
    public function update(int|string $id, object|array $data): array|object;

    /**
     * Update (replace) an existing collection of records
     */
    public function replaceList(array $data): array|object;

    /**
     * Partial update of an existing record
     */
    public function patch(int|string $id, object|array $data): array|object;

    /**
     * Delete an existing record
     */
    public function delete(int|string $id): bool|object;

    /**
     * Delete an existing collection of records
     */
    public function deleteList(array $data = []): bool|object;

    /**
     * Fetch an existing record
     */
    public function fetch(int|string $id): false|array|object;

    /**
     * Fetch a collection of records
     */
    public function fetchAll(array $params = []): Paginator|ResponseInterface;

    public function setRequest(RequestInterface $request): self;
}
