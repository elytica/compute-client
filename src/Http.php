<?php
namespace Elytica\ComputeClient;
use GuzzleHttp\Client;

class Http {
  protected $user, $options, $headers, $httpClient, $base_url;
  function __construct($token, $url) {
    $this->headers = [
      "Accept" => "application/json",
      "Authorization" => "Bearer $token"
    ];
    $this->base_url = $url;
    $this->options["timeout"] = 300;
    $this->httpClient = new Client(['verify' => false]);
  }
  
  protected function request(String $type, String $route, $data = []) {
    if (is_object($data)) {
      $data = (array)$data;
    }
    return $this->httpClient->request($type,
      "$this->base_url/$route",
      ["headers" => $this->headers, "json" => $data],
      $this->options);
  }

  protected function getRequest(String $route, $data=[]) {
    return json_decode($this->request("GET", $route, $data)
      ->getBody()->getContents(), false);
  }

  protected function downloadRequest(String $route, $data=[]) {
    return $this->httpClient->request("GET",
      "$this->base_url/$route",
      ["headers" => $this->headers, "sink" => $data],
      $this->options);
  }

  protected function putRequest(String $route, $data=[]) {
    return json_decode($this->request("PUT", $route, $data)
      ->getBody()->getContents(), false);
  }

  protected function deleteRequest(String $route, $data=[]) {
    return $this->request("DELETE", $route, $data);
  }


  protected function postRequest(String $route, $data=[]) {
    return json_decode($this->request("POST", $route, $data)
             ->getBody()->getContents(), false);
  }

  protected function postMultipart(String $route, $data=[]) {
    return $this->request("POST", $route, $data);
  }
}

?>
