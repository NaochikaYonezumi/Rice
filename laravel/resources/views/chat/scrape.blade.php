@extends('layouts.app')
@section('title', 'URL スクレイピング')

@section('content')
<div class="flex flex-col h-full" x-data="scrapeApp()">

    <div class="px-6 py-4 border-b border-gray-200 bg-white flex items-center justify-between">
        <h1 class="font-semibold text-gray-800">URL スクレイピング</h1>
        <span class="text-xs text-gray-400">WebページをRAGコーパスに追加</span>
    </div>

    <div class="flex-1 overflow-y-auto p-6">

        {{-- 入力フォーム --}}
        <div class="bg-white rounded-xl border border-gray-100 p-6 mb-6">
            <div class="flex gap-3">
                <input type="url" x-model="url" placeholder="https://example.com/page"
                       @keydown.enter.prevent="submit()"
                       class="flex-1 border border-gray-200 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-300">
                <button @click="submit()" :disabled="loading || !url"
                        class="bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white text-sm px-5 py-2 rounded-lg whitespace-nowrap">
                    <span x-show="!loading">追加</span>
                    <span x-show="loading">処理中...</span>
                </button>
            </div>
            <p class="text-xs mt-2" x-show="status"
               :class="isError ? 'text-red-500' : 'text-green-600'"
               x-text="status"></p>
        </div>

        {{-- URL 一覧 --}}
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                <span class="text-sm font-medium text-gray-700">登録済み URL</span>
                <span class="text-xs text-gray-400" x-text="rows.length + ' 件'"></span>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium text-gray-500 text-xs uppercase tracking-wider">URL</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-500 text-xs uppercase tracking-wider">チャンク</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-500 text-xs uppercase tracking-wider">登録日</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="rows.length === 0">
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-400 text-sm">
                                URLが登録されていません
                            </td>
                        </tr>
                    </template>
                    <template x-for="row in rows" :key="row.id">
                        <tr class="border-b border-gray-50 hover:bg-gray-50">
                            <td class="px-4 py-3 max-w-xs">
                                <a :href="row.url" target="_blank"
                                   class="text-blue-600 hover:underline text-xs truncate block"
                                   :title="row.url" x-text="row.url"></a>
                            </td>
                            <td class="px-4 py-3">
                                <span x-show="row.status === 'ok'" class="text-green-600 text-xs" x-text="'✓ ' + row.chunks_indexed + ' チャンク'"></span>
                                <span x-show="row.status !== 'ok'" class="text-red-400 text-xs">エラー</span>
                            </td>
                            <td class="px-4 py-3 text-gray-400 text-xs" x-text="row.created_at"></td>
                            <td class="px-4 py-3 text-right">
                                <button @click="deleteUrl(row.id)"
                                        class="text-red-400 hover:text-red-600 text-xs">削除</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function scrapeApp() {
    return {
        url: '',
        loading: false,
        status: '',
        isError: false,
        rows: @json($urlRows),

        async submit() {
            if (!this.url || this.loading) return;
            this.loading = true;
            this.status = '';
            this.isError = false;

            try {
                const res = await fetch('{{ route("scrape.store") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ url: this.url, collection: 'default' }),
                });
                const data = await res.json();

                if (!res.ok) {
                    this.isError = true;
                    this.status = 'エラー: ' + (data.error || res.status);
                } else {
                    this.status = `✓ ${data.chunks_added ?? 0} チャンク追加しました`;
                    this.rows.unshift({
                        id: data.id,
                        url: this.url,
                        collection: this.collection,
                        chunks_indexed: data.chunks_added ?? 0,
                        status: 'ok',
                        created_at: '今',
                    });
                    this.url = '';
                }
            } catch (e) {
                this.isError = true;
                this.status = 'エラー: ' + e.message;
            } finally {
                this.loading = false;
            }
        },

        async deleteUrl(id) {
            if (!confirm('このURLをインデックスから削除しますか？')) return;
            try {
                await fetch(`/scrape/url/${id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                });
                this.rows = this.rows.filter(r => r.id !== id);
            } catch (e) {
                alert('削除に失敗しました: ' + e.message);
            }
        },
    };
}
</script>
@endsection
