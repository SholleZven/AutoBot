<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Telegram\Bot\Api;

class BotReset extends Command
{
    protected $signature = 'bot:reset';
    protected $description = 'Reset Telegram bot webhook and clear conflicts';

    public function handle()
    {
        $this->info("🔄 Resetting Telegram bot...");

        try {
            $telegram = new Api(config('services.telegram.bot_token'));

            // Настраиваем SSL для разработки
            if (config('app.debug', false)) {
                $guzzleClient = new \GuzzleHttp\Client([
                    'verify' => false,
                    'curl' => [
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_SSL_VERIFYHOST => false,
                    ]
                ]);

                $telegram->setHttpClientHandler(
                    new \Telegram\Bot\HttpClients\GuzzleHttpClient($guzzleClient)
                );
            }

            // Удаляем webhook
            $result = $telegram->removeWebhook();
            $this->info("✅ Webhook removed successfully");

            // Получаем информацию о боте
            $botInfo = $telegram->getMe();
            $this->info("✅ Bot info: @{$botInfo->getUsername()}");

            $this->info("✅ Bot reset completed. You can now start the bot with: php artisan bot:start");

        } catch (\Exception $e) {
            $this->error("❌ Error resetting bot: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
