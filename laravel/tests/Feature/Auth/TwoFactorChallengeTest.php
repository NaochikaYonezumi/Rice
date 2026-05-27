<?php

namespace Tests\Feature\Auth;

use App\Mail\TwoFactorCodeMail;
use App\Models\TrustedDevice;
use App\Models\TwoFactorCode;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TwoFactorChallengeTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_redirects_to_two_factor_challenge(): void
    {
        Mail::fake();
        $user = User::factory()->create(['password' => Hash::make('secret123!')]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret123!',
        ]);

        $response->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
        $this->assertDatabaseCount('two_factor_codes', 1);
        Mail::assertSent(TwoFactorCodeMail::class, fn ($mail) => $mail->hasTo($user->email));
    }

    public function test_login_with_invalid_credentials_does_not_create_code(): void
    {
        Mail::fake();
        $user = User::factory()->create(['password' => Hash::make('secret123!')]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseCount('two_factor_codes', 0);
        Mail::assertNothingSent();
    }

    public function test_verify_with_correct_code_logs_user_in(): void
    {
        Mail::fake();
        $user = User::factory()->create(['password' => Hash::make('secret123!')]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret123!',
        ]);

        $code = $this->latestPlainCode($user);
        $this->assertNotNull($code);

        $response = $this->post(route('two-factor.verify'), ['code' => $code]);
        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_verify_with_wrong_code_increments_attempts(): void
    {
        Mail::fake();
        $user = User::factory()->create(['password' => Hash::make('secret123!')]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret123!',
        ]);

        $this->post(route('two-factor.verify'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
        $this->assertEquals(1, TwoFactorCode::where('user_id', $user->id)->latest('id')->first()->attempts);
    }

    public function test_exceeding_max_attempts_invalidates_code(): void
    {
        Mail::fake();
        $user = User::factory()->create(['password' => Hash::make('secret123!')]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret123!',
        ]);

        $plain = $this->latestPlainCode($user);
        $max = (int) config('two_factor.max_attempts', 5);
        for ($i = 0; $i < $max; $i++) {
            $this->post(route('two-factor.verify'), ['code' => '999999']);
        }

        // 上限超過後は正解コードでも通らない
        $response = $this->post(route('two-factor.verify'), ['code' => $plain]);
        $response->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_expired_code_cannot_be_used(): void
    {
        Mail::fake();
        $user = User::factory()->create(['password' => Hash::make('secret123!')]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret123!',
        ]);

        $plain = $this->latestPlainCode($user);
        // コードを期限切れにする
        TwoFactorCode::where('user_id', $user->id)
            ->update(['expires_at' => now()->subMinute()]);

        $this->post(route('two-factor.verify'), ['code' => $plain])
            ->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_recovery_code_logs_user_in_and_revokes_trusted_devices(): void
    {
        Mail::fake();
        $user = User::factory()->create(['password' => Hash::make('secret123!')]);
        $plainRecovery = $user->generateRecoveryCodes();
        // 別の信頼デバイスが事前に存在
        TrustedDevice::create([
            'user_id' => $user->id,
            'token_hash' => Hash::make('xxx'),
            'expires_at' => now()->addDays(30),
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret123!',
        ]);

        $response = $this->post(route('two-factor.recovery'), [
            'recovery_code' => $plainRecovery[0],
        ]);
        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);

        // 信頼デバイスは全て revoke されている
        $this->assertDatabaseCount('trusted_devices', 0);
        // 同じリカバリーコードは2回使えない
        $this->assertFalse($user->fresh()->consumeRecoveryCode($plainRecovery[0]));
    }

    public function test_resend_issues_a_new_code(): void
    {
        Mail::fake();
        $user = User::factory()->create(['password' => Hash::make('secret123!')]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret123!',
        ]);

        Mail::assertSentCount(1);
        $oldCount = TwoFactorCode::where('user_id', $user->id)->count();

        $this->post(route('two-factor.resend'));

        Mail::assertSentCount(2);
        $this->assertGreaterThan($oldCount, TwoFactorCode::where('user_id', $user->id)->count());
    }

    public function test_trusted_device_skips_two_factor(): void
    {
        Mail::fake();
        $user = User::factory()->create(['password' => Hash::make('secret123!')]);

        // 信頼デバイスを事前に発行
        $trusted = TrustedDevice::create([
            'user_id' => $user->id,
            'token_hash' => Hash::make('cookie-token'),
            'expires_at' => now()->addDays(30),
        ]);
        $cookieName = config('two_factor.trusted_device_cookie');

        $response = $this->withoutMiddleware(\Illuminate\Cookie\Middleware\EncryptCookies::class)
            ->withCookie($cookieName, $trusted->id . '|cookie-token')
            ->post('/login', [
                'email' => $user->email,
                'password' => 'secret123!',
            ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);
        Mail::assertNothingSent();
    }

    public function test_pending_session_required_for_challenge(): void
    {
        // 認証もpending stateもなく challenge にアクセス
        $this->get(route('two-factor.challenge'))
            ->assertRedirect(route('login'));

        $this->post(route('two-factor.verify'), ['code' => '123456'])
            ->assertRedirect(route('login'));
    }

    /**
     * テストヘルパ: TwoFactorService の代わりに直接 plain code を取得する手段がないため、
     * issueCode をモックする代わりにフィクスチャ的に既知コードに置き換える。
     */
    protected function latestPlainCode(User $user): ?string
    {
        // 既存のコードを破棄し、既知のコードで上書き
        $user->twoFactorCodes()->whereNull('consumed_at')->delete();
        $plain = '123456';
        TwoFactorCode::create([
            'user_id' => $user->id,
            'code_hash' => Hash::make($plain),
            'expires_at' => now()->addMinutes(10),
        ]);
        return $plain;
    }
}
