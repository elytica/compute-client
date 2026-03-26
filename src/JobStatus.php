<?php
namespace Elytica\ComputeClient;

class JobStatus
{
    const RESET     = 0;
    const QUEUED    = 1;
    const ACCEPT    = 2;
    const PROCESS   = 3;
    const COMPLETED = 4;
    const HALTED    = 5;
}
