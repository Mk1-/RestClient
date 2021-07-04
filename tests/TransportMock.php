<?php
declare(strict_types = 1);

namespace RestClient\Tests;

use RestClient\Exception\NetworkException;
use RestClient\Exception\RequestException;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\RequestInterface;


class TransportMock implements ClientInterface
{
    /**
     * Mock: Sends a PSR-7 request and returns a PSR-7 response.
     * @param RequestInterface $request
     * @return ResponseInterface
     * @throws \Psr\Http\Client\ClientExceptionInterface If an error happens while processing the request.
     */
    public function sendRequest(RequestInterface $request) : ResponseInterface
    {
        $uri = $request->getUri();

        if ( $uri == "http://testmock/dummy" ) {
            return new Response(200, [], "dummy answer");
        }

        if ( $uri == "http://testmock/checkauth" ) {
            return new Response(200, [], $request->getHeader("Authorization")[0]);
        }

        if ( $uri == "http://nonetwork/" ) {
            throw new NetworkException($request, "network error", 404);
        }

        throw new RequestException($request, "other error", 500);
    }
}
