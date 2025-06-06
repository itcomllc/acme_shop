<?php

namespace App\Logging;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Monolog\Level;
use Illuminate\Support\Facades\DB;

/**
 * Database Log Handler - 無限ループ対策版
 */
class DatabaseLogHandler extends AbstractProcessingHandler
{
    private bool $isLogging = false;  // ログ中フラグ
    private array $buffer = [];      // バッファリング用
    private int $bufferSize = 100;   // バッファサイズ

    public function __construct($level = Level::Debug, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
    }

    /**
     * レコードを書き込む
     */
    protected function write(LogRecord $record): void
    {
        // 無限ループ防止
        if ($this->isLogging) {
            return;
        }

        try {
            $this->isLogging = true;
            
            // データベース関連のエラーログは除外
            if ($this->isDatabaseRelatedLog($record)) {
                return;
            }

            // バッファリングして書き込み
            $this->buffer[] = $this->formatRecord($record);
            
            if (count($this->buffer) >= $this->bufferSize) {
                $this->flushBuffer();
            }
            
        } catch (\Throwable $e) {
            // エラーが発生してもログを出力しない（無限ループ防止）
            // 必要に応じてファイルに出力
            error_log("DatabaseLogHandler error: " . $e->getMessage());
        } finally {
            $this->isLogging = false;
        }
    }

    /**
     * データベース関連のログかチェック
     */
    private function isDatabaseRelatedLog(LogRecord $record): bool
    {
        $message = $record->message;
        $context = $record->context;
        
        // データベース関連のキーワードをチェック
        $dbKeywords = [
            'Database Query',
            'SQL',
            'SQLSTATE',
            'Connection failed',
            'system_logs',
            'DatabaseLogHandler',
            'database connection',
            'PDO',
            'mysql',
            'sqlite'
        ];
        
        foreach ($dbKeywords as $keyword) {
            if (stripos($message, $keyword) !== false) {
                return true;
            }
        }
        
        // コンテキストにSQLが含まれているかチェック
        if (isset($context['sql']) || isset($context['query'])) {
            return true;
        }
        
        return false;
    }

    /**
     * レコードをフォーマット
     */
    private function formatRecord(LogRecord $record): array
    {
        return [
            'level' => $record->level->name,
            'message' => $record->message,
            'context' => json_encode($record->context),
            'extra' => json_encode($record->extra),
            'channel' => $record->channel ?? 'default',
            'created_at' => $record->datetime->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * バッファをフラッシュ
     */
    private function flushBuffer(): void
    {
        if (empty($this->buffer)) {
            return;
        }

        try {
            // データベース接続チェック
            if (!$this->isDatabaseAvailable()) {
                $this->buffer = []; // バッファをクリア
                return;
            }

            // バッチ挿入でパフォーマンス向上
            DB::table('system_logs')->insert($this->buffer);
            $this->buffer = [];
            
        } catch (\Throwable $e) {
            // エラーが発生した場合はバッファをクリア
            $this->buffer = [];
            error_log("Failed to flush log buffer: " . $e->getMessage());
        }
    }

    /**
     * データベースが利用可能かチェック
     */
    private function isDatabaseAvailable(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * デストラクタでバッファをフラッシュ
     */
    public function __destruct()
    {
        if (!$this->isLogging && !empty($this->buffer)) {
            $this->flushBuffer();
        }
    }

    /**
     * ハンドラを手動でフラッシュ
     */
    public function close(): void
    {
        if (!$this->isLogging) {
            $this->flushBuffer();
        }
        parent::close();
    }
}