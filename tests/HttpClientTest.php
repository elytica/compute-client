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

  private function createNewProjectAndJob() {
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
    return [$project, $job];
  }

  public function testCreateNewProjectAndJob() {
    list($project, $job) = $this->createNewProjectAndJob();
    print_r($this->httpClient->deleteProject($project->id));
  }

  public function testUploadFileAssignAndRun() {
    // create project and job
    list($project, $job) = $this->createNewProjectAndJob();

    // upload main file
    $results = $this->httpClient->uploadInputFile("file.hlpl",
      "def main():\n\treturn 0", $project->id);
    $this->assertIsObject($results);
    $this->assertIsArray($results->oldfiles);
    $this->assertIsArray($results->newfiles);
    $this->assertEquals(1, count($results->newfiles));
    $file = $results->newfiles[0];
    $this->assertIsInt($file->id);
    $this->assertEquals("file.hlpl", $file->filename);

    // assign file to job
    $assignment = $this->httpClient->assignFileToJob($project->id, $job->id, $file->id, 1);
    $this->assertIsObject($assignment);
    $this->assertIsObject($assignment->new);
    $this->assertIsInt($assignment->new->id);
    $this->assertEquals($file->id, $assignment->new->file_id);
    $this->assertEquals($job->id, $assignment->new->job_id);

    // queue job
    $queued_job = $this->httpClient->queueJob($job->id);
    $this->assertIsObject($queued_job);
    $this->assertIsInt($queued_job->id);
    $this->assertEquals($queued_job->status, 1);

    // stop job
    $halt_job = $this->httpClient->haltJob($job->id);
    $this->assertIsObject($halt_job);
    $this->assertIsInt($halt_job->id);
    $this->assertEquals($halt_job->id, $queued_job->id);
    $this->assertEquals($halt_job->status, 5);


    // download file
    $tmp = tmpfile();
    $tmp_path = stream_get_meta_data($tmp)['uri'];
    $file_download = $this->httpClient->downloadFile($project->id, $file->id, $tmp);
    $this->assertEquals($file_download->getStatusCode(), 200);
    $this->assertEquals("def main():\n\treturn 0", file_get_contents($tmp_path));
    
    // delete project
    $success = true;
    $this->httpClient->deleteProject($project->id, function ($e) use (&$success) {
      $success = false;
    });
    $this->assertTrue($success);
  }

}
