<?php

namespace App\Services\Fsp;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PrivateDataService
{
    public function lookup(string $email): string
    {
        $key = (string) config('app.private_lookup_key');

        if ($key === '') {
            throw new RuntimeException('PRIVATE_LOOKUP_KEY не задан.');
        }

        return hash_hmac('sha256', mb_strtolower(trim($email)), $key);
    }

    public function emailExists(string $email, ?int $exceptUserId = null): bool
    {
        $query = DB::connection('mysql_private')
            ->table('user_private_data')
            ->where('email_lookup', $this->lookup($email));

        if ($exceptUserId !== null) {
            $query->where('user_id', '<>', $exceptUserId);
        }

        return $query->exists();
    }

    public function put(int $userId, array $data): void
    {
        $email = mb_strtolower(trim((string) $data['email']));

        DB::connection('mysql_private')->transaction(function () use ($userId, $data, $email) {
            DB::connection('mysql_private')->table('user_private_data')->updateOrInsert(
                ['user_id' => $userId],
                [
                    'email_ciphertext' => Crypt::encryptString($email),
                    'email_lookup' => $this->lookup($email),
                    'full_name_ciphertext' => isset($data['full_name'])
                        ? Crypt::encryptString((string) $data['full_name'])
                        : null,
                    'phone_ciphertext' => isset($data['phone'])
                        ? Crypt::encryptString((string) $data['phone'])
                        : null,
                    'updated_at' => now(),
                ]
            );

            DB::connection('mysql_private')->table('private_audit_log')->insert([
                'user_id' => $userId,
                'action' => 'private_data_written',
                'created_at' => now(),
            ]);
        });
    }

    public function get(int $userId): ?array
    {
        $row = DB::connection('mysql_private')->table('user_private_data')->where('user_id', $userId)->first();

        if (! $row) {
            return null;
        }

        DB::connection('mysql_private')->table('private_audit_log')->insert([
            'user_id' => $userId,
            'action' => 'private_data_read',
            'created_at' => now(),
        ]);

        return [
            'email' => Crypt::decryptString($row->email_ciphertext),
            'full_name' => $row->full_name_ciphertext ? Crypt::decryptString($row->full_name_ciphertext) : null,
            'phone' => $row->phone_ciphertext ? Crypt::decryptString($row->phone_ciphertext) : null,
        ];
    }

    public function delete(int $userId): void
    {
        DB::connection('mysql_private')->table('user_private_data')->where('user_id', $userId)->delete();
    }
}
