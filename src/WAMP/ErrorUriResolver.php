<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\WAMP;

use BabDev\WebSocket\Server\WAMP\Middleware\DispatchMessageToHandler;

/**
 * The error URI resolver is responsible for resolving URIs for identifying errors,
 * which is provided as part of the "CALLERROR" WAMP message to the client.
 */
interface ErrorUriResolver
{
    /**
     * Resolve the error URI or CURIE for the given error type.
     *
     * Because the types of errors will be application dependent other than the "handler not found"
     * scenario in the default {@see DispatchMessageToHandler} middleware, a predefined list of error
     * types is not provided by this library. However, implementations should at a minimum support the
     * "not-found" type, and must return a generic type if they do not support creating a URI for a
     * specific type of error.
     *
     * @param non-empty-string $errorType
     *
     * @return non-empty-string
     */
    public function resolve(string $errorType): string;
}
