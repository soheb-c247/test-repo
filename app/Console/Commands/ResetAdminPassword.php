<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Offline password recovery for the single administrator (there is no
 * e-mail based reset in the application).
 */
class ResetAdminPassword extends Command
{
    protected $signature = 'lab:admin-password';

    protected $description = 'Set a new password for the laboratory administrator';

    public function handle(): int
    {
        $admin = User::query()->where('is_lab_admin', true)->first();

        if (! $admin) {
            $this->error('No administrator exists. Run: php artisan db:seed --class=AdminUserSeeder');

            return self::FAILURE;
        }

        $password = (string) $this->secret("New password for {$admin->email}");
        $confirmation = (string) $this->secret('Confirm password');

        $validator = Validator::make(
            ['password' => $password, 'password_confirmation' => $confirmation],
            ['password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()]]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $admin->forceFill(['password' => $password])->save();
        $this->info('Administrator password updated.');

        return self::SUCCESS;
    }
}
