<?php

declare(strict_types=1);

namespace Lmc\Api\Rest;

use Laminas\InputFilter\InputFilterInterface;
use Laminas\Paginator\Paginator;
use Lmc\Api\Auth\Identity\IdentityInterface;
use Lmc\Api\Problem\ApiProblem;
use Lmc\Api\Problem\ApiProblemResponse;
use Override;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

abstract class AbstractResourceHandler implements ResourceHandlerInterface
{
    /**
     * The entity_class config for the calling controller api-tools-rest config
     */
    protected string $entityClass;

    /**
     * The collection_class config for the calling controller api-tools-rest config
     */
    protected string $collectionClass;

    /**
     * Current identity, if discovered in the resource event.
     */
    protected ?IdentityInterface $identity = null;

    /**
     * Input filter, if discovered in the resource event.
     */
    protected ?InputFilterInterface $inputFilter = null;

    protected ?RequestInterface $request = null;

    /**
     * Set the entity_class for the controller config calling this resource
     */
    public function setEntityClass(string $className): self
    {
        $this->entityClass = $className;
        return $this;
    }

    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    public function setCollectionClass(string $className): self
    {
        $this->collectionClass = $className;
        return $this;
    }

    public function getCollectionClass(): string
    {
        return $this->collectionClass;
    }

    /**
     * Retrieve the identity, if any
     *
     * Proxies to the resource event to find the identity, if not already
     * composed, and composes it.
     */
    public function getIdentity(): ?IdentityInterface
    {
        return $this->identity;
    }

    public function setIdentity(IdentityInterface $identity): self
    {
        $this->identity = $identity;
        return $this;
    }

    /**
     * Retrieve the input filter, if any
     *
     * Proxies to the resource event to find the input filter, if not already
     * composed, and composes it.
     */
    public function getInputFilter(): ?InputFilterInterface
    {
        return $this->inputFilter;
    }

    #[Override]
    public function setInputFilter(?InputFilterInterface $inputFilter = null): self
    {
        $this->inputFilter = $inputFilter;
        return $this;
    }

    #[Override]
    public function setRequest(RequestInterface $request): self
    {
        $this->request = $request;
        return $this;
    }

    public function getRequest(): ?RequestInterface
    {
        return $this->request;
    }

    /**
     * Create a resource
     */
    #[Override]
    public function create(mixed $data): array|object
    {
        return new ApiProblemResponse(
            new ApiProblem(405, 'The POST method has not been defined')
        );
    }

    /**
     * Delete a resource
     */
    #[Override]
    public function delete(string|int $id): bool|object
    {
        return new ApiProblemResponse(
            new ApiProblem(405, 'The DELETE method has not been defined for individual resources')
        );
    }

    /**
     * Delete a collection, or members of a collection
     */
    #[Override]
    public function deleteList(array $data = []): bool|object
    {
        return new ApiProblemResponse(
            new ApiProblem(405, 'The DELETE method has not been defined for collections')
        );
    }

    /**
     * Fetch a resource
     */
    #[Override]
    public function fetch(string|int $id): array|object
    {
        return new ApiProblemResponse(
            new ApiProblem(405, 'The GET method has not been defined for individual resources')
        );
    }

    /**
     * Fetch all or a subset of resources
     */
    #[Override]
    public function fetchAll(array $params = []): Paginator|ResponseInterface
    {
        return new ApiProblemResponse(
            new ApiProblem(405, 'The GET method has not been defined for collections')
        );
    }

    /**
     * Patch (partial in-place update) a resource
     */
    #[Override]
    public function patch(string|int $id, object|array $data): array|object
    {
        return new ApiProblemResponse(
            new ApiProblem(405, 'The PATCH method has not been defined for individual resources')
        );
    }

    /**
     * Patch (partial in-place update) a collection or members of a collection
     */
    public function patchList(mixed $data): ResponseInterface
    {
        return new ApiProblemResponse(
            new ApiProblem(405, 'The PATCH method has not been defined for collections')
        );
    }

    /**
     * Replace a collection or members of a collection
     */
    #[Override]
    public function replaceList(array $data): array|object
    {
        return new ApiProblemResponse(
            new ApiProblem(405, 'The PUT method has not been defined for collections')
        );
    }

    /**
     * Update a resource
     */
    #[Override]
    public function update(string|int $id, object|array $data): array|object
    {
        return new ApiProblemResponse(
            new ApiProblem(405, 'The PUT method has not been defined for individual resources')
        );
    }
}
