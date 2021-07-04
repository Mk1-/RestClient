<?php
declare(strict_types=1);

namespace RestClient\Exception;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;


class NetworkException extends ClientException implements NetworkExceptionInterface
{
    private RequestInterface $request;

    public function __construct(RequestInterface $request, string $message = '', int $code = 0)
    {
        $this->request = $request;
        parent::__construct($message, $code);
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
