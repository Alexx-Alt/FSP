<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    public function create(array $input): User
    {
        // Проверяем данные формы
        $data = Validator::make($input, [
            'username' => [
                'required',
                'string',
                'max:50',
                Rule::unique('users', 'username'),
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:100',
                Rule::unique('users', 'email'),
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'max:72',
                'confirmed',
            ],
            'role' => [
                'required',
                Rule::in(['applicant', 'employer']),
            ],
        ], [
            'username.required' => 'Укажи имя пользователя.',
            'username.unique' => 'Это имя уже занято.',
            'email.required' => 'Укажи почту.',
            'email.email' => 'Проверь адрес почты.',
            'email.unique' => 'Эта почта уже зарегистрирована.',
            'password.min' => 'Пароль должен содержать минимум 8 символов.',
            'password.confirmed' => 'Пароли не совпадают.',
            'role.in' => 'Выбери доступную роль.',
        ])->validate();

        // Хеширование выполнит модель
        return User::create([
            'username' => $data['username'],
            'email' => mb_strtolower($data['email']),
            'password_hash' => $data['password'],
            'role' => $data['role'],
        ]);
    }
}