@extends('layouts.app')
@section('title', 'ナレッジベース')
@section('header', 'ナレッジベース管理')

@section('css')
<style>
    [x-cloak] { display: none !important; }
    .knowledge-status-badge { font-size: 0.75rem; }
    .knowledge-url-cell {
        word-break: break-all;
        max-width: 420px;
    }
    .knowledge-error {
        font-size: 0.75rem;
        color: #dc3545;
        word-break: break-all;
    }
    /* タグ風コレクションチップ */
    .collection-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 9px;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 600;
        background-color: #eef2ff;
        color: #4338ca;
        border: 1px solid #c7d2fe;
        cursor: pointer;
        transition: background-color 0.15s;
        max-width: 100%;
        white-space: nowrap;
    }
    .collection-chip:hover {
        background-color: #e0e7ff;
        border-color: #a5b4fc;
    }
    .collection-chip .collection-name {
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .table-active {
        background-color: #fff7ed !important;
        outline: 2px solid #fdba74;
    }
    /* 長押し中のフィードバック */
    .long-press-active {
        background-color: #fef3c7 !important;
        transition: background-color 0.2s;
    }
    /* 詳細パネル */
    .detail-panel { box-shadow: -8px 0 24px rgba(0,0,0,0.08); }
    .detail-resize-handle {
        position: absolute; left: 0; top: 0; height: 100%; width: 6px;
        cursor: col-resize; background-color: transparent; transition: background-color 0.15s;
        z-index: 5;
    }
    .detail-resize-handle:hover, .detail-resize-handle:active {
        background-color: rgba(99, 102, 241, 0.5);
    }
    /* 詳細表示中の行ハイライト */
    .table-info {
        background-color: #dbeafe !important;
    }
    /* 複数コレクション表示: チップを横並びに、はみ出したら折り返し */
    .collection-chip-list {
        display: flex;
        align-items: center;
        gap: 4px;
        flex-wrap: wrap;
        max-width: 100%;
    }
    .collection-chip-list .collection-chip {
        font-size: 0.7rem;
        padding: 2px 7px;
    }
    .collection-chip-list .collection-chip-edit {
        background-color: #f3f4f6;
        color: #4b5563;
        border-color: #d1d5db;
        font-size: 0.7rem;
        padding: 2px 6px;
    }
    .collection-chip-list .collection-chip-edit:hover {
        background-color: #e5e7eb;
    }

    /* ===== ダークモード上書き ===== */
    html.theme-dark .collection-chip {
        background-color: rgba(88,101,242,0.18) !important;
        color: #c7d2fe !important;
        border-color: #4338ca !important;
    }
    html.theme-dark .collection-chip:hover {
        background-color: rgba(88,101,242,0.3) !important;
        border-color: #818cf8 !important;
    }
    html.theme-dark .collection-chip-list .collection-chip-edit {
        background-color: #2b2d31 !important;
        color: #cbd5e1 !important;
        border-color: #3f4147 !important;
    }
    html.theme-dark .collection-chip-list .collection-chip-edit:hover {
        background-color: #3f4147 !important;
    }
    html.theme-dark .table-active {
        background-color: rgba(251,191,36,0.12) !important;
        outline-color: #92400e !important;
        color: #fde68a !important;
    }
    html.theme-dark .long-press-active {
        background-color: rgba(251,191,36,0.2) !important;
    }
    html.theme-dark .table-info {
        background-color: rgba(88,101,242,0.18) !important;
    }
    html.theme-dark .knowledge-error { color: #fca5a5 !important; }
    /* テーブル全体 */
    html.theme-dark table.table thead th {
        background-color: #202225 !important;
        color: #b9bbbe !important;
        border-bottom-color: #42454a !important;
    }
    html.theme-dark table.table tbody tr {
        background-color: #2f3136 !important;
        color: #dcddde !important;
    }
    html.theme-dark table.table tbody tr:hover {
        background-color: #34363c !important;
    }
    html.theme-dark table.table td,
    html.theme-dark table.table th { border-color: #42454a !important; }
</style>
@endsection

@section('content')
<div style="padding-left:8.333%;padding-right:8.333%;">
{{-- 共通アラート (両フォーム共有) --}}
@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

{{-- ファイル取り込み (1行ミニバー) --}}
<form action="{{ route('knowledge.upload') }}" method="POST" enctype="multipart/form-data"
      class="d-flex align-items-center bg-white border rounded p-2 mb-2"
      style="gap:8px;"
      x-data="collectionPicker('default')">
    @csrf
    <i class="fas fa-file-upload text-success ml-1"></i>
    <div class="custom-file" style="flex:7 1 0;min-width:0;">
        <input type="file" name="file" id="file" class="custom-file-input" required
               accept=".pdf,.docx,.doc,.pptx,.ppt,.xlsx,.xls,.csv,.txt,.md,.rtf,.html,.htm,.epub,.odt,.ods,.odp,.png,.jpg,.jpeg,.gif,.webp,.bmp,.tif,.tiff,.heic,.heif"
               onchange="document.getElementById('file-label').innerText = this.files[0]?.name || 'ファイル選択';">
        <label class="custom-file-label" id="file-label" for="file">ファイル選択 (PDF / Word / Excel / PowerPoint / 画像 OCR)</label>
    </div>

    {{-- コレクション入力 + オートコンプリート (ファイル選択:タグ = 7:3)
         複数指定する場合は カンマ区切り。例: "モビリティ,EV,2025年版" --}}
    <div class="position-relative" style="flex:3 1 0;min-width:0;" @click.outside="acOpen = false">
        <input type="text" name="collection" class="form-control form-control-sm"
               x-model="value"
               @focus="acOpen = true; loadCollections()"
               @input="acOpen = true; acIdx = 0"
               @keydown.arrow-down.prevent="acIdx = Math.min(acIdx + 1, filtered.length - 1)"
               @keydown.arrow-up.prevent="acIdx = Math.max(acIdx - 1, 0)"
               @keydown.enter="if(acOpen && filtered[acIdx]) { $event.preventDefault(); insertCollectionToken(filtered[acIdx].name); acOpen = false; }"
               @keydown.escape="acOpen = false"
               placeholder="コレクション (例: モビリティ,EV)  ※カンマ区切りで複数可" maxlength="512" autocomplete="off">
        <div x-show="acOpen && filtered.length > 0" x-cloak
             class="position-absolute bg-white border rounded shadow-sm"
             style="top:100%;left:0;right:0;z-index:20;max-height:240px;overflow-y:auto;margin-top:2px;">
            <template x-for="(c, idx) in filtered" :key="c.name + idx">
                <div @mousedown.prevent="value = c.name; acOpen = false"
                     @mouseenter="acIdx = idx"
                     :class="idx === acIdx ? 'bg-light' : ''"
                     class="px-2 py-1 small" style="cursor:pointer;">
                    <i class="fas fa-tag text-secondary mr-1" style="font-size:10px;"></i>
                    <span x-text="c.name"></span>
                    <span x-show="c.source === 'rag-api'" class="text-muted ml-1" style="font-size:9px;">rag</span>
                </div>
            </template>
            <template x-if="value && !filtered.some(c => c.name === value)">
                <div class="px-2 py-1 small border-top text-success" style="background-color:#f0fdf4;">
                    <i class="fas fa-plus-circle mr-1"></i>
                    <span>「<span x-text="value"></span>」を新規作成</span>
                </div>
            </template>
        </div>
    </div>

    <button type="submit" class="btn btn-sm btn-success flex-shrink-0">
        <i class="fas fa-upload"></i> 取り込む
    </button>
</form>

{{-- 外部サイトの取り込み (折り畳み: Alpine 制御) --}}
<div class="card card-primary mb-3" x-data="{ crawlOpen: false }">
    <div class="card-header d-flex align-items-center" style="cursor:pointer;"
         @click="crawlOpen = !crawlOpen">
        <i class="fas fa-spider mr-2"></i>
        <h3 class="card-title mb-0 flex-grow-1">外部サイトの取り込み (クローリング)</h3>
        <i class="fas text-white" :class="crawlOpen ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
    </div>
    <div x-show="crawlOpen" x-collapse style="display:none;">
        <form action="{{ route('knowledge.crawl') }}" method="POST" x-data="collectionPicker(@js(old('collection', 'default')))">
            @csrf
            <div class="card-body">
                <div class="form-group">
                    <label for="url">ベースURL</label>
                    <input type="url" name="url" id="url" class="form-control"
                           placeholder="https://manual.example.com/" required value="{{ old('url') }}">
                    <small class="text-muted">※このURL配下の内部リンクを再帰的に巡回して取り込みます。</small>
                </div>

                <div class="form-group">
                    <label for="collection">コレクション (任意 / カンマ区切りで複数指定可)</label>
                    <div class="position-relative" @click.outside="acOpen = false">
                        <input type="text" name="collection" id="collection" class="form-control"
                               x-model="value"
                               @focus="acOpen = true; loadCollections()"
                               @input="acOpen = true; acIdx = 0"
                               @keydown.arrow-down.prevent="acIdx = Math.min(acIdx + 1, filtered.length - 1)"
                               @keydown.arrow-up.prevent="acIdx = Math.max(acIdx - 1, 0)"
                               @keydown.enter="if(acOpen && filtered[acIdx]) { $event.preventDefault(); insertCollectionToken(filtered[acIdx].name); acOpen = false; }"
                               @keydown.escape="acOpen = false"
                               placeholder="default (例: モビリティ,EV)" autocomplete="off">
                        <div x-show="acOpen && filtered.length > 0" x-cloak
                             class="position-absolute bg-white border rounded shadow-sm"
                             style="top:100%;left:0;right:0;z-index:20;max-height:240px;overflow-y:auto;margin-top:2px;">
                            <template x-for="(c, idx) in filtered" :key="c.name + idx">
                                <div @mousedown.prevent="value = c.name; acOpen = false"
                                     @mouseenter="acIdx = idx"
                                     :class="idx === acIdx ? 'bg-light' : ''"
                                     class="px-2 py-1 small" style="cursor:pointer;">
                                    <i class="fas fa-tag text-secondary mr-1" style="font-size:10px;"></i>
                                    <span x-text="c.name"></span>
                                </div>
                            </template>
                            <template x-if="value && !filtered.some(c => c.name === value)">
                                <div class="px-2 py-1 small border-top text-success" style="background-color:#f0fdf4;">
                                    <i class="fas fa-plus-circle mr-1"></i>
                                    <span>「<span x-text="value"></span>」を新規作成</span>
                                </div>
                            </template>
                        </div>
                    </div>
                    <small class="text-muted">顧客ごとに RAG を切り替えたい場合に使用します。カンマで複数タグを付けられます。</small>
                </div>

                <div class="form-row">
                    <div class="form-group col-sm-6">
                        <label for="max_pages">最大ページ数</label>
                        <input type="number" name="max_pages" id="max_pages" class="form-control"
                               min="1" max="300" value="{{ old('max_pages', 30) }}">
                        <small class="text-muted">この件数まで取得して打ち切ります。</small>
                    </div>
                    <div class="form-group col-sm-6">
                        <label for="max_depth">巡回深さ</label>
                        <input type="number" name="max_depth" id="max_depth" class="form-control"
                               min="0" max="5" value="{{ old('max_depth', 2) }}">
                        <small class="text-muted">0 = 指定URLのみ / 2 推奨</small>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-spider"></i> クローリング開始
                </button>
                <small class="text-muted ml-2">※サイト規模によって数十秒〜数分かかります。</small>
            </div>
        </form>
    </div>
</div>

<div class="row mt-3">
    <div class="col-12">
        <div class="card" x-data="knowledgeListApp()" x-init="init()">
            <div class="card-header d-flex align-items-center" style="gap:8px;">
                <h3 class="card-title mb-0">登録済みソース一覧</h3>

                {{-- 選択モード操作バー --}}
                <template x-if="selectionMode">
                    <div class="ml-auto d-flex align-items-center" style="gap:8px;flex:1;justify-content:flex-end;">
                        <span class="badge badge-info"><i class="fas fa-check-square mr-1"></i><span x-text="selectedIds.length"></span>件選択中</span>

                        {{-- 動作モード: 置換 / 追加 / 削除 --}}
                        <select class="form-control form-control-sm" style="width:auto;" x-model="bulkMode" title="動作モード">
                            <option value="replace">置換</option>
                            <option value="append">追加</option>
                            <option value="remove">削除</option>
                        </select>
                        {{-- コレクション一括変更 (オートコンプリート付き / カンマ区切り複数指定可) --}}
                        <div class="position-relative" style="min-width:260px;" @click.outside="bulkAcOpen = false">
                            <input type="text" class="form-control form-control-sm"
                                   x-model="bulkCollection"
                                   @focus="bulkAcOpen = true; loadCollections()"
                                   @input="bulkAcOpen = true; bulkAcIdx = 0"
                                   @keydown.arrow-down.prevent="bulkAcIdx = Math.min(bulkAcIdx + 1, filteredCollections.length - 1)"
                                   @keydown.arrow-up.prevent="bulkAcIdx = Math.max(bulkAcIdx - 1, 0)"
                                   @keydown.enter.prevent="filteredCollections[bulkAcIdx] ? (bulkInsertToken(filteredCollections[bulkAcIdx].name), bulkAcOpen = false) : applyBulkCollection()"
                                   @keydown.escape="bulkAcOpen = false"
                                   placeholder="コレクション名 (カンマで複数指定可)" maxlength="512">
                            {{-- 候補リスト --}}
                            <div x-show="bulkAcOpen && filteredCollections.length > 0" x-cloak
                                 class="position-absolute bg-white border rounded shadow-sm"
                                 style="top:100%;left:0;right:0;z-index:10;max-height:200px;overflow-y:auto;">
                                <template x-for="(c, idx) in filteredCollections" :key="c.name + idx">
                                    <div @mousedown.prevent="bulkInsertToken(c.name); bulkAcOpen = false"
                                         @mouseenter="bulkAcIdx = idx"
                                         :class="idx === bulkAcIdx ? 'bg-light' : ''"
                                         class="px-2 py-1 small" style="cursor:pointer;">
                                        <i class="fas fa-tag text-secondary mr-1" style="font-size:10px;"></i>
                                        <span x-text="c.name"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary" @click="applyBulkCollection()"
                                :disabled="_parseBulkTokens().length === 0 || selectedIds.length === 0">
                            <i class="fas fa-check"></i> 適用
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" @click="exitSelectionMode()">
                            <i class="fas fa-times"></i> 解除
                        </button>
                    </div>
                </template>
                <template x-if="!selectionMode">
                    <small class="ml-auto text-muted">💡 行を長押し (0.5秒以上) で選択モード</small>
                </template>
            </div>

            <div class="card-body p-0">
                @if($sources->isEmpty())
                    <div class="p-3 text-muted" id="knowledge-empty-message">
                        まだ登録されたソースはありません。
                    </div>
                @endif
                <div class="table-responsive" id="knowledge-table-wrap" @if($sources->isEmpty()) style="display:none" @endif>
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th x-show="selectionMode" style="width:3%;">
                                    <input type="checkbox" :checked="selectedIds.length === allIds.length && allIds.length > 0"
                                           :indeterminate.prop="selectedIds.length > 0 && selectedIds.length < allIds.length"
                                           @change="$event.target.checked ? selectAll() : clearSelection()">
                                </th>
                                <th style="width: 7%;">種別</th>
                                <th style="width: 33%;">ソース / タイトル</th>
                                <th style="width: 14%;">コレクション</th>
                                <th style="width: 10%;">状態</th>
                                <th style="width: 9%;" class="text-right">チャンク</th>
                                <th style="width: 12%;">最終更新</th>
                                <th style="width: 12%;" class="text-right">操作</th>
                            </tr>
                        </thead>
                        <tbody id="knowledge-sources-tbody">
                            @foreach($sources as $source)
                                @php
                                    $type = $source->source_type ?: 'url';
                                    $typeIcon = ['url' => 'fa-globe', 'file' => 'fa-file-alt', 'email' => 'fa-envelope-open-text'][$type] ?? 'fa-circle';
                                    $typeLabel = ['url' => 'URL', 'file' => 'ファイル', 'email' => 'メール'][$type] ?? $type;
                                    $typeColor = ['url' => 'primary', 'file' => 'success', 'email' => 'warning'][$type] ?? 'secondary';
                                @endphp
                                <tr data-source-id="{{ $source->id }}"
                                    :class="[selectedIds.includes({{ $source->id }}) ? 'table-active' : '', detailId === {{ $source->id }} ? 'table-info' : '']"
                                    @mousedown="startLongPress({{ $source->id }}, $event)"
                                    @mouseup="cancelLongPress()"
                                    @mouseleave="cancelLongPress()"
                                    @touchstart="startLongPress({{ $source->id }}, $event)"
                                    @touchend="cancelLongPress()"
                                    @click="onRowClick({{ $source->id }}, $event)"
                                    style="cursor:pointer;">
                                    <td x-show="selectionMode">
                                        <input type="checkbox" :checked="selectedIds.includes({{ $source->id }})"
                                               @click.stop="toggleSelect({{ $source->id }})">
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $typeColor }}"><i class="fas {{ $typeIcon }} mr-1"></i>{{ $typeLabel }}</span>
                                    </td>
                                    <td class="knowledge-url-cell">
                                        @if($type === 'url')
                                            <a href="{{ $source->url }}" target="_blank" rel="noopener" @click.stop>{{ $source->url }}</a>
                                        @else
                                            <strong>{{ $source->title ?: $source->url }}</strong>
                                            <small class="d-block text-muted" title="{{ $source->url }}">{{ \Illuminate\Support\Str::limit($source->url, 60) }}</small>
                                        @endif
                                        <div class="knowledge-error" data-error>
                                            @if($source->error_message){{ \Illuminate\Support\Str::limit($source->error_message, 200) }}@endif
                                        </div>
                                    </td>
                                    <td class="knowledge-collection-cell" data-collection-cell>
                                        @php
                                            $cols = method_exists($source, 'allCollections')
                                                ? $source->allCollections()
                                                : [($source->collection ?: 'default')];
                                            $joinedCols = implode(',', $cols);
                                        @endphp
                                        {{-- タグ風チップを 1 件ずつ。ペンアイコンの「+ 編集」ボタンで一括編集 --}}
                                        <div class="collection-chip-list collection-display"
                                             data-source-id="{{ $source->id }}"
                                             data-collection="{{ $joinedCols }}"
                                             title="クリックで編集">
                                            @foreach($cols as $col)
                                                <span class="collection-chip"
                                                      @click.stop="!selectionMode && showCollectionEditor({{ $source->id }}, $event)">
                                                    <i class="fas fa-tag" style="font-size:9px;"></i>
                                                    <span class="collection-name">{{ $col }}</span>
                                                </span>
                                            @endforeach
                                            <span class="collection-chip collection-chip-edit"
                                                  @click.stop="!selectionMode && showCollectionEditor({{ $source->id }}, $event)"
                                                  title="コレクションを編集">
                                                <i class="fas fa-pen" style="font-size:9px;"></i>
                                                編集
                                            </span>
                                        </div>
                                        {{-- インライン編集 (オートコンプリート付き / カンマ区切り複数指定可) --}}
                                        <div class="position-relative d-none collection-edit-wrap" style="min-width:260px;"
                                             @click.outside="if (inlineEditId === {{ $source->id }}) saveInline({{ $source->id }})">
                                            <input type="text" class="form-control form-control-sm collection-edit"
                                                   maxlength="512"
                                                   data-source-id="{{ $source->id }}"
                                                   x-model="inlineValue"
                                                   placeholder="例: モビリティ,EV,2025年版"
                                                   @focus="inlineAcOpen = true; loadInlineCollections()"
                                                   @input="inlineAcOpen = true; inlineAcIdx = 0"
                                                   @keydown.arrow-down.prevent="inlineAcIdx = Math.min(inlineAcIdx + 1, filteredInline.length - 1)"
                                                   @keydown.arrow-up.prevent="inlineAcIdx = Math.max(inlineAcIdx - 1, 0)"
                                                   @keydown.enter.prevent="if(inlineAcOpen && filteredInline[inlineAcIdx]) { inlineInsertToken(filteredInline[inlineAcIdx].name); inlineAcOpen = false; } else { saveInline({{ $source->id }}); }"
                                                   @keydown.escape="hideInlineEditor()"
                                                   @click.stop
                                                   x-show="inlineEditId === {{ $source->id }}">
                                            <div x-show="inlineEditId === {{ $source->id }} && inlineAcOpen && filteredInline.length > 0" x-cloak
                                                 class="position-absolute bg-white border rounded shadow-sm"
                                                 style="top:100%;left:0;right:0;z-index:30;max-height:200px;overflow-y:auto;margin-top:2px;">
                                                <template x-for="(c, idx) in filteredInline" :key="c.name + idx">
                                                    <div @mousedown.prevent="inlineInsertToken(c.name); inlineAcOpen = false"
                                                         @mouseenter="inlineAcIdx = idx"
                                                         :class="idx === inlineAcIdx ? 'bg-light' : ''"
                                                         class="px-2 py-1 small" style="cursor:pointer;">
                                                        <i class="fas fa-tag text-secondary mr-1" style="font-size:10px;"></i>
                                                        <span x-text="c.name"></span>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        @php
                                            $statusLabel = ['ok' => '取得済', 'pending' => '処理中', 'error' => 'エラー'][$source->status] ?? $source->status;
                                            $statusClass = ['ok' => 'badge-success', 'pending' => 'badge-warning', 'error' => 'badge-danger'][$source->status] ?? 'badge-secondary';
                                        @endphp
                                        <span class="badge {{ $statusClass }} knowledge-status-badge" data-status="{{ $source->status }}">{{ $statusLabel }}</span>
                                    </td>
                                    <td class="text-right" data-chunks>
                                        {{ number_format($source->chunks_indexed ?? 0) }}
                                    </td>
                                    <td data-updated>
                                        <small>
                                            @if($source->updated_at)
                                                {{ $source->updated_at->format('Y-m-d H:i') }}
                                            @else
                                                -
                                            @endif
                                        </small>
                                    </td>
                                    <td class="text-right" @click.stop>
                                        <form action="{{ route('knowledge.refresh', $source) }}"
                                              method="POST" class="d-inline"
                                              onsubmit="return confirm('このソースを再クロールします。続行しますか？');">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-primary" title="再クロール">
                                                <i class="fas fa-sync-alt"></i>
                                            </button>
                                        </form>
                                        <form action="{{ route('knowledge.destroy', $source) }}"
                                              method="POST" class="d-inline"
                                              onsubmit="return confirm('このソースを削除します。続行しますか？');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="削除">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ===== 側パネル (詳細表示・編集) ===== --}}
            <div x-show="detailOpen" x-cloak
                 :style="'width:' + detailWidth + 'px;'"
                 class="position-fixed bg-white border-left shadow-lg detail-panel"
                 style="top:0;right:0;height:100vh;z-index:1050;display:flex;flex-direction:column;">

                {{-- リサイズハンドル (左端) --}}
                <div class="detail-resize-handle"
                     @mousedown.prevent="startResizeDetail($event)"
                     title="ドラッグで幅を変更"></div>

                {{-- ヘッダ --}}
                <div class="px-3 py-2 border-bottom d-flex align-items-center" style="background:#f9fafb;gap:8px;">
                    <span x-show="detail" class="badge"
                          :class="{'badge-primary': detail?.source_type === 'url', 'badge-success': detail?.source_type === 'file', 'badge-warning': detail?.source_type === 'email'}">
                        <i class="fas" :class="{'fa-globe': detail?.source_type === 'url', 'fa-file-alt': detail?.source_type === 'file', 'fa-envelope-open-text': detail?.source_type === 'email'}"></i>
                        <span x-text="({url:'URL',file:'ファイル',email:'メール'})[detail?.source_type] || 'ソース'"></span>
                    </span>
                    <strong class="flex-grow-1 text-truncate" x-text="detail?.title || detail?.url || '読み込み中...'"></strong>
                    <button type="button" class="btn btn-sm btn-light" @click="detailOpen = false" title="閉じる">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                {{-- 本体 --}}
                <div class="flex-grow-1 overflow-auto p-3" x-show="detailLoading == false">
                    <template x-if="detail">
                        <div>
                            {{-- メタ情報 --}}
                            <div class="small text-muted mb-2">
                                <div><strong>URL/ID:</strong> <span x-text="detail.url"></span></div>
                                <div><strong>コレクション:</strong> <span x-text="detail.collection"></span> /
                                     <strong>チャンク数:</strong> <span x-text="detail.chunks_indexed"></span> /
                                     <strong>状態:</strong> <span x-text="detail.status"></span></div>
                                <div x-show="detail.updated_at"><strong>更新:</strong> <span x-text="detail.updated_at"></span></div>
                            </div>

                            {{-- タイトル編集 --}}
                            <template x-if="detail.content_editable">
                                <div class="form-group mb-2">
                                    <label class="small mb-1">タイトル</label>
                                    <input type="text" class="form-control form-control-sm"
                                           x-model="detail.title" maxlength="255">
                                </div>
                            </template>

                            {{-- 本文 --}}
                            <div class="form-group mb-2">
                                <div class="d-flex align-items-center mb-1">
                                    <label class="small mb-0 flex-grow-1">本文</label>
                                    <span class="text-muted" style="font-size:10px;" x-text="(detail.content?.length || 0) + ' 字'"></span>
                                </div>
                                <template x-if="detail.content_editable">
                                    <textarea class="form-control" style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12px;min-height:60vh;"
                                              x-model="detail.content" maxlength="500000"></textarea>
                                </template>
                                <template x-if="!detail.content_editable">
                                    <pre class="bg-light border rounded p-2 small" style="white-space:pre-wrap;word-break:break-word;max-height:65vh;overflow:auto;" x-text="detail.content || '(本文プレビューなし - URL ソースは再クロールで更新できます)'"></pre>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
                <div x-show="detailLoading" class="d-flex align-items-center justify-content-center p-5 text-muted">
                    <i class="fas fa-circle-notch fa-spin mr-2"></i>読み込み中...
                </div>

                {{-- フッタ --}}
                <div class="px-3 py-2 border-top d-flex align-items-center" style="background:#f9fafb;gap:8px;">
                    <span x-show="detailSaveMsg" class="small" :class="detailSaveError ? 'text-danger' : 'text-success'" x-text="detailSaveMsg"></span>
                    <div class="ml-auto" style="display:flex;gap:6px;">
                        <button type="button" class="btn btn-sm btn-light" @click="detailOpen = false">閉じる</button>
                        <button type="button" class="btn btn-sm btn-primary" @click="saveDetail()"
                                x-show="detail?.content_editable" :disabled="detailSaving">
                            <i x-show="detailSaving" class="fas fa-circle-notch fa-spin mr-1"></i>
                            <span x-text="detailSaving ? '保存中…' : '保存して再インデックス'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<script>
// 既存コレクションのキャッシュ (画面内のすべての picker で共有)
window._knowledgeCollections = window._knowledgeCollections || { loaded: false, items: [] };

async function _loadKnowledgeCollections(force = false) {
    if (window._knowledgeCollections.loaded && !force) return window._knowledgeCollections.items;
    try {
        const res = await fetch('/api/knowledge/collections', { headers: { Accept: 'application/json' } });
        if (res.ok) {
            const data = await res.json();
            window._knowledgeCollections.items = data.collections || [];
        }
    } catch (_) {}
    window._knowledgeCollections.loaded = true;
    return window._knowledgeCollections.items;
}

// コレクション名入力欄向けのオートコンプリート用 Alpine コンポーネント
// (カンマ区切りで複数指定する場合は、最後のカンマ以降のトークンに対して絞り込み + 補完を行う)
function collectionPicker(initial = '') {
    return {
        value: initial,
        acOpen: false,
        acIdx: 0,
        collections: [],
        async loadCollections() {
            this.collections = await _loadKnowledgeCollections();
        },
        // カンマ区切りの最後の token を取り出す
        _lastToken() {
            const s = (this.value || '');
            const i = Math.max(s.lastIndexOf(','), s.lastIndexOf('，'));
            return (i < 0 ? s : s.slice(i + 1)).trim().toLowerCase();
        },
        // 補完候補から 1 件選んだ時、最後のトークンだけを置き換える
        insertCollectionToken(name) {
            const s = (this.value || '');
            const i = Math.max(s.lastIndexOf(','), s.lastIndexOf('，'));
            const head = i < 0 ? '' : s.slice(0, i + 1);
            // 末尾にカンマを付けて次の入力に進みやすくする
            this.value = (head ? head : '') + name + ',';
            this.acIdx = 0;
        },
        get filtered() {
            const q = this._lastToken();
            // 既に value に含まれているコレクションは候補から除く
            const existing = (this.value || '').split(/[,，]/).map(s => s.trim()).filter(Boolean);
            const pool = this.collections.filter(c => !existing.includes(c.name));
            if (!q) return pool;
            const prefix = [], rest = [];
            pool.forEach(c => {
                const name = (c.name || '').toLowerCase();
                if (name.startsWith(q)) prefix.push(c);
                else if (name.includes(q)) rest.push(c);
            });
            return [...prefix, ...rest];
        },
    };
}

function knowledgeListApp() {
    return {
        // 一覧の全 ID (DOM 走査で構築)
        allIds: @json($sources->pluck('id')->toArray()),
        // 選択モード
        selectionMode: false,
        selectedIds: [],
        // 長押し検出
        longPressTimer: null,
        longPressFired: false,
        // 一括変更フォーム
        bulkCollection: '',
        bulkAcOpen: false,
        bulkAcIdx: 0,
        // 既存コレクション一覧 (オートコンプリート用)
        collections: [],
        collectionsLoaded: false,
        // インライン編集 (個別チップ)
        inlineEditId: null,
        inlineValue: '',
        inlineAcOpen: false,
        inlineAcIdx: 0,
        // 詳細パネル
        detailOpen: false,
        detailId: null,
        detail: null,
        detailLoading: false,
        detailSaving: false,
        detailSaveMsg: '',
        detailSaveError: false,
        detailWidth: parseInt(localStorage.getItem('knowledgeDetailWidth') || '540', 10),
        csrfToken: document.querySelector('meta[name="csrf-token"]')?.content,
        statusTimer: null,

        init() {
            this.startStatusPoll();
            // 一覧クリックで選択モード解除 (ESC)
            window.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.selectionMode) this.exitSelectionMode();
            });
        },

        // ----- 長押し検出 -----
        startLongPress(id, e) {
            if (e.button !== undefined && e.button !== 0) return; // 左クリックのみ
            this.longPressFired = false;
            this.longPressTimer = setTimeout(() => {
                this.longPressFired = true;
                this.selectionMode = true;
                if (!this.selectedIds.includes(id)) this.selectedIds.push(id);
                if (navigator.vibrate) try { navigator.vibrate(40); } catch (_) {}
            }, 500);
        },
        cancelLongPress() {
            if (this.longPressTimer) clearTimeout(this.longPressTimer);
            this.longPressTimer = null;
        },
        onRowClick(id, e) {
            // 長押しが発火していればクリック本来の動作は止める
            if (this.longPressFired) { this.longPressFired = false; e.preventDefault(); return; }
            if (this.selectionMode) {
                e.preventDefault();
                this.toggleSelect(id);
                return;
            }
            // 通常モード: 詳細パネルを開く
            this.openDetail(id);
        },

        // ===== 詳細パネル =====
        async openDetail(id) {
            this.detailOpen = true;
            this.detailId = id;
            this.detailLoading = true;
            this.detail = null;
            this.detailSaveMsg = '';
            this.detailSaveError = false;
            try {
                const res = await fetch(`/knowledge/sources/${id}`, { headers: { Accept: 'application/json' } });
                if (!res.ok) { this.detail = null; this.detailSaveMsg = '読み込みに失敗しました'; this.detailSaveError = true; return; }
                this.detail = await res.json();
            } catch (e) {
                this.detailSaveMsg = '通信エラー: ' + e.message; this.detailSaveError = true;
            } finally {
                this.detailLoading = false;
            }
        },
        async saveDetail() {
            if (!this.detail || !this.detail.content_editable) return;
            this.detailSaving = true;
            this.detailSaveMsg = '';
            this.detailSaveError = false;
            try {
                const res = await fetch(`/knowledge/sources/${this.detail.id}/content`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({ title: this.detail.title, content: this.detail.content }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) { this.detailSaveMsg = data.message || '保存に失敗しました'; this.detailSaveError = true; return; }
                this.detailSaveMsg = data.message || '保存しました';
                this.detailSaveError = false;
                // 一覧の表示を更新
                const tr = document.querySelector(`tr[data-source-id="${this.detail.id}"]`);
                if (tr) {
                    const chunks = tr.querySelector('[data-chunks]');
                    if (chunks) chunks.textContent = (data.chunks_indexed ?? this.detail.chunks_indexed ?? 0).toLocaleString();
                }
            } catch (e) {
                this.detailSaveMsg = '通信エラー: ' + e.message; this.detailSaveError = true;
            } finally {
                this.detailSaving = false;
                setTimeout(() => { this.detailSaveMsg = ''; }, 3000);
            }
        },
        startResizeDetail(e) {
            const startX = e.clientX, startW = this.detailWidth;
            const prevUS = document.body.style.userSelect;
            document.body.style.userSelect = 'none';
            document.body.style.cursor = 'col-resize';
            const onMove = (me) => {
                this.detailWidth = Math.max(360, Math.min(1200, startW - (me.clientX - startX)));
            };
            const onUp = () => {
                localStorage.setItem('knowledgeDetailWidth', String(this.detailWidth));
                document.body.style.userSelect = prevUS;
                document.body.style.cursor = '';
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
            };
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        },

        // ----- 選択 API -----
        toggleSelect(id) {
            const i = this.selectedIds.indexOf(id);
            if (i === -1) this.selectedIds.push(id);
            else this.selectedIds.splice(i, 1);
            if (this.selectedIds.length === 0) this.selectionMode = false;
        },
        selectAll() { this.selectedIds = [...this.allIds]; },
        clearSelection() { this.selectedIds = []; },
        exitSelectionMode() {
            this.selectionMode = false;
            this.selectedIds = [];
            this.bulkCollection = '';
            this.bulkAcOpen = false;
        },

        // ----- オートコンプリート (一括) -----
        async loadCollections() {
            this.collections = await _loadKnowledgeCollections();
            this.collectionsLoaded = true;
        },
        // 入力値全体ではなく「最後のカンマ以降」をクエリにする (タグ入力対応)
        _lastTokenOf(value) {
            const s = (value || '');
            const i = Math.max(s.lastIndexOf(','), s.lastIndexOf('，'));
            return (i < 0 ? s : s.slice(i + 1)).trim().toLowerCase();
        },
        _filter(value) {
            const q = this._lastTokenOf(value);
            const existing = (value || '').split(/[,，]/u).map(s => s.trim()).filter(Boolean);
            const pool = this.collections.filter(c => !existing.includes(c.name));
            if (!q) return pool;
            const prefix = [], rest = [];
            pool.forEach(c => {
                const name = (c.name || '').toLowerCase();
                if (name.startsWith(q)) prefix.push(c);
                else if (name.includes(q)) rest.push(c);
            });
            return [...prefix, ...rest];
        },
        get filteredCollections() { return this._filter(this.bulkCollection); },
        get filteredInline()      { return this._filter(this.inlineValue); },
        async loadInlineCollections() {
            this.collections = await _loadKnowledgeCollections();
        },
        // bulk 入力欄に補完候補を挿入 (最後のトークンを差し替え + カンマで次へ)
        bulkInsertToken(name) {
            const s = (this.bulkCollection || '');
            const i = Math.max(s.lastIndexOf(','), s.lastIndexOf('，'));
            const head = i < 0 ? '' : s.slice(0, i + 1);
            this.bulkCollection = (head ? head : '') + name + ',';
            this.bulkAcIdx = 0;
        },
        // inline 入力欄向け同等処理
        inlineInsertToken(name) {
            const s = (this.inlineValue || '');
            const i = Math.max(s.lastIndexOf(','), s.lastIndexOf('，'));
            const head = i < 0 ? '' : s.slice(0, i + 1);
            this.inlineValue = (head ? head : '') + name + ',';
            this.inlineAcIdx = 0;
        },

        // ----- 一括適用 (複数コレクション + モード対応) -----
        // bulkMode: 'replace' (置換) | 'append' (追加) | 'remove' (削除)
        bulkMode: 'replace',
        // カンマ区切りの bulkCollection を配列にして送信。空除去 + 不正文字除去はサーバ側でも実施。
        _parseBulkTokens() {
            const raw = (this.bulkCollection || '').split(/[,，\n\r]+/u).map(s => s.trim()).filter(Boolean);
            // 不正文字を含むトークンはクライアントでも除外
            return raw.filter(t => !/[\s\/\\#?&]/u.test(t));
        },
        async applyBulkCollection() {
            const tokens = this._parseBulkTokens();
            if (tokens.length === 0) return;
            if (this.selectedIds.length === 0) return;
            try {
                const res = await fetch('/knowledge/sources/bulk-collection', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({
                        ids:         this.selectedIds,
                        collections: tokens,
                        mode:        this.bulkMode,
                    }),
                });
                if (!res.ok) { alert('一括更新に失敗しました'); return; }
                const data = await res.json();
                // DOM 反映は一覧再描画が無いので最小: 各行を rerender するために `statuses` ポーリングをトリガー。
                // ここでは行ごとの datasets を更新するに留め、ページリロードで完全表示。
                window._knowledgeCollections.loaded = false;
                window._knowledgeCollections.items = [];
                this.collectionsLoaded = false;
                this.collections = [];
                const modeLabel = { replace: '置換', append: '追加', remove: '削除' }[this.bulkMode] || '更新';
                alert(`${data.updated} 件のコレクションを${modeLabel}しました: ${tokens.join(', ')}\n(画面を再読込で反映されます)`);
                this.exitSelectionMode();
                // 軽量に再読込 (大量ソースでも DOM 再構築は受け入れる)
                setTimeout(() => window.location.reload(), 250);
            } catch (e) {
                alert('通信エラー: ' + e.message);
            }
        },

        // ----- 個別行クリックで複数コレクションを編集 (Alpine + オートコンプリート) -----
        showCollectionEditor(id, e) {
            const list = e.currentTarget.closest('.collection-display') || e.currentTarget;
            const td   = list?.closest('[data-collection-cell]');
            const wrap = td?.querySelector('.collection-edit-wrap');
            const input = td?.querySelector('.collection-edit');
            if (!td || !wrap || !input) return;
            // 他の編集中セルを閉じる
            this.hideInlineEditor();
            list.classList.add('d-none');
            wrap.classList.remove('d-none');
            this.inlineEditId = id;
            // dataset.collection には現在のカンマ区切り文字列が入っている
            this.inlineValue  = list.dataset.collection || 'default';
            this.inlineAcIdx  = 0;
            this.inlineAcOpen = false;
            this.$nextTick(() => { try { input.focus(); input.select(); } catch (_) {} });
        },
        hideInlineEditor() {
            // 開いていたセルを元に戻す
            if (this.inlineEditId == null) return;
            const list = document.querySelector(`.collection-display[data-source-id="${this.inlineEditId}"]`);
            const td   = list?.closest('[data-collection-cell]');
            if (td) {
                const wrap = td.querySelector('.collection-edit-wrap');
                if (list) list.classList.remove('d-none');
                if (wrap) wrap.classList.add('d-none');
            }
            this.inlineEditId = null;
            this.inlineValue  = '';
            this.inlineAcOpen = false;
        },
        async saveInline(id) {
            const list = document.querySelector(`.collection-display[data-source-id="${id}"]`);
            const oldVal = list?.dataset.collection || 'default';
            // 入力値をカンマ区切りで配列化
            const tokens = (this.inlineValue || '').split(/[,，\n\r]+/u).map(s => s.trim()).filter(Boolean);
            if (tokens.length === 0) { this.hideInlineEditor(); return; }
            const newJoined = tokens.join(',');
            if (newJoined === oldVal) { this.hideInlineEditor(); return; }
            // 不正文字を含むトークンは事前に除外 (サーバ側もガード)
            const valid = tokens.filter(t => !/[\s\/\\#?&]/u.test(t));
            if (valid.length === 0) {
                alert('コレクション名にスペース・/ \\ # ? & は使えません。');
                this.hideInlineEditor();
                return;
            }
            try {
                const res = await fetch(`/knowledge/sources/${id}/collection`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({ collections: valid }),
                });
                if (!res.ok) { alert('更新に失敗しました'); this.hideInlineEditor(); return; }
                const data = await res.json();
                // 表示更新: チップ群を再描画
                if (list) {
                    const cols = Array.isArray(data.collections) ? data.collections : [data.collection];
                    list.dataset.collection = cols.join(',');
                    // 既存の通常チップを撤去 (編集ボタンは残す)
                    Array.from(list.querySelectorAll('.collection-chip:not(.collection-chip-edit)')).forEach(n => n.remove());
                    const editBtn = list.querySelector('.collection-chip-edit');
                    cols.forEach(name => {
                        const chip = document.createElement('span');
                        chip.className = 'collection-chip';
                        chip.innerHTML = `<i class="fas fa-tag" style="font-size:9px;"></i><span class="collection-name"></span>`;
                        chip.querySelector('.collection-name').textContent = name;
                        chip.addEventListener('click', (ev) => {
                            ev.stopPropagation();
                            if (!this.selectionMode) this.showCollectionEditor(id, ev);
                        });
                        if (editBtn) list.insertBefore(chip, editBtn);
                        else list.appendChild(chip);
                    });
                }
                // 新規コレクション追加の可能性 → キャッシュをリセット
                window._knowledgeCollections.loaded = false;
                window._knowledgeCollections.items = [];
                this.collectionsLoaded = false;
                this.collections = [];
            } catch (e) {
                alert('通信エラー: ' + e.message);
            } finally {
                this.hideInlineEditor();
            }
        },

        // ----- 状態ポーリング (処理中タスクの更新) -----
        startStatusPoll() {
            const STATUS_LABEL = { ok: '取得済', pending: '処理中', error: 'エラー' };
            const STATUS_CLASS = { ok: 'badge-success', pending: 'badge-warning', error: 'badge-danger' };
            const tbody = document.getElementById('knowledge-sources-tbody');
            const hasPending = () => tbody && tbody.querySelectorAll('[data-status="pending"]').length > 0;
            const poll = async () => {
                try {
                    const res = await fetch('{{ route("knowledge.statuses") }}', { headers: { Accept: 'application/json' } });
                    if (!res.ok) return;
                    const data = await res.json();
                    (data.sources || []).forEach(s => {
                        const tr = tbody?.querySelector(`tr[data-source-id="${s.id}"]`);
                        if (!tr) return;
                        const badge = tr.querySelector('[data-status]');
                        if (badge) {
                            badge.textContent = STATUS_LABEL[s.status] ?? s.status;
                            badge.className = 'badge knowledge-status-badge ' + (STATUS_CLASS[s.status] ?? 'badge-secondary');
                            badge.setAttribute('data-status', s.status);
                        }
                        const chunks = tr.querySelector('[data-chunks]');
                        if (chunks) chunks.textContent = (s.chunks_indexed ?? 0).toLocaleString();
                        const updated = tr.querySelector('[data-updated]');
                        if (updated && s.updated_at) updated.innerHTML = '<small>' + s.updated_at + '</small>';
                        const err = tr.querySelector('[data-error]');
                        if (err) err.textContent = s.error_message ?? '';
                    });
                } catch (_) {}
            };
            if (hasPending()) {
                this.statusTimer = setInterval(() => {
                    poll().then(() => {
                        if (!hasPending() && this.statusTimer) { clearInterval(this.statusTimer); this.statusTimer = null; }
                    });
                }, 3000);
            }
        },
    };
}
</script>
@endsection
