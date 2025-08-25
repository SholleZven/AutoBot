<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramController extends Controller
{
    public function handle(): JsonResponse
    {

        $update = Telegram::commandsHandler(true);
        $message = $update->getMessage();

        if (!$message) {
            return response()->json('ok', 200);
        }

        $chatId = $message->getChat()->getId();
        $text = $message->getText();

        // создаём пользователя при первом обращении
        $user = User::firstOrCreate(['telegram_user_id' => $chatId]);

        switch (true) {
            case $text === '/start':
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text'    => "Привет! Вы можете добавить авто командой /addcar"
                ]);
                break;

            case str_starts_with($text, '/addcar'):

                // пример: /addcar Toyota 120000
                $parts = explode(' ', $text, 3);
                if (count($parts) < 3) {
                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => "Введите данные автомобиля в таком формате (через пробел): /addcar Модель Текущий пробег"
                    ]);
                } else {
                    Vehicle::create([
                        'user_id' => $user->id,
                        'name' => $parts[1],
                        'initial_mileage' => (int)$parts[2]
                    ]);
                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => "Автомобиль {$parts[1]} добавлен!"
                    ]);
                }
                break;

            case str_starts_with($text, '/addtask'):

                // пример: /addtask 1 Масло 5000
                $parts = explode(' ', $text, 4);
                if (count($parts) < 4) {
                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => "Формат: /addtask <id авто> <Описание> <Интервал км>"
                    ]);
                } else {
                    Task::create([
                        'vehicle_id' => (int)$parts[1],
                        'description' => $parts[2],
                        'interval_km' => (int)$parts[3]
                    ]);
                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => "Задача '{$parts[2]}' добавлена к авто #{$parts[1]}"
                    ]);
                }
                break;

            case str_starts_with($text, '/mileage'):

                // пример: /mileage 1 125000
                $parts = explode(' ', $text, 3);
                if (count($parts) < 3) {
                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => "Формат: /mileage <id авто> <Пробег>"
                    ]);
                } else {
                    $vehicle = Vehicle::find((int)$parts[1]);
                    $oldMileage = $vehicle->initial_mileage;
                    $currentMileage = (int)$parts[2];
                    $vehicle->update(['initial_mileage' => $currentMileage]);

                    // проверка задач
                    foreach ($vehicle->tasks as $task) {
                        if ($currentMileage >= $oldMileage + $task->interval_km) {
                            Telegram::sendMessage([
                                'chat_id' => $chatId,
                                'text' => "В этом месяце вам необходимо заменить: {$task->description}"
                            ]);
                        }
                    }
                }
                break;
        }

        return response()->json('ok', 200);
    }
}
