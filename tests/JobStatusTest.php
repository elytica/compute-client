<?php
use PHPUnit\Framework\TestCase;
use Elytica\ComputeClient\JobStatus;
use Elytica\ComputeClient\JobFailureReason;

class JobStatusTest extends TestCase
{
    public function testStatusConstantValues(): void
    {
        $this->assertSame(0, JobStatus::RESET);
        $this->assertSame(1, JobStatus::QUEUED);
        $this->assertSame(2, JobStatus::ACCEPT);
        $this->assertSame(3, JobStatus::PROCESS);
        $this->assertSame(4, JobStatus::COMPLETED);
        $this->assertSame(5, JobStatus::HALTED);
    }

    public function testStatusConstantsAreUnique(): void
    {
        $values = [
            JobStatus::RESET,
            JobStatus::QUEUED,
            JobStatus::ACCEPT,
            JobStatus::PROCESS,
            JobStatus::COMPLETED,
            JobStatus::HALTED,
        ];
        $this->assertCount(count($values), array_unique($values));
    }

    public function testFailureReasonConstantValues(): void
    {
        $this->assertSame('out_of_memory',    JobFailureReason::OUT_OF_MEMORY);
        $this->assertSame('server_busy',      JobFailureReason::SERVER_BUSY);
        $this->assertSame('download_failed',  JobFailureReason::DOWNLOAD_FAILED);
        $this->assertSame('process_crashed',  JobFailureReason::PROCESS_CRASHED);
        $this->assertSame('websocket_broken', JobFailureReason::WEBSOCKET_BROKEN);
        $this->assertSame('timeout',          JobFailureReason::TIMEOUT);
        $this->assertSame('invalid_job',      JobFailureReason::INVALID_JOB);
    }

    public function testFailureReasonConstantsAreUnique(): void
    {
        $values = [
            JobFailureReason::OUT_OF_MEMORY,
            JobFailureReason::SERVER_BUSY,
            JobFailureReason::DOWNLOAD_FAILED,
            JobFailureReason::PROCESS_CRASHED,
            JobFailureReason::WEBSOCKET_BROKEN,
            JobFailureReason::TIMEOUT,
            JobFailureReason::INVALID_JOB,
        ];
        $this->assertCount(count($values), array_unique($values));
    }
}
