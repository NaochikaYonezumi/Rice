<?php

namespace Tests\Unit;

use App\Models\AiSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 6-4: User::effectiveSignature() の Unit テスト
 */
class UserSignatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_returns_null_type()
    {
        $user = User::factory()->create([
            'signature_enabled' => false,
            'signature_text'    => 'Some text',
            'signature_html'    => '<p>Some html</p>',
        ]);
        $sig = $user->effectiveSignature();
        $this->assertNull($sig['type']);
        $this->assertNull($sig['content']);
    }

    public function test_html_signature_takes_precedence_over_text()
    {
        $user = User::factory()->create([
            'signature_enabled' => true,
            'signature_text'    => 'My text sig',
            'signature_html'    => '<p>My <strong>html</strong> sig</p>',
        ]);
        $sig = $user->effectiveSignature();
        $this->assertSame('html', $sig['type']);
        $this->assertStringContainsString('html', $sig['content']);
    }

    public function test_text_used_when_html_empty()
    {
        $user = User::factory()->create([
            'signature_enabled' => true,
            'signature_text'    => 'My text sig',
            'signature_html'    => null,
        ]);
        $sig = $user->effectiveSignature();
        $this->assertSame('text', $sig['type']);
        $this->assertSame('My text sig', $sig['content']);
    }

    public function test_falls_back_to_global_ai_setting_when_both_empty()
    {
        AiSetting::getSettings()->update(['agent_signature' => '---' . PHP_EOL . 'グローバル署名']);
        $user = User::factory()->create([
            'signature_enabled' => true,
            'signature_text'    => null,
            'signature_html'    => null,
        ]);
        $sig = $user->effectiveSignature();
        $this->assertSame('text', $sig['type']);
        $this->assertStringContainsString('グローバル署名', $sig['content']);
    }
}
