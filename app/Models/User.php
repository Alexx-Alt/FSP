<?php

namespace App\Models;

use App\Services\Fsp\PrivateDataService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Throwable;

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
    'profile_visibility',
    'consent_processing',
    'consent_publication',
    'fsp_id_external',
    'fsp_id_linked',
])]
#[Hidden(['password_hash', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    use Notifiable;

    protected $primaryKey = 'user_id';

    public $timestamps = false;

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    /**
     * Колонка email основной БД содержит HMAC для поиска.
     * Настоящий адрес расшифровывается из отдельной приватной БД.
     */
    public function getEmailAttribute(): ?string
    {
        if (! $this->exists) {
            return null;
        }

        try {
            return app(PrivateDataService::class)->get((int) $this->getKey())['email'] ?? null;
        } catch (Throwable) {
            return null;
        }
    }

    protected function casts(): array
    {
        return [
            'password_hash' => 'hashed',
            'created_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'salary_expected' => 'integer',
            'consent_processing' => 'boolean',
            'consent_publication' => 'boolean',
            'fsp_id_linked' => 'boolean',
        ];
    }
}
