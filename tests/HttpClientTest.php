<?php
use PHPUnit\Framework\TestCase;
use Elytica\ComputeClient\HttpClient;

class HttpClientTest extends TestCase {
  protected $httpClient;

  protected function setUp(): void {
    $token = getenv("COMPUTE_TOKEN");
    $url = "https://service.elytica.com";
    $this->httpClient = new HttpClient($token, $url);
  }

  public function testGetUserId() {
    $userId = $this->httpClient->getUserId();
    $this->assertIsInt($userId);
    $user = $this->httpClient->whoami(true);
    $this->assertIsInt($user->id);
  }

  public function testGetUserName() {
    $userName = $this->httpClient->getUserName();
    $this->assertIsString($userName);
   }

  public function testCreateNewProjectAndJob() {
    $projectName = "Test Project";
    $projectDesc = "This is a test project";
    $applications = $this->httpClient->getApplications(true);
    $this->assertIsArray($applications);
    $this->assertGreaterThanOrEqual(1, count($applications));
    $project = $this->httpClient->createNewProject($projectName, $projectDesc, $applications[0]->id, true);
    $this->assertEquals($projectName, $project->name);
    $this->assertEquals($projectDesc, $project->description);
    $this->assertIsInt($project->id);
    $jobName = "Test Job";
    $job = $this->httpClient->createNewJob($project->id, $jobName, true);
    $this->assertEquals($jobName, $job->name);
    $filtered_projects = array_filter($this->httpClient->getProjects(), function($p) use ($project) {
      return $p->id === $project->id;
    });
    $this->assertGreaterThanOrEqual(1, count($filtered_projects));
    $filtered_jobs = array_filter($this->httpClient->getJobs($project->id), function($j) use ($job) {
      return $j->id === $job->id;
    });
    $this->assertGreaterThanOrEqual(1, count($filtered_jobs));
    $this->assertEquals($this->httpClient->deleteProject($project->id)->getStatusCode(), 200);
  }

}
