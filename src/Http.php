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

  protected function postMultipart(String $route, $data = []) {
    return $this->httpClient->request("POST",
      "$this->base_url/$route",
      ["headers" => $this->headers, "multipart" => $data],
      $this->options);
  }

  protected function downloadRequest(String $route, $data) {
    return $this->httpClient->request("GET",
      "$this->base_url/$route",
      ["headers" => $this->headers, "sink" => $data],
      $this->options);
  }

  private function wrapRequest(String $type, String $route, $data=[], callable $error_callback=null) {
    try {
      $result = $this->request($type, $route, $data);
      if ($result->getStatusCode() > 298 || $result->getStatusCode() < 200) {
        throw new \Exception($result->getBody()->getContents());
      }
      return json_decode($result->getBody()->getContents(), false);
    } catch (\Exception $e) {
      if ($error_callback !== null && is_callable($error_callback)) {
        $error_callback($e);
      }
    }
    return null;
  }

  protected function getRequest(String $route, $data=[], callable $error_callback=null) {
    return $this->wrapRequest("GET", $route, $data, $error_callback);
  }

  protected function deleteRequest(String $route, $data=[], callable $error_callback=null) {
    return $this->wrapRequest("DELETE", $route, $data, $error_callback);
  }

  protected function postRequest(String $route, $data=[], callable $error_callback=null) {
    return $this->wrapRequest("POST", $route, $data, $error_callback);
  }

  protected function putRequest(String $route, $data=[], callable $error_callback=null) {
    return $this->wrapRequest("PUT", $route, $data, $error_callback);
  }

  protected function uploadFile(String $route,
    String $filename, String $contents, callable $error_callback=null) {
    try {
      $multipart = [
        [
          "name" => "files[]",
          "filename" => $filename,
          "contents" => $contents 
        ]
      ];
      return json_decode(
        $this->postMultipart($route, $multipart, $this->options)
        ->getBody()->getContents());
    } catch(\Exception $e) {
      if ($error_callback !== null && is_callable($error_callback)) {
        $error_callback($e);
      }
    }
    return null;
  }
}

?>
