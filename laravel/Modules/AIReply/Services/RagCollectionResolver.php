<?php

namespace Modules\AIReply\Services;

use App\Models\AiSetting;
use App\Models\Customer;
use App\Models\EmailThread;

/**
 * スレッドから RAG コレクション名を解決するサービス。
 *
 * 優先順位:
 *  1. thread.customer.rag_collection が設定済み → それを返す
 *  2. thread.customer.domain と一致する customers の rag_collection
 *  3. スレッド最新メールの送信元ドメインと一致する customers の rag_collection
 *  4. AiSetting.default_collection
 *  5. 'default'
 *
 * ハードコードされた collection 名 (例: 'mf_faq') は使わない。
 * 全て DB 経由で解決する。
 */
class RagCollectionResolver
{
    /**
     * 指定スレッドに対する RAG コレクション名を返す。常に非空文字列。
     */
    public function resolve(EmailThread $thread): string
    {
        // (1) 直接紐付いた customer に rag_collection があればそれを使う
        $customer = $thread->customer;
        if ($customer && $this->nonEmpty($customer->rag_collection)) {
            return (string) $customer->rag_collection;
        }

        // (2) thread.customer の domain で他の customers を引き当てる
        //     (同じ会社で複数顧客レコードがある場合に rag_collection が設定された方を使う想定)
        if ($customer && $this->nonEmpty($customer->domain)) {
            $byCustomerDomain = Customer::where('domain', $customer->domain)
                ->whereNotNull('rag_collection')
                ->where('rag_collection', '!=', '')
                ->orderBy('id')
                ->first();
            if ($byCustomerDomain && $this->nonEmpty($byCustomerDomain->rag_collection)) {
                return (string) $byCustomerDomain->rag_collection;
            }
        }

        // (3) 最新メールの送信元ドメインで引き当てる
        $fromDomain = $this->extractFromDomain($thread);
        if ($this->nonEmpty($fromDomain)) {
            $byFromDomain = Customer::where('domain', $fromDomain)
                ->whereNotNull('rag_collection')
                ->where('rag_collection', '!=', '')
                ->orderBy('id')
                ->first();
            if ($byFromDomain && $this->nonEmpty($byFromDomain->rag_collection)) {
                return (string) $byFromDomain->rag_collection;
            }
        }

        // (4) AiSetting の default_collection
        try {
            $settings = AiSetting::getSettings();
            if ($settings && $this->nonEmpty($settings->default_collection)) {
                return (string) $settings->default_collection;
            }
        } catch (\Throwable $e) {
            // テーブル未マイグレ等は無視
        }

        // (5) 最終フォールバック
        return 'default';
    }

    /**
     * スレッドの最新メール (受信ベース) の from_address からドメインを抽出。
     */
    private function extractFromDomain(EmailThread $thread): ?string
    {
        $latest = $thread->emails()->orderByDesc('received_at')->first();
        if (!$latest || empty($latest->from_address)) {
            return null;
        }
        $email = (string) $latest->from_address;
        $at = strrpos($email, '@');
        if ($at === false) return null;
        $domain = substr($email, $at + 1);
        return $domain !== false ? trim($domain) : null;
    }

    private function nonEmpty($value): bool
    {
        return $value !== null && $value !== '' && trim((string) $value) !== '';
    }
}
