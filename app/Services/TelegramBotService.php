<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use App\Models\Vehicle;
use Telegram\Bot\Api;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\HttpClients\GuzzleHttpClient;

class TelegramBotService
{
    private Api $telegram;
    private int $offset = 0;

    public function __construct()
    {
        $this->telegram = new Api(config('services.telegram.bot_token'));

        // Disable SSL verification for development
        if (config('app.debug', false)) {
            $guzzleClient = new \GuzzleHttp\Client([
                'verify' => false,
                'curl' => [
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                ]
            ]);

            $this->telegram->setHttpClientHandler(
                new GuzzleHttpClient($guzzleClient)
            );
        }
    }

    /**
     * Запуск polling для получения обновлений
     */
    public function startPolling(): void
    {
        Log::info('Telegram bot polling started');

        // Сбрасываем webhook перед началом polling
        try {
            $this->telegram->removeWebhook();
            Log::info('Webhook removed successfully');
        } catch (\Exception $e) {
            Log::warning('Failed to remove webhook: ' . $e->getMessage());
        }

        // Устанавливаем уникальный offset для избежания конфликтов
        $this->offset = time();
        Log::info("Starting with offset: {$this->offset}");

        while (true) {
            try {
                $updates = $this->telegram->getUpdates([
                    'offset' => $this->offset,
                    'timeout' => config('services.telegram.polling_timeout', 30)
                ]);

                foreach ($updates as $update) {
                    $this->handleUpdate($update);
                    $this->offset = $update->getUpdateId() + 1;
                }

                sleep(config('services.telegram.polling_interval', 1));

            } catch (\Exception $e) {
                Log::error('Telegram polling error: ' . $e->getMessage());

                // Если конфликт с другим экземпляром бота, ждем дольше
                // if (strpos($e->getMessage(), 'Conflict') !== false) {
                //     Log::info('Bot conflict detected, waiting 30 seconds...');
                //     sleep(30);
                // } else {
                //     sleep(5); // ждем 5 секунд перед повтором при ошибке
                // }
            }
        }
    }

    /**
     * Обработка одного обновления
     */
    private function handleUpdate($update): void
    {
        $message = $update->getMessage();
        if (!$message) {
            return;
        }

        // Проверяем, что это объект сообщения, а не коллекция
        if (is_array($message) || $message instanceof \Illuminate\Support\Collection) {
            return;
        }

        $chatId = $message->getChat()->getId();
        $text = $message->getText();

        if (!$text) {
            return;
        }

        Log::info("Received message from {$chatId}: {$text}");

        // Создаем пользователя при первом обращении
        $user = User::firstOrCreate(['telegram_user_id' => $chatId]);

        $this->handleCommand($chatId, $text, $user);
    }

    /**
     * Обработка команд
     */
    private function handleCommand(int $chatId, string $text, User $user): void
    {
        switch (true) {
            case $text === '/start':
                $this->handleStart($chatId);
                break;

            case str_starts_with($text, '/addcar'):
                $this->handleAddCar($chatId, $text, $user);
                break;

            case str_starts_with($text, '/addtask'):
                $this->handleAddTask($chatId, $text);
                break;

            case str_starts_with($text, '/mileage'):
                $this->handleMileage($chatId, $text);
                break;

            case $text === '/mycars':
                $this->handleMyCars($chatId, $user);
                break;

            case $text === '/mytasks':
            case $text === '/mytask':
                $this->handleMyTasks($chatId, $user);
                break;

            case str_starts_with($text, '/help'):
                $this->handleHelp($chatId);
                break;

            default:
                // Игнорируем неизвестные сообщения, не отправляем ответ
                break;
        }
    }

    /**
     * Команда /start
     */
    private function handleStart(int $chatId): void
    {
        $message = "🚗 Добро пожаловать в AutoBot!\n\n";
        $message .= "Я помогу вам отслеживать техническое обслуживание ваших автомобилей.\n\n";
        $message .= "Доступные команды:\n";
        $message .= "/addcar - добавить автомобиль\n";
        $message .= "/mycars - мои автомобили\n";
        $message .= "/addtask - добавить задачу обслуживания\n";
        $message .= "/mytasks - мои задачи\n";
        $message .= "/mileage - обновить пробег\n";
        $message .= "/help - помощь\n\n";
        $message .= "Начните с добавления автомобиля: /addcar Toyota 120000";

        $this->sendMessage($chatId, $message);
    }

    /**
     * Команда /addcar
     */
    private function handleAddCar(int $chatId, string $text, User $user): void
    {
        $parts = explode(' ', $text, 3);
        if (count($parts) < 3) {
            $this->sendMessage($chatId, "❌ Формат: /addcar название текущий_пробег\n\nПример: /addcar Toyota 120000");
            return;
        }

        try {
            $vehicle = Vehicle::create([
                'user_id' => $user->id,
                'name' => $parts[1],
                'initial_mileage' => (int)$parts[2]
            ]);

            $this->sendMessage($chatId, "✅ Автомобиль '{$parts[1]}' добавлен!\nID: {$vehicle->id}\nПробег: {$parts[2]} км");
        } catch (\Exception $e) {
            Log::error("Error adding car: " . $e->getMessage());
            $this->sendMessage($chatId, "❌ Ошибка при добавлении автомобиля. Попробуйте еще раз.");
        }
    }

