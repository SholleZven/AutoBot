<?php

require_once 'vendor/autoload.php';

use App\Services\TelegramBotService;

// Простой тест для проверки работы сервиса
try {
    echo "Testing TelegramBotService...\n";

    $botService = new TelegramBotService();
    echo "✅ TelegramBotService created successfully\n";

    // Проверяем конфигурацию
    $token = config('services.telegram.bot_token');
    if (empty($token)) {
        echo "❌ TELEGRAM_BOT_TOKEN not configured\n";
        echo "Please set TELEGRAM_BOT_TOKEN in your .env file\n";
        exit(1);
    }

    echo "✅ Telegram bot token configured\n";
    echo "Bot is ready to start!\n";
    echo "Run: php artisan bot:start\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

