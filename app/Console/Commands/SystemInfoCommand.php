<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

class SystemInfoCommand extends Command
{
    protected $signature = 'system:info';
    protected $description = 'Display environment and queue diagnostic information.';

    public function handle(): int
    {
        $this->info('System Environment');
        $this->info('==================');
        $this->table(['Item', 'Value'], [
            ['OS', php_uname('s') . ' ' . php_uname('r')],
            ['Hostname', php_uname('n')],
            ['PHP Version', PHP_VERSION],
            ['PHP User', get_current_user() . ' (uid: ' . getmyuid() . ')'],
            ['Web User', function_exists('posix_getpwuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? 'unknown') : 'unknown'],
            ['Server Software', $_SERVER['SERVER_SOFTWARE'] ?? 'cli'],
            ['Laravel Env', config('app.env')],
            ['APP_URL', config('app.url')],
        ]);

        $this->newLine();
        $this->info('Queue Configuration');
        $this->info('===================');
        $connection = config('queue.default');
        $this->table(['Item', 'Value'], [
            ['QUEUE_CONNECTION', $connection],
            ['Default Queue', config("queue.connections.{$connection}.queue", 'default')],
            ['Notifications Queue', config('notifications.queue.default', 'notifications')],
            ['Failed Jobs Table', config('queue.failed.table', 'failed_jobs')],
            ['Failed Jobs Count', $this->failedJobsCount()],
            ['Redis Available', $this->redisAvailable()],
        ]);

        $this->newLine();
        $this->info('Worker / Service Detection');
        $this->info('==========================');
        $this->table(['Service', 'Available / Running'], [
            ['supervisorctl', $this->commandOutput('supervisorctl version 2>&1') ? 'available' : 'not available'],
            ['systemctl', $this->commandOutput('systemctl --version 2>&1') ? 'available' : 'not available'],
            ['queue:work process', $this->workerRunning() ? 'yes' : 'no'],
        ]);

        $this->newLine();
        $this->info('Pending Jobs');
        $this->info('============');
        $queues = ['default', 'notifications'];
        foreach ($queues as $queue) {
            try {
                $size = Queue::size($queue);
            } catch (\Throwable $e) {
                $size = 'error: ' . $e->getMessage();
            }
            $this->line("  {$queue}: {$size}");
        }

        return Command::SUCCESS;
    }

    protected function failedJobsCount(): int|string
    {
        try {
            return DB::table(config('queue.failed.table', 'failed_jobs'))->count();
        } catch (\Throwable $e) {
            return 'error: ' . $e->getMessage();
        }
    }

    protected function redisAvailable(): string
    {
        try {
            Redis::ping();
            return 'yes';
        } catch (\Throwable $e) {
            return 'no (' . $e->getMessage() . ')';
        }
    }

    protected function workerRunning(): bool
    {
        $output = $this->commandOutput("pgrep -f 'queue:work.*queue=notifications.*default'");
        return ! empty($output);
    }

    protected function commandOutput(string $command): ?string
    {
        if (! function_exists('shell_exec')) {
            return null;
        }

        $output = @shell_exec($command);
        return is_string($output) && trim($output) !== '' ? trim($output) : null;
    }
}
