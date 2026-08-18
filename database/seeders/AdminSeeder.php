<?php

namespace Database\Seeders;

use App\Http\Requests\Auth\ApiAuthRequest;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $credentials = config('admin');
        $values = array_values($credentials);

        if (count(array_filter($values, fn (mixed $value): bool => filled($value))) === 0) {
            $this->command?->warn('Platform admin was not seeded because ADMIN_* variables are not configured.');

            return;
        }

        if (count(array_filter($values, fn (mixed $value): bool => filled($value))) !== count($values)) {
            throw new InvalidArgumentException('All ADMIN_* environment variables must be configured together.');
        }

        $email = mb_strtolower(trim((string) $credentials['email']));
        $phone = ApiAuthRequest::normalizePhone((string) $credentials['phone']);

        Validator::make([
            ...$credentials,
            'email' => $email,
            'phone' => $phone,
        ], [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['required', 'regex:/^\+?[0-9]{7,15}$/'],
            'password' => ['required', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ])->validate();

        $user = User::withTrashed()->firstOrNew(['email' => $email]);
        $user->fill([
            'first_name' => $credentials['first_name'],
            'last_name' => $credentials['last_name'],
            'email' => $email,
            'phone' => $phone,
            'password' => Hash::make((string) $credentials['password']),
            'is_active' => true,
        ]);
        $user->deleted_at = null;
        $user->save();
        $user->syncRoles(['platform_admin']);
    }
}
