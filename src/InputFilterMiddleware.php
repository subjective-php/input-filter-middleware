<?php

namespace Chadicus\Psr\Middleware;

use ArrayAccess;
use DominionEnterprises\Filterer;
use Http\Message\StreamFactory as StreamFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Interface for all middleware.
 */
final class InputFilterMiddleware implements MiddlewareInterface
{
    /**
     * Object in which to store the input.
     *
     * @var ArrayAccess
     */
    private $container;

    /**
     * The specification to apply to the input.
     *
     * @var array
     */
    private $filters;

    /**
     * Location of the request to expect the input.
     *
     * @var string
     */
    private $inputLocation;

    /**
     * Factory for creating message streams.
     *
     * @var StreamFactoryInterface;
     */
    private $streamFactory;

    /**
     * Create a new instance of the middleware.
     *
     * @param ArrayAccess            $container     Object in which to store the input.
     * @param array                  $filters       The specification to apply to the input.
     * @param string                 $inputLocation Location of the request to expect the input 'body' or 'query'.
     * @param StreamFactoryInterface $streamFactory Factory to create message stream upon filter error.
     *
     * @throws \InvalidArgumentException Thrown if $inputLocation is not 'body' or 'query'.
     */
    public function __construct(
        ArrayAccess $container,
        array $filters,
        $inputLocation,
        StreamFactoryInterface $streamFactory
    ) {
        $this->container = $container;
        $this->filters = $filters;
        if (!in_array($inputLocation, ['body', 'query'])) {
            throw new \InvalidArgumentException('$inputLocation must be "body" or "query"');
        }

        $this->inputLocation = $inputLocation;
        $this->streamFactory = $streamFactory;
    }

    /**
     * Execute this middleware.
     *
     * @param  ServerRequestInterface $request  The PSR7 request.
     * @param  ResponseInterface      $response The PSR7 response.
     * @param  callable               $next     The Next middleware.
     *
     * @return ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        $input = $request->getQueryParams();
        if ($this->inputLocation === 'body') {
            $input = (array)$request->getParsedBody();
        }

        list($success, $filteredInput, $error) = Filterer::filter($this->filters, $input);
        if (!$success) {
            return $response->withStatus(400, 'Bad Request')->withBody($this->createStream($error));
        }

        $this->container['input'] = $filteredInput;

        return $next($request, $response);
    }

    private function createStream(string $error) : StreamInterface
    {
        $json = json_encode(['error' => ['message' => $error]]);
        return $this->streamFactory->createStream($json);
    }
}