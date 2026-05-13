<?php

namespace Tests\Feature\Phase6;

use App\Models\AiLog;
use App\Models\EmailThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 6-3: AI 利用統計レポート API (Feature)
 */
class AiUsageReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_usage_endpoint_returns_summary()
    {
        $user = User::factory()->create();
        $thread = EmailThread::create(['subject' => 'T', 'status' => 'inbox']);

        // 採用 2 件 / 破棄 1 件
        AiLog::create([
            'email_thread_id' => $thread->id,
            'user_id' => $user->id,
            'provider' => 'default',
            'prompt_summary' => 'p1',
            'generated_reply' => 'gen 1',
            'confidence_score' => 90,
            'was_adopted' => 1,
            'collection' => 'mf_faq',
        ]);
        AiLog::create([
            'email_thread_id' => $thread->id,
            'user_id' => $user->id,
            'provider' => 'default',
            'prompt_summary' => 'p2',
            'generated_reply' => 'gen 2',
            'confidence_score' => 80,
            'was_adopted' => 1,
            'collection' => 'mf_faq',
        ]);
        AiLog::create([
            'email_thread_id' => $thread->id,
            'user_id' => $user->id,
            'provider' => 'default',
            'prompt_summary' => 'p3',
            'generated_reply' => 'gen 3',
            'confidence_score' => 60,
            'was_adopted' => 0,
            'collection' => 'hive_faq',
        ]);

        $res = $this->actingAs($user)->getJson('/reports/ai-usage');
        $res->assertOk();
        $data = $res->json();

        // 評価済み = 3 件 (採用 2 / 破棄 1) → 採用率 0.6667
        $this->assertSame(3, $data['summary']['total']);
        $this->assertSame(2, $data['summary']['adopted']);
        $this->assertSame(1, $data['summary']['discarded']);
        $this->assertEqualsWithDelta(0.6667, $data['summary']['adoption_rate'], 0.001);

        // by_collection が mf_faq / hive_faq を返す
        $colNames = collect($data['by_collection'])->pluck('collection')->all();
        $this->assertContains('mf_faq', $colNames);
        $this->assertContains('hive_faq', $colNames);
    }

    public function test_group_by_user_returns_only_user_breakdown()
    {
        $user = User::factory()->create(['name' => 'Solo']);
        $thread = EmailThread::create(['subject' => 'T', 'status' => 'inbox']);
        AiLog::create([
            'email_thread_id' => $thread->id,
            'user_id' => $user->id,
            'provider' => 'default',
            'prompt_summary' => 'p',
            'generated_reply' => 'g',
            'confidence_score' => 80,
            'was_adopted' => 1,
        ]);

        $res = $this->actingAs($user)->getJson('/reports/ai-usage?group_by=user');
        $res->assertOk();
        $this->assertArrayHasKey('by_user', $res->json());
        $this->assertArrayNotHasKey('by_collection', $res->json());
    }
}
