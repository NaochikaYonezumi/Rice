<?php

namespace Tests\Feature;

use App\Models\Email;
use App\Models\EmailThread;
use App\Models\MailSetting;
use App\Models\PendingEmail;
use App\Models\ThreadMerge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Modules\MailClient\Services\EmailFetcher;
use Mockery\MockInterface;

class Phase2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        MailSetting::getSettings();
    }

    public function test_reply_saves_from_address()
    {
        $user = User::factory()->create();
        $thread = EmailThread::create(['subject' => 'Test', 'status' => 'inbox']);
        $email = Email::create(['thread_id' => $thread->id, 'message_id' => '123', 'subject' => 'Test', 'from_address' => 'sender@example.com', 'to_address' => 'me@example.com', 'body_text' => 'hello']);

        $response = $this->actingAs($user)->postJson(route('emails.reply', $email), [
            'from_address' => 'my-alias@example.com',
            'to' => 'sender@example.com',
            'body' => 'my reply',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('pending_emails', [
            'from_address' => 'my-alias@example.com',
            'to_address' => 'sender@example.com',
            'body' => 'my reply',
        ]);
    }

    public function test_approve_uses_pending_from_address()
    {
        $this->withoutExceptionHandling();
        $user = User::factory()->create();
        $thread = EmailThread::create(['subject' => 'Test', 'status' => 'inbox']);
        $email = Email::create(['thread_id' => $thread->id, 'message_id' => '123', 'subject' => 'Test', 'from_address' => 'sender@example.com', 'to_address' => 'me@example.com', 'body_text' => 'hello']);

        $pending = PendingEmail::create([
            'in_reply_to_email_id' => $email->id,
            'reply_type' => 'reply',
            'from_address' => 'my-alias@example.com',
            'to_address' => 'sender@example.com',
            'subject' => 'Re: Test',
            'body' => 'my reply',
            'status' => 'pending',
        ]);

        Mail::fake();

        $fetcherMock = $this->mock(EmailFetcher::class, function (MockInterface $mock) use ($thread) {
            $mock->shouldReceive('resolveThread')->andReturn($thread);
        });

        // Approve endpoint expects created_by_user_id check
        $user2 = User::factory()->create();
        $pending->created_by_user_id = $user2->id; // Someone else created it
        $pending->save();

        $response = $this->actingAs($user)->postJson(route('pending.approve', $pending));

        if ($response->status() !== 200) {
            dd($response->json());
        }

        $response->assertStatus(200);

        $this->assertDatabaseHas('emails', [
            'thread_id' => $thread->id,
            'from_address' => 'my-alias@example.com',
            'to_address' => 'sender@example.com',
            'body_text' => 'my reply',
        ]);
    }

    public function test_merge_creates_virtual_link()
    {
        $user = User::factory()->create();
        $targetThread = EmailThread::create(['subject' => 'Target', 'status' => 'inbox']);
        $sourceThread = EmailThread::create(['subject' => 'Source', 'status' => 'inbox']);
        $sourceEmail = Email::create(['thread_id' => $sourceThread->id, 'message_id' => '123', 'subject' => 'Source', 'from_address' => 'a@b.com', 'to_address' => 'c@d.com', 'body_text' => 'body']);

        $response = $this->actingAs($user)->postJson(route('threads.merge', $targetThread), [
            'merge_thread_id' => $sourceThread->id,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('thread_merges', [
            'target_thread_id' => $targetThread->id,
            'source_thread_id_original' => $sourceThread->id,
        ]);

        // Verify source thread subject is updated to target thread subject
        $this->assertEquals('Target', $sourceThread->fresh()->subject);

        // Verify source email is NOT moved to target thread
        $this->assertEquals($sourceThread->id, $sourceEmail->fresh()->thread_id);
    }

    public function test_search_hides_merged_source_threads()
    {
        $user = User::factory()->create();
        $targetThread = EmailThread::create(['subject' => 'Target', 'status' => 'inbox']);
        $sourceThread = EmailThread::create(['subject' => 'Source', 'status' => 'inbox']);
        
        ThreadMerge::create([
            'target_thread_id' => $targetThread->id,
            'source_thread_id_original' => $sourceThread->id,
            'source_subject' => 'Source',
            'source_tags' => [],
            'merged_email_ids' => [],
        ]);

        $response = $this->actingAs($user)->getJson(route('emails.search'));

        $response->assertStatus(200);
        $data = $response->json();

        $ids = array_column($data, 'id');
        $this->assertContains($targetThread->id, $ids);
        $this->assertNotContains($sourceThread->id, $ids);
    }

    public function test_thread_view_includes_merged_emails()
    {
        $user = User::factory()->create();
        $targetThread = EmailThread::create(['subject' => 'Target', 'status' => 'inbox']);
        $sourceThread = EmailThread::create(['subject' => 'Source', 'status' => 'inbox']);
        $sourceEmail = Email::create(['thread_id' => $sourceThread->id, 'message_id' => '123', 'subject' => 'Source', 'from_address' => 'a@b.com', 'to_address' => 'c@d.com', 'body_text' => 'body']);

        ThreadMerge::create([
            'target_thread_id' => $targetThread->id,
            'source_thread_id_original' => $sourceThread->id,
            'source_subject' => 'Source',
            'source_tags' => [],
            'merged_email_ids' => [$sourceEmail->id],
        ]);

        $response = $this->actingAs($user)->getJson(route('threads.show', $targetThread));

        $response->assertStatus(200);
        $data = $response->json();
        
        $emailIds = array_column($data['emails'], 'id');
        $this->assertContains($sourceEmail->id, $emailIds);
    }

    public function test_toggle_pin()
    {
        $user = User::factory()->create();
        $thread = EmailThread::create(['subject' => 'Test', 'is_pinned' => false]);

        $response = $this->actingAs($user)->postJson(route('threads.pin', $thread), ['is_pinned' => true]);
        $response->assertStatus(200);
        $this->assertTrue($thread->fresh()->is_pinned);

        $response = $this->actingAs($user)->postJson(route('threads.pin', $thread), ['is_pinned' => false]);
        $response->assertStatus(200);
        $this->assertFalse($thread->fresh()->is_pinned);
    }

    public function test_status_change_assigns_actor_when_thread_has_no_assignee(): void
    {
        $user = User::factory()->create();
        $thread = EmailThread::create(['subject' => 'X', 'status' => 'inbox']);
        $this->assertNull($thread->assigned_user_id);

        $response = $this->actingAs($user)->putJson(route('threads.status', $thread), [
            'status' => EmailThread::STATUS_DONE,
        ]);

        $response->assertStatus(200);
        $fresh = $thread->fresh();
        $this->assertSame(EmailThread::STATUS_DONE, $fresh->status);
        $this->assertSame($user->id, $fresh->assigned_user_id);
    }

    public function test_status_change_assigns_actor_for_any_status_not_just_completed(): void
    {
        $user = User::factory()->create();
        $thread = EmailThread::create(['subject' => 'X', 'status' => 'inbox']);

        $response = $this->actingAs($user)->putJson(route('threads.status', $thread), [
            'status' => EmailThread::STATUS_HOLD,
        ]);

        $response->assertStatus(200);
        $fresh = $thread->fresh();
        $this->assertSame(EmailThread::STATUS_HOLD, $fresh->status);
        $this->assertSame($user->id, $fresh->assigned_user_id);
    }

    public function test_status_change_does_not_overwrite_existing_assignee(): void
    {
        $previousAssignee = User::factory()->create();
        $actor = User::factory()->create();
        $thread = EmailThread::create([
            'subject' => 'X',
            'status' => 'inbox',
            'assigned_user_id' => $previousAssignee->id,
        ]);

        $response = $this->actingAs($actor)->putJson(route('threads.status', $thread), [
            'status' => EmailThread::STATUS_DONE,
        ]);

        $response->assertStatus(200);
        $this->assertSame($previousAssignee->id, $thread->fresh()->assigned_user_id);
    }

    public function test_reply_completes_thread_and_assigns_replier_when_unassigned(): void
    {
        $user = User::factory()->create();
        $thread = EmailThread::create(['subject' => 'X', 'status' => 'inbox']);
        $email = Email::create([
            'thread_id' => $thread->id,
            'message_id' => 'm-1',
            'subject' => 'X',
            'from_address' => 'a@example.com',
            'to_address' => 'b@example.com',
            'body_text' => 'q',
        ]);

        $response = $this->actingAs($user)->postJson(route('emails.reply', $email), [
            'to' => 'a@example.com',
            'body' => 'reply',
        ]);

        $response->assertStatus(200);
        $fresh = $thread->fresh();
        $this->assertSame(EmailThread::STATUS_DONE, $fresh->status);
        $this->assertSame($user->id, $fresh->assigned_user_id);
    }

    public function test_reply_does_not_overwrite_existing_assignee_even_when_completing(): void
    {
        $previousAssignee = User::factory()->create();
        $replier = User::factory()->create();
        $thread = EmailThread::create([
            'subject' => 'X',
            'status' => 'inbox',
            'assigned_user_id' => $previousAssignee->id,
        ]);
        $email = Email::create([
            'thread_id' => $thread->id,
            'message_id' => 'm-2',
            'subject' => 'X',
            'from_address' => 'a@example.com',
            'to_address' => 'b@example.com',
            'body_text' => 'q',
        ]);

        $response = $this->actingAs($replier)->postJson(route('emails.reply', $email), [
            'to' => 'a@example.com',
            'body' => 'reply',
        ]);

        $response->assertStatus(200);
        $fresh = $thread->fresh();
        $this->assertSame(EmailThread::STATUS_DONE, $fresh->status);
        $this->assertSame($previousAssignee->id, $fresh->assigned_user_id);
    }
}
