<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\Fsp\PrivateDataService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Throwable;

class CreateNewUser implements CreatesNewUsers
{
    public function __construct(private readonly PrivateDataService $privateData)
    {
    }

    public function create(array $input): User
    {
        $validator = Validator::make($input, [
            'username' => ['required', 'string', 'max:50', Rule::unique('users', 'username')],
            'email' => ['required', 'string', 'email', 'max:100'],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
            'role' => ['required', Rule::in(['applicant', 'employer'])],
        ], [
            'username.required' => 'Укажи имя пользователя.',
            'username.unique' => 'Это имя уже занято.',
            'email.required' => 'Укажи почту.',
            'email.email' => 'Проверь адрес почты.',
            'password.min' => 'Пароль должен содержать минимум 8 символов.',
            'password.confirmed' => 'Пароли не совпадают.',
            'role.in' => 'Выбери доступную роль.',
        ]);

        $validator->after(function ($validator) use ($input) {
            if (isset($input['email']) && $this->privateData->emailExists((string) $input['email'])) {
                $validator->errors()->add('email', 'Эта почта уже зарегистрирована.');
            }
        });

        $data = $validator->validate();
        $email = mb_strtolower(trim($data['email']));

        $user = DB::transaction(fn () => User::create([
            'username' => $data['username'],
            // В основной БД хранится только HMAC, а не адрес почты.
            'email' => $this->privateData->lookup($email),
            'password_hash' => $data['password'],
            'role' => $data['role'],
        ]));

        try {
            $this->privateData->put($user->user_id, [
                'email' => $email,
                'full_name' => $data['username'],
            ]);
        } catch (Throwable $exception) {
            // Компенсация: не оставляем аккаунт без приватных данных.
            $user->delete();

            throw $exception;
        }

        return $user->fresh();
    }
}
