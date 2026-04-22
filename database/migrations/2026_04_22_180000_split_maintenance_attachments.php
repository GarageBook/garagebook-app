<?php

use App\Models\MaintenanceLog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_logs', function (Blueprint $table) {
            $table->json('media_attachments')->nullable()->after('attachments');
            $table->json('file_attachments')->nullable()->after('media_attachments');
        });

        DB::table('maintenance_logs')
            ->select(['id', 'attachments'])
            ->orderBy('id')
            ->chunkById(100, function ($logs): void {
                foreach ($logs as $log) {
                    $attachments = MaintenanceLog::normalizeAttachmentPaths($log->attachments);
                    [$media, $files] = MaintenanceLog::splitAttachments($attachments);

                    DB::table('maintenance_logs')
                        ->where('id', $log->id)
                        ->update([
                            'media_attachments' => $media === [] ? null : json_encode($media),
                            'file_attachments' => $files === [] ? null : json_encode($files),
                        ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('maintenance_logs')
            ->select(['id', 'media_attachments', 'file_attachments'])
            ->orderBy('id')
            ->chunkById(100, function ($logs): void {
                foreach ($logs as $log) {
                    $media = MaintenanceLog::normalizeAttachmentPaths($log->media_attachments);
                    $files = MaintenanceLog::normalizeAttachmentPaths($log->file_attachments);
                    $attachments = array_values(array_unique([...$media, ...$files]));

                    DB::table('maintenance_logs')
                        ->where('id', $log->id)
                        ->update([
                            'attachments' => $attachments === [] ? null : json_encode($attachments),
                        ]);
                }
            });

        Schema::table('maintenance_logs', function (Blueprint $table) {
            $table->dropColumn(['media_attachments', 'file_attachments']);
        });
    }
};
