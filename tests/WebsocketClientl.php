<?php
use PHPUnit\Framework\TestCase;
use Elytica\ComputeClient\WebsocketClient;

class WebsocketClientTest extends TestCase
{
    private $auth_url, $ws_url, $token;

    protected function setUp(): void
    {
        $this->auth_url = 'https://service.elytica.com/broadcasting/auth';
        $this->ws_url = 'wss://socket.elytica.com/app';
        $this->token = getenv('COMPUTE_TOKEN');
        if (!$this->token) {
            throw new Exception('COMPUTE_TOKEN environment variable not set');
        }
    }

    public function testInit()
    {
        $client = new WebsocketClient($this->auth_url, $this->ws_url, 'elytica_service', 'elytica_service', 60);
        $this->assertInstanceOf(WebsocketClient::class, $client);
    }

    public function testAddChannel()
    {
        $client = new WebsocketClient($this->auth_url, $this->ws_url, 'elytica_service', 'elytica_service', 60);
        $client->addInitChannel('test');
        $channels = $client->getChannels();
        $this->assertEquals(['test'], $channels);
    }

    public function testSendChannelAuth()
    {
        $client = new WebsocketClient($this->auth_url, $this->ws_url, 'elytica_service', 'elytica_service', 60);
        $client->token = $this->token;
        $channel = 'jobs';
        $auth = $client->sendChannelAuth($channel);
        $this->assertEquals($channel, $auth->channel);
        $this->assertObjectHasAttribute('auth', $auth->data);
        $this->assertObjectHasAttribute('channel_data', $auth->data);
        $this->assertObjectHasAttribute('channel_id', $auth->data);
    }

    public function testPingPong()
    {
        $client = new WebsocketClient($this->auth_url, $this->ws_url, 'elytica_service', 'elytica_service', 60);
        $client->token = $this->token;
        $pongReceived = false;
        $client->connect(function ($message, $conn) use ($client, &$pongReceived) {
            $json_msg = json_decode($message);
            if ($json_msg->event === "pusher:pong") {
                $pongReceived = true;
                $client->loop->stop();
            }
        });
        $this->assertTrue($pongReceived);
    }
}

