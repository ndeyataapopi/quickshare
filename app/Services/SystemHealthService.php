<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class SystemHealthService
{
    public function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            return ['status' => 'healthy', 'message' => 'Connected'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => 'Connection failed'];
        }
    }

    public function checkRedis(): array
    {
        try {
            $redis = app('redis');
            $redis->ping();
            return ['status' => 'healthy', 'message' => 'Connected'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => 'Connection failed: ' . $e->getMessage()];
        }
    }

    public function checkQueue(): array
    {
        try {
            $queue = config('queue.default');
            
            // For Redis queue, check actual Redis connection
            if ($queue === 'redis') {
                $redis = app('redis');
                $redis->ping();
                // Check if there are any jobs in the queue to see if it's being processed
                $queueName = config('queue.connections.redis.queue', 'default');
                $queueLength = $redis->llen('queues:' . $queueName);
                return ['status' => 'running', 'message' => "Active ({$queueLength} jobs pending)"];
            }
            
            // For database queue, check database connection
            if ($queue === 'database') {
                DB::connection()->getPdo();
                return ['status' => 'running', 'message' => 'Active (database)'];
            }
            
            return ['status' => 'unknown', 'message' => "Queue driver: {$queue}"];
        } catch (\Exception $e) {
            return ['status' => 'stopped', 'message' => 'Not running: ' . $e->getMessage()];
        }
    }

    public function checkScheduler(): array
    {
        try {
            // Check if scheduler is running by checking a cache key
            $lastRun = Cache::get('scheduler_last_run');
            
            if ($lastRun) {
                $minutesAgo = now()->diffInMinutes($lastRun);
                if ($minutesAgo < 5) {
                    return ['status' => 'running', 'message' => "Active (last run {$minutesAgo} min ago)"];
                }
                return ['status' => 'stopped', 'message' => "Inactive (last run {$minutesAgo} min ago)"];
            }
            
            return ['status' => 'unknown', 'message' => 'No heartbeat found'];
        } catch (\Exception $e) {
            return ['status' => 'stopped', 'message' => 'Not running: ' . $e->getMessage()];
        }
    }

    public function getAllHealth(): array
    {
        return [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'queue' => $this->checkQueue(),
            'scheduler' => $this->checkScheduler(),
        ];
    }
}
