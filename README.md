# compute-client

PHP client for the [elytica](https://elytica.com) Compute API. Ships as a
plain PHP library and as a Laravel package (auto-discovered service provider
and publishable config).

## Requirements

- PHP 8.2+
- Laravel 11, 12, or 13 (Laravel 13 requires PHP 8.3+)
- ext-json, ext-mbstring

## Installation

```bash
composer require elytica/compute-client
```

## Configuration

The client reads two values: an API token and a base URL.

### Laravel

The service provider is registered automatically. Publish the config if you
want to edit it directly:

```bash
php artisan vendor:publish --tag=compute-config
```

Then set the credentials in `.env`:

```dotenv
COMPUTE_TOKEN=your-api-token
COMPUTE_BASE_URL=https://service.elytica.com
```

Resolve the service from the container:

```php
use Elytica\ComputeClient\ComputeService;

$compute = app(ComputeService::class);
echo $compute->getUserName();
```

### Standalone (no Laravel)

Construct the service directly with the token and base URL:

```php
use Elytica\ComputeClient\ComputeService;

$compute = new ComputeService(
    token: 'your-api-token',
    base_url: 'https://service.elytica.com',
);
```

The constructor calls `whoami()` and throws `RuntimeException` if
authentication fails.

## Usage

### User and applications

```php
$compute->getUserId();         // int
$compute->getUserName();       // string
$compute->whoami();            // raw user object
$compute->getApplications();   // available compute applications
```

### Projects

```php
$projects = $compute->getProjects();

$project = $compute->createNewProject(
    project_name:        'My project',
    project_description: 'Optimization run for X',
    application:         $applicationId,
    webhook_url:         'https://example.com/webhooks/compute', // optional
    webhook_secret:      'shared-secret',                         // optional
);

$compute->updateProject(
    project_id:   $project->id,
    description:  'Updated description',
);

$compute->deleteProject($project->id);
```

### Jobs

```php
$job = $compute->createNewJob($project->id, 'Job 1', priority: 100);

// Upload an input file and assign it to the job
$upload = $compute->uploadInputFile(
    filename:   'model.lp',
    contents:   file_get_contents('/path/to/model.lp'),
    project_id: $project->id,
);

$compute->assignFileToJob(
    project_id: $project->id,
    job_id:     $job->id,
    file_id:    $upload[0]->id,
    arg:        1,
);

// Queue the job for execution
$compute->queueJob($job->id);

// Halt a running job
$compute->haltJob($job->id);
```

### Files

```php
$inputs  = $compute->getInputFiles($project->id);
$outputs = $compute->getOutputFiles($job->id, $project->id);

// Stream a file to disk
$compute->downloadFile($project->id, $fileId, '/tmp/result.csv');
```

### Job status constants

`Elytica\ComputeClient\JobStatus` exposes the lifecycle states:

| Constant     | Value | Meaning                       |
| ------------ | ----- | ----------------------------- |
| `RESET`      | 0     | Created, not yet queued       |
| `QUEUED`     | 1     | Waiting to be picked up       |
| `ACCEPT`     | 2     | Accepted by a worker          |
| `PROCESS`    | 3     | Running                       |
| `COMPLETED`  | 4     | Finished successfully         |
| `HALTED`     | 5     | Halted (manually or on error) |

`Elytica\ComputeClient\JobFailureReason` enumerates failure causes
(`OUT_OF_MEMORY`, `SERVER_BUSY`, `DOWNLOAD_FAILED`, `PROCESS_CRASHED`,
`WEBSOCKET_BROKEN`, `TIMEOUT`, `INVALID_JOB`).

## V2 API

The V2 endpoints reduce round-trips for common workflows.

### Consolidated user context

Returns user, applications, projects, and subscription in one call:

```php
$context = $compute->getUserContext();
```

### Batch job status

```php
$statuses = $compute->getJobBatchStatus([101, 102, 103]);
```

### Batch halt

```php
$compute->haltJobs([101, 102, 103]);
```

### Atomic project + jobs

Create a project and its jobs in a single request:

```php
$workflow = $compute->createProjectWorkflow(
    projectName:        'Daily run',
    projectDescription: 'Scheduled batch',
    application:        $applicationId,
    jobs: [
        ['name' => 'Job A', 'priority' => 100],
        ['name' => 'Job B', 'priority' => 50],
    ],
    webhookUrl:    'https://example.com/webhooks/compute',
    webhookSecret: 'shared-secret',
);
```

### Webhook signature verification

Webhooks are signed with HMAC-SHA256 using the project's `webhook_secret`.
Verify the signature on the receiving side before trusting the payload:

```php
use Elytica\ComputeClient\ComputeService;

$raw       = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';

if (! ComputeService::verifyWebhookSignature($raw, $signature, $secret)) {
    http_response_code(401);
    exit;
}

$payload = json_decode($raw);
// ... handle event
```

## Error handling

Most write methods accept an optional `$error_callback` invoked with the
caught exception. The method returns `null` on failure:

```php
$result = $compute->queueJob($job->id, function (\Throwable $e) {
    report($e);
});

if ($result === null) {
    // request failed; callback already received the exception
}
```

If you omit the callback, failures are swallowed silently and the method
returns `null` — pass a callback (or wrap the call yourself) when you need
to react to errors.

## WebSocket client

`Elytica\ComputeClient\WebsocketClient` connects to the elytica realtime
channel (Pusher protocol via Ratchet) and is useful for streaming job
progress events:

```php
use Elytica\ComputeClient\WebsocketClient;

$ws = new WebsocketClient(
    auth_url: 'https://service.elytica.com/broadcasting/auth',
    ws_url:   'wss://ws.elytica.com:443',
    app_key:  $appKey,
    app_id:   $appId,
    token:    $token,
    timeout:  10,
);

$ws->addInitChannel("private-job.$jobId");

$ws->connect(function ($message, $conn) use ($ws) {
    // handle event
    if ($message->event === 'job.completed') {
        $ws->stop();
    }
});
```

## Testing

```bash
composer install
./vendor/bin/phpunit --testsuite unit
```

The test suite is matrixed across PHP 8.2 / 8.3 and Laravel 11 / 12 / 13 in
CI (`.github/workflows/tests.yml`). The PHP 8.2 + Laravel 13 cell is
excluded because Laravel 13 requires PHP 8.3+.

## License

MIT — see `composer.json` for author details.
