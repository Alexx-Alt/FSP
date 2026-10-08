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
    'primary_category',
    'desired_grade',
    'confirmed_grade',
    'grade_status',
    'salary_expected',
    'work_format',
])]
#[Hidden(['password_hash', 'remember_token'])]
class User extends Authenticatable
{
    use Notifiable;

    protected $primaryKey = 'user_id';

    public $timestamps = false;

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    protected function casts(): array
    {
        return [
            'password_hash' => 'hashed',
            'created_at' => 'datetime',
            'salary_expected' => 'integer',
        ];
    }
}
