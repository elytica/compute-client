<?php
use PHPUnit\Framework\TestCase;
use Elytica\ComputeClient\ComputeService;
use Elytica\ComputeClient\JobStatus;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;

class ComputeServiceTest extends TestCase
{
    private function makeService(): TestableComputeService
    {
        return new TestableComputeService();
    }

    private function mockClient(array $responseBody, int $status = 200, ?array &$capturedOptions = null): Client
    {
        $mock = $this->createMock(Client::class);
        $response = new Response($status, [], json_encode($responseBody));
        if ($capturedOptions !== null) {
            $mock->method('request')
                ->willReturnCallback(function ($method, $url, $options) use ($response, &$capturedOptions) {
                    $capturedOptions = ['method' => $method, 'url' => $url, 'options' => $options];
                    return $response;
                });
        } else {
            $mock->method('request')->willReturn($response);
        }
        return $mock;
    }

    public function testGetUserId(): void
    {
        $this->assertEquals(42, $this->makeService()->getUserId());
    }

    public function testGetUserName(): void
    {
        $this->assertEquals('Test User', $this->makeService()->getUserName());
    }

    public function testCreateNewProject(): void
    {
        $service = $this->makeService();
        $captured = [];
        $service->setHttpClient($this->mockClient(['id' => 1, 'name' => 'My Project'], 200, $captured));

        $result = $service->createNewProject('My Project', 'Desc', 1);

        $this->assertEquals(1, $result->id);
        $this->assertEquals('POST', $captured['method']);
        $this->assertStringContainsString('api/projects', $captured['url']);
        $this->assertEquals('My Project', $captured['options']['json']['name']);
        $this->assertEquals('Desc', $captured['options']['json']['description']);
        $this->assertEquals(1, $captured['options']['json']['application']);
    }

    public function testCreateNewProjectIncludesWebhookWhenBothProvided(): void
    {
        $service = $this->makeService();
        $captured = [];
        $service->setHttpClient($this->mockClient(['id' => 1], 200, $captured));

        $service->createNewProject('P', 'D', 1, 'https://example.com/hook', 'secret');

        $this->assertEquals('https://example.com/hook', $captured['options']['json']['webhook_url']);
        $this->assertEquals('secret', $captured['options']['json']['webhook_secret']);
    }

    public function testCreateNewProjectOmitsWebhookWhenIncomplete(): void
    {
        $service = $this->makeService();
        $captured = [];
        $service->setHttpClient($this->mockClient(['id' => 1], 200, $captured));

        // Only URL, no secret — neither should be sent
        $service->createNewProject('P', 'D', 1, 'https://example.com/hook', null);

        $this->assertArrayNotHasKey('webhook_url', $captured['options']['json']);
        $this->assertArrayNotHasKey('webhook_secret', $captured['options']['json']);
    }

    public function testCreateNewJobDefaultPriority(): void
    {
        $service = $this->makeService();
        $captured = [];
        $service->setHttpClient($this->mockClient(['id' => 5, 'name' => 'Job'], 200, $captured));

        $service->createNewJob(1, 'My Job');

        $this->assertEquals(100, $captured['options']['json']['priority']);
        $this->assertEquals('My Job', $captured['options']['json']['name']);
    }

    public function testCreateNewJobCustomPriority(): void
    {
        $service = $this->makeService();
        $captured = [];
        $service->setHttpClient($this->mockClient(['id' => 5], 200, $captured));

        $service->createNewJob(1, 'High Priority', 200);

        $this->assertEquals(200, $captured['options']['json']['priority']);
    }

    public function testQueueJobSendsQueuedStatus(): void
    {
        $service = $this->makeService();
        $captured = [];
        $service->setHttpClient($this->mockClient(['id' => 1, 'status' => 1], 200, $captured));

        $service->queueJob(1);

        $this->assertEquals(JobStatus::QUEUED, $captured['options']['json']['updatedstatus']);
        $this->assertEquals('PUT', $captured['method']);
    }

    public function testUpdateProjectOnlySendsNonNullFields(): void
    {
        $service = $this->makeService();
        $captured = [];
        $service->setHttpClient($this->mockClient(['id' => 1], 200, $captured));

        $service->updateProject(1, null, null, 'New description', null);

        $this->assertArrayHasKey('description', $captured['options']['json']);
        $this->assertArrayNotHasKey('name', $captured['options']['json']);
        $this->assertArrayNotHasKey('webhook_url', $captured['options']['json']);
        $this->assertEquals('PATCH', $captured['method']);
    }

    public function testHaltJobPostsToCorrectEndpoint(): void
    {
        $service = $this->makeService();
        $captured = [];
        $service->setHttpClient($this->mockClient(['id' => 1, 'status' => 5], 200, $captured));

        $service->haltJob(7);

        $this->assertStringContainsString('api/jobs/7/halt', $captured['url']);
        $this->assertEquals('POST', $captured['method']);
    }

    public function testErrorCallbackInvokedOnFailure(): void
    {
        $service = $this->makeService();
        $mock = $this->createMock(Client::class);
        $mock->method('request')->willThrowException(new \Exception('Connection refused'));
        $service->setHttpClient($mock);

        $errorCalled = false;
        $service->haltJob(1, function ($e) use (&$errorCalled) {
            $errorCalled = true;
        });

        $this->assertTrue($errorCalled);
    }

