/**
 * 砍价（Bargain）前台脚本
 *
 * 两种模式：发起页（无 ?zhiji_bargain 参数）/ 助力落地页（有参数）。
 * head 输出 + DOM 安全启动（同 ops-drawer/quiz 模式）。
 */
(function () {
    'use strict';

    var CFG = window.ZHIJI_BARGAIN_CFG || { nonce: '', ajax: '/wp-admin/admin-ajax.php', bid: '' };

    function api(api_name, extra) {
        var d = new FormData();
        d.append('action', 'zhiji_api');
        d.append('api', api_name);
        d.append('nonce', CFG.nonce);
        if (extra) {
            Object.keys(extra).forEach(function (k) { d.append(k, extra[k]); });
        }
        return fetch(CFG.ajax, { method: 'POST', credentials: 'same-origin', body: d })
            .then(function (r) { return r.json(); });
    }

    function boot() {
        var createBtn = document.getElementById('zhiji-bargain-create-btn');
        var assistBtn = document.getElementById('zhiji-bargain-assist-btn');
        var resultBox = document.getElementById('zhiji-bargain-result');

        // 发起
        if (createBtn) {
            createBtn.addEventListener('click', function () {
                createBtn.disabled = true;
                createBtn.textContent = '发起中…';
                api('zhiji_bargain_create').then(function (j) {
                    var msg = (j.data && j.data.msg) || '操作失败';
                    if (j.success) {
                        var share = (j.data && j.data.share_url) || '';
                        if (resultBox) {
                            resultBox.innerHTML = '<p style="color:#22c55e;font-weight:600">' + msg + '</p>'
                                + (share ? '<p>分享链接：<input readonly style="width:100%" value="' + share + '" onclick="this.select()"></p>' : '');
                        }
                    } else {
                        createBtn.disabled = false;
                        createBtn.textContent = '发起砍价';
                        if (resultBox) { resultBox.innerHTML = '<p class="description">' + msg + '</p>'; }
                    }
                }).catch(function () {
                    createBtn.disabled = false;
                    createBtn.textContent = '发起砍价';
                });
            });
        }

        // 助力
        if (assistBtn) {
            assistBtn.addEventListener('click', function () {
                assistBtn.disabled = true;
                assistBtn.textContent = '助力中…';
                api('zhiji_bargain_assist', { bid: CFG.bid }).then(function (j) {
                    var msg = (j.data && j.data.msg) || '操作失败';
                    assistBtn.textContent = msg;
                    if (j.success) {
                        assistBtn.className = 'button';
                        setTimeout(function () { location.reload(); }, 2000);
                    } else {
                        setTimeout(function () { assistBtn.disabled = false; }, 3000);
                    }
                }).catch(function () {
                    assistBtn.disabled = false;
                    assistBtn.textContent = '帮 TA 砍一刀';
                });
            });
        }
    }

    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
