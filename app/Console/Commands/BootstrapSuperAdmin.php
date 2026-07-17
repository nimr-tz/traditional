<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BootstrapSuperAdmin extends Command
{
    protected $signature = 'tmsc:bootstrap-super-admin
        {email : Email address for the administrator}
        {--name= : Administrator display name}';

    protected $description = 'Create or promote the initial TMSC super administrator';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $password = (string) env('TMSC_BOOTSTRAP_PASSWORD');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('A valid email address is required.');

            return self::FAILURE;
        }

        if (strlen($password) < 12) {
            $this->error('Set TMSC_BOOTSTRAP_PASSWORD to at least 12 characters for this one-time command.');

            return self::FAILURE;
        }

        $name = trim((string) ($this->option('name') ?: Str::headline(Str::before($email, '@'))));
        $user = User::firstOrNew(['email' => $email]);

        $user->forceFill([
            'name' => $name,
            'first_name' => $user->first_name ?: Str::before($name, ' '),
            'last_name' => $user->last_name ?: Str::after($name, ' '),
            'password' => Hash::make($password),
            'role' => User::ROLE_SUPER_ADMIN,
            'email_verified_at' => $user->email_verified_at ?: now(),
            'payment_status' => 'verified',
        ])->save();

        $this->info("Super administrator ready: {$email}");

        return self::SUCCESS;
    }
}
