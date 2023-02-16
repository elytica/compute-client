<?php
namespace Elytica\ComputeClient;
use WebsocketClient;
use Elytica\ComputeClient\Http;

class HttpClient extends Http {
  protected $user, $options, $headers;

  function __construct($token, $base_url) {
    parent::__construct($token, $base_url);
    $this->user = $this->whoami();
    $this->options["timeout"] = 300;
  }

  function getUserId() {
    return $this->user->id;
  }
  
  function getUserName() {
    return $this->user->name;
  }

  function getUnfinishedJobs() {
    return $this->getRequest("api/unfinishedjobs");
  }

  function getAllApplications() {
    return $this->getRequest("api/applications/all");
  }

  function downloadFile($project_id, $file_id, $filepath) {
      if (!is_dir(dirname($filepath))) {
          return false;
      }
      try {
          $this->downloadRequest("api/projects/$project_id/download/$file_id", $filepath);
      } catch(RequestException $e) {
          // Handle the exception here
          $response = $e->getResponse();
          $statusCode = $response->getStatusCode();
          $reason = $response->getReasonPhrase();
          // ...
          return false;
      }
      return true;
  }

  function getUserSubscription($user_id) {
    try {
      return $this->getRequest("api/useractivesubscriptions/$user_id");
    } catch(\Exception $e) {
       $this->catchResponseFailure($e);
    }
  }

  function uploadResults($path, $project_id, $job_id) {
    try {
      $files = array_diff(scandir($path), array(".", ".."));
      $multipart = [];
      foreach($files as $file) {
        array_push($multipart, [
          "name" => "files[]",
          "filename" => $file,
          "contents" => file_get_contents($path . $file)
        ]);
      }
      $this->httpClient->request("POST",
        $_ENV["url"] . "/api/projects/" . $project_id . "/uploadoutput/" . $job_id,
        ["headers" => $this->headers,
          "multipart" => $multipart
        ], $this->options);
    } catch(\Exception $e) {
       $this->catchResponseFailure($e);
    }
  }

  function whoami() {
    return $this->getRequest("api/user");
  }
}

?>
