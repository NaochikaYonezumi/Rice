<?php

namespace Tests\Unit;

use App\Models\AiSetting;
use App\Models\Customer;
use App\Models\Email;
use App\Models\EmailThread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AIReply\Services\RagCollectionResolver;
use Tests\TestCase;

/**
 * Phase 6-1: RAG コレクション解決ロジックの Unit テスト
 *
 * 優先順位:
 *  1. customer.rag_collection
 *  2. customer.domain 一致顧客の rag_collection
 *  3. 送信元ドメイン一致顧客の rag_collection
 *  4. AiSetting.default_collection
 *  5. 'default'
 */
class RagCollectionResolverTest extends TestCase
{
    use RefreshDatabase;

    private function resolver(): RagCollectionResolver
    {
        return app(RagCollectionResolver::class);
    }

    public function test_customer_rag_collection_is_used_first()
    {
        $customer = Customer::create([
            'name' => 'MF',
            'email' => 'mf@example.com',
            'domain' => 'mf.example.com',
            'rag_collection' => 'mf_faq',
        ]);
        $thread = EmailThread::create([
            'subject' => 'Test',
            'customer_id' => $customer->id,
            'status' => 'inbox',
        ]);

        $this->assertSame('mf_faq', $this->resolver()->resolve($thread));
    }

    public function test_falls_back_to_customer_domain_match()
    {
        // 直接の customer は null だが、同じ domain の別 customer に rag_collection あり
        $customer = Customer::create([
            'name' => 'Hive (no collection)',
            'email' => 'a@hive.example',
            'domain' => 'hive.example',
        ]);
        Customer::create([
            'name' => 'Hive (with collection)',
            'email' => 'b@hive.example',
            'domain' => 'hive.example',
            'rag_collection' => 'hive_faq',
        ]);
        $thread = EmailThread::create([
            'subject' => 'Test',
            'customer_id' => $customer->id,
            'status' => 'inbox',
        ]);

        $this->assertSame('hive_faq', $this->resolver()->resolve($thread));
    }

    public function test_falls_back_to_from_address_domain()
    {
        Customer::create([
            'name' => 'Example',
            'email' => 'support@example.org',
            'domain' => 'example.org',
            'rag_collection' => 'example_faq',
        ]);
        $thread = EmailThread::create(['subject' => 'Test', 'status' => 'inbox']);
        Email::create([
            'thread_id' => $thread->id,
            'subject' => 'Test',
            'from_address' => 'someone@example.org',
            'to_address' => 'us@yourcompany.com',
            'body_text' => '',
            'received_at' => now(),
        ]);

        $this->assertSame('example_faq', $this->resolver()->resolve($thread->fresh()));
    }

    public function test_falls_back_to_ai_settings_default_collection()
    {
        AiSetting::getSettings()->update(['default_collection' => 'global_faq']);
        $thread = EmailThread::create(['subject' => 'Test', 'status' => 'inbox']);

        $this->assertSame('global_faq', $this->resolver()->resolve($thread->fresh()));
    }

    public function test_final_fallback_returns_default_literal()
    {
        // AiSetting レコードを default_collection NULL に
        $settings = AiSetting::getSettings();
        $settings->default_collection = null;
        $settings->save();

        $thread = EmailThread::create(['subject' => 'Test', 'status' => 'inbox']);

        $this->assertSame('default', $this->resolver()->resolve($thread->fresh()));
    }
}
