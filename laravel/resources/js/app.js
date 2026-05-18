import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

Alpine.plugin(collapse);

/**
 * 「選択中ルーム」のグローバルストア。メール / 添付 / チャットの3画面で共有し、
 * localStorage で永続化する。クリアするまで画面遷移しても外れない。
 */
document.addEventListener('alpine:init', () => {
    Alpine.store('room', {
        id:         localStorage.getItem('activeRoomId') || null,
        name:       localStorage.getItem('activeRoomName') || null,
        isPersonal: localStorage.getItem('activeRoomIsPersonal') === 'true',

        init() {
            // URL の ?room_id があれば最優先で取り込む (リンク経由・別タブでルーム状態を引き継ぐ)
            const urlRoom = new URLSearchParams(window.location.search).get('room_id');
            if (urlRoom && /^\d+$/.test(urlRoom)) {
                this.id = parseInt(urlRoom, 10);
                localStorage.setItem('activeRoomId', String(this.id));
                // 名前は URL からは取れない。各ページが /customers をロードした後に補完される。
            } else if (this.id !== null && this.id !== 'none' && /^\d+$/.test(String(this.id))) {
                this.id = parseInt(this.id, 10);
            }
        },

        select(c) {
            this.id         = c?.id ?? null;
            this.name       = c?.name ?? null;
            this.isPersonal = !!c?.is_personal;
            if (this.id == null) {
                this.clear();
                return;
            }
            localStorage.setItem('activeRoomId', this.id);
            localStorage.setItem('activeRoomName', this.name ?? '');
            localStorage.setItem('activeRoomIsPersonal', this.isPersonal ? 'true' : 'false');
            window.dispatchEvent(new CustomEvent('room-changed', { detail: { id: this.id, name: this.name, isPersonal: this.isPersonal } }));
        },

        clear() {
            this.id = null;
            this.name = null;
            this.isPersonal = false;
            localStorage.removeItem('activeRoomId');
            localStorage.removeItem('activeRoomName');
            localStorage.removeItem('activeRoomIsPersonal');
            window.dispatchEvent(new CustomEvent('room-changed', { detail: null }));
        },
    });
});

window.Alpine = Alpine;
Alpine.start();
