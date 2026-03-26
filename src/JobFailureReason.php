<?php
namespace Elytica\ComputeClient;

class JobFailureReason
{
    const OUT_OF_MEMORY    = 'out_of_memory';
    const SERVER_BUSY      = 'server_busy';
    const DOWNLOAD_FAILED  = 'download_failed';
    const PROCESS_CRASHED  = 'process_crashed';
    const WEBSOCKET_BROKEN = 'websocket_broken';
    const TIMEOUT          = 'timeout';
    const INVALID_JOB      = 'invalid_job';
}
