<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Telegram\Bot\Laravel\Facades\Telegram;

class AskMileage extends Command
{
    protected $signature = 'bot:askMileage';
    protected $description = 'Запросить пробег у пользователей';

    public function handle()
    {
        $users = User::with('vehicles')->get();
        foreach ($users as $user) {
            foreach ($user->vehicles as $vehicle) {
                Telegram::sendMessage([
                    'chat_id' => $user->telegram_user_id,
                    'text' => "Введите текущий пробег для {$vehicle->name}: /mileage {$vehicle->id} <пробег>"
                ]);
            }
        }
    }
}

