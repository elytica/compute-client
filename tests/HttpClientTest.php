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

    public function testGetUserSubscription() {
        $userId = 1;
        $subscription = $this->httpClient->getUserSubscription($userId, true);
        $this->assertIsArray($subscription);
    }
}
