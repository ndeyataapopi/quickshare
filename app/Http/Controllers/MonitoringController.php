<?php

namespace App\Http\Controllers;

use App\Models\LoginAttempt;
use App\Models\UserSession;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class MonitoringController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'timestamp' => now()->toIso8601String(),
            'system' => $this->getSystemMetrics(),
            'database' => $this->getDatabaseMetrics(),
            'cache' => $this->getCacheMetrics(),
            'queue' => $this->getQueueMetrics(),
            'security' => $this->getSecurityMetrics(),
            'storage' => $this->getStorageMetrics(),
        ]);
    }

    protected function getSystemMetrics(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'server_time' => now()->toIso8601String(),
            'timezone' => config('app.timezone'),
            'environment' => app()->environment(),
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
            'memory_peak' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
        ];
    }

    protected function getDatabaseMetrics(): array
    {
        try {
            $connection = DB::connection();
            $pdo = $connection->getPdo();

            return [
                'status' => 'connected',
                'driver' => $connection->getDriverName(),
                'database' => $connection->getDatabaseName(),
                'version' => $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION),
                'connections' => $pdo->getAttribute(\PDO::ATTR_SERVER_INFO) ?? 'N/A',
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function getCacheMetrics(): array
    {
        try {
            Redis::ping();
            $info = Redis::info('stats');

            return [
                'status' => 'connected',
                'driver' => config('cache.default'),
                'keyspace_hits' => $info['keyspace_hits'] ?? 0,
                'keyspace_misses' => $info['keyspace_misses'] ?? 0,
                'total_commands' => $info['total_commands_processed'] ?? 0,
                'used_memory' => $info['used_memory_human'] ?? 'N/A',
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function getQueueMetrics(): array
    {
        try {
            $queues = ['default', 'high', 'emails', 'notifications'];
            $metrics = [];

            foreach ($queues as $queue) {
                $metrics[$queue] = [
                    'pending' => Queue::size($queue),
                    'failed' => DB::table('failed_jobs')->where('queue', $queue)->count(),
                ];
            }

            return [
                'status' => 'connected',
                'driver' => config('queue.default'),
                'queues' => $metrics,
                'total_failed' => DB::table('failed_jobs')->count(),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function getSecurityMetrics(): array
    {
        $now = now();
        $last24h = $now->subHours(24);

        return [
            'active_sessions' => UserSession::where('last_activity_at', '>=', $last24h)->count(),
            'failed_logins_24h' => LoginAttempt::where('created_at', '>=', $last24h)->where('success', false)->count(),
            'successful_logins_24h' => LoginAttempt::where('created_at', '>=', $last24h)->where('success', true)->count(),
            'audit_logs_24h' => AuditLog::where('created_at', '>=', $last24h)->count(),
        ];
    }

    protected function getStorageMetrics(): array
    {
        try {
            return [
                'status' => 'connected',
                'default_disk' => config('filesystems.default'),
                'disks' => array_map(function ($disk) {
                    return [
                        'driver' => config("filesystems.disks.{$disk}.driver"),
                        'root' => config("filesystems.disks.{$disk}.root"),
                    ];
                }, array_keys(config('filesystems.disks'))),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }
}
