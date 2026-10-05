<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'username',
    'email',
    'password_hash',
    'role',
    'headline',
    'city',
    'bio',
    'skills',
    'company',
])]
#[Hidden(['password_hash', 'remember_token'])]
class User extends Authenticatable
{
    use Notifiable;

    // Ключ нашей таблицы
    protected $primaryKey = 'user_id';

    // В таблице нет updated_at
    public $timestamps = false;

    // Столбец с хешем пароля
    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    protected function casts(): array
    {
        return [
            'password_hash' => 'hashed',
            'created_at' => 'datetime',
        ];
    }
}