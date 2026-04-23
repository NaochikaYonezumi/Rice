@extends('layouts.app')
@section('title', 'ドキュメント')

@section('content')
<div class="flex flex-col h-full" x-data="docApp()">

    <div class="px-6 py-4 border-b border-gray-200 bg-white flex items-center justify-between">
        <h1 class="font-semibold text-gray-800">ドキュメント管理</h1>
        <span class="text-xs text-gray-400">PDF / Word / Markdown をアップロードしてRAGコーパスに追加</span>
    </div>

    <div class="flex-1 overflow-y-auto p-6">

        {{-- アップロードゾーン --}}
        <div class="bg-white rounded-xl border-2 border-dashed border-gray-200 hover:border-blue-400 transition-colors p-8 text-center mb-6"
             @dragover.prevent @drop.prevent="handleDrop($event)">
            <svg class="w-10 h-10 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
            </svg>
            <p class="text-sm text-gray-500 mb-3">ファイルをドラッグ＆ドロップ、または選択</p>
            <p class="text-xs text-gray-400 mb-4">対応形式: PDF, Word, Excel, PowerPoint, CSV, HTML, Markdown, テキスト, EPUB, RTF, ODT など — 最大 20MB</p>

            <div class="flex items-center justify-center gap-3">
                <label class="cursor-pointer bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded-lg">
                    ファイルを選択
                    <input type="file" class="hidden" accept="*/*"
                           multiple @change="handleFileSelect($event)">
                </label>
            </div>

            <div class="mt-4" x-show="uploading">
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-blue-600 h-2 rounded-full transition-all" :style="'width:' + uploadProgress + '%'"></div>
                </div>
            </div>
            <p class="text-xs mt-2" x-show="uploadStatus"
               :class="uploadStatus.includes('エラー') ? 'text-red-500' : 'text-gray-500'"
               x-text="uploadStatus"></p>
        </div>

        {{-- ドキュメント一覧 --}}
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium text-gray-500 text-xs uppercase tracking-wider">ファイル名</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-500 text-xs uppercase tracking-wider">種別</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-500 text-xs uppercase tracking-wider">インデックス</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-500 text-xs uppercase tracking-wider">登録日</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody id="doc-list">
                    @forelse($documents as $doc)
                    <tr class="border-b border-gray-50 hover:bg-gray-50" id="doc-{{ $doc->id }}">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $doc->original_name }}</td>
                        <td class="px-4 py-3">
                            <span class="bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded">{{ $doc->type_icon }}</span>
                        </td>
                        <td class="px-4 py-3">
                            @if($doc->is_indexed)
                            <span class="text-green-600 text-xs">✓ {{ $doc->chunks_indexed }} チャンク</span>
                            @else
                            <span class="text-gray-400 text-xs">未インデックス</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-400 text-xs">{{ $doc->created_at->format('Y/m/d H:i') }}</td>
                        <td class="px-4 py-3 text-right">
                            <button @click="deleteDoc({{ $doc->id }})"
                                class="text-red-400 hover:text-red-600 text-xs">削除</button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-400 text-sm">
                            ドキュメントがありません。ファイルをアップロードしてください。
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function docApp() {
    return {
        uploading: false,
        uploadProgress: 0,
        uploadStatus: '',

        handleFileSelect(event) {
            this.uploadFiles(Array.from(event.target.files));
        },

        handleDrop(event) {
            this.uploadFiles(Array.from(event.dataTransfer.files));
        },

        async uploadFiles(files) {
            if (files.length === 0) return;
            this.uploading = true;
            this.uploadProgress = 0;

            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                this.uploadStatus = `${file.name} をアップロード中... (${i + 1}/${files.length})`;

                const formData = new FormData();
                formData.append('file', file);
                formData.append('collection', 'default');
                formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

                try {
                    const res = await fetch('{{ route("documents.store") }}', {
                        method: 'POST',
                        body: formData,
                    });
                    const data = await res.json();
                    if (!res.ok) {
                        const msg = data.message || (data.errors ? JSON.stringify(data.errors) : res.status);
                        this.uploadStatus = `${file.name}: エラー - ${msg}`;
                        continue;
                    }
                    if (data.id) {
                        this.addDocRow(data);
                        this.uploadStatus = `${file.name} ✓ インデックス完了（${data.chunks_indexed}チャンク）`;
                    }
                } catch (e) {
                    this.uploadStatus = `${file.name}: エラー - ${e.message}`;
                }

                this.uploadProgress = Math.round(((i + 1) / files.length) * 100);
            }

            this.uploading = false;
        },

        addDocRow(doc) {
            const tbody = document.getElementById('doc-list');
            const emptyRow = tbody.querySelector('td[colspan]');
            if (emptyRow) emptyRow.closest('tr').remove();

            const tr = document.createElement('tr');
            tr.id = `doc-${doc.id}`;
            tr.className = 'border-b border-gray-50 hover:bg-gray-50';
            tr.innerHTML = `
                <td class="px-4 py-3 font-medium text-gray-800">${doc.original_name}</td>
                <td class="px-4 py-3"><span class="bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded">${doc.type_icon}</span></td>
<td class="px-4 py-3">${doc.is_indexed ? `<span class="text-green-600 text-xs">✓ ${doc.chunks_indexed} チャンク</span>` : '<span class="text-gray-400 text-xs">未インデックス</span>'}</td>
                <td class="px-4 py-3 text-gray-400 text-xs">今</td>
                <td class="px-4 py-3 text-right"><button @click="deleteDoc(${doc.id})" class="text-red-400 hover:text-red-600 text-xs">削除</button></td>
            `;
            tbody.prepend(tr);
        },

        async deleteDoc(id) {
            if (!confirm('削除しますか？ChromaDBのインデックスも削除されます。')) return;
            try {
                await fetch(`/documents/${id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                });
                document.getElementById(`doc-${id}`)?.remove();
            } catch (e) {
                alert('削除に失敗しました: ' + e.message);
            }
        },
    };
}
</script>
@endsection
