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
  protected $app_key, $app_id;
  protected $token;
  public $loop;
  function __construct($auth_url, $ws_url, $app_key, $app_id, $token, $timeout) {
    $this->loop = Factory::create();
    $this->client = new Client(['verify' => false]);
    $this->react_connector = new ReactConnector($this->loop, ['timeout' => 10]);
    $this->ws_url = $ws_url;
    $this->app_key = $app_key;
    $this->timeout = $timeout;
    $this->auth_url = $auth_url;
    $this->app_id = $app_id;
    $this->token = $token;
    $this->alive = false;
  }

  private function run() {
    $this->loop->run();
  }

  public function stop() {
    $this->loop->stop();
  }
 
  function addInitChannel($channel_name) {
    array_push($this->channels, $channel_name);
  }
  
  public function getChannels() {
   return $this->channels;
  }

  public function isAlive() {
    return $this->alive;
  }

  public function connect(callable $callback, callable $error_callback=null) {
    $connector = new RatchetConnector($this->loop,
      $this->react_connector);
    $connector("$this->ws_url/app/$this->app_key")
      ->then(function($conn) use ($callback, $error_callback) {
          $conn->on('message', function($msg) use ($callback, $conn, $error_callback) {
          $json_msg = json_decode($msg);
          $this->pong($json_msg);
          if ($json_msg->event === "pusher:connection_established") {
            $this->socket_id = json_decode($json_msg->data)->socket_id;
            $this->alive = true;
            foreach ($this->channels as $channel) {
              $this->subscribeToChannel($channel, $conn, $error_callback); 
            }
          }
          $callback(json_decode($msg), $conn);
          });
        })->otherwise(function($e) use ($error_callback) {
          echo "Could not connect: {$e->getMessage()}\n";
          if (($error_callback !== null) && is_callable($error_callback)) {
            $error_callback($e);
          }
        });
    $this->run();
  }

  public function sendChannelAuth($channel_name, callable $error_callback=null) {
    try {
      $response = $this->client->request('POST', $this->auth_url, [
          'headers' => [
              'Accept' => 'application/json',
              'Authorization' => "Bearer $this->token",
              'X-App-ID' => $this->app_id,
              'X-Socket-ID' => $this->socket_id,
          ],
          'json' => [
              'socket_id' => $this->socket_id,
              'channel_name' => $channel_name,
          ],
          'timeout' => $this->timeout,
      ]);
    
      $contents = json_decode($response->getBody()->getContents());
      $contents->channel = $channel_name;
      return $contents;
    } catch (\GuzzleHttp\Exception\RequestException $e) {
       if (($error_callback !== null) && is_callable($error_callback)) {
         $error_callback($e);
       }
    }
    return null;
  }

  function subscribeToChannel($channel_name, $conn, callable $error_callback=null) {
    $auth = $this->sendChannelAuth($channel_name, $error_callback);
    $conn->send(json_encode(
       ["data" => $auth,
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


  function pong($json_msg) {
    if ($json_msg->event === "pusher:pong") {
      $this->alive = true;
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

