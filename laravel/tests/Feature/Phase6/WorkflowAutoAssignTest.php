<?php

namespace Tests\Feature\Phase6;

use App\Models\Email;
use App\Models\EmailThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Workflow\Models\WorkflowLog;
use Modules\Workflow\Models\WorkflowRule;
use Modules\Workflow\Services\WorkflowEngine;
use Tests\TestCase;

/**
 * Phase 6-2: 自動割当 + RR フォールバック + 履歴記録 (Feature)
 */
class WorkflowAutoAssignTest extends TestCase
{
    use RefreshDatabase;

    private function threadFrom(string $from, string $subject = 'Test'): EmailThread
    {
        $thread = EmailThread::create(['subject' => $subject, 'status' => 'inbox']);
        Email::create([
            'thread_id' => $thread->id,
            'subject' => $subject,
            'from_address' => $from,
            'to_address' => 'we@us.com',
            'body_text' => '',
            'received_at' => now(),
        ]);
        return $thread->fresh();
    }

    public function test_rule_match_assigns_user_and_logs()
    {
        $member = User::factory()->create(['role' => 'member']);
        WorkflowRule::create([
            'name' => 'r',
            'match_type' => WorkflowRule::MATCH_FROM_DOMAIN,
            'match_value' => 'mf.example.com',
            'assign_user_id' => $member->id,
            'priority' => 10,
            'is_active' => true,
        ]);

        $thread = $this->threadFrom('boss@mf.example.com');
        app(WorkflowEngine::class)->autoAssign($thread);

        $thread->refresh();
        $this->assertSame($member->id, $thread->assigned_user_id);
        $this->assertDatabaseHas('ext_workflow_logs', [
            'thread_id' => $thread->id,
            'assigned_user_id' => $member->id,
            'assigned_by' => WorkflowLog::ASSIGNED_BY_RULE,
        ]);
    }

    public function test_falls_back_to_round_robin_when_no_rule_matches()
    {
        User::factory()->create(['role' => 'member', 'name' => 'M1']);
        User::factory()->create(['role' => 'member', 'name' => 'M2']);

        $t1 = $this->threadFrom('first@unknown.com');
        $t2 = $this->threadFrom('second@unknown.com');

        app(WorkflowEngine::class)->autoAssign($t1);
        app(WorkflowEngine::class)->autoAssign($t2);

        $t1->refresh(); $t2->refresh();
        $this->assertNotNull($t1->assigned_user_id);
        $this->assertNotNull($t2->assigned_user_id);
        $this->assertNotSame($t1->assigned_user_id, $t2->assigned_user_id, 'RR で別ユーザに割当されるべき');

        $this->assertDatabaseHas('ext_workflow_logs', [
            'thread_id' => $t1->id,
            'assigned_by' => WorkflowLog::ASSIGNED_BY_ROUND_ROBIN,
        ]);
        $this->assertDatabaseHas('ext_workflow_logs', [
            'thread_id' => $t2->id,
            'assigned_by' => WorkflowLog::ASSIGNED_BY_ROUND_ROBIN,
        ]);
    }
}
