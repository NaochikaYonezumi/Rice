<?php

namespace Tests\Unit;

use App\Models\Email;
use App\Models\EmailThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Workflow\Models\WorkflowRule;
use Modules\Workflow\Services\WorkflowEngine;
use Tests\TestCase;

/**
 * Phase 6-2: WorkflowEngine::matches / assignByRule の Unit テスト
 */
class WorkflowRuleMatchTest extends TestCase
{
    use RefreshDatabase;

    private function engine(): WorkflowEngine
    {
        return app(WorkflowEngine::class);
    }

    private function thread(string $from, string $subject = 'Test', string $to = 'we@us.com'): EmailThread
    {
        $thread = EmailThread::create(['subject' => $subject, 'status' => 'inbox']);
        Email::create([
            'thread_id' => $thread->id,
            'subject' => $subject,
            'from_address' => $from,
            'to_address' => $to,
            'body_text' => '',
            'received_at' => now(),
        ]);
        return $thread->fresh();
    }

    public function test_from_address_exact_match()
    {
        $user = User::factory()->create();
        WorkflowRule::create([
            'name' => 'r1',
            'match_type' => WorkflowRule::MATCH_FROM_ADDRESS,
            'match_value' => 'boss@example.com',
            'assign_user_id' => $user->id,
            'priority' => 10,
            'is_active' => true,
        ]);

        $assigned = $this->engine()->assignByRule($this->thread('boss@example.com'));
        $this->assertNotNull($assigned);
        $this->assertSame($user->id, $assigned->id);
    }

    public function test_from_domain_match()
    {
        $user = User::factory()->create();
        WorkflowRule::create([
            'name' => 'r-domain',
            'match_type' => WorkflowRule::MATCH_FROM_DOMAIN,
            'match_value' => 'biggie.com',
            'assign_user_id' => $user->id,
            'priority' => 50,
            'is_active' => true,
        ]);

        $this->assertSame($user->id, $this->engine()->assignByRule($this->thread('anyone@biggie.com'))->id);
    }

    public function test_subject_contains_match()
    {
        $user = User::factory()->create();
        WorkflowRule::create([
            'name' => 'r-subject',
            'match_type' => WorkflowRule::MATCH_SUBJECT_CONTAINS,
            'match_value' => '請求書',
            'assign_user_id' => $user->id,
            'priority' => 100,
            'is_active' => true,
        ]);

        $this->assertSame($user->id, $this->engine()->assignByRule($this->thread('a@b.com', '【至急】請求書の件'))->id);
    }

    public function test_lower_priority_value_wins()
    {
        $u1 = User::factory()->create(['name' => 'Low']);
        $u2 = User::factory()->create(['name' => 'High']);

        WorkflowRule::create([
            'name' => 'high-priority-2',
            'match_type' => WorkflowRule::MATCH_FROM_DOMAIN,
            'match_value' => 'a.com',
            'assign_user_id' => $u2->id,
            'priority' => 200, // 値が大きい = 優先度低
            'is_active' => true,
        ]);
        WorkflowRule::create([
            'name' => 'low-priority-1',
            'match_type' => WorkflowRule::MATCH_FROM_DOMAIN,
            'match_value' => 'a.com',
            'assign_user_id' => $u1->id,
            'priority' => 1, // 値が小さい = 優先度高
            'is_active' => true,
        ]);

        $assigned = $this->engine()->assignByRule($this->thread('x@a.com'));
        $this->assertSame($u1->id, $assigned->id);
    }

    public function test_inactive_rule_is_skipped()
    {
        $user = User::factory()->create();
        WorkflowRule::create([
            'name' => 'r-off',
            'match_type' => WorkflowRule::MATCH_FROM_ADDRESS,
            'match_value' => 'off@x.com',
            'assign_user_id' => $user->id,
            'priority' => 10,
            'is_active' => false,
        ]);

        $this->assertNull($this->engine()->assignByRule($this->thread('off@x.com')));
    }

    public function test_no_rule_matches_returns_null()
    {
        $this->assertNull($this->engine()->assignByRule($this->thread('unknown@nowhere.com')));
    }
}
