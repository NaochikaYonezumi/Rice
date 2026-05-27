<?php

namespace Tests\Feature;

use App\Models\Email;
use App\Models\EmailThread;
use App\Models\MailAccount;
use App\Models\MailSetting;
use App\Models\PendingEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        MailSetting::getSettings();
    }

    public function test_user_can_list_only_their_own_mail_accounts(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        MailAccount::create(['user_id' => $a->id, 'name' => 'A-account', 'email_address' => 'a@x.com']);
        MailAccount::create(['user_id' => $b->id, 'name' => 'B-account', 'email_address' => 'b@x.com']);

        $response = $this->actingAs($a)->get(route('mail-accounts.index'));
        $response->assertStatus(200);
        $response->assertSee('A-account');
        $response->assertDontSee('B-account');
    }

    public function test_create_mail_account(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->post(route('mail-accounts.store'), [
            'name' => '個人Gmail',
            'email_address' => 'me@gmail.com',
            'is_active' => 1,
            'inbox_protocol' => 'imap',
            'imap_host' => 'imap.gmail.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_username' => 'me@gmail.com',
            'imap_password' => 'secret',
            'imap_folder' => 'INBOX',
            'smtp_enabled' => 1,
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'smtp_username' => 'me@gmail.com',
            'smtp_password' => 'secret',
        ]);
        $response->assertRedirect(route('mail-accounts.index'));
        $this->assertDatabaseHas('mail_accounts', [
            'user_id' => $user->id,
            'name' => '個人Gmail',
            'email_address' => 'me@gmail.com',
        ]);
        // password should be stored encrypted (not plaintext)
        $account = MailAccount::where('user_id', $user->id)->first();
        $this->assertSame('secret', $account->imap_password);
        $raw = \DB::table('mail_accounts')->where('id', $account->id)->value('imap_password');
        $this->assertNotSame('secret', $raw, 'password should be encrypted in DB');
    }

    public function test_user_cannot_edit_another_users_account(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $account = MailAccount::create(['user_id' => $a->id, 'name' => 'A', 'email_address' => 'a@x.com']);

        $response = $this->actingAs($b)->get(route('mail-accounts.edit', $account));
        $response->assertStatus(403);
    }

    public function test_visibility_scope_hides_other_users_threads(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $sharedThread = EmailThread::create(['subject' => 'Shared', 'status' => 'inbox']);
        $aOwnedThread = EmailThread::create(['subject' => 'Private of A', 'status' => 'inbox', 'owner_user_id' => $a->id]);
        $bOwnedThread = EmailThread::create(['subject' => 'Private of B', 'status' => 'inbox', 'owner_user_id' => $b->id]);

        $visibleToA = EmailThread::visibleTo($a->id)->pluck('id')->all();
        sort($visibleToA);
        $expected = [$sharedThread->id, $aOwnedThread->id];
        sort($expected);
        $this->assertSame($expected, $visibleToA);

        // B cannot access A's thread via HTTP
        $response = $this->actingAs($b)->getJson(route('threads.show', $aOwnedThread));
        $response->assertStatus(403);
    }

    public function test_reply_with_mail_account_id_stores_it_on_pending_email(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::create([
            'user_id' => $user->id, 'name' => 'mine',
            'email_address' => 'me@x.com',
            'smtp_enabled' => true, 'smtp_host' => 'smtp.x.com', 'smtp_username' => 'me@x.com', 'smtp_password' => 'pw',
        ]);
        $thread = EmailThread::create(['subject' => 'T', 'status' => 'inbox']);
        $email = Email::create([
            'thread_id' => $thread->id, 'message_id' => 'm1', 'subject' => 'T',
            'from_address' => 'x@y.com', 'to_address' => 'me@x.com', 'body_text' => '..',
        ]);

        $response = $this->actingAs($user)->postJson(route('emails.reply', $email), [
            'to' => 'x@y.com',
            'body' => 'reply',
            'mail_account_id' => $account->id,
        ]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('pending_emails', [
            'in_reply_to_email_id' => $email->id,
            'mail_account_id' => $account->id,
        ]);
    }

    public function test_reply_rejects_other_users_account(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $bAccount = MailAccount::create([
            'user_id' => $b->id, 'name' => "B's", 'email_address' => 'b@x.com',
            'smtp_enabled' => true, 'smtp_host' => 'smtp', 'smtp_username' => 'b', 'smtp_password' => 'pw',
        ]);
        $thread = EmailThread::create(['subject' => 'T', 'status' => 'inbox']);
        $email = Email::create([
            'thread_id' => $thread->id, 'message_id' => 'm', 'subject' => 'T',
            'from_address' => 'x@y.com', 'to_address' => 'z@y.com', 'body_text' => '..',
        ]);

        $response = $this->actingAs($a)->postJson(route('emails.reply', $email), [
            'to' => 'x@y.com',
            'body' => 'reply',
            'mail_account_id' => $bAccount->id,
        ]);
        $response->assertStatus(403);
    }

    public function test_password_blank_on_update_keeps_existing(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::create([
            'user_id' => $user->id, 'name' => 'mine', 'email_address' => 'me@x.com',
            'inbox_protocol' => 'imap', 'imap_host' => 'h', 'imap_username' => 'u', 'imap_password' => 'original',
        ]);

        $response = $this->actingAs($user)->put(route('mail-accounts.update', $account), [
            'name' => 'mine renamed',
            'email_address' => 'me@x.com',
            'inbox_protocol' => 'imap',
            'imap_host' => 'h',
            'imap_username' => 'u',
            'imap_password' => '', // 空 → 既存値保持
        ]);
        $response->assertRedirect(route('mail-accounts.index'));
        $this->assertSame('original', $account->fresh()->imap_password);
        $this->assertSame('mine renamed', $account->fresh()->name);
    }
}
