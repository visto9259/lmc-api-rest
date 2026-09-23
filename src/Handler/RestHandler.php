<?php

declare(strict_types=1);

namespace Lmc\Api\Rest\Handler;

use Exception;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\Paginator\Paginator;
use Lmc\Api\Auth\Identity\IdentityInterface;
use Lmc\Api\Rest\ResourceHandlerInterface;
use Mezzio\Hal\HalResource;
use Mezzio\Hal\HalResponseFactory;
use Mezzio\Hal\ResourceGenerator;
use Mezzio\ProblemDetails\ProblemDetailsResponseFactory;
use Override;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Traversable;

use function array_filter;
use function array_map;
use function in_array;
use function is_array;
use function is_bool;
use function is_object;
use function sprintf;
use function strtolower;
use function strtoupper;

use const ARRAY_FILTER_USE_BOTH;

class RestHandler implements RequestHandlerInterface
{
    protected int $page = 1;
    protected int $pageSize;
    protected string $pageSizeParam;
    protected string $pageParam;
    protected array $allowedCollectionMethods;
    protected array $allowedEntityMethods;

    protected array $allowedWhitelistParams;

    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly ResourceHandlerInterface $resource,
        private readonly array $restConfig,
        private readonly ProblemDetailsResponseFactory $problemDetailsResponseFactory,
        private readonly ResourceGenerator $resourceGenerator,
        private readonly HalResponseFactory $halResponseFactory,
    ) {
        $this->pageSizeParam            = $this->restConfig['page_size_param'] ?? 'limit';
        $this->pageParam                = $this->restConfig['page_param'] ?? 'page';
        $this->pageSize                 = (int) $this->restConfig['page_size'] ?? 25;
        $this->allowedCollectionMethods = isset($this->restConfig['collection_http_methods'])
            ? $this->normalize($this->restConfig['collection_http_methods'])
            : [];
        $this->allowedEntityMethods     = isset($this->restConfig['entity_http_methods'])
            ? $this->normalize($this->restConfig['entity_http_methods'])
            : [];

        $this->allowedWhitelistParams = isset($this->restConfig['collection_query_whitelist'])
            ? $this->normalize($this->restConfig['collection_query_whitelist'], true)
            : [];
        $this->allowedWhitelistParams = [$this->pageSizeParam, $this->pageParam, ...$this->allowedWhitelistParams];
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $method              = $request->getMethod();
        $isCollectionRequest = $this->isCollectionRequest($request);
        if (! $this->allowedMethod($method, $isCollectionRequest)) {
            return $this->problemDetailsResponseFactory->createResponse(
                $request,
                405,
                sprintf(
                    'Method %s is not allowed for %s.',
                    $method,
                    $isCollectionRequest ? 'collection' : 'entity'
                )
            );
        }

        /** @var IdentityInterface $identity */
        $identity = $request->getAttribute(IdentityInterface::class);
        $this->resource->setIdentity($identity);

        /** @var InputFilterInterface $inputFilter */
        $inputFilter     = $request->getAttribute(InputFilterInterface::class);
        $validatedParams = $request->getAttribute('validated_params');
        $this->resource->setInputFilter($inputFilter);

        $this->resource->setRequest($request);

        return match ($method) {
            'GET' => $this->handleGet($request, $isCollectionRequest),
            'POST' => $this->handlePost($request, $isCollectionRequest, $validatedParams),
            'DELETE' => $this->handleDelete($request, $isCollectionRequest),
            'PUT' => $this->handlePut($request, $isCollectionRequest, $inputFilter),
            default => $this->problemDetailsResponseFactory->createResponse(
                $request,
                500,
                'No resource listener'
            ),
        };
    }

    private function handleGet(ServerRequestInterface $request, bool $isCollectionRequest): ResponseInterface
    {
        if ($isCollectionRequest) {
            $queryParams = $request->getQueryParams();
            $params      = $this->whitelistParams($queryParams);
            try {
                $result = $this->resource->fetchAll($params);
            } catch (Throwable $exception) {
                return $this->problemDetailsResponseFactory->createResponseFromThrowable($request, $exception);
            }
            if ($result instanceof ResponseInterface) {
                return $result;
            }

            if (
                ! is_array($result)
                && ! $result instanceof Traversable
                && ! $result instanceof HalResource
                && is_object($result)
            ) {
                throw new Exception('check this');
            }

            return $this->createHalCollectionResponse($result, $request);
        } else {
            $id = $request->getAttribute($this->restConfig['route_identifier_name']);
            try {
                $result = $this->resource->fetch($id);
            } catch (Throwable $exception) {
                return $this->problemDetailsResponseFactory->createResponseFromThrowable($request, $exception);
            }
            if ($result instanceof ResponseInterface) {
                return $result;
            }
            return $this->createHalEntity($result, $request);
        }
    }

    private function handlePost(
        ServerRequestInterface $request,
        bool $isCollectionRequest,
        array|null $validatedParams,
    ): ResponseInterface {
        if ($isCollectionRequest) {
            if (null !== $validatedParams) {
                $params = $validatedParams;
            } else {
                $params = $request->getParsedBody();
            }

            try {
                $result = $this->resource->create($params);
            } catch (Throwable $exception) {
                return $this->problemDetailsResponseFactory->createResponseFromThrowable($request, $exception);
            }
            if ($result instanceof ResponseInterface) {
                return $result;
            }
            return $this->createHalEntity($result, $request, 201);
        }
        return $this->problemDetailsResponseFactory->createResponse(
            $request,
            403,
            'Method Not Allowed'
        );
    }

    private function handleDelete(ServerRequestInterface $request, bool $isCollectionRequest): ResponseInterface
    {
        /** @var string|null $id */
        $id = $request->getAttribute($this->restConfig['route_identifier_name']);
        if (null === $id) {
            return $this->problemDetailsResponseFactory->createResponse(
                $request,
                422,
                'Invalid entity id'
            );
        }
        try {
            $result = $this->resource->delete($id);
        } catch (Throwable $exception) {
            return $this->problemDetailsResponseFactory->createResponseFromThrowable($request, $exception);
        }
        if ($result instanceof ResponseInterface) {
            return $result;
        }
        if (is_bool($result) && $result) {
            return $this->responseFactory->createResponse(204);
        }
        return $this->problemDetailsResponseFactory->createResponse(
            $request,
            422,
            'Unable to delete entity'
        );
    }

    private function handlePut(
        ServerRequestInterface $request,
        bool $isCollectionRequest,
        ?InputFilterInterface $inputFilter,
    ): ResponseInterface {
        if (! $isCollectionRequest) {
            if (null !== $inputFilter) {
                $params = $inputFilter->getValues();
            } else {
                $params = $request->getParsedBody();
            }

            try {
                $id = $request->getAttribute($this->restConfig['route_identifier_name']);
                if (null === $id) {
                    return $this->problemDetailsResponseFactory->createResponse(
                        $request,
                        422,
                        'Invalid entity id'
                    );
                }
                $result = $this->resource->update($id, $params);
            } catch (Throwable $exception) {
                return $this->problemDetailsResponseFactory->createResponseFromThrowable($request, $exception);
            }
            if ($result instanceof ResponseInterface) {
                return $result;
            }
            return $this->createHalEntity($result, $request);
        }
        return $this->problemDetailsResponseFactory->createResponse(
            $request,
            403,
            'Method Not Allowed'
        );
    }

    private function isCollectionRequest(ServerRequestInterface $request): bool
    {
        /** @var int|null $id */
        $id = $request->getAttribute($this->restConfig['route_identifier_name']);
        return $id === null;
    }

    private function allowedMethod(string $method, bool $isCollectionRequest): bool
    {
        return $isCollectionRequest
            ? in_array($method, $this->normalize($this->restConfig['collection_http_methods']))
            : in_array($method, $this->normalize($this->restConfig['entity_http_methods']));
    }

    private function normalize(array $methods, bool $toLower = false): array
    {
        return array_map(function (string $value) use ($toLower) {
            return $toLower ? strtolower($value) : strtoupper($value);
        }, $methods);
    }

    private function whitelistParams(array $queryParams): array
    {
        return array_filter($queryParams, function (string $value, string $key): bool {
            return in_array($key, $this->allowedWhitelistParams);
        }, ARRAY_FILTER_USE_BOTH);
    }

    private function createHalCollectionResponse(Paginator $result, ServerRequestInterface $request): ResponseInterface
    {
        $result->setItemCountPerPage($this->pageSize);
        $result->setCurrentPageNumber($this->page);
        $resource = $this->resourceGenerator->fromObject($result, $request);
        return $this->halResponseFactory->createResponse($request, $resource);
    }

    private function createHalEntity(
        mixed $result,
        ServerRequestInterface $request,
        int $status = 200
    ): ResponseInterface {
        $resource = $this->resourceGenerator->fromObject($result, $request);
        return $this->halResponseFactory->createResponse($request, $resource)->withStatus($status);
    }
}
