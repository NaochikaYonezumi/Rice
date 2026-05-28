<?php

namespace Tests\Feature\Phase6;

use App\Models\AiSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 6-4: Agent 別メール署名 (Feature)
 */
class UserSignatureFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_update_saves_signature_fields()
    {
        $user = User::factory()->create();

        $res = $this->actingAs($user)->patch('/profile', [
            'name'              => $user->name,
            'email'             => $user->email,
            'signature_text'    => "---\n山田 太郎",
            'signature_html'    => '<p><strong>山田 太郎</strong></p><script>alert(1)</script>',
            'signature_enabled' => '1',
        ]);

        $res->assertRedirect();
        $user->refresh();

        $this->assertSame("---\n山田 太郎", $user->signature_text);
        $this->assertTrue((bool) $user->signature_enabled);
        // script は SignatureSanitizer で除去されている
        $this->assertStringNotContainsString('<script>', (string) $user->signature_html);
        $this->assertStringContainsString('山田 太郎', (string) $user->signature_html);
    }

    public function test_disabled_signature_returns_null_effective_signature()
    {
        $user = User::factory()->create([
            'signature_enabled' => false,
            'signature_text'    => 'X',
        ]);
        $this->assertNull($user->effectiveSignature()['type']);
    }

    public function test_fallback_to_global_signature()
    {
        AiSetting::getSettings()->update(['agent_signature' => 'グローバル']);
        $user = User::factory()->create([
            'signature_enabled' => true,
            'signature_text'    => null,
            'signature_html'    => null,
        ]);
        $sig = $user->effectiveSignature();
        $this->assertSame('text', $sig['type']);
        $this->assertStringContainsString('グローバル', $sig['content']);
    }

    public function test_compose_window_includes_user_signature_in_initial_body()
    {
        $user = User::factory()->create([
            'signature_enabled' => true,
            'signature_text'    => 'My Sig',
        ]);

        $res = $this->actingAs($user)->get('/emails/compose-window');
        $res->assertOk();
        // ビューに userSignature 経由で 'My Sig' が埋め込まれていること
        $res->assertSeeText('My Sig');
    }
}
