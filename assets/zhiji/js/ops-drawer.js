/**
 * 运维台「行级详情抽屉」交互脚本
 *
 * 数据来源：每行的 `data-zhiji-detail` 属性（服务端 wp_json_encode，零 AJAX）。
 * 结构遵循行业做法（uxpatterns.dev / UserPilot）：概览大字区 → 明细列表 → 原始数据默认折叠。
 *
 * ⚠️ 2026-09-28 从 OpsPage.php 的内联 <script> 抽出（方案 P2-B）。抽出时有两个必须处理的点：
 *   1) **head 输出、DOM 可能尚未存在** —— 本站点实测 wp_footer 输出曾出现脚本丢失/500，
 *      故沿用「head 更稳」的既有结论；因此这里改为 **DOM 安全启动**（DOMContentLoaded / readyState 判定），
 *      否则 document.getElementById('zhiji-ops-modal') 会拿到 null，整块交互静默失效
 *      （正是附录 K.3「脚本先于 DOM 输出导致特效不生效」那类坑）。
 *   2) **i18n 文案不能写死在 js 里** —— 通过 window.ZHIJI_OPS_DRAWER 由 PHP 注入（wp_add_inline_script）。
 */
(function () {
    'use strict';

    var CFG = window.ZHIJI_OPS_DRAWER || { meta: 'meta', title: '#' };

    function boot() {
        var mask = document.getElementById('zhiji-ops-modal');
        // 非运维页（脚本在后台全局 head 加载）→ 直接退出，不做任何绑定
        if (!mask) {
            return;
        }

        var body = mask.querySelector('.zhiji-ops-modal-body');
        var title = document.getElementById('zhiji-ops-modal-title');
        var stTag = document.getElementById('zhiji-ops-modal-status');
        var lastFocus = null;

        function esc(s) {
            var d = document.createElement('div');
            d.textContent = (null === s || undefined === s) ? '' : String(s);
            return d.innerHTML;
        }

        function render(d) {
            if (!body) {
                return;
            }
            var html = '';

            if (d.primary && d.primary.length) {
                html += '<div class="zhiji-ops-dl-primary">';
                for (var i = 0; i < d.primary.length; i++) {
                    html += '<div class="zhiji-ops-dl-pcell">'
                        + '<span class="zhiji-ops-dl-k">' + esc(d.primary[i].k) + '</span>'
                        + '<span class="zhiji-ops-dl-v">' + esc(d.primary[i].v) + '</span></div>';
                }
                html += '</div>';
            }

            if (d.fields && d.fields.length) {
                html += '<div class="zhiji-ops-dl-fields">';
                for (var j = 0; j < d.fields.length; j++) {
                    var it = d.fields[j];
                    if (!it) {
                        continue;
                    }
                    html += '<div class="zhiji-ops-dl-item"><span class="zhiji-ops-dl-k">' + esc(it.k) + '</span>'
                        + (it.pre
                            ? '<pre class="zhiji-ops-pre">' + esc(it.v) + '</pre>'
                            : '<span class="zhiji-ops-dl-v">' + esc(it.v) + '</span>')
                        + '</div>';
                }
                html += '</div>';
            }

            if (d.meta) {
                html += '<details class="zhiji-ops-dl-meta">'
                    + '<summary>' + esc(CFG.meta) + '</summary>'
                    + '<pre class="zhiji-ops-pre">' + esc(d.meta) + '</pre></details>';
            }

            body.innerHTML = html || '<p class="description">无数据</p>';
        }

        function open(d) {
            lastFocus = document.activeElement;
            render(d);
            if (stTag) {
                if (d.status && d.status.text) {
                    stTag.textContent = d.status.text;
                    stTag.className = 'zhiji-ops-tag ' + ('ok' === d.status.tone ? 'cleared' : 'active');
                    stTag.hidden = false;
                } else {
                    stTag.hidden = true;
                }
            }
            if (title) {
                title.textContent = CFG.title + ' #' + (d.id || '—');
            }
            mask.hidden = false;
            var closeBtn = mask.querySelector('.zhiji-ops-modal-close');
            if (closeBtn) {
                closeBtn.focus();
            }
        }

        function close() {
            mask.hidden = true;
            if (body) {
                body.innerHTML = '';
            }
            if (lastFocus && lastFocus.focus) {
                lastFocus.focus();
            }
            lastFocus = null;
        }

        // 事件委托：点击「详情」按钮 → 读取所在行 data-zhiji-detail；点遮罩空白处关闭
        document.addEventListener('click', function (e) {
            var btn = e.target.closest ? e.target.closest('.zhiji-ops-detail-btn') : null;
            if (btn) {
                var tr = btn.closest('tr');
                var raw = tr ? tr.getAttribute('data-zhiji-detail') : '';
                var data = null;
                try {
                    data = JSON.parse(raw);
                } catch (err) {
                    data = null;
                }
                if (data && (data.fields || data.primary || data.meta)) {
                    open(data);
                }
                return;
            }
            if (e.target.closest && e.target.closest('.zhiji-ops-modal-close')) {
                close();
                return;
            }
            // 点遮罩（非抽屉本体）关闭
            if (e.target === mask) {
                close();
            }
        });

        // Esc 关闭
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !mask.hidden) {
                close();
            }
        });
    }

    // DOM 安全启动：head 加载时 readyState 仍为 loading，必须等 DOMContentLoaded
    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
