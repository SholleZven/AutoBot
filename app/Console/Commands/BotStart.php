<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class BotStart extends Command
{
    protected $signature = 'bot:start {--daemon : Run in background}';
    protected $description = 'Start Telegram bot (with optional daemon mode)';

    public function handle()
    {
        $isDaemon = $this->option('daemon');

        if ($isDaemon) {
            $this->info("🚀 Starting Telegram bot in daemon mode...");

            // Проверяем, не запущен ли уже бот
            $pidFile = \storage_path('app/bot.pid');
            if (file_exists($pidFile)) {
                $pid = file_get_contents($pidFile);
                if (\Process::run("ps -p {$pid}")->successful()) {
                    $this->error("Bot is already running with PID: {$pid}");
                    return 1;
                } else {
                    unlink($pidFile);
                }
            }

            // Запускаем бота в фоне
            $command = "php " . \base_path('artisan') . " bot:polling > /dev/null 2>&1 & echo $! > {$pidFile}";

            if (PHP_OS_FAMILY === 'Windows') {
                $command = "start /B php " . \base_path('artisan') . " bot:polling > nul 2>&1";
            }

            \Process::run($command);

            $this->info("✅ Bot started in daemon mode");
            $this->info("PID file: {$pidFile}");
            $this->info("Use 'php artisan bot:stop' to stop the bot");

        } else {
            $this->info("🚀 Starting Telegram bot in foreground mode...");
            $this->info("Press Ctrl+C to stop the bot");

            $this->call('bot:polling');
        }

        return 0;
    }
}
