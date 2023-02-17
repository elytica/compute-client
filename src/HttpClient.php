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

  function downloadFile($project_id, $file_id, $filepath) {
      if (!is_dir(dirname($filepath))) {
          return false;
      }
      try {
          $this->downloadRequest("api/projects/$project_id/download/$file_id", $filepath);
      } catch(RequestException $e) {
          $response = $e->getResponse();
          return false;
      }
      return true;
  }

  function getApplications() {
    try {
      return $this->getRequest("api/applications");
    } catch(\Exception $e) {
       $this->catchResponseFailure($e);
    }
  }

  function createNewProject($project_name, $project_description, $application) {
    $data = array(
      "name" => $project_name,
      "description" => $project_description,
      "application" => $application
    );
    return $this->postRequest("api/projects", $data);
  }

  function createNewJob($project_id, $job_name) {
    $data = array(
      "name" => $job_name,
      "priority" => 100
    );
    return $this->postRequest("api/projects/$project_id/createjob", $data);
  }

  function whoami() {
    return $this->getRequest("api/user");
  }
}

?>
