<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\TelegramBotService;

class BotPolling extends Command
{
    protected $signature = 'bot:polling';
    protected $description = 'Long polling Telegram Bot API';

    public function handle()
    {
        $this->info("🚀 Starting Telegram bot polling...");
        $this->info("Press Ctrl+C to stop the bot");

        try {
            $botService = new TelegramBotService();
            $botService->startPolling();
        } catch (\Exception $e) {
            $this->error("Bot error: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
