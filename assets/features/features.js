/* =========================================================
   zhiji 子主题 · 功能前端逻辑（features.js）
   所有行为由 window.zhijiFeatures 配置控制（由 PHP 注入启用项）。
   纯原生实现，无第三方依赖。
   ========================================================= */
(function () {
    'use strict';

    var C = window.zhijiFeatures || {};
    var doc = document;

    function ready(fn) {
        if (doc.readyState !== 'loading') { fn(); }
        else { doc.addEventListener('DOMContentLoaded', fn); }
    }
    function $(sel, ctx) { return (ctx || doc).querySelector(sel); }
    function $all(sel, ctx) { return Array.prototype.slice.call((ctx || doc).querySelectorAll(sel)); }
    function contentRoot() {
        return $('.article-content') || $('.entry-content') || $('.post-content');
    }
    function isSingle() {
        return doc.body && doc.body.className && doc.body.className.indexOf('single') !== -1;
    }

    ready(function () {

        /* ---------- 复制版权声明 ---------- */
        if (C.copyright && isSingle()) {
            var proot = contentRoot();
            doc.addEventListener('copy', function (e) {
                if (!proot) { return; }
                var sel = window.getSelection();
                if (!sel || !sel.toString()) { return; }
                var node = sel.anchorNode;
                if (!node || !proot.contains(node)) { return; }
                var site = C.site || {};
                var extra = '\n\n© 出处：' + (site.name || '本站') + ' ' + (site.permalink || site.url || '');
                if (e.clipboardData) {
                    e.clipboardData.setData('text/plain', sel.toString() + extra);
                    e.preventDefault();
                }
            });
        }
    });
})();
