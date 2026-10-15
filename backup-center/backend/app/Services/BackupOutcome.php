<?php

namespace App\Services;

use App\Models\BackupLog;
use App\Models\BackupUpload;
use Illuminate\Support\Facades\DB;

/** Completion means every target captured at report time acknowledged the upload. */
class BackupOutcome
{
    public function refresh(BackupLog $record): void
    {
        DB::transaction(function () use ($record) {
            $log = BackupLog::whereKey($record->id)->lockForUpdate()->firstOrFail();
            // Historical rows have no target snapshot; don't invent completion evidence.
            if ($log->expected_storage_ids === null) return;
            $expected = $log->expected_storage_ids;
            $uploads = $log->uploads()->whereIn('storage_id', $expected)->get()->keyBy('storage_id');
            $failed = empty($expected) || $uploads->contains(fn ($u) => $u->status === BackupUpload::STATUS_FAILED);
            $complete = !empty($expected) && collect($expected)->every(
                fn ($id) => isset($uploads[$id]) && $uploads[$id]->status === BackupUpload::STATUS_UPLOADED
            );
            $status = $failed ? BackupLog::STATUS_FAILED : ($complete ? BackupLog::STATUS_SUCCESS : BackupLog::STATUS_RUNNING);
            $changed = $log->status !== $status;
            $log->status = $status;
            $log->upload_status = $failed ? BackupLog::UPLOAD_FAILED : ($complete ? BackupLog::UPLOAD_UPLOADED : BackupLog::UPLOAD_UPLOADING);
            $log->uploaded_at = $complete ? now() : null;
            $log->save();
            $website = $log->website;
            if ($website && $website->last_backup_filename === $log->filename) {
                $website->update(['last_backup_status' => $status]);
            }
            if ($changed && $status !== BackupLog::STATUS_RUNNING) {
                app(NotificationDispatcher::class)->notifyBackupCompleted($log);
            }
        });
    }
}