    /**
     * Команда /addtask
     */
    private function handleAddTask(int $chatId, string $text): void
    {
        $parts = explode(' ', $text, 4);
        if (count($parts) < 4) {
            $this->sendMessage($chatId, "❌ Формат: /addtask ID_авто описание интервал_км\n\nПример: /addtask 1 Замена_масла 5000");
            return;
        }

        try {
            $vehicleId = (int)$parts[1];
            $vehicle = Vehicle::find($vehicleId);

            if (!$vehicle) {
                $this->sendMessage($chatId, "❌ Автомобиль с ID {$vehicleId} не найден.");
                return;
            }

            $task = Task::create([
                'vehicle_id' => $vehicleId,
                'description' => $parts[2],
                'interval_km' => (int)$parts[3]
            ]);

            $this->sendMessage($chatId, "✅ Задача '{$parts[2]}' добавлена к автомобилю '{$vehicle->name}'\nИнтервал: {$parts[3]} км");
        } catch (\Exception $e) {
            Log::error("Error adding task: " . $e->getMessage());
            $this->sendMessage($chatId, "❌ Ошибка при добавлении задачи. Попробуйте еще раз.");
        }
    }

    /**
     * Команда /mileage
     */
    private function handleMileage(int $chatId, string $text): void
    {
        $parts = explode(' ', $text, 3);
        if (count($parts) < 3) {
            $this->sendMessage($chatId, "❌ Формат: /mileage ID_авто пробег\n\nПример: /mileage 1 125000");
            return;
        }

        try {
            $vehicleId = (int)$parts[1];
            $currentMileage = (int)$parts[2];

            $vehicle = Vehicle::find($vehicleId);
            if (!$vehicle) {
                $this->sendMessage($chatId, "❌ Автомобиль с ID {$vehicleId} не найден.");
                return;
            }

            $oldMileage = $vehicle->initial_mileage;
            $vehicle->update(['initial_mileage' => $currentMileage]);

            $message = "✅ Пробег обновлен!\n";
            $message .= "Автомобиль: {$vehicle->name}\n";
            $message .= "Предыдущий пробег: {$oldMileage} км\n";
            $message .= "Текущий пробег: {$currentMileage} км\n";
            $message .= "Пробег с последнего обновления: " . ($currentMileage - $oldMileage) . " км\n\n";

            // Проверка задач
            $tasksToDo = [];
            foreach ($vehicle->tasks as $task) {
                if ($currentMileage >= $oldMileage + $task->interval_km) {
                    $tasksToDo[] = $task->description;
                }
            }

            if (!empty($tasksToDo)) {
                $message .= "🔧 Требуется обслуживание:\n";
                foreach ($tasksToDo as $task) {
                    $message .= "• {$task}\n";
                }
            } else {
                $message .= "✅ Все задачи обслуживания в порядке!";
            }

            $this->sendMessage($chatId, $message);

        } catch (\Exception $e) {
            Log::error("Error updating mileage: " . $e->getMessage());
            $this->sendMessage($chatId, "❌ Ошибка при обновлении пробега. Попробуйте еще раз.");
        }
    }

    /**
     * Команда /mycars
     */
    private function handleMyCars(int $chatId, User $user): void
    {
        $vehicles = $user->vehicles;

        if ($vehicles->isEmpty()) {
            $this->sendMessage($chatId, "🚗 У вас пока нет автомобилей.\n\nДобавьте первый автомобиль командой /addcar");
            return;
        }

        $message = "🚗 Ваши автомобили:\n\n";
        foreach ($vehicles as $vehicle) {
            $message .= "ID: {$vehicle->id}\n";
            $message .= "Название: {$vehicle->name}\n";
            $message .= "Пробег: {$vehicle->initial_mileage} км\n";
            $message .= "Задач: " . $vehicle->tasks->count() . "\n\n";
        }

        $this->sendMessage($chatId, $message);
    }

    /**
     * Команда /mytasks
     */
    private function handleMyTasks(int $chatId, User $user): void
    {
        $vehicles = $user->vehicles()->with('tasks')->get();
        $allTasks = $vehicles->flatMap->tasks;

        if ($allTasks->isEmpty()) {
            $this->sendMessage($chatId, "📋 Задачи отсутствуют.\n\nДобавьте первую задачу командой /addtask");
            return;
        }

        $message = "📋 Ваши задачи обслуживания:\n\n";
        $hasTasks = false;

        foreach ($vehicles as $vehicle) {
            if ($vehicle->tasks->isNotEmpty()) {
                $hasTasks = true;
                $message .= "🚗 {$vehicle->name} (ID: {$vehicle->id}):\n";
                foreach ($vehicle->tasks as $task) {
                    $message .= "• {$task->description} (каждые {$task->interval_km} км)\n";
                }
                $message .= "\n";
            }
        }

        if (!$hasTasks) {
            $message = "📋 Задачи отсутствуют.\n\nДобавьте первую задачу командой /addtask";
        }

        $this->sendMessage($chatId, $message);
    }

    /**
     * Команда /help
     */
    private function handleHelp(int $chatId): void
    {
        $message = "📖 Справка по командам AutoBot:\n\n";
        $message .= "/start - начать работу с ботом\n";
        $message .= "/addcar название пробег - добавить автомобиль\n";
        $message .= "/mycars - показать мои автомобили\n";
        $message .= "/addtask ID_авто описание интервал - добавить задачу\n";
        $message .= "/mytasks или /mytask - показать мои задачи\n";
        $message .= "/mileage ID_авто пробег - обновить пробег\n";
        $message .= "/help - эта справка\n\n";
        $message .= "Примеры:\n";
        $message .= "/addcar Toyota 120000\n";
        $message .= "/addtask 1 Замена_масла 5000\n";
        $message .= "/mileage 1 125000";

        $this->sendMessage($chatId, $message);
    }

    /**
     * Отправка сообщения
     */
    public function sendMessage(int $chatId, string $text): void
    {
        try {
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $text
            ]);
        } catch (\Exception $e) {
            Log::error("Error sending message to {$chatId}: " . $e->getMessage());
        }
    }
}
