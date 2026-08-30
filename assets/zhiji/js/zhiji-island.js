/**
 * 知集 · 灵动岛 (Dynamic Island) 前端逻辑
 * 暴露全局 window.zhijiIsland.show(text, ms) / .hide()
 * 并在页面加载后自动弹出一次（文案按页面类型自适应）。
 */
(function () {
    'use strict';

    var duration = 4000;
    var timer = null;

    function el() {
        return document.getElementById('zhijiIsland');
    }

    function hide() {
        var e = el();
        if (!e) { return; }
        e.classList.remove('active');
        e.classList.add('inactive');
        e.style.opacity = '0';
        if (timer) { clearTimeout(timer); timer = null; }
    }

    function show(text, ms) {
        var e = el();
        if (!e) { return; }
        if (text) {
            var t = e.querySelector('.zhiji-island-text');
            if (t) { t.textContent = text; }
        }
        e.classList.remove('inactive');
        e.classList.add('active');
        e.style.opacity = '1';
        if (timer) { clearTimeout(timer); }
        timer = setTimeout(hide, ms || duration);
    }

    function autoText() {
        var body = document.body;
        if (body.classList.contains('home') || body.classList.contains('front-page')) {
            return '欢迎来到知集';
        }
        if (body.classList.contains('single')) {
            var h = document.querySelector('h1.entry-title, .article-title, .post-title');
            return '正在阅读：' + (h ? h.textContent.trim() : '文章');
        }
        if (body.classList.contains('category') || body.classList.contains('archive')) {
            return '正在浏览分类';
        }
        if (location.pathname.indexOf('/user') > -1 || body.classList.contains('user')) {
            return '欢迎来到用户中心';
        }
        if (location.pathname.indexOf('/message') > -1 || location.pathname.indexOf('/notice') > -1) {
            return '查看消息';
        }
        return '欢迎访问知集';
    }

    window.zhijiIsland = { show: show, hide: hide };

    window.zhijiIslandInit = function (ms) {
        if (ms) { duration = ms; }
        setTimeout(function () { show(autoText(), duration); }, 800);
    };

    // 与本插件其他模块联动：兑换/登录/公告等可 dispatch 此事件
    document.addEventListener('zhiji:island', function (ev) {
        var d = ev.detail || {};
        show(d.text || autoText(), d.duration || duration);
    });
})();
