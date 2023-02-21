<?php
use PHPUnit\Framework\TestCase;
use Elytica\ComputeClient\Http;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;

class HttpTest extends TestCase {
  public function testConstruct() {
    $token = "my-token";
    $url = "https://example.com";
    $http = new TestHttp($token, $url);
    $this->assertInstanceOf(Client::class, $http->getHttpClient());
    $this->assertEquals($http->getHeaders()["Authorization"], "Bearer $token");
    $this->assertEquals($http->getBaseUrl(), $url);
  }

  public function testGetRequest() {
    $httpClientMock = $this->createMock(Client::class);
    $response = new Response(200, [], json_encode(["foo" => "bar"]));
    $httpClientMock->method("request")->willReturn($response);
    $http = new TestHttp("my-token", "https://example.com");
    $http->setHttpClient($httpClientMock);
    $result = $http->testGetRequest("test");
    $this->assertEquals("bar", $result->foo);
  }

  public function testPutRequest() {
    $httpClientMock = $this->createMock(Client::class);
    $response = new Response(200, [], json_encode(["success" => true]));
    $httpClientMock->method("request")->willReturn($response);

    $http = new TestHttp("my-token", "https://example.com");
    $http->setHttpClient($httpClientMock);

    $result = $http->testPutRequest("test", ["foo" => "bar"]);
    $this->assertEquals(true, $result->success);
  }
}

class TestHttp extends Http {
  public function testGetRequest($route, $data=[]) {
    return $this->getRequest($route, $data);
  }

  public function setHttpClient($client) {
    $this->httpClient = $client;
  }

  public function testPutRequest($route, $data=[]) {
    return $this->putRequest($route, $data); 
  }

  public function getHttpClient() {
    return $this->httpClient;
  }

  public function getHeaders() {
    return $this->headers;
  }

  public function getBaseUrl() {
    return $this->base_url;
  }
}

