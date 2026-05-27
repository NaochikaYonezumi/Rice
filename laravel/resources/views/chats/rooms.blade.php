@extends('layouts.app')
@section('title', 'チャットルーム')

@section('css')
<style>
    /* ===== ダークサイドバー配色 ===== */
    .chat-sidebar      { background-color: #2b2d31; color: #dbdee1; }
    .chat-sidebar-head { background-color: #1e1f22; border-bottom: 1px solid #1e1f22; }
    .chat-channel      { color: #b5bac1; padding: 6px 10px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 6px; margin: 1px 8px; }
    .chat-channel:hover   { background-color: #35373c; color: #f2f3f5; }
    .chat-channel.active  { background-color: #404249; color: #ffffff; font-weight: 600; }
    .chat-channel .hash    { color: #80848e; font-weight: 600; }
    .chat-channel.active .hash { color: #ffffff; }
    /* バンドル先スレッドの「未読メール数」バッジ。 */
    .chat-channel .badge-email-unread {
        background: #3b82f6; color: #ffffff;
        font-size: 10px; font-weight: 800;
        border-radius: 8px; min-width: 18px; height: 18px; padding: 0 5px;
        display: inline-flex; align-items: center; justify-content: center;
        gap: 3px; line-height: 1; margin-left: auto;
    }
    .chat-channel .badge-email-unread i { font-size: 8px; }

    .chat-main         { background-color: #313338; }
    .chat-header       { background-color: #313338; border-bottom: 1px solid #1e1f22; color: #f2f3f5; }
    .chat-messages     { background-color: #313338; }

    /* ===== メッセージ行レイアウト ===== */
    .msg-row { padding: 4px 16px 4px 64px; position: relative; min-height: 32px; }
    .msg-row:hover { background-color: #2e3035; }
    .msg-row .avatar { position: absolute; left: 16px; top: 4px; width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; }
    .msg-row .author { color: #f2f3f5; font-weight: 600; font-size: 14px; }
    .msg-row .ts     { color: #949ba4; font-size: 11px; margin-left: 6px; }
    .msg-row .body   { color: #dbdee1; font-size: 14px; line-height: 1.45; white-space: pre-wrap; word-wrap: break-word; }
    .msg-row.compact { padding-top: 0; padding-bottom: 0; min-height: 22px; }
    .msg-row.compact .author, .msg-row.compact .avatar, .msg-row.compact .ts-header { display: none; }
    .msg-row.compact .body { padding-left: 0; }
    .msg-row .floating-ts { display: none; position: absolute; left: 16px; top: 50%; transform: translateY(-50%); width: 38px; text-align: center; color: #949ba4; font-size: 10px; }
    .msg-row.compact:hover .floating-ts { display: block; }

    .date-divider {
        display: flex; align-items: center;
        margin: 16px 16px 8px;
        font-size: 11px; color: #949ba4; text-transform: uppercase; letter-spacing: 0.05em;
    }
    .date-divider::before, .date-divider::after {
        content: ''; flex: 1; height: 1px; background-color: #3f4147;
    }
    .date-divider span { padding: 0 12px; font-weight: 700; }

    /* 入力欄 */
    .chat-input-wrap   { background-color: #313338; padding: 0 16px 24px; }
    .chat-input-box    {
        background-color: #383a40; border-radius: 8px;
        display: flex; align-items: flex-end; gap: 8px; padding: 11px 16px;
    }
    .chat-input-box textarea {
        flex: 1; background: transparent; border: none; outline: none; resize: none;
        color: #dbdee1; font-size: 14px; line-height: 1.4; max-height: 200px;
    }
    .chat-input-box textarea::placeholder { color: #87898c; }
    .chat-send-hint    { color: #949ba4; font-size: 10px; padding: 4px 0 0 8px; }
    .chat-send-hint kbd { background: #1e1f22; border: 1px solid #404249; padding: 1px 4px; border-radius: 3px; font-size: 10px; color: #dbdee1; }

    /* スクロールバー */
    .chat-messages::-webkit-scrollbar { width: 8px; }
    .chat-messages::-webkit-scrollbar-thumb { background: #1a1b1e; border-radius: 4px; }
    .chat-messages::-webkit-scrollbar-track { background: transparent; }
</style>
@endsection

@section('content')
<div class="flex flex-1 h-full" x-data="chatRoomsApp()">

    {{-- 左: ルーム一覧 --}}
    <aside class="w-60 shrink-0 chat-sidebar flex flex-col">
        <div class="chat-sidebar-head px-3 py-3 flex items-center gap-2">
            <h3 class="text-sm font-bold flex-1" style="color:#f2f3f5;">チャットルーム</h3>
            <button @click="createRoom()" class="text-xs"
                    style="color:#b5bac1;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#b5bac1'"
                    title="新規ルーム">
                <i class="fas fa-plus"></i>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto py-2">
            <template x-for="r in rooms" :key="r.id">
                <div @click="selectRoom(r)"
                     :class="selected?.id === r.id ? 'chat-channel active' : 'chat-channel'">
                    <span class="hash">#</span>
                    <span class="flex-1 truncate text-sm" x-text="r.name"></span>
                    {{-- バンドル先スレッドの受信メール件数バッジ。is_read は問わず合計。 --}}
                    <span class="badge-email-unread" x-show="r.received_email_count > 0"
                          :title="'受信スレッド ' + r.received_email_count + ' 件'">
                        <i class="fas fa-envelope"></i><span x-text="r.received_email_count"></span>
                    </span>
                </div>
            </template>
            <template x-if="rooms.length === 0">
                <p class="px-3 py-6 text-center text-xs" style="color:#80848e;">ルームがありません</p>
            </template>
        </div>
    </aside>

    {{-- 右: メッセージ画面 --}}
    <main class="flex-1 flex flex-col chat-main">
        <template x-if="selected">
            <div class="flex flex-col h-full">
                {{-- ヘッダ --}}
                <div class="chat-header px-4 py-3 flex items-center gap-2 shrink-0">
                    <span style="color:#80848e;font-weight:600;">#</span>
                    <h2 class="text-base font-bold flex-1" x-text="selected.name"></h2>
                    <button @click="renameRoom()"
                            class="text-xs px-2 py-1 rounded"
                            style="color:#949ba4;"
                            onmouseover="this.style.color='#5865f2';this.style.backgroundColor='#252633'"
                            onmouseout="this.style.color='#949ba4';this.style.backgroundColor='transparent'"
                            title="ルーム名を変更">
                        <i class="fas fa-pen"></i>
                    </button>
                    <button @click="deleteRoom()"
                            class="text-xs px-2 py-1 rounded"
                            style="color:#949ba4;"
                            onmouseover="this.style.color='#f23f42';this.style.backgroundColor='#3f1e22'"
                            onmouseout="this.style.color='#949ba4';this.style.backgroundColor='transparent'"
                            title="ルームを削除">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>

                {{-- メッセージ一覧 --}}
                <div class="chat-messages flex-1 overflow-y-auto py-4" x-ref="msgList">
                    <template x-if="messages.length === 0">
                        <div class="text-center py-12" style="color:#80848e;">
                            <i class="fas fa-hashtag fa-3x mb-3" style="color:#3f4147;"></i>
                            <p class="text-base font-bold" style="color:#dbdee1;" x-text="'#' + (selected?.name || '')"></p>
                            <p class="text-sm mt-1">最初のメッセージで会話を始めましょう。</p>
                        </div>
                    </template>

                    <template x-for="(m, idx) in renderedMessages" :key="m.id">
                        <div>
                            {{-- 日付区切り --}}
                            <template x-if="m.show_date">
                                <div class="date-divider"><span x-text="m.date_label"></span></div>
                            </template>
                            <div :class="m.compact ? 'msg-row compact' : 'msg-row'">
                                <template x-if="!m.compact">
                                    <div class="avatar" :style="'background-color:' + avatarColor(m.user_id)" x-text="avatarLetter(m.author)"></div>
                                </template>
                                <template x-if="m.compact">
                                    <span class="floating-ts" x-text="m.time_only"></span>
                                </template>
                                <template x-if="!m.compact">
                                    <div class="ts-header">
                                        <span class="author" x-text="m.author"></span>
                                        <span class="ts" x-text="m.created_at"></span>
                                    </div>
                                </template>
                                <div class="body" x-text="m.content"></div>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- 入力欄 --}}
                <div class="chat-input-wrap shrink-0">
                    <div class="chat-input-box">
                        <textarea x-ref="ta" x-model="input" rows="1" maxlength="5000"
                                  @keydown="onInputKeydown($event)"
                                  @input="autoresize($event)"
                                  :placeholder="'#' + selected.name + ' へメッセージを送信'"></textarea>
                        <button @click="send()" :disabled="!input?.trim() || sending"
                                style="color:#5865f2;"
                                onmouseover="if(!this.disabled)this.style.color='#7983f5'"
                                onmouseout="this.style.color='#5865f2'"
                                class="disabled:opacity-30">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                    <div class="chat-send-hint">
                        <kbd>Ctrl</kbd> + <kbd>Enter</kbd> で送信、<kbd>Enter</kbd> で改行
                    </div>
                </div>
            </div>
        </template>
        <template x-if="!selected">
            <div class="flex-1 flex flex-col items-center justify-center" style="color:#80848e;">
                <i class="fas fa-comments fa-4x mb-4" style="color:#3f4147;"></i>
                <p class="text-base">左のリストからルームを選択、または <button @click="createRoom()" class="text-blue-400 hover:underline">＋新規作成</button></p>
            </div>
        </template>
    </main>
</div>

<script>
function chatRoomsApp() {
    return {
        rooms: [], selected: null, messages: [], input: '', sending: false,
        csrfToken: document.querySelector('meta[name="csrf-token"]').content,
        async init() { await this.load(); },
        async load() {
            const r = await fetch('/api/chat-rooms', { headers:{Accept:'application/json'} });
            if (r.ok) this.rooms = (await r.json()).rooms || [];
        },
        async createRoom() {
            const name = prompt('ルーム名を入力してください');
            if (!name?.trim()) return;
            const r = await fetch('/api/chat-rooms', {
                method:'POST',
                headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':this.csrfToken},
                body: JSON.stringify({ name }),
            });
            if (!r.ok) { alert('作成失敗'); return; }
            await this.load();
            const d = await r.json();
            const room = this.rooms.find(x => x.id === d.room.id);
            if (room) this.selectRoom(room);
        },
        async deleteRoom() {
            if (!this.selected || !confirm(`#${this.selected.name} を削除しますか？`)) return;
            await fetch(`/api/chat-rooms/${this.selected.id}`, { method:'DELETE', headers:{'X-CSRF-TOKEN':this.csrfToken}});
            this.selected = null; this.messages = [];
            this.load();
        },
        async renameRoom() {
            if (!this.selected) return;
            const name = prompt('新しいルーム名を入力してください', this.selected.name || '');
            if (name === null) return;          // キャンセル
            const trimmed = name.trim();
            if (!trimmed || trimmed === this.selected.name) return;
            const r = await fetch(`/api/chat-rooms/${this.selected.id}`, {
                method:'PUT',
                headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':this.csrfToken},
                body: JSON.stringify({ name: trimmed }),
            });
            if (!r.ok) {
                let msg = '変更に失敗しました';
                try { msg = (await r.json()).error || msg; } catch (_) {}
                alert(msg);
                return;
            }
            const d = await r.json();
            // 選択中ルーム & 一覧をその場で反映 (load() でも良いが選択を保ちたい)
            this.selected = { ...this.selected, ...(d.room || {}) };
            const idx = this.rooms.findIndex(x => x.id === this.selected.id);
            if (idx >= 0) this.rooms[idx] = { ...this.rooms[idx], ...(d.room || {}) };
        },
        async selectRoom(r) {
            this.selected = r;
            this.messages = [];
            const res = await fetch(`/api/chat-rooms/${r.id}/messages`, { headers:{Accept:'application/json'} });
            if (res.ok) {
                this.messages = (await res.json()).comments || [];
                this.$nextTick(() => this.scrollToBottom());
            }
        },
        scrollToBottom() {
            const el = this.$refs.msgList;
            if (el) el.scrollTop = el.scrollHeight;
        },

        // ===== メッセージのレンダリング (連投はコンパクト表示, 日付区切り挿入) =====
        get renderedMessages() {
            const out = [];
            let prev = null;
            const today = new Date().toDateString();
            const yest = new Date(Date.now() - 86400000).toDateString();
            for (const m of this.messages) {
                const d = m.created_at || '';
                // created_at は 'Y/m/d H:i' 形式。日付部分のみ
                const datePart = d.substring(0, 10);  // 'YYYY/MM/DD'
                const timeOnly = d.substring(11);
                const dObj = new Date(d.replace(/\//g, '-'));
                let dateLabel = datePart;
                if (dObj.toDateString() === today) dateLabel = '今日';
                else if (dObj.toDateString() === yest) dateLabel = '昨日';

                const showDate = !prev || (prev.created_at || '').substring(0, 10) !== datePart;
                const compact = !showDate && prev
                    && prev.user_id === m.user_id
                    && prev.created_at === m.created_at;  // 同分内は連結扱い
                out.push({ ...m, show_date: showDate, date_label: dateLabel, compact, time_only: timeOnly });
                prev = m;
            }
            return out;
        },

        avatarLetter(name) {
            if (!name) return '?';
            return name.trim().charAt(0).toUpperCase();
        },
        avatarColor(userId) {
            const palette = ['#5865f2','#3ba55c','#eb459e','#faa61a','#ed4245','#9b59b6','#1abc9c','#e67e22'];
            return palette[(userId || 0) % palette.length];
        },

        // ===== 入力ハンドル =====
        onInputKeydown(e) {
            // Ctrl+Enter または Cmd+Enter で送信
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                this.send();
            }
            // それ以外の Enter は通常通り改行 (preventDefault しない)
        },
        autoresize(e) {
            const ta = e.target;
            ta.style.height = 'auto';
            ta.style.height = Math.min(ta.scrollHeight, 200) + 'px';
        },

        async send() {
            const text = (this.input || '').trim();
            if (!text || this.sending || !this.selected) return;
            this.sending = true;
            try {
                const r = await fetch(`/api/chat-rooms/${this.selected.id}/messages`, {
                    method:'POST',
                    headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':this.csrfToken},
                    body: JSON.stringify({ content: text }),
                });
                if (!r.ok) { alert('送信失敗'); return; }
                const d = await r.json();
                this.messages.push(d.comment);
                this.input = '';
                this.$nextTick(() => {
                    if (this.$refs.ta) this.$refs.ta.style.height = 'auto';
                    this.scrollToBottom();
                });
            } finally { this.sending = false; }
        },
    };
}
</script>
@endsection
