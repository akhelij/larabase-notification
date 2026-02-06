<?php

namespace Akhelij\LarabaseNotification;

class LarabaseSendReport
{
    /** @var array<string, array> */
    protected array $successes = [];

    /** @var array<string, array> */
    protected array $failures = [];

    public function addSuccess(string $token, array $response): void
    {
        $this->successes[$token] = $response;
    }

    public function addFailure(string $token, array $response): void
    {
        $this->failures[$token] = $response;
    }

    /** @return array<string, array> */
    public function successes(): array
    {
        return $this->successes;
    }

    /** @return array<string, array> */
    public function failures(): array
    {
        return $this->failures;
    }

    public function hasFailures(): bool
    {
        return ! empty($this->failures);
    }

    public function successCount(): int
    {
        return count($this->successes);
    }

    public function failureCount(): int
    {
        return count($this->failures);
    }

    /** @return string[] */
    public function failedTokens(): array
    {
        return array_keys($this->failures);
    }

    /** @return string[] */
    public function unregisteredTokens(): array
    {
        $tokens = [];

        foreach ($this->failures as $token => $response) {
            $errorCode = $response['error']['details'][0]['errorCode'] ?? '';

            if ($errorCode === 'UNREGISTERED') {
                $tokens[] = $token;
            }
        }

        return $tokens;
    }
}
