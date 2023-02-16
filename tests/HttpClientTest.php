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

    public function testGetUnfinishedJobs() {
        $jobs = $this->httpClient->getUnfinishedJobs(true);
        $this->assertIsArray($jobs);
    }

    public function testGetAllApplications() {
        $applications = $this->httpClient->getAllApplications(true);
        $this->assertIsArray($applications);
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
    }

}
