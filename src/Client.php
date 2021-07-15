<?php
declare(strict_types = 1);

namespace RestClient;

use RestClient\Exception\UnknownAuthMethodException;
use Nyholm\Psr7\Uri;
use Nyholm\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\ClientExceptionInterface;


class Client
{
    public const AUTH_NONE = 0;
    public const AUTH_BASIC = 50;
    public const AUTH_BEARER = 51;

    private string $baseUrl;
    private string $apiSecret;
    private string $login;
    private int    $authType;
    private ClientInterface $transport;


    /**
     * Client constructor.
     * @param string $apiBaseUri base URL for api (e.q. http://any.server.org/)
     * @param int    $authType   authorization method - use predefined const values
     * @param string $apiSecret  secret (token, password) if authType is other than AUTH_NONE
     * @param string $login      user login name - only for AUTH_BASIC
     * @param ClientInterface|null $transport PSR-18 compatible transport object
     * @throws UnknownAuthMethodException
     */
    public function __construct(string $apiBaseUri,
                                int $authType = self::AUTH_NONE, string $apiSecret = '', string $login = '',
                                ClientInterface $transport = null)
    {
        $this->authType = $this->validateAuthType($authType);
        $this->apiSecret = $apiSecret;
        $this->login = $login;
        $this->prepareUrlAndSecret($apiBaseUri);
        $this->transport = $transport ?? new Transport;
    }


    /**
     * @param string $endpoint API endpoint, relative to $apiBaseUri
     * @param string $method   valid HTTP method - GET, POST, ...
     * @return array           [{HTTP response status}, {response body}]
     * @throws \LogicException
     */
    public function callApi(string $endpoint, string $method = 'GET', string $body = '') : array
    {
        $rq = new Request($method, $this->baseUrl . $endpoint, [], $body);
        if ( $this->authType != self::AUTH_NONE ) {
            $rq = $this->addAuthHeader($rq);
        }

        try {
            $response = $this->transport->sendRequest($rq);
        }
        catch (ClientExceptionInterface $ex) {
            return [$ex->getCode(), $ex->getMessage()];
        }

        $body = $response->getBody()->__toString();
        $stat = $response->getStatusCode();
        return [$stat, $body];
    }

    /**
     * @param string $inUri
     */
    private function prepareUrlAndSecret(string $inUri) : void
    {
        $uri = new Uri($inUri);

        $this->baseUrl = '';

        if ( $uri->getScheme() != '' ) {
            $this->baseUrl .= $uri->getScheme() . ':';
        }

        $hostport = $uri->getHost();
        if ( $uri->getPort() !== null ) {
            $hostport .= ':' . $uri->getPort();
        }
        if ( $hostport != '' ) {
            $this->baseUrl .= '//' . $hostport;
        }

        if ( $this->authType == self::AUTH_BASIC ) {
            $UI = explode(':', $uri->getUserInfo());
            if ( $this->apiSecret == '' && count($UI) > 1 ) {
                $this->apiSecret = $UI[1];
            }
            if ( $this->login == '' ) {
                $this->login = $UI[0];
            }
        }
    }

    /**
     * @param int $authType
     * @return int
     * @throws UnknownAuthMethodException
     */
    private function validateAuthType(int $authType) : int
    {
        switch ( $authType ) {
            case self::AUTH_NONE:
            case self::AUTH_BASIC:
            case self::AUTH_BEARER:
                return $authType;
            default:
                throw new UnknownAuthMethodException();
        }
    }


    /**
     * @param RequestInterface $rq
     * @return RequestInterface
     * @throws \LogicException
     */
    private function addAuthHeader(RequestInterface $rq) : RequestInterface
    {
        switch ( $this->authType ) {
            case self::AUTH_BASIC:
                $line = "Basic " . base64_encode($this->login . ':' . $this->apiSecret);
                break;
            case self::AUTH_BEARER:
                $line = "Bearer " . $this->apiSecret;
                break;
            default:
                // this should never happen
                throw new \LogicException("Unexpected value of authType property.");
        }
        return $rq->withHeader("Authorization", $line);
    }
}
