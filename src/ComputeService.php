<?php
namespace Elytica\ComputeClient;
use GuzzleHttp\Exception\RequestException;
use Elytica\ComputeClient\Http;
use Elytica\ComputeClient\JobStatus;

class ComputeService extends Http {
  protected $user, $options, $headers;

  function __construct($token = null, $base_url = null) {
    if ($token === null) {
      $token = function_exists('config')
        ? config('compute.token', '')
        : '';
    }
    if ($base_url === null) {
      $base_url = function_exists('config')
        ? config('compute.base_url', 'https://service.elytica.com')
        : 'https://service.elytica.com';
    }
    parent::__construct($token, $base_url);
    $user = $this->whoami();
    if ($user === null) {
      throw new \RuntimeException(
        'Failed to authenticate with the Compute API. Check your token and base URL.'
      );
    }
    $this->user = $user;
    $this->options["timeout"] = 300;
  }

  function getUserId(): int {
    return $this->user->id;
  }

  function getUserName(): string {
    return $this->user->name;
  }

  function whoami() {
    return $this->getRequest("api/user");
  }

  function downloadFile($project_id, $file_id, $file,
    callable $error_callback = null) {
    try {
      return $this->downloadRequest("api/projects/$project_id/download/$file_id", $file);
    } catch (RequestException $e) {
      if (is_callable($error_callback)) {
        $error_callback($e);
      }
    }
    return null;
  }

  function getApplications() {
    return $this->getRequest("api/applications");
  }

  public function getProjects() {
    return $this->getRequest("api/projects");
  }

  public function getJobs($project_id) {
    return $this->getRequest("api/projects/$project_id/getjobs");
  }

  public function deleteProject(int $project_id, callable $error_callback = null) {
    return $this->deleteRequest("api/projects/$project_id", [], $error_callback);
  }

  function createNewProject($project_name, $project_description, $application,
    $webhook_url = null, $webhook_secret = null) {
    $data = [
      "name"        => $project_name,
      "description" => $project_description,
      "application" => $application,
    ];
    if ($webhook_url && $webhook_secret) {
      $data["webhook_url"]    = $webhook_url;
      $data["webhook_secret"] = $webhook_secret;
    }
    return $this->postRequest("api/projects", $data);
  }

  function updateProject($project_id,
    $webhook_url   = null,
    $webhook_secret = null,
    $description   = null,
    $name          = null,
    $error_callback = null) {
    $data = array_merge(
      $webhook_url    ? ['webhook_url'    => $webhook_url]    : [],
      $webhook_secret ? ['webhook_secret' => $webhook_secret] : [],
      $name           ? ['name'           => $name]           : [],
      $description    ? ['description'    => $description]    : []
    );
    return $this->patchRequest("api/projects/$project_id", $data, $error_callback);
  }

  function createNewJob($project_id, $job_name, int $priority = 100) {
    $data = [
      "name"     => $job_name,
      "priority" => $priority,
    ];
    return $this->postRequest("api/projects/$project_id/createjob", $data);
  }

  function uploadInputFile(String $filename, String $contents,
    int $project_id, callable $error_callback = null) {
    return $this->uploadFile("api/projects/$project_id/upload",
      $filename, $contents, $error_callback);
  }

  function queueJob(int $job_id, callable $error_callback = null) {
    return $this->putRequest("api/update/$job_id",
      ["updatedstatus" => JobStatus::QUEUED], $error_callback);
  }

  function haltJob(int $job_id, callable $error_callback = null) {
    return $this->postRequest("api/jobs/$job_id/halt", [], $error_callback);
  }

  function assignFileToJob(int $project_id, int $job_id,
    int $file_id, int $arg = 1, callable $error_callback = null) {
    $data = [
      "file" => $file_id,
      "arg"  => $arg,
    ];
    return $this->postRequest("api/projects/$project_id/assignfile/$job_id",
      $data, $error_callback);
  }

  function getOutputFiles(int $job_id, int $project_id, callable $error_callback = null) {
    return $this->getRequest("api/projects/$project_id/outputfiles/$job_id",
      [], $error_callback);
  }

  function getInputFiles(int $project_id, callable $error_callback = null) {
    return $this->getRequest("api/projects/$project_id/files",
      [], $error_callback);
  }

  // --- V2 API ---

  /**
   * Returns consolidated user context: user info, applications, projects, and
   * subscription in a single request — replacing several V1 calls.
   */
  public function getUserContext(): ?object {
    return $this->getRequest("api/v2/user/context");
  }

  /**
   * Returns the status of multiple jobs in a single request.
   *
   * @param int[] $jobIds
   */
  public function getJobBatchStatus(array $jobIds): ?object {
    $ids = implode(',', array_map('intval', $jobIds));
    return $this->getRequest("api/v2/jobs/batch-status?job_ids=$ids");
  }

  /**
   * Halts multiple jobs in a single request.
   *
   * @param int[] $jobIds
   */
  public function haltJobs(array $jobIds, callable $error_callback = null): ?object {
    return $this->postRequest("api/v2/jobs/batch", [
      "action"  => "halt",
      "job_ids" => array_map('intval', $jobIds),
    ], $error_callback);
  }

  /**
   * Atomically creates a project, optionally with pre-defined jobs, in a single
   * request. Jobs should be arrays with at least a "name" key, e.g.:
   *   [['name' => 'Job 1', 'priority' => 100], ...]
   *
   * @param array<array{name: string, priority?: int}> $jobs
   */
  public function createProjectWorkflow(
    string $projectName,
    string $projectDescription,
    int $application,
    array $jobs = [],
    ?string $webhookUrl = null,
    ?string $webhookSecret = null,
    callable $error_callback = null
  ): ?object {
    $data = [
      "name"        => $projectName,
      "description" => $projectDescription,
      "application" => $application,
      "jobs"        => $jobs,
    ];
    if ($webhookUrl && $webhookSecret) {
      $data["webhook_url"]    = $webhookUrl;
      $data["webhook_secret"] = $webhookSecret;
    }
    return $this->postRequest("api/v2/projects/workflow", $data, $error_callback);
  }

  /**
   * Verifies an incoming webhook payload against the HMAC-SHA256 signature
   * produced by the server using the project's webhook_secret.
   *
   * Usage:
   *   $raw     = file_get_contents('php://input');
   *   $sig     = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
   *   $valid   = ComputeService::verifyWebhookSignature($raw, $sig, $secret);
   */
  public static function verifyWebhookSignature(
    string $payload,
    string $signature,
    string $secret
  ): bool {
    $expected = hash_hmac('sha256', $payload, $secret);
    return hash_equals($expected, $signature);
  }
}
