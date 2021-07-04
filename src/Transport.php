<?php
declare(strict_types = 1);

namespace RestClient;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Client\ClientInterface;


class Transport implements ClientInterface
{
    /**
     * Sends a PSR-7 request and returns a PSR-7 response.
     * @param RequestInterface $request
     * @return ResponseInterface
     * @throws \Psr\Http\Client\ClientExceptionInterface If an error happens while processing the request.
     */
    public function sendRequest(RequestInterface $request) : ResponseInterface
    {
        $OP = $this->prepareOptions($request);
        $ch = curl_init();
        curl_setopt_array($ch, $OP);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        if ( $errno != CURLE_OK ) {
            $this->parseError($request, curl_errno($ch), $ch); // throws exception and ends method - required by ClientInterface
        }
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return new Response($code, [], $body);
    }


    /**
     * @param RequestInterface $rq
     * @return array
     * @throws \Psr\Http\Client\ClientExceptionInterface
     */
    private function prepareOptions(RequestInterface $rq) : array
    {
        $OP = [
            CURLOPT_HEADER => 0,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FAILONERROR => 1,
            CURLOPT_USERAGENT => "RestClient Mk1-",
            CURLOPT_URL => $rq->getUri()->__toString(),
            CURLOPT_CUSTOMREQUEST => strtoupper($rq->getMethod()),
            CURLOPT_VERBOSE => 0,
        ];

        switch (strtoupper($rq->getMethod())) {
            case 'HEAD':
                $OP[CURLOPT_NOBODY] = true;
                break;
            case 'POST':
            case 'PUT':
            case 'DELETE':
            case 'PATCH':
            case 'OPTIONS':
                $OP[CURLOPT_POSTFIELDS] = (string)$rq->getBody();
                break;
        }

        $HD = $rq->getHeader("Authorization");
        if ( is_array($HD) && count($HD) > 0) {
            $auth = $HD[0];
            $T = explode(' ', $auth, 2);
            switch(strtoupper($T[0])) {
                case 'BASIC':
                    $OP[CURLOPT_HTTPAUTH] =  CURLAUTH_BASIC;
                    $OP[CURLOPT_USERPWD] = base64_decode($T[1]);
                    break;
                case 'BEARER':
                    $OP[CURLOPT_HTTPAUTH] =  CURLAUTH_BEARER;
                    $OP[CURLOPT_XOAUTH2_BEARER] = $T[1];
                    break;
                default:
                    throw new Exception\RequestException($rq, "Unsupported Authorization type.", 500);
            }
        }
        return $OP;
    }


    /**
     * @param RequestInterface $request
     * @param int $errno
     * @param resource $ch
     * @throws \Psr\Http\Client\ClientExceptionInterface If an error happens while processing the request.
     */
    protected function parseError(RequestInterface $request, int $errno, $ch): void
    {
        switch ($errno) {
            case CURLE_COULDNT_RESOLVE_PROXY:
            case CURLE_COULDNT_RESOLVE_HOST:
            case CURLE_COULDNT_CONNECT:
            case CURLE_OPERATION_TIMEOUTED:
            case CURLE_SSL_CONNECT_ERROR:
                $msg = curl_error($ch);
                curl_close($ch);
                throw new Exception\NetworkException($request, $msg, 404);
            default:
                $msg  = curl_error($ch);
                $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                curl_close($ch);
                throw new Exception\RequestException($request, trim($msg), $code);
        }
    }
}
