<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Provisions the single laboratory administrator from ADMIN_EMAIL /
 * ADMIN_PASSWORD (see config/lab.php). The password is hashed; it is never
 * printed or logged. Every other account loses administrator access.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = Str::lower(trim((string) config('lab.admin.email')));
        $password = (string) config('lab.admin.password');

        if ($email === '') {
            $this->command?->warn('ADMIN_EMAIL is not set — administrator not provisioned.');

            return;
        }

        DB::transaction(function () use ($email, $password) {
            $admin = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

            if (! $admin) {
                if ($password === '') {
                    $this->command?->warn('ADMIN_PASSWORD is not set — administrator not created.');

                    return;
                }

                $admin = new User;
                $admin->name = (string) config('lab.admin.name', 'Lab Administrator');
                $admin->email = $email;
                $admin->password = $password;
                $admin->email_verified_at = now();
                $this->command?->info("Administrator created: {$email}");
            } else {
                $admin->email = $email;
                if ($password !== '' && ! Hash::check($password, $admin->password)) {
                    $admin->password = $password;
                    $this->command?->info("Administrator password synchronised from ADMIN_PASSWORD for {$email}");
                }
            }

            $admin->forceFill(['is_lab_admin' => true])->save();

            User::query()->whereKeyNot($admin->id)->where('is_lab_admin', true)->update(['is_lab_admin' => false]);
        });
    }
}
