<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\AdminControllerBase;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{DB, Log, File, Cache, Auth};
use Illuminate\Routing\Controllers\{HasMiddleware, Middleware};

/**
 * Admin System Logs Controller
 * システムログの表示・管理
 */
class AdminSystemLogsController extends AdminControllerBase implements HasMiddleware
{
    private const ITEMS_PER_PAGE = 50;
    private const MAX_LOG_LINES = 10000;
    
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            ...parent::middleware(),
            new Middleware('permission:system.logs.view'),
        ];
    }

    /**
     * Display system logs page
     */
    public function index(Request $request)
    {
        if ($request->expectsJson()) {
            return $this->getLogsData($request);
        }

        $logFiles = $this->getAvailableLogFiles();
        $logSources = $this->getLogSources();
        
        return view('admin.system.logs', compact('logFiles', 'logSources'));
    }

    /**
     * Get logs data for AJAX requests
     */
    public function getLogsData(Request $request): JsonResponse
    {
        try {
            $source = $request->get('source', 'database');
            $level = $request->get('level');
            $search = $request->get('search');
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');
            $page = (int) $request->get('page', 1);
            $perPage = min((int) $request->get('per_page', self::ITEMS_PER_PAGE), 100);

            switch ($source) {
                case 'database':
                    $logsData = $this->getDatabaseLogs($level, $search, $dateFrom, $dateTo, $page, $perPage);
                    break;
                case 'file':
                    $logFile = $request->get('file', 'laravel.log');
                    $logsData = $this->getFileLogs($logFile, $level, $search, $dateFrom, $dateTo, $page, $perPage);
                    break;
                default:
                    throw new \InvalidArgumentException('Invalid log source');
            }

            return response()->json([
                'success' => true,
                'data' => $logsData
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load system logs', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load logs',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get database logs
     */
    private function getDatabaseLogs(
        ?string $level, 
        ?string $search, 
        ?string $dateFrom, 
        ?string $dateTo, 
        int $page, 
        int $perPage
    ): array {
        $query = DB::table('system_logs')->orderBy('created_at', 'desc');

        // Apply filters
        if ($level) {
            $query->where('level', $level);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('message', 'like', "%{$search}%")
                  ->orWhere('context', 'like', "%{$search}%")
                  ->orWhere('channel', 'like', "%{$search}%");
            });
        }

        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom . ' 00:00:00');
        }

        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo . ' 23:59:59');
        }

        // Get total count for pagination
        $total = $query->count();

        // Apply pagination
        $offset = ($page - 1) * $perPage;
        $logs = $query->skip($offset)->take($perPage)->get();

        // Transform logs
        $formattedLogs = $logs->map(function ($log) {
            return [
                'id' => $log->id,
                'level' => $log->level,
                'channel' => $log->channel,
                'message' => $log->message,
                'context' => $log->context ? json_decode($log->context, true) : null,
                'extra' => $log->extra ? json_decode($log->extra, true) : null,
                'created_at' => $log->created_at,
                'formatted_time' => \Carbon\Carbon::parse($log->created_at)->format('Y-m-d H:i:s'),
                'level_class' => $this->getLevelClass($log->level)
            ];
        });

        return [
            'logs' => $formattedLogs,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total)
            ]
        ];
    }

    /**
     * Get file logs
     */
    private function getFileLogs(
        string $logFile, 
        ?string $level, 
        ?string $search, 
        ?string $dateFrom, 
        ?string $dateTo, 
        int $page, 
        int $perPage
    ): array {
        $logPath = storage_path("logs/{$logFile}");
        
        if (!File::exists($logPath)) {
            throw new \InvalidArgumentException("Log file not found: {$logFile}");
        }

        // Cache key for parsed logs
        $cacheKey = "log_file_parsed_{$logFile}_" . filemtime($logPath);
        
        $parsedLogs = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($logPath) {
            return $this->parseLogFile($logPath);
        });

        // Apply filters
        $filteredLogs = collect($parsedLogs)->filter(function ($log) use ($level, $search, $dateFrom, $dateTo) {
            // Level filter
            if ($level && strtolower($log['level']) !== strtolower($level)) {
                return false;
            }

            // Search filter
            if ($search && !str_contains(strtolower($log['message']), strtolower($search))) {
                return false;
            }

            // Date filters
            if ($dateFrom && $log['date'] < $dateFrom) {
                return false;
            }

            if ($dateTo && $log['date'] > $dateTo . ' 23:59:59') {
                return false;
            }

            return true;
        });

        // Sort by date descending
        $filteredLogs = $filteredLogs->sortByDesc('date')->values();

        $total = $filteredLogs->count();
        $offset = ($page - 1) * $perPage;
        $paginatedLogs = $filteredLogs->slice($offset, $perPage)->values();

        return [
            'logs' => $paginatedLogs->map(function ($log) {
                $log['level_class'] = $this->getLevelClass($log['level']);
                return $log;
            }),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total)
            ]
        ];
    }

    /**
     * Parse log file
     */
    private function parseLogFile(string $logPath): array
    {
        $logs = [];
        $handle = fopen($logPath, 'r');
        
        if (!$handle) {
            return [];
        }

        $lineCount = 0;
        $currentLog = null;

        while (($line = fgets($handle)) !== false && $lineCount < self::MAX_LOG_LINES) {
            $line = trim($line);
            
            if (empty($line)) {
                continue;
            }

            // Check if line starts with timestamp (new log entry)
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
                // Save previous log if exists
                if ($currentLog) {
                    $logs[] = $currentLog;
                }

                // Parse new log entry
                $currentLog = $this->parseLogLine($line, $matches[1]);
                $lineCount++;
            } else {
                // Continuation of previous log
                if ($currentLog) {
                    $currentLog['message'] .= "\n" . $line;
                }
            }
        }

        // Add last log
        if ($currentLog) {
            $logs[] = $currentLog;
        }

        fclose($handle);
        
        return array_reverse($logs); // Most recent first
    }

    /**
     * Parse single log line
     */
    private function parseLogLine(string $line, string $timestamp): array
    {
        // Pattern: [timestamp] environment.LEVEL: message {"context":"data"}
        $pattern = '/^\[([^\]]+)\]\s+(\w+)\.(\w+):\s+(.+?)(\s+\{.*\})?$/';
        
        if (preg_match($pattern, $line, $matches)) {
            $context = null;
            if (!empty($matches[5])) {
                $contextStr = trim($matches[5]);
                $context = json_decode($contextStr, true);
            }

            return [
                'date' => $timestamp,
                'environment' => $matches[2],
                'level' => strtoupper($matches[3]),
                'message' => $matches[4],
                'context' => $context,
                'raw_line' => $line,
                'formatted_time' => \Carbon\Carbon::parse($timestamp)->format('Y-m-d H:i:s')
            ];
        }

        // Fallback for non-standard format
        return [
            'date' => $timestamp,
            'environment' => 'unknown',
            'level' => 'INFO',
            'message' => $line,
            'context' => null,
            'raw_line' => $line,
            'formatted_time' => \Carbon\Carbon::parse($timestamp)->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Get available log files
     */
    private function getAvailableLogFiles(): array
    {
        $logDir = storage_path('logs');
        $files = [];

        if (File::isDirectory($logDir)) {
            $fileList = File::files($logDir);
            
            foreach ($fileList as $file) {
                if ($file->getExtension() === 'log') {
                    $fileName = $file->getFilename();
                    $files[] = [
                        'name' => $fileName,
                        'size' => $this->formatBytes($file->getSize()),
                        'modified' => \Carbon\Carbon::createFromTimestamp($file->getMTime())->format('Y-m-d H:i:s'),
                        'path' => $file->getPathname()
                    ];
                }
            }
        }

        // Sort by modification time, newest first
        usort($files, function ($a, $b) {
            return strcmp($b['modified'], $a['modified']);
        });

        return $files;
    }

    /**
     * Get log sources
     */
    private function getLogSources(): array
    {
        return [
            'database' => [
                'name' => 'Database Logs',
                'description' => 'Logs stored in database via custom logging channel',
                'icon' => 'database'
            ],
            'file' => [
                'name' => 'File Logs',
                'description' => 'Traditional log files in storage/logs',
                'icon' => 'document-text'
            ]
        ];
    }

    /**
     * Get log statistics
     */
    public function getStatistics(Request $request): JsonResponse
    {
        try {
            $timeframe = $request->get('timeframe', '24h');
            $since = $this->getTimeframeSince($timeframe);

            $stats = [
                'database' => $this->getDatabaseLogStats($since),
                'files' => $this->getFileLogStats(),
                'levels' => $this->getLogLevelDistribution($since),
                'channels' => $this->getChannelDistribution($since),
                'recent_errors' => $this->getRecentErrors($since)
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get log statistics', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get statistics'
            ], 500);
        }
    }

    /**
     * Clear old logs
     */
    public function clearLogs(Request $request): JsonResponse
    {
        $request->validate([
            'source' => 'required|in:database,file',
            'file' => 'required_if:source,file|string',
            'days' => 'required|integer|min:1|max:365'
        ]);

        try {
            $this->authorize('system.logs.manage');

            $source = $request->source;
            $days = $request->days;
            $cutoffDate = now()->subDays($days);

            $deletedCount = 0;

            if ($source === 'database') {
                $deletedCount = DB::table('system_logs')
                    ->where('created_at', '<', $cutoffDate)
                    ->delete();
            } else {
                $logFile = $request->file;
                $logPath = storage_path("logs/{$logFile}");
                
                if (File::exists($logPath)) {
                    File::delete($logPath);
                    $deletedCount = 1;
                }
            }

            Log::info('System logs cleared', [
                'source' => $source,
                'days' => $days,
                'deleted_count' => $deletedCount,
                'admin_id' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => "Successfully cleared {$deletedCount} log entries",
                'deleted_count' => $deletedCount
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to clear logs', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to clear logs'
            ], 500);
        }
    }

    /**
     * Download log file
     */
    public function downloadLog(Request $request)
    {
        $request->validate([
            'source' => 'required|in:database,file',
            'file' => 'required_if:source,file|string',
            'format' => 'required|in:json,csv,txt'
        ]);

        try {
            $this->authorize('system.logs.view');

            $source = $request->source;
            $format = $request->format;

            if ($source === 'database') {
                return $this->downloadDatabaseLogs($format);
            } else {
                $logFile = $request->file;
                return $this->downloadFileLog($logFile, $format);
            }
        } catch (\Exception $e) {
            Log::error('Failed to download logs', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return back()->with('error', 'Failed to download logs');
        }
    }

    /**
     * Get database log statistics
     */
    private function getDatabaseLogStats(\Carbon\Carbon $since): array
    {
        if (!DB::getSchemaBuilder()->hasTable('system_logs')) {
            return [
                'total' => 0,
                'levels' => [],
                'recent_count' => 0
            ];
        }

        return [
            'total' => DB::table('system_logs')->count(),
            'levels' => DB::table('system_logs')
                ->select('level', DB::raw('count(*) as count'))
                ->groupBy('level')
                ->pluck('count', 'level')
                ->toArray(),
            'recent_count' => DB::table('system_logs')
                ->where('created_at', '>=', $since)
                ->count()
        ];
    }

    /**
     * Get file log statistics
     */
    private function getFileLogStats(): array
    {
        $logDir = storage_path('logs');
        $stats = [
            'total_files' => 0,
            'total_size' => 0,
            'files' => []
        ];

        if (File::isDirectory($logDir)) {
            $files = File::files($logDir);
            
            foreach ($files as $file) {
                if ($file->getExtension() === 'log') {
                    $stats['total_files']++;
                    $stats['total_size'] += $file->getSize();
                    
                    $stats['files'][] = [
                        'name' => $file->getFilename(),
                        'size' => $file->getSize(),
                        'size_formatted' => $this->formatBytes($file->getSize())
                    ];
                }
            }
        }

        $stats['total_size_formatted'] = $this->formatBytes($stats['total_size']);
        
        return $stats;
    }

    /**
     * Get log level distribution
     */
    private function getLogLevelDistribution(\Carbon\Carbon $since): array
    {
        if (!DB::getSchemaBuilder()->hasTable('system_logs')) {
            return [];
        }

        return DB::table('system_logs')
            ->select('level', DB::raw('count(*) as count'))
            ->where('created_at', '>=', $since)
            ->groupBy('level')
            ->orderBy('count', 'desc')
            ->pluck('count', 'level')
            ->toArray();
    }

    /**
     * Get channel distribution
     */
    private function getChannelDistribution(\Carbon\Carbon $since): array
    {
        if (!DB::getSchemaBuilder()->hasTable('system_logs')) {
            return [];
        }

        return DB::table('system_logs')
            ->select('channel', DB::raw('count(*) as count'))
            ->where('created_at', '>=', $since)
            ->groupBy('channel')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->pluck('count', 'channel')
            ->toArray();
    }

    /**
     * Get recent errors
     */
    private function getRecentErrors(\Carbon\Carbon $since): array
    {
        if (!DB::getSchemaBuilder()->hasTable('system_logs')) {
            return [];
        }

        return DB::table('system_logs')
            ->whereIn('level', ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'])
            ->where('created_at', '>=', $since)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get(['level', 'message', 'channel', 'created_at'])
            ->map(function ($log) {
                return [
                    'level' => $log->level,
                    'message' => substr($log->message, 0, 100) . (strlen($log->message) > 100 ? '...' : ''),
                    'channel' => $log->channel,
                    'time' => \Carbon\Carbon::parse($log->created_at)->diffForHumans(),
                    'level_class' => $this->getLevelClass($log->level)
                ];
            })
            ->toArray();
    }

    /**
     * Get CSS class for log level
     */
    private function getLevelClass(string $level): string
    {
        return match (strtoupper($level)) {
            'EMERGENCY', 'ALERT', 'CRITICAL' => 'bg-red-100 text-red-800',
            'ERROR' => 'bg-red-50 text-red-700',
            'WARNING' => 'bg-yellow-100 text-yellow-800',
            'NOTICE' => 'bg-blue-100 text-blue-800',
            'INFO' => 'bg-green-100 text-green-800',
            'DEBUG' => 'bg-gray-100 text-gray-800',
            default => 'bg-gray-100 text-gray-600'
        };
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = floor(log($bytes, 1024));
        
        return round($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
    }

    /**
     * Get timeframe since date
     */
    private function getTimeframeSince(string $timeframe): \Carbon\Carbon
    {
        return match ($timeframe) {
            '1h' => now()->subHour(),
            '24h' => now()->subDay(),
            '7d' => now()->subWeek(),
            '30d' => now()->subMonth(),
            default => now()->subDay()
        };
    }

    /**
     * Download database logs
     */
    private function downloadDatabaseLogs(string $format)
    {
        $logs = DB::table('system_logs')
            ->orderBy('created_at', 'desc')
            ->limit(10000) // Limit for performance
            ->get();

        $filename = 'system_logs_' . now()->format('Y-m-d_H-i-s') . '.' . $format;

        switch ($format) {
            case 'json':
                return response()->json($logs)
                    ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
                    
            case 'csv':
                $csvData = "Level,Channel,Message,Created At\n";
                foreach ($logs as $log) {
                    $csvData .= sprintf(
                        "\"%s\",\"%s\",\"%s\",\"%s\"\n",
                        $log->level,
                        $log->channel,
                        str_replace('"', '""', $log->message),
                        $log->created_at
                    );
                }
                
                return response($csvData)
                    ->header('Content-Type', 'text/csv')
                    ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
                    
            case 'txt':
                $txtData = '';
                foreach ($logs as $log) {
                    $txtData .= sprintf(
                        "[%s] %s.%s: %s\n",
                        $log->created_at,
                        $log->channel,
                        $log->level,
                        $log->message
                    );
                }
                
                return response($txtData)
                    ->header('Content-Type', 'text/plain')
                    ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
        }
    }

    /**
     * Download file log
     */
    private function downloadFileLog(string $logFile, string $format)
    {
        $logPath = storage_path("logs/{$logFile}");
        
        if (!File::exists($logPath)) {
            abort(404, 'Log file not found');
        }

        $filename = pathinfo($logFile, PATHINFO_FILENAME) . '_' . now()->format('Y-m-d_H-i-s') . '.' . $format;

        switch ($format) {
            case 'json':
                $logs = $this->parseLogFile($logPath);
                return response()->json($logs)
                    ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
                    
            case 'csv':
                $logs = $this->parseLogFile($logPath);
                $csvData = "Date,Level,Environment,Message\n";
                foreach ($logs as $log) {
                    $csvData .= sprintf(
                        "\"%s\",\"%s\",\"%s\",\"%s\"\n",
                        $log['date'],
                        $log['level'],
                        $log['environment'],
                        str_replace('"', '""', $log['message'])
                    );
                }
                
                return response($csvData)
                    ->header('Content-Type', 'text/csv')
                    ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
                    
            case 'txt':
                return response()->file($logPath, [
                    'Content-Disposition' => "attachment; filename=\"{$filename}\""
                ]);
        }
    }
}