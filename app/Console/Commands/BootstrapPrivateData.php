<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Fsp\PrivateDataService;
use Illuminate\Console\Command;

class BootstrapPrivateData extends Command
{
    protected $signature = 'fsp:private-bootstrap';

    protected $description = 'Переносит персональные данные demo-пользователей в зашифрованную БД';

    public function handle(PrivateDataService $privateData): int
    {
        foreach ($this->demoUsers() as $userId => $identity) {
            $user = User::query()->find($userId);

            if (! $user) {
                continue;
            }

            if (! $privateData->emailExists($identity['email'], $userId)) {
                $privateData->put($userId, [
                    'email' => $identity['email'],
                    'full_name' => $identity['name'],
                ]);
            }

            $lookup = $privateData->lookup($identity['email']);

            if ($user->getRawOriginal('email') !== $lookup) {
                User::query()->whereKey($userId)->update(['email' => $lookup]);
            }
        }

        $this->info('Приватные данные demo-пользователей подготовлены.');

        return self::SUCCESS;
    }

    private function demoUsers(): array
    {
        return [
            1 => ['name' => 'Алексей Соколов', 'email' => 'candidate@fsp.demo'],
            2 => ['name' => 'Марина Петрова', 'email' => 'marina@fsp.demo'],
            3 => ['name' => 'Иван Морозов', 'email' => 'ivan@fsp.demo'],
            4 => ['name' => 'Дарья Волкова', 'email' => 'daria@fsp.demo'],
            5 => ['name' => 'Тимур Ахметов', 'email' => 'timur@fsp.demo'],
            6 => ['name' => 'Анна Лебедева', 'email' => 'anna@fsp.demo'],
            7 => ['name' => 'Сергей Орлов', 'email' => 'sergey@fsp.demo'],
            8 => ['name' => 'Полина Смирнова', 'email' => 'polina@fsp.demo'],
            9 => ['name' => 'Никита Белов', 'email' => 'nikita@fsp.demo'],
            10 => ['name' => 'Елена Крылова', 'email' => 'elena@fsp.demo'],
            11 => ['name' => 'Михаил Романов', 'email' => 'mikhail@fsp.demo'],
            12 => ['name' => 'Ольга Кузнецова', 'email' => 'olga@fsp.demo'],
            101 => ['name' => 'Ирина Назарова', 'email' => 'employer@fsp.demo'],
            102 => ['name' => 'Павел Власов', 'email' => 'pavel.hr@fsp.demo'],
            103 => ['name' => 'Роман Хабибуллин', 'email' => 'roman.hr@fsp.demo'],
            104 => ['name' => 'Светлана Громова', 'email' => 'svetlana.hr@fsp.demo'],
            105 => ['name' => 'Денис Уваров', 'email' => 'denis.hr@fsp.demo'],
            106 => ['name' => 'Ксения Котова', 'email' => 'ksenia.hr@fsp.demo'],
            107 => ['name' => 'Андрей Ким', 'email' => 'andrey.lead@fsp.demo'],
            900 => ['name' => 'Администратор ФСП', 'email' => 'admin@fsp.demo'],
        ];
    }
}
