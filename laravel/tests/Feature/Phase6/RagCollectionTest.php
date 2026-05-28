<?php

namespace Tests\Feature\Phase6;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase 6-1: 顧客 RAG コレクション切替 E2E (Feature)
 */
class RagCollectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_create_with_rag_collection()
    {
        $user = User::factory()->create(['role' => 'admin']);

        $res = $this->actingAs($user)->postJson('/customers', [
            'name'           => 'MF',
            'email'          => 'mf@example.com',
            'domain'         => 'mf.example.com',
            'rag_collection' => 'mf_faq',
        ]);

        $res->assertOk();
        $this->assertDatabaseHas('customers', [
            'name' => 'MF',
            'rag_collection' => 'mf_faq',
        ]);
    }

    public function test_collections_endpoint_returns_db_distinct_when_python_unreachable()
    {
        $user = User::factory()->create();

        // Customer に 2 つ別々の rag_collection
        Customer::create(['name' => 'A', 'email' => 'a@a.com', 'rag_collection' => 'alpha_faq']);
        Customer::create(['name' => 'B', 'email' => 'b@b.com', 'rag_collection' => 'beta_faq']);
        // Python 側が応答しないようにモック
        Http::fake([
            '*/collections' => Http::response([], 503),
        ]);

        $res = $this->actingAs($user)->getJson('/api/knowledge/collections');

        $res->assertOk();
        $names = collect($res->json('collections'))->pluck('name')->all();
        $this->assertContains('alpha_faq', $names);
        $this->assertContains('beta_faq', $names);
        $this->assertContains('default', $names);
    }
}
