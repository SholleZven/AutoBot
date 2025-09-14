<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class BotStatus extends Command
{
    protected $signature = 'bot:status';
    protected $description = 'Check Telegram bot status';

    public function handle()
    {
        $pidFile = \storage_path('app/bot.pid');

        if (!file_exists($pidFile)) {
            $this->error("❌ Bot is not running (PID file not found)");
            return 1;
        }

        $pid = trim(file_get_contents($pidFile));

        if (empty($pid)) {
            $this->error("❌ Invalid PID file");
            return 1;
        }

        // Проверяем, запущен ли процесс
        if (PHP_OS_FAMILY === 'Windows') {
            $result = \Process::run("tasklist /FI \"PID eq {$pid}\"");
            if (strpos($result->output(), $pid) === false) {
                $this->error("❌ Bot process with PID {$pid} not found");
                unlink($pidFile);
                return 1;
            }
        } else {
            $result = \Process::run("ps -p {$pid}");
            if (!$result->successful()) {
                $this->error("❌ Bot process with PID {$pid} not found");
                unlink($pidFile);
                return 1;
            }
        }

        $this->info("✅ Bot is running with PID: {$pid}");
        $this->info("PID file: {$pidFile}");

        return 0;
    }
}
