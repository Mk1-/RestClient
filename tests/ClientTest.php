<?php

namespace RestClient\Tests;

use ReflectionClass;
use PHPUnit\Framework\TestCase;
use RestClient\Client;
use Symfony\Component\Dotenv\Dotenv;


class ClientTest extends TestCase
{

    public function test_object_creation_and_url_from_uri() {
        $uri = "http://user:pass@my.own.api.org:9999/api1";
        $c = new Client($uri);
        $baseUrl = $this->getPrivatePropertyValue($c, 'baseUrl');
        $this->assertEquals("http://my.own.api.org:9999", $baseUrl);
    }

    public function test_object_creation_and_url_from_uri_2() {
        $uri = "https://my.own.api.org/api1/";
        $c = new Client($uri);
        $baseUrl = $this->getPrivatePropertyValue($c, 'baseUrl');
        $this->assertEquals("https://my.own.api.org", $baseUrl);
    }

    public function test_object_creation_and_basic_secret_from_uri() {
        $uri = "http://user:pass@my.own.api.org:9999/api1";
        $c = new Client($uri, Client::AUTH_BASIC);
        $secret = $this->getPrivatePropertyValue($c,'apiSecret');
        $this->assertEquals("pass", $secret);
    }

    public function test_with_mock_transport_network_error() {
        $trans = new TransportMock();
        $uri = "http://nonetwork";
        $c = new Client($uri, Client::AUTH_NONE, '', '', $trans);
        [$stat, $body] = $c->callApi("/");
        $this->assertEquals(404, $stat);
    }

    public function test_with_mock_transport_500_response() {
        $trans = new TransportMock();
        $uri = "http://testmock";
        $c = new Client($uri, Client::AUTH_NONE, '', '', $trans);
        [$stat, $body] = $c->callApi("/unknown");
        $this->assertEquals(500, $stat);
    }

    public function test_with_mock_transport_dummy_request() {
        $trans = new TransportMock();
        $uri = "http://testmock";
        $c = new Client($uri, Client::AUTH_NONE, '', '', $trans);
        [$stat, $body] = $c->callApi("/dummy");
        $this->assertEquals(200, $stat);
        $this->assertEquals("dummy answer", $body);
    }

    public function test_with_mock_transport_basic_secret() {
        $trans = new TransportMock();
        $uri = "http://user:pass@testmock";
        $c = new Client($uri, Client::AUTH_BASIC, '', '', $trans);
        [$stat, $body] = $c->callApi("/checkauth");
        $this->assertEquals(200, $stat);
        $this->assertEquals("Basic " . base64_encode("user:pass"), $body);
    }

    public function test_with_mock_transport_basic_secret_2() {
        $trans = new TransportMock();
        $uri = "http://testmock";
        $c = new Client($uri, Client::AUTH_BASIC, 'pass', 'user', $trans);
        [$stat, $body] = $c->callApi("/checkauth");
        $this->assertEquals(200, $stat);
        $this->assertEquals("Basic " . base64_encode("user:pass"), $body);
    }

    public function test_with_real_transport_non_exsisting_uri() {
        $uri = "http://404.php.net/";
        $c = new Client($uri);
        [$stat, $body] = $c->callApi("/");
        $this->assertEquals(404, $stat);
        $this->assertEquals("Could not resolve host: 404.php.net", $body);
    }

    public function test_with_real_transport_non_existing_endpoint() {
        $uri = "https://reqres.in";
        $c = new Client($uri);
        [$stat, $body] = $c->callApi("/api/unknown/23");
        $this->assertEquals(404, $stat);
        $this->assertEquals("The requested URL returned error: 404", $body);
    }

    public function test_with_real_transport_GET_request() {
        $uri = "https://reqres.in";
        $c = new Client($uri);
        [$stat, $body] = $c->callApi("/api/users/2");
        $this->assertEquals(200, $stat);
        $this->assertEquals('{"data":{"id":2,"email":"janet.weaver@reqres.in"', substr($body, 0, 48));
    }

    public function test_with_real_transport_HEAD_request() {
        $uri = "https://reqres.in";
        $c = new Client($uri);
        [$stat, $body] = $c->callApi("/api/users/2", 'HEAD');
        $this->assertEquals(200, $stat);
        $this->assertEquals('', substr($body, 0, 48));
    }

    public function test_with_real_transport_POST_request() {
        $uri = "https://reqres.in";
        $c = new Client($uri);
        $rqBody = '{"name": "morpheus", "job": "leader" }';
        [$stat, $body] = $c->callApi("/api/users", 'POST', $rqBody);
        $this->assertEquals(201, $stat);
        $this->assertEquals('{"{\"name\": \"morpheus\", \"job\": \"leader\" }', substr($body, 0, 48));
    }

    public function test_with_real_transport_DELETE_request() {
        $uri = "https://reqres.in";
        $c = new Client($uri);
        [$stat, $body] = $c->callApi("/api/users/2", 'delete');
        $this->assertEquals(204, $stat);
        $this->assertEquals('', substr($body, 0, 48));
    }

    public function test_with_real_transport_GitHub_public_access() {
        $dotenv = new Dotenv();
        $dotenv->loadEnv(__DIR__.'/../.env');
        $uri = "https://api.github.com";
        $user = $_ENV['GITHUB_USER'];
        $c = new Client($uri);
        [$stat, $body] = $c->callApi("/users/" . $user);
        $this->assertEquals(200, $stat);
        $this->assertEquals('{"login":"' . $user . '","id":', substr($body, 0, 17 + strlen($user)));
    }

    public function test_with_real_transport_GitHub_with_BASIC_AUTH() {
        $dotenv = new Dotenv();
        $dotenv->loadEnv(__DIR__.'/../.env');
        $uri = "https://api.github.com";
        $user = $_ENV['GITHUB_USER'];
        $c = new Client($uri, Client::AUTH_BASIC, $_ENV['GITHUB_TOKEN'], $user);
        [$stat, $body] = $c->callApi("/users/" . $user);
        $this->assertEquals(200, $stat);
        $this->assertObjectHasAttribute("total_private_repos", json_decode($body), "This fail means, that wrong login or token is set in .env.local file.");
    }

    public function test_with_real_transport_GitHub_with_BEARER_AUTH() {
        $dotenv = new Dotenv();
        $dotenv->loadEnv(__DIR__.'/../.env');
        $uri = "https://api.github.com";
        $user = $_ENV['GITHUB_USER'];
        $c = new Client($uri, Client::AUTH_BEARER, $_ENV['GITHUB_TOKEN']);
        [$stat, $body] = $c->callApi("/users/" . $user);
        $msg = "This fail means, that wrong login or token is set in .env.local file.";
        $this->assertEquals(200, $stat, $msg);
        $this->assertObjectHasAttribute("total_private_repos", json_decode($body), $msg);
    }

    //
    // utils
    //
    /**
     * @param $object
     * @param $propertyName
     * @return mixed
     * @throws \ReflectionException
     */
    public function getPrivatePropertyValue($object, $propertyName ) {
        $reflector = new ReflectionClass( get_class($object) );
        $property = $reflector->getProperty( $propertyName );
        $property->setAccessible( true );
        return $property->getValue($object);
    }
}