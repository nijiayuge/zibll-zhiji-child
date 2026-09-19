/*!
 * 知集主题 · 前端提示统一入口
 *
 * 铁律：业务代码一律调用 window.zhijiNotify()，不要自己引入或调用任何提示库。
 * 优先使用父主题前端已保证加载的 notyf；不可用时降级为自绘轻提示（零依赖）。
 */
(function (w, d) {
    'use strict';

    var STYLE_ID = 'zhiji-notify-style';
    var HOST_ID = 'zhiji-notify-host';

    function injectStyle() {
        if (d.getElementById(STYLE_ID)) { return; }
        var s = d.createElement('style');
        s.id = STYLE_ID;
        s.textContent = [
            '.zhiji-toast-host{position:fixed;top:18px;right:18px;z-index:999999;display:flex;flex-direction:column;gap:10px;pointer-events:none}',
            '.zhiji-toast{pointer-events:auto;min-width:220px;max-width:340px;padding:12px 14px;border-radius:10px;background:#fff;color:#333;',
            'box-shadow:0 6px 20px rgba(0,0,0,.12);border-left:4px solid var(--zhiji-brand,#2e7cf6);font-size:14px;line-height:1.5;',
            'opacity:0;transform:translateY(-6px);transition:opacity .18s ease,transform .18s ease}',
            '.zhiji-toast.is-show{opacity:1;transform:none}',
            '.zhiji-toast__title{font-weight:500;margin-bottom:2px}',
            '.zhiji-toast__body{color:#666;font-size:13px}',
            '.zhiji-toast--success{border-left-color:#1D9E75}',
            '.zhiji-toast--warning{border-left-color:#BA7517}',
            '.zhiji-toast--error{border-left-color:#E24B4A}',
            '.zhiji-toast a{color:var(--zhiji-brand,#2e7cf6);text-decoration:none}',
            '.zhiji-badge-dirty{display:inline-block !important;background:#E24B4A;color:#fff;border-radius:8px;padding:0 6px;font-size:12px}'
        ].join('');
        (d.head || d.documentElement).appendChild(s);
    }

    function host() {
        var el = d.getElementById(HOST_ID);
        if (!el) {
            el = d.createElement('div');
            el.id = HOST_ID;
            el.className = 'zhiji-toast-host';
            d.body.appendChild(el);
        }
        return el;
    }

    function fallbackToast(type, title, content, link) {
        injectStyle();
        var box = d.createElement('div');
        box.className = 'zhiji-toast zhiji-toast--' + (type || 'info');

        var t = d.createElement('div');
        t.className = 'zhiji-toast__title';
        t.textContent = title || '';
        box.appendChild(t);

        if (content) {
            var b = d.createElement('div');
            b.className = 'zhiji-toast__body';
            b.textContent = content;
            box.appendChild(b);
        }
        if (link) {
            var a = d.createElement('a');
            a.href = link;
            a.textContent = '查看详情';
            a.style.display = 'inline-block';
            a.style.marginTop = '6px';
            box.appendChild(a);
        }

        host().appendChild(box);
        w.requestAnimationFrame(function () { box.classList.add('is-show'); });
        w.setTimeout(function () {
            box.classList.remove('is-show');
            w.setTimeout(function () { if (box.parentNode) { box.parentNode.removeChild(box); } }, 220);
        }, 4200);
    }

    function notyfToast(type, title, content) {
        var nf = w.notyf;
        if (!nf) { return false; }
        try {
            var inst = w.zhijiNotyf || null;
            if (!inst) {
                inst = (typeof nf === 'function') ? new nf() : ((typeof nf === 'object' && nf) ? nf : null);
                if (inst) { w.zhijiNotyf = inst; }
            }
            if (!inst || typeof inst[type === 'error' ? 'error' : (type === 'success' ? 'success' : 'open')] !== 'function') {
                if (inst && typeof inst.open === 'function') {
                    inst.open({ type: type || 'info', message: (title ? title + '：' : '') + (content || ''), duration: 4200 });
                    return true;
                }
                return false;
            }
            var fn = inst[type === 'error' ? 'error' : (type === 'success' ? 'success' : 'open')];
            if (type === 'error' || type === 'success') {
                fn.call(inst, (title ? title + '：' : '') + (content || ''));
            } else {
                fn.call(inst, { type: 'info', message: (title ? title + '：' : '') + (content || ''), duration: 4200 });
            }
            return true;
        } catch (e) {
            return false;
        }
    }

    /** 统一提示入口 */
    function zhijiNotify(type, title, content, link) {
        if (title && typeof title === 'object') {
            var o = title;
            type = o.type; title = o.title; content = o.content; link = o.link;
        }
        if (!notyfToast(type || 'info', title || '', content || '')) {
            fallbackToast(type, title, content, link);
        }
    }

    /** 消费服务端下发的提示队列 */
    function zhijiNotifyFlush() {
        var q = w.zhijiToastQueue;
        if (!q || !q.length) { return; }
        w.zhijiToastQueue = [];
        for (var i = 0; i < q.length; i++) {
            zhijiNotify(q[i].type || 'info', q[i].title || '', q[i].content || '', q[i].link || '');
        }
    }

    w.zhijiNotify = zhijiNotify;
    w.zhijiNotifyFlush = zhijiNotifyFlush;

    if (d.readyState === 'loading') {
        d.addEventListener('DOMContentLoaded', zhijiNotifyFlush);
    } else {
        zhijiNotifyFlush();
    }
})(window, document);
