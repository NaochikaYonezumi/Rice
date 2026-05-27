<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            // テストユーザはデフォルトでリカバリーコード生成済み扱い。
            // 二段階認証フローの強制リダイレクトが既存テストを巻き込まないようにする。
            'two_factor_recovery_generated_at' => now(),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * リカバリーコード未取得状態 (新規登録直後相当) のユーザを作る。
     */
    public function withoutRecoveryCodes(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_recovery_generated_at' => null,
        ]);
    }
}
