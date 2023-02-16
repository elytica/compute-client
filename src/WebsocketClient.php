<?php
namespace Elytica\ComputeClient;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use React\EventLoop\Factory;
use React\Socket\Connector as ReactConnector;
use Ratchet\Client\Connector as RatchetConnector;
use function React\Promise\resolve;
use function Clue\React\Block\await;
use React\Promise\Timer;

class WebsocketClient {
  protected $client, $react_connector, $channels = array(),
    $socket_id, $alive, $timeout;
  protected $ws_url, $auth_url;
  protected $app_key;
  public $loop;
  function __construct($auth_url, $ws_url, $app_key, $timeout) {
    $this->loop = Factory::create();
    $this->client = new Client(['verify' => false]);
    $this->react_connector = new ReactConnector($this->loop, ['timeout' => 10]);
    $this->jobs_channel = "presence-jobs.";
    $this->ws_url = $ws_url;
    $this->app_key = $app_key;
    $this->timeout = $timeout;
    $this->auth_url = $auth_url;
  }

  function run() {
    $this->loop->run();
  }
 
  function addInitChannel($channel_name) {
    array_push($this->channels, $channel_name);
  }
  
  function getChannels() {
   return $this->channels;
  }

  function connect(callable $callback) {
    $connector = new RatchetConnector($this->loop,
      $this->react_connector);
    $connector("$this->ws_url/app/$this->app_key")
      ->then(function($conn) use ($callback) {
          $conn->on('message', function($msg) use ($callback, $conn) {
          $json_msg = json_decode($msg);
          $this->pong($json_msg);
          if ($json_msg->event === "pusher:connection_established") {
            $this->socket_id = json_decode($json_msg->data)->socket_id;
            $this->alive = true;
            foreach ($this->channels as $channel) {
              $this->connectToAuthChannel($channel, $conn); 
            }
            $this->onConnectionKeepAlive($json_msg, $conn);
          }
          $callback($msg, $conn);
          });
        },
        function($e) use ($callback) {
          echo "Could not connect: {$e->getMessage()}\n";
          $this->connect($callback);
        });
  }

  function connectToAuthChannel($channel_name, $conn) {
    $auth = $this->sendChannelAuth($channel_name);
    $this->subscribeToChannel($auth, $conn);
  }

  public function sendChannelAuth($channel_name) {
    $options['timeout'] = $timeout;
    $contents = json_decode($this->client->request('POST',
      $this->auth_url, [
        'headers' => [
          'Accept' => 'application/json',
          'Authorization' => 'Bearer '. $this->token,
          'X-App-ID' => $this->app_id,
          'X-Socket-ID' => $this->socket_id,
        ],
        'json' => ['socket_id' => $this->socket_id,
          'channel_name' => $channel_name]
      ], $options)->getBody()->getContents());
    $contents->channel = $channel_name;
    return $contents;
  }

  function subscribeToChannel($channel_data, $conn) {
    $conn->send(json_encode(
       ["data" => $channel_data,
        "event" => "pusher:subscribe"]
    ));
  }

  function unsubscribeToChannel($channel, $conn) {
    $conn->send(json_encode(
       ["data" => ["channel" => $channel],
        "event" => "pusher:unsubscribe"]
    ));
  }

  function ping($conn) {
    $conn->send(json_encode(["event" => "pusher:ping"]));
  }

  function finished($conn, $job_id) {
    $conn->send(json_encode([
      "event" => "client-finished",
      "channel" => $this->jobs_channel . $job_id,
      "data" => json_encode(["finished" => true])
    ]));
  }

  function halted($conn, $job_id) {
    $conn->send(json_encode([
      "event" => "client-halted",
      "channel" => $this->jobs_channel . $job_id,
      "data" => json_encode(["halted" => true])
    ]));
  }

  function pong($json_msg) {
    if ($json_msg->event === "pusher:pong") {
      $this->alive = true;
    }
  }

  function onConnectionKeepAlive($json_msg, $conn) {
    if ($json_msg->event === "pusher:connection_established") {
      echo $this->addColor("Connected", 'green') . PHP_EOL;
      $this->loop->addPeriodicTimer(14, function () use ($conn) {
        if (!$this->alive) {
          exit($this->addColor("Disconnected", 'red') . PHP_EOL);
        } else {
          $this->alive = false;
        }
        $this->ping($conn);
      });
    }
  }

  function sendStdOut($conn, $job_id, $data) {
    if (!empty($data)) { 
      $conn->send(json_encode([
        "event" => "client-stdout",
        "channel" => $this->jobs_channel . $job_id,
        "data" => json_encode(["stdout" => $data])
      ]));
    }
  }

  function broadcastOnChannel($conn, $channel, $event, $data) {
    $conn->send(json_encode([
      "event" => $event,
      "channel" => $channel,
      "data" => json_encode($data)
    ]));
  }
}

?>

