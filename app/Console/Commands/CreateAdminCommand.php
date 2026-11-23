<?php

namespace App\Console\Commands;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

#[Signature('app:create-admin {--name= : Full name} {--email= : Email address} {--password= : Password (prompted when omitted)}')]
#[Description('Create an administrator account. Admins can never register publicly.')]
class CreateAdminCommand extends Command
{
    public function handle(RecordAuditLog $audit): int
    {
        $name = $this->option('name') ?: $this->ask('Name');
        $email = $this->option('email') ?: $this->ask('Email');
        $password = $this->option('password') ?: $this->secret('Password');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', Password::default()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $admin = new User([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);
        $admin->role = UserRole::Admin;
        $admin->email_verified_at = now();
        $admin->save();

        $audit('admin.created', $admin, metadata: ['via' => 'artisan']);

        $this->info("Administrator {$email} created.");

        return self::SUCCESS;
    }
}
