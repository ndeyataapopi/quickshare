<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class SystemStatusController extends Controller
{
    public function index(Request $request)
    {
        $connection = config('queue.default');
        $defaultQueue = config("queue.connections.{$connection}.queue", 'default');
        $notificationQueue = config('notifications.queue.default', 'notifications');

        $data = [
            'queue_connection' => $connection,
            'queue_driver' => config("queue.connections.{$connection}.driver", $connection),
            'queues' => [
                $defaultQueue => $this->queueSize($defaultQueue),
                $notificationQueue => $this->queueSize($notificationQueue),
            ],
            'failed_jobs' => $this->failedJobsCount(),
            'last_failed_job' => $this->lastFailedJob(),
            'worker_running' => $this->workerRunning(),
            'worker_pid' => $this->workerPid(),
            'worker_uptime' => $this->workerUptime(),
            'last_restart' => cache('illuminate:queue:restart'),
        ];

        return view('admin.system-status.index', $data);
    }

    public function restartWorker(Request $request)
    {
        Artisan::call('queue:restart');

        return redirect()->route('admin.system-status.index')
            ->with('success', 'Queue restart signal sent. Running workers will stop after their current job.');
    }

    public function retryFailed(Request $request)
    {
        Artisan::call('queue:retry', ['id' => 'all']);

        return redirect()->route('admin.system-status.index')
            ->with('success', 'Retry command dispatched for all failed jobs.');
    }

    public function clearFailed(Request $request)
    {
        Artisan::call('queue:flush');

        return redirect()->route('admin.system-status.index')
            ->with('success', 'Failed jobs cleared.');
    }

    protected function queueSize(string $queue): int|string
    {
        try {
            return Queue::size($queue);
        } catch (\Throwable $e) {
            return 'error: ' . $e->getMessage();
        }
    }

    protected function failedJobsCount(): int
    {
        try {
            return DB::table(config('queue.failed.table', 'failed_jobs'))->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    protected function lastFailedJob(): ?object
    {
        try {
            return DB::table(config('queue.failed.table', 'failed_jobs'))
                ->latest('failed_at')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function workerRunning(): bool
    {
        return ! empty($this->workerPid());
    }

    protected function workerPid(): ?string
    {
        return $this->shellExec("pgrep -f 'queue:work.*queue=notifications.*default'");
    }

    protected function workerUptime(): ?string
    {
        $pid = $this->workerPid();
        if (empty($pid)) {
            return null;
        }

        $start = $this->shellExec("ps -o lstart= -p {$pid}");
        if (empty($start)) {
            return null;
        }

        try {
            $started = \Carbon\Carbon::parse($start);
            return $started->diffForHumans();
        } catch (\Throwable $e) {
            return $start;
        }
    }

    protected function shellExec(string $command): ?string
    {
        if (! function_exists('shell_exec')) {
            return null;
        }

        $output = @shell_exec($command);
        return is_string($output) && trim($output) !== '' ? trim($output) : null;
    }
}
