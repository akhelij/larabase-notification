<?php

namespace Akhelij\LarabaseNotification\Tests\Unit;

use Akhelij\LarabaseNotification\LarabaseSendReport;
use PHPUnit\Framework\TestCase;

class LarabaseSendReportTest extends TestCase
{
    public function test_empty_report(): void
    {
        $report = new LarabaseSendReport();

        $this->assertSame([], $report->successes());
        $this->assertSame([], $report->failures());
        $this->assertFalse($report->hasFailures());
        $this->assertSame(0, $report->successCount());
        $this->assertSame(0, $report->failureCount());
        $this->assertSame([], $report->failedTokens());
        $this->assertSame([], $report->unregisteredTokens());
    }

    public function test_add_success(): void
    {
        $report = new LarabaseSendReport();
        $report->addSuccess('token1', ['name' => 'projects/test/messages/123']);

        $this->assertSame(1, $report->successCount());
        $this->assertFalse($report->hasFailures());
        $this->assertArrayHasKey('token1', $report->successes());
    }

    public function test_add_failure(): void
    {
        $report = new LarabaseSendReport();
        $report->addFailure('token1', [
            'error' => ['message' => 'Invalid token', 'details' => [['errorCode' => 'INVALID_ARGUMENT']]],
        ]);

        $this->assertSame(1, $report->failureCount());
        $this->assertTrue($report->hasFailures());
        $this->assertSame(['token1'], $report->failedTokens());
    }

    public function test_unregistered_tokens(): void
    {
        $report = new LarabaseSendReport();

        $report->addFailure('token1', [
            'error' => ['message' => 'Unregistered', 'details' => [['errorCode' => 'UNREGISTERED']]],
        ]);
        $report->addFailure('token2', [
            'error' => ['message' => 'Invalid', 'details' => [['errorCode' => 'INVALID_ARGUMENT']]],
        ]);
        $report->addFailure('token3', [
            'error' => ['message' => 'Unregistered', 'details' => [['errorCode' => 'UNREGISTERED']]],
        ]);

        $this->assertSame(['token1', 'token3'], $report->unregisteredTokens());
        $this->assertSame(['token1', 'token2', 'token3'], $report->failedTokens());
    }

    public function test_mixed_results(): void
    {
        $report = new LarabaseSendReport();

        $report->addSuccess('token1', ['name' => 'projects/test/messages/123']);
        $report->addSuccess('token2', ['name' => 'projects/test/messages/456']);
        $report->addFailure('token3', [
            'error' => ['message' => 'Unregistered', 'details' => [['errorCode' => 'UNREGISTERED']]],
        ]);

        $this->assertSame(2, $report->successCount());
        $this->assertSame(1, $report->failureCount());
        $this->assertTrue($report->hasFailures());
        $this->assertSame(['token3'], $report->unregisteredTokens());
    }

    public function test_unregistered_tokens_with_missing_details(): void
    {
        $report = new LarabaseSendReport();
        $report->addFailure('token1', ['error' => ['message' => 'Some error']]);

        $this->assertSame([], $report->unregisteredTokens());
        $this->assertSame(['token1'], $report->failedTokens());
    }
}
