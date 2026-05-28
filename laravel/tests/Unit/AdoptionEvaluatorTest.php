<?php

namespace Tests\Unit;

use App\Models\AiLog;
use App\Models\EmailThread;
use App\Models\PendingEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AIReply\Services\AdoptionEvaluator;
use Tests\TestCase;

/**
 * Phase 6-3: 採用率算定 (Levenshtein) の Unit テスト
 */
class AdoptionEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    private function evaluator(): AdoptionEvaluator
    {
        return app(AdoptionEvaluator::class);
    }

    private function createPendingWithLog(string $generated, string $sentBody): array
    {
        $user   = User::factory()->create();
        $thread = EmailThread::create(['subject' => 'T', 'status' => 'inbox']);

        $log = AiLog::create([
            'email_thread_id'  => $thread->id,
            'user_id'          => $user->id,
            'provider'         => 'default',
            'prompt_summary'   => 'p',
            'generated_reply'  => $generated,
            'confidence_score' => 80,
        ]);

        $pending = PendingEmail::create([
            'reply_type'         => PendingEmail::TYPE_COMPOSE,
            'to_address'         => 'to@example.com',
            'subject'            => 'T',
            'body'               => $sentBody,
            'status'             => PendingEmail::STATUS_APPROVED,
            'created_by'         => $user->name,
            'created_by_user_id' => $user->id,
            'ai_log_id'          => $log->id,
        ]);

        return [$pending, $log];
    }

    public function test_no_ai_log_id_leaves_log_unchanged()
    {
        $user = User::factory()->create();
        $pending = PendingEmail::create([
            'reply_type'         => PendingEmail::TYPE_COMPOSE,
            'to_address'         => 'a@b.c',
            'subject'            => 's',
            'body'               => 'body',
            'status'             => PendingEmail::STATUS_APPROVED,
            'created_by'         => $user->name,
            'created_by_user_id' => $user->id,
            'ai_log_id'          => null,
        ]);

        $this->evaluator()->evaluate($pending);

        // ai_log_id がない場合は何も起こらない (no exception)
        $this->assertTrue(true);
    }

    public function test_identical_body_is_adopted()
    {
        [$pending, $log] = $this->createPendingWithLog('Hello world', 'Hello world');

        $this->evaluator()->evaluate($pending);

        $log->refresh();
        $this->assertSame(1, (int) $log->was_adopted);
        $this->assertSame(0, (int) $log->edit_distance);
        $this->assertNotNull($log->sent_at);
    }

    public function test_minor_edit_is_adopted()
    {
        // 100 文字の本文に 5 文字 (5%) の差分 → 採用 (10% 以下)
        $a = str_repeat('a', 100);
        $b = str_repeat('a', 95) . 'bbbbb';
        [$pending, $log] = $this->createPendingWithLog($a, $b);

        $this->evaluator()->evaluate($pending);

        $log->refresh();
        $this->assertSame(1, (int) $log->was_adopted);
    }

    public function test_large_edit_is_discarded()
    {
        // 100 文字に 50 文字 (50%) 異なる
        $a = str_repeat('a', 100);
        $b = str_repeat('a', 50) . str_repeat('b', 50);
        [$pending, $log] = $this->createPendingWithLog($a, $b);

        $this->evaluator()->evaluate($pending);

        $log->refresh();
        $this->assertSame(0, (int) $log->was_adopted);
    }

    public function test_completely_rewritten_is_discarded()
    {
        [$pending, $log] = $this->createPendingWithLog('Hello world', 'Totally different content here');

        $this->evaluator()->evaluate($pending);

        $log->refresh();
        $this->assertSame(0, (int) $log->was_adopted);
    }
}
