/*!
 * 知集主题 · 皮肤选择器
 *
 * 切换逻辑：把选中皮肤的 CSS 变量写入 <style id="zhiji-skin-vars">（覆盖服务端渲染的那份），
 * 同时写 Cookie 记住选择 —— 下次刷新时由 PHP 直接渲染，避免闪屏（FOUC）。
 */
(function (w, d) {
    'use strict';

    var COOKIE = 'zhiji_skin';
    var STYLE_ID = 'zhiji-skin-vars';
    var ROOT = 'zhiji-skin-switcher';

    function skins() {
        return w.zhijiSkins || {};
    }

    function activeSlug() {
        return w.zhijiSkinActive || '';
    }

    function styleEl() {
        var el = d.getElementById(STYLE_ID);
        if (!el) {
            el = d.createElement('style');
            el.id = STYLE_ID;
            d.head.appendChild(el);
        }
        return el;
    }

    function applyVars(slug) {
        var s = skins()[slug];
        if (!s || !s.css) { return false; }
        styleEl().textContent = s.css;
        return true;
    }

    function saveCookie(slug) {
        var oneYear = 31536000;
        d.cookie = COOKIE + '=' + encodeURIComponent(slug) +
            '; path=/; max-age=' + oneYear + '; SameSite=Lax';
    }

    function markActive(slug) {
        var items = d.querySelectorAll('.' + ROOT + ' .zhiji-skin-item');
        for (var i = 0; i < items.length; i++) {
            items[i].classList.toggle('is-active', items[i].getAttribute('data-skin') === slug);
        }
    }

    function switchTo(slug) {
        if (!skins()[slug]) { return; }
        applyVars(slug);
        saveCookie(slug);
        w.zhijiSkinActive = slug;
        markActive(slug);
        if (typeof w.zhijiNotify === 'function') {
            w.zhijiNotify('success', '皮肤已切换', skins()[slug].label || slug);
        }
    }

    function init() {
        var box = d.getElementById(ROOT);
        if (!box || !Object.keys(skins()).length) { return; }

        box.hidden = false;
        markActive(activeSlug());

        var toggle = box.querySelector('.zhiji-skin-switcher__toggle');
        var close = box.querySelector('.zhiji-skin-switcher__close');

        if (toggle) {
            toggle.addEventListener('click', function (e) {
                e.preventDefault();
                box.classList.toggle('is-open');
            });
        }
        if (close) {
            close.addEventListener('click', function () {
                box.classList.remove('is-open');
            });
        }

        box.addEventListener('click', function (e) {
            var item = e.target.closest ? e.target.closest('.zhiji-skin-item') : null;
            if (item) {
                switchTo(item.getAttribute('data-skin'));
            }
        });

        d.addEventListener('click', function (e) {
            if (!box.contains(e.target)) {
                box.classList.remove('is-open');
            }
        });

        d.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                box.classList.remove('is-open');
            }
        });
    }

    // 暴露给业务代码：window.zhijiSetSkin('agate')
    w.zhijiSetSkin = switchTo;

    if (d.readyState === 'loading') {
        d.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(window, document);
