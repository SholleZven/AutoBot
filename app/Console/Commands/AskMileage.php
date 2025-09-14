<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\TelegramBotService;

class AskMileage extends Command
{
    protected $signature = 'bot:ask-mileage';
    protected $description = 'Запросить пробег у пользователей';

    public function handle()
    {
        $this->info("📊 Requesting mileage from users...");

        $users = User::with('vehicles')->get();
        $totalRequests = 0;

        foreach ($users as $user) {
            if ($user->vehicles->isEmpty()) {
                continue;
            }

            $message = "📊 Пожалуйста, обновите пробег ваших автомобилей:\n\n";

            foreach ($user->vehicles as $vehicle) {
                $message .= "🚗 {$vehicle->name} (ID: {$vehicle->id})\n";
                $message .= "Текущий пробег: {$vehicle->initial_mileage} км\n";
                $message .= "Команда: /mileage {$vehicle->id} <новый пробег>\n\n";
            }

            try {
                $botService = new TelegramBotService();
                $botService->sendMessage($user->telegram_user_id, $message);
                $totalRequests++;
            } catch (\Exception $e) {
                $this->error("Failed to send message to user {$user->telegram_user_id}: " . $e->getMessage());
            }
        }

        $this->info("✅ Sent mileage requests to {$totalRequests} users");

        return 0;
    }
}