    // --- verifyWebhookSignature ---

    public function testVerifyWebhookSignatureValid(): void
    {
        $payload   = '{"job_id":1,"status":"completed"}';
        $secret    = 'webhook-secret';
        $signature = hash_hmac('sha256', $payload, $secret);

        $this->assertTrue(ComputeService::verifyWebhookSignature($payload, $signature, $secret));
    }

    public function testVerifyWebhookSignatureInvalidSignature(): void
    {
        $payload = '{"job_id":1,"status":"completed"}';
        $secret  = 'webhook-secret';

        $this->assertFalse(ComputeService::verifyWebhookSignature($payload, 'tampered', $secret));
    }

    public function testVerifyWebhookSignatureWrongSecret(): void
    {
        $payload   = '{"job_id":1,"status":"completed"}';
        $signature = hash_hmac('sha256', $payload, 'correct-secret');

        $this->assertFalse(ComputeService::verifyWebhookSignature($payload, $signature, 'wrong-secret'));
    }

    public function testVerifyWebhookSignatureTamperedPayload(): void
    {
        $secret    = 'webhook-secret';
        $original  = '{"job_id":1,"status":"completed"}';
        $signature = hash_hmac('sha256', $original, $secret);
        $tampered  = '{"job_id":1,"status":"halted"}';

        $this->assertFalse(ComputeService::verifyWebhookSignature($tampered, $signature, $secret));
    }

    // --- V2 API ---

    public function testGetUserContextCallsV2Endpoint(): void
    {
        $service = $this->makeService();
        $captured = [];
        $service->setHttpClient($this->mockClient(['user' => ['id' => 1], 'projects' => []], 200, $captured));

        $service->getUserContext();

        $this->assertStringContainsString('api/v2/user/context', $captured['url']);
        $this->assertEquals('GET', $captured['method']);
    }

    public function testGetJobBatchStatusBuildsQueryString(): void
    {
        $service = $this->makeService();
        $captured = [];
        $service->setHttpClient($this->mockClient(['jobs' => []], 200, $captured));

        $service->getJobBatchStatus([1, 2, 3]);

        $this->assertStringContainsString('api/v2/jobs/batch-status', $captured['url']);
        $this->assertStringContainsString('job_ids=1,2,3', $captured['url']);
        $this->assertEquals('GET', $captured['method']);
    }

    public function testGetJobBatchStatusFiltersNonIntegers(): void
    {
        $service = $this->makeService();
        $captured = [];
        $service->setHttpClient($this->mockClient(['jobs' => []], 200, $captured));

        $service->getJobBatchStatus([1, 2]);

        $this->assertStringContainsString('job_ids=1,2', $captured['url']);
    }

    public function testHaltJobsCallsV2BatchEndpoint(): void
    {
        $service = $this->makeService();
        $captured = [];
        $service->setHttpClient($this->mockClient(['success_count' => 2], 200, $captured));

        $service->haltJobs([5, 6]);

        $this->assertStringContainsString('api/v2/jobs/batch', $captured['url']);
        $this->assertEquals('halt', $captured['options']['json']['action']);
        $this->assertEquals([5, 6], $captured['options']['json']['job_ids']);
        $this->assertEquals('POST', $captured['method']);
    }

    public function testCreateProjectWorkflowSendsJobsArray(): void
    {
        $service = $this->makeService();
        $captured = [];
        $service->setHttpClient($this->mockClient(['id' => 1, 'jobs' => []], 200, $captured));

        $jobs = [['name' => 'Job 1', 'priority' => 150]];
        $service->createProjectWorkflow('My Project', 'Desc', 1, $jobs);

        $this->assertStringContainsString('api/v2/projects/workflow', $captured['url']);
        $this->assertEquals('POST', $captured['method']);
        $this->assertEquals($jobs, $captured['options']['json']['jobs']);
        $this->assertEquals('My Project', $captured['options']['json']['name']);
    }

    public function testCreateProjectWorkflowIncludesWebhookWhenProvided(): void
    {
        $service = $this->makeService();
        $captured = [];
        $service->setHttpClient($this->mockClient(['id' => 1], 200, $captured));

        $service->createProjectWorkflow('P', 'D', 1, [], 'https://example.com/hook', 'secret');

        $this->assertEquals('https://example.com/hook', $captured['options']['json']['webhook_url']);
        $this->assertEquals('secret', $captured['options']['json']['webhook_secret']);
    }
}

/**
 * Test double that skips the constructor's whoami() call and HTTP client creation,
 * allowing the mock Guzzle client to be injected via setHttpClient().
 */
class TestableComputeService extends ComputeService
{
    public function __construct()
    {
        $this->headers  = ['Authorization' => 'Bearer test-token', 'Accept' => 'application/json'];
        $this->base_url = 'https://example.com';
        $this->options  = ['timeout' => 300];
        $this->httpClient = new Client(['verify' => true]);
        $this->user     = (object)['id' => 42, 'name' => 'Test User'];
    }
}
