<?php

namespace App\Jobs;

use App\Models\Node;
use App\Models\User;
use App\Services\RSessionManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExecuteRCodeJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public int $tries = 1;

    public function __construct(
        public readonly Node $node,
        public readonly User $user,
        public readonly string $code,
    ) {}

    public function handle(RSessionManager $sessions): void
    {
        $sessions->execute($this->user, $this->code);
    }

    public function failed(\Throwable $exception): void
    {
        // R session errors are handled in RSessionManager
    }
}
