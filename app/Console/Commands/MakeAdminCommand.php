<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class MakeAdminCommand extends Command
{
    protected $signature = 'make:admin';

    protected $description = 'Create a new admin user';

    public function handle(): int
    {
        $name = $this->ask('Name');

        $email = $this->askValidated('Email', 'email', [
            'email' => ['required', 'email', 'unique:users,email'],
        ]);

        $password = $this->secretValidated('Password', 'password', [
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => UserRole::Admin,
        ]);

        $this->info("Admin user \"{$user->name}\" <{$user->email}> created successfully.");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, array<int, string>>  $rules
     */
    private function askValidated(string $question, string $field, array $rules): string
    {
        while (true) {
            $value = $this->ask($question);

            $validator = Validator::make([$field => $value], $rules);

            if ($validator->passes()) {
                return $value;
            }

            $this->error($validator->errors()->first($field));
        }
    }

    /**
     * @param  array<string, array<int, string>>  $rules
     */
    private function secretValidated(string $question, string $field, array $rules): string
    {
        while (true) {
            $value = $this->secret($question);

            $validator = Validator::make([$field => $value], $rules);

            if ($validator->passes()) {
                return $value;
            }

            $this->error($validator->errors()->first($field));
        }
    }
}
