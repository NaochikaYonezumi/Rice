import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

Alpine.plugin(collapse);

window.Alpine = Alpine;

// Alpine.start() 内のディレクティブ評価で throw されると後続の x-cloak 除去が
// 行われず、ページが真っ白なまま固まる事故が稀に発生する.
// try/catch で吸って console に出し、最終手段で x-cloak を強制除去する.
// (layouts/app.blade.php 側にも 3.5 秒のセーフティネットあり.)
try {
    Alpine.start();
} catch (e) {
    console.error('[Alpine] start() threw — running x-cloak fallback', e);
    try {
        document.querySelectorAll('[x-cloak]').forEach((n) => n.removeAttribute('x-cloak'));
    } catch (_) {}
}

// グローバルな unhandledrejection / error をコンソールに出す.
// 真っ白の原因調査に役立つよう、サイレントに消えていた例外を可視化.
window.addEventListener('unhandledrejection', (ev) => {
    try { console.error('[unhandledrejection]', ev.reason); } catch (_) {}
});
window.addEventListener('error', (ev) => {
    try { console.error('[window.error]', ev.message, ev.filename + ':' + ev.lineno + ':' + ev.colno); } catch (_) {}
});
