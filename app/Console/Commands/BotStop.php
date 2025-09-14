<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class BotStop extends Command
{
    protected $signature = 'bot:stop';
    protected $description = 'Stop Telegram bot daemon';

    public function handle()
    {
        $pidFile = \storage_path('app/bot.pid');

        if (!file_exists($pidFile)) {
            $this->error("Bot is not running (PID file not found)");
            return 1;
        }

        $pid = trim(file_get_contents($pidFile));

        if (empty($pid)) {
            $this->error("Invalid PID file");
            unlink($pidFile);
            return 1;
        }

        // Проверяем, запущен ли процесс
        if (PHP_OS_FAMILY === 'Windows') {
            $result = \Process::run("tasklist /FI \"PID eq {$pid}\"");
            if (strpos($result->output(), $pid) === false) {
                $this->error("Bot process with PID {$pid} not found");
                unlink($pidFile);
                return 1;
            }

            // Останавливаем процесс
            \Process::run("taskkill /PID {$pid} /F");
        } else {
            $result = \Process::run("ps -p {$pid}");
            if (!$result->successful()) {
                $this->error("Bot process with PID {$pid} not found");
                unlink($pidFile);
                return 1;
            }

            // Останавливаем процесс
            \Process::run("kill {$pid}");
        }

        unlink($pidFile);
        $this->info("✅ Bot stopped successfully");

        return 0;
    }
}
