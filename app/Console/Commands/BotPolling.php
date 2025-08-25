<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Telegram\Bot\Api;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Task;

class BotPolling extends Command
{
    protected $signature = 'bot:polling';
    protected $description = 'Long polling Telegram Bot API';

    public function handle()
    {
        $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
        $offset = 0;

        $this->info("Bot polling started...");

        while (true) {
            $updates = $telegram->getUpdates([
                'offset' => $offset,
                'timeout' => 30
            ]);

            foreach ($updates as $update) {
                $message = $update->getMessage();
                if (!$message) continue;

                $chatId = $message->getChat()->getId();
                $text   = $message->getText();

                // создаём пользователя
                $user = User::firstOrCreate(['telegram_user_id' => $chatId]);

                // простейший роутинг
                if ($text === '/start') {
                    $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => "Привет! Используй /addcar <название> <пробег>"
                    ]);
                }
                elseif (str_starts_with($text, '/addcar')) {
                    $parts = explode(' ', $text, 3);
                    if (count($parts) < 3) {
                        $telegram->sendMessage([
                            'chat_id'=>$chatId,
                            'text'=>"Формат: /addcar <Название> <Пробег>"
                        ]);
                    } else {
                        Vehicle::create([
                            'user_id' => $user->id,
                            'name' => $parts[1],
                            'initial_mileage' => (int)$parts[2]
                        ]);
                        $telegram->sendMessage([
                            'chat_id'=>$chatId,
                            'text'=>"Авто {$parts[1]} добавлено!"
                        ]);
                    }
                }

                // обновляем offset, чтобы не получать одно и то же
                $offset = $update->getUpdateId() + 1;
            }

            sleep(1); // чтобы не перегружать API
        }
    }
}
