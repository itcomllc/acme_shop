<?php

namespace App\Logging;

use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Illuminate\Support\Facades\Auth;

/**
 * Database Logger Factory - 無限ループ対策版
 */
class DatabaseLoggerFactory
{
    /**
     * Create a custom Monolog instance for database logging.
     */
    public function __invoke(array $config): Logger
    {
        $logger = new Logger('database');
        
        // DatabaseLogHandlerを追加
        $handler = new DatabaseLogHandler(
            $config['level'] ?? 'debug',
            $config['bubble'] ?? true
        );
        
        $logger->pushHandler($handler);
        
        // プロセッサーを追加（ただし、データベース関連は除外）
        $logger->pushProcessor(new PsrLogMessageProcessor());
        
        // データベースログ用の特別なプロセッサー
        $logger->pushProcessor(function ($record) {
            // データベース関連のログは処理しない
            if ($this->isDatabaseRelatedRecord($record)) {
                return false; // レコードを破棄
            }
            
            // ユーザー情報を追加（認証済みの場合）
            if (Auth::check()) {
                $record['extra']['user_id'] = Auth::id();
                $record['extra']['user_email'] = Auth::user()->email;
            }
            
            // リクエスト情報を追加
            if (request()) {
                $record['extra']['ip'] = request()->ip();
                $record['extra']['user_agent'] = request()->userAgent();
                $record['extra']['url'] = request()->fullUrl();
                $record['extra']['method'] = request()->method();
            }
            
            return $record;
        });
        
        return $logger;
    }
    
    /**
     * データベース関連のレコードかチェック
     */
    private function isDatabaseRelatedRecord($record): bool
    {
        $message = $record['message'] ?? '';
        $context = $record['context'] ?? [];
        
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
}