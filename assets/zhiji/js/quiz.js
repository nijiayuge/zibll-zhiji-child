/**
 * 互动答题（Quiz）前台脚本
 *
 * 数据流：开始答题 → zhiji_quiz_start（随机抽题，不含答案）→ 用户作答 →
 *         zhiji_quiz_submit（服务端判分 + 发积分）→ 结果 + 解析。
 *
 * ⚠️ 2026-09-29：从 OpsPage/内联抽出为独立文件（head 输出 —— 本项目 wp_footer 不可靠，
 *    同 ops-drawer.js 结论），因此做了 **DOM 安全启动**（DOMContentLoaded）。
 *    配置（nonce/ajax）由 PHP 经 wp_add_inline_script 注入 window.ZHIJI_QUIZ_CFG。
 */
(function () {
    'use strict';

    var CFG = window.ZHIJI_QUIZ_CFG || { nonce: '', ajax: '/wp-admin/admin-ajax.php' };

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = (null === s || undefined === s) ? '' : String(s);
        return d.innerHTML;
    }

    function boot() {
        var btn = document.getElementById('zhiji-quiz-start');
        if (!btn) {
            return; // 非答题页（脚本在启用页全站 head 加载）
        }
        var box = document.getElementById('zhiji-quiz-body');

        btn.addEventListener('click', function () {
            btn.disabled = true;
            btn.textContent = '出题中…';
            var d = new FormData();
            d.append('action', 'zhiji_api');
            d.append('api', 'zhiji_quiz_start');
            fetch(CFG.ajax, { method: 'POST', credentials: 'same-origin', body: d })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    btn.disabled = false;
                    btn.textContent = '开始答题';
                    var qs = (j.data && j.data.questions) || [];
                    if (!qs.length) {
                        box.innerHTML = '<p class="description">题库为空，请在后台配置题库。</p>';
                        return;
                    }
                    var ANS = {};
                    var h = '';
                    qs.forEach(function (q) {
                        h += '<div class="zhiji-quiz-q" style="margin-bottom:12px"><strong>' + esc(q.q) + '</strong>';
                        q.choices.forEach(function (c, i) {
                            h += '<label style="display:block;margin:4px 0"><input type="radio" name="zq' + q.id + '" value="' + (i + 1) + '"> ' + (i + 1) + '. ' + esc(c) + '</label>';
                        });
                        h += '</div>';
                        ANS[q.id] = 1;
                    });
                    h += '<button class="button button-primary" id="zhiji-quiz-submit">提交答案</button>';
                    box.innerHTML = h;

                    document.getElementById('zhiji-quiz-submit').addEventListener('click', function () {
                        this.disabled = true;
                        var d2 = new FormData();
                        d2.append('action', 'zhiji_api');
                        d2.append('api', 'zhiji_quiz_submit');
                        Object.keys(ANS).forEach(function (id) {
                            var sel = document.querySelector('input[name=zq' + id + ']:checked');
                            d2.append('answers[' + id + ']', sel ? sel.value : 0);
                        });
                        d2.append('nonce', CFG.nonce);
                        fetch(CFG.ajax, { method: 'POST', credentials: 'same-origin', body: d2 })
                            .then(function (r) { return r.json(); })
                            .then(function (j) {
                                var res = j.data || {};
                                var h2 = '<p><strong>答对 ' + esc(res.correct) + '/' + esc(res.total) + '</strong>，获得积分 +' + esc(res.points || 0) + (res.capped ? '（已达今日上限）' : '') + '</p>';
                                (res.results || []).forEach(function (r2) {
                                    h2 += '<div style="margin:6px 0"><strong>' + esc(r2.q) + '</strong><br>'
                                        + '你的选择：' + esc(r2.choice) + ' ｜ 正确答案：' + esc(r2.answer) + (r2.right ? ' ✅' : ' ❌')
                                        + '<br><span class="description">' + esc(r2.explain) + '</span></div>';
                                });
                                box.innerHTML = h2;
                            });
                    });
                });
        });
    }

    // DOM 安全启动（head 加载时 DOM 未就绪）
    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
