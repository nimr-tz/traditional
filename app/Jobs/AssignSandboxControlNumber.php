<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Billing\GepgService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AssignSandboxControlNumber implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $userId)
    {
    }

    public function handle(GepgService $gepg): void
    {
        $user = User::find($this->userId);

        if (! $user || $user->payment_status !== 'submitted' || $user->control_number) {
            return;
        }

        $user->forceFill(['control_number' => $gepg->generateControlNumber()])->save();

        $gepg->notifyControlNumberIssued($user);
    }
}
