<?php

namespace App\Enums;

/**
 * Lifecycle of one SyncRun. Running is the only non-terminal state.
 */
enum SyncRunStatus: string
{
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Partial = 'partial';
    case Failed = 'failed';
    case NeedsPassword = 'needs_password';

    public function label(): string
    {
        return match ($this) {
            self::Running => 'Running',
            self::Succeeded => 'Succeeded',
            self::Partial => 'Partial',
            self::Failed => 'Failed',
            self::NeedsPassword => 'Needs password',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Running => 'info',
            self::Succeeded => 'success',
            self::Partial, self::NeedsPassword => 'warning',
            self::Failed => 'danger',
        };
    }
}
