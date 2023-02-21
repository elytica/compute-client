<?php
use PHPUnit\Framework\TestCase;
use Elytica\ComputeClient\WebsocketClient;
use Elytica\ComputeClient\HttpClient;

class WebsocketClientTest extends TestCase
{
  private $auth_url, $ws_url, $token;
  private $client;

  private function setupProjectAndJob() {
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
    return array($job, $project);
  }

  private function setupWebsockets(array $project_and_job, callable $callback)
  {
    list($job, $project) = $project_and_job;
    $job_channel = "presence-jobs.$job->id";
    $success = false;
    $client = new WebsocketClient($this->auth_url, $this->ws_url, 'elytica_service', 'elytica_service', $this->token, 3);
    $client->addInitChannel($job_channel);
    $client->connect(function ($message, $conn) use (&$success, &$client, $callback) {
      $success = $callback($client, $message, $conn);
    }, function($e) use (&$success, &$client) { 
      $success = false;
      $client->stop();
    });
    $this->assertTrue($success); 
    print_r($this->httpClient->deleteProject($project->id));
  }

  protected function setUp(): void
  {
    $this->auth_url = 'https://service.elytica.com/broadcasting/auth';
    $this->ws_url = 'wss://socket.elytica.com';
    $this->token = getenv('COMPUTE_TOKEN');
    $url = "https://service.elytica.com";
    $this->httpClient = new HttpClient($this->token, $url);
    if (!$this->token) {
      throw new Exception('COMPUTE_TOKEN environment variable not set');
    }
  }

  public function testConnect()
  {
    $connected = false;
    $client = new WebsocketClient($this->auth_url, $this->ws_url, 'elytica_service', 'elytica_service', $this->token, 3);
    $client->connect(function ($message, $conn) use (&$connected, &$client) {
      $connected = true;
      $client->stop();
    }, function($e) use (&$connected, &$client) { 
      $connected = false;
      $client->stop();
    });
    $this->assertTrue($connected);
  }

  public function testSubscribeChannel()
  {
    $this->setupWebsockets($this->setupProjectAndJob(), function ($client, $message, $conn) {
				if ($message->event == 'pusher_internal:subscription_succeeded') {
      $client->stop();
      return true;
    }
    return false;
    });
  }
}
