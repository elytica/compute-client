<?php
namespace Elytica\ComputeClient;
use WebsocketClient;
use Elytica\ComputeClient\Http;

class ComputeService extends Http {
  protected $user, $options, $headers;

  function __construct($token, $base_url="https://service.elytica.com") {
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

  function downloadFile($project_id, $file_id, $file,
    callable $error_callback = null) {
    try {
      return $this->downloadRequest("api/projects/$project_id/download/$file_id", $file);
    } catch(RequestException $e) {
      if (is_callable($error_callback)) {
        $error_callback($e);
      }
    }
    return null;
  }

  function getApplications() {
    try {
      return $this->getRequest("api/applications");
    } catch(\Exception $e) {
       $this->catchResponseFailure($e);
    }
  }

  public function getProjects() {
    return $this->getRequest("api/projects");
  }

  public function getJobs($project_id) {
    return $this->getRequest("api/projects/$project_id/getjobs");
  }

  public function deleteProject(int $project_id, callable $error_callback=null) {
    return $this->deleteRequest("api/projects/${project_id}", [], $error_callback);
  }

  function createNewProject($project_name, $project_description, $application, $webhook_url=null, $webhook_secret=null) {
    $data = array(
      "name" => $project_name,
      "description" => $project_description,
      "application" => $application
    );
    if ($webhook_url && $webhook_secret) {
      $data["webhook_url"] = $webhook_url;
      $data["webhook_secret"] = $webhook_secret;
    }
    return $this->postRequest("api/projects", $data);
  }


  function updateProject($project_id,
    $webhook_url=null,
    $webhook_secret=null,
    $description=null,
    $name=null,
    $error_callback=null) {
    $data = array_merge(
        $webhook_url ? ['webhook_url' => $webhook_url] : [],
        $webhook_secret ? ['webhook_secret' => $webhook_secret] : [],
        $name ? ['name' => $name] : [],
        $description ? ['description' => $description] : []
    );
    return $this->patchRequest("api/projects/$project_id",
      $data, $error_callback);
  }

  function createNewJob($project_id, $job_name) {
    $data = array(
      "name" => $job_name,
      "priority" => 100
    );
    return $this->postRequest("api/projects/$project_id/createjob", $data);
  }

  function uploadInputFile(String $filename, String $contents,
    int $project_id, callable $error_callback=null) {
    return $this->uploadFile("api/projects/$project_id/upload",
      $filename, $contents, $error_callback);
  }

  function queueJob(int $job_id, callable $error_callback=null) {
    return $this->putRequest("api/update/$job_id",
      array("updatedstatus" => 1), $error_callback);
  }

  function haltJob(int $job_id, callable $error_callback=null) {
    return $this->postRequest("api/jobs/$job_id/halt",[], $error_callback);
  }

  function assignFileToJob(int $project_id, int $job_id,
    int $file_id, int $arg=1, callable $error_callback=null) {
    $data = array(
      "file" => $file_id,
      "arg" => $arg
    );
    return $this->postRequest("api/projects/$project_id/assignfile/$job_id",
      $data, $error_callback);
  }

  function getOutputFiles(int $job_id, int $project_id, callable $error_callback=null) {
    return $this->getRequest("api/projects/$project_id/outputfiles/$job_id",
      $error_callback);
  }

  function getInputFiles(int $project_id, callable $error_callback=null) {
    return $this->getRequest("api/projects/$project_id/files",
      $error_callback);
  }

  function whoami() {
    return $this->getRequest("api/user");
  }
}

?>
