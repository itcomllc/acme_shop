<?php

namespace App\Logging;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Illuminate\Support\Facades\{DB, Auth};

class DatabaseLogHandler extends AbstractProcessingHandler
{
    /**
     * Writes the record down to the log of the implementing handler
     */
    protected function write(LogRecord $record): void
    {
        try {
            // データベースへのログ書き込みを行うが、
            // データベース接続エラーの場合は無視する
            if (!$this->isDatabaseAvailable()) {
                return;
            }

            DB::table('system_logs')->insert([
                'level' => strtolower($record->level->name),
                'channel' => $record->channel ?? 'default',
                'message' => $record->message,
                'context' => json_encode($record->context),
                'extra' => json_encode($record->extra),
                'user_id' => Auth::id(),
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'created_at' => $record->datetime,
            ]);
        } catch (\Exception $e) {
            // データベースログの書き込みに失敗した場合は無視
            // 無限ループを避けるため
        }
    }

    /**
     * Check if database is available
     */
    private function isDatabaseAvailable(): bool
    {
        try {
            DB::connection()->getPdo();
            return DB::getSchemaBuilder()->hasTable('system_logs');
        } catch (\Exception $e) {
            return false;
        }
    }
}