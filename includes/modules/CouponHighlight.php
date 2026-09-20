<?php
/**
 * @module  CouponHighlight
 * @desc    优惠码高亮复制：消息中心通知正文里的优惠码自动高亮，点击即复制
 * @option  coupon_highlight_enabled  总开关（默认开启）
 * @hook    wp_enqueue_scripts(99)
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/CouponHighlight.php`
 */

defined('ABSPATH') || exit;

/* ============================================================
 * 模块自注册
 * ============================================================ */
Zhiji_Registry::register_module('coupon_highlight', array(
    'title'    => '优惠码高亮复制',
    'parent'   => 'zhiji_pay',
    'priority' => 20,
    'option'   => 'coupon_highlight_enabled',
));

/* ============================================================
 * 前台资源（CSS/JS 为静态内容，heredoc 无插值，直接输出）
 * ============================================================ */

/**
 * 仅前台且开关开启时输出
 *
 * @return void
 */
function zhiji_coupon_highlight_assets()
{
    if (is_admin()) {
        return;
    }
    if (!zhiji_is_enabled('coupon_highlight_enabled', true)) {
        return;
    }

    $css = <<<'ZHIJI_CP_CSS'
.zhiji-cp{display:inline-block;background:#fff6ec;border:1px dashed #ffb366;color:#e8590c;font-weight:600;border-radius:6px;padding:0 7px;margin:0 2px;cursor:pointer;transition:all .15s ease;letter-spacing:.5px}
.zhiji-cp:hover{background:#ffe3c7;box-shadow:0 0 0 2px rgba(255,140,40,.18)}
.zhiji-cp.copied{background:#e6f7ed;border-color:#7bd9a8;color:#16a34a}
.zhiji-cp-tip{position:fixed;z-index:99999;background:#333;color:#fff;font-size:12px;padding:5px 12px;border-radius:6px;pointer-events:none;opacity:0;transition:opacity .2s;box-shadow:0 4px 12px rgba(0,0,0,.2)}
.zhiji-cp-tip.show{opacity:1}
ZHIJI_CP_CSS;

    $js = <<<'ZHIJI_CP_JS'
/* 优惠码高亮点击复制（消息中心，MutationObserver 处理异步内容） */
(function () {
function zhijiCpBoot() {
var isMsgPage = !!document.querySelector('.msg-center, .msg-box, [class*="msg-center"]') ||
/\/message(\/|$)/.test(window.location.pathname) ||
(window.location.href.indexOf('user_center=msg') !== -1);
if (!isMsgPage) return;
function isCoupon(s) { return /[A-Za-z]/.test(s) && /[0-9]/.test(s); }
function decorate(root) {
if (!root) return;
var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null);
var nodes = [];
while (walker.nextNode()) { nodes.push(walker.currentNode); }
nodes.forEach(function (node) {
var parent = node.parentElement;
if (!parent || parent.closest('.zhiji-cp')) return;
var text = node.nodeValue;
if (!text || !/[A-Za-z0-9]{12,}/.test(text)) return;
var re = /\b([A-Za-z0-9]{12,64})\b/g, m, out = null, last = 0;
while ((m = re.exec(text))) {
var code = m[1];
if (!isCoupon(code)) continue;
if (!out) out = document.createDocumentFragment();
out.appendChild(document.createTextNode(text.slice(last, m.index)));
var span = document.createElement('span');
span.className = 'zhiji-cp';
span.textContent = code;
span.setAttribute('data-code', code);
span.title = '点击复制优惠码';
out.appendChild(span);
last = m.index + m[0].length;
}
if (out) {
out.appendChild(document.createTextNode(text.slice(last)));
parent.replaceChild(out, node);
}
});
}
var tip = null;
function showTip(x, y, msg) {
if (!tip) { tip = document.createElement('div'); tip.className = 'zhiji-cp-tip'; document.body.appendChild(tip); }
tip.textContent = msg;
tip.style.left = (x + 10) + 'px'; tip.style.top = (y - 30) + 'px';
tip.classList.add('show');
clearTimeout(tip._t);
tip._t = setTimeout(function () { tip.classList.remove('show'); }, 1400);
}
document.addEventListener('click', function (e) {
var cp = e.target.closest ? e.target.closest('.zhiji-cp') : null;
if (!cp) return;
var code = cp.getAttribute('data-code') || cp.textContent;
var ok = function () { cp.classList.add('copied'); showTip(e.clientX, e.clientY, '✅ 优惠码已复制'); setTimeout(function () { cp.classList.remove('copied'); }, 1500); };
if (navigator.clipboard && navigator.clipboard.writeText) {
navigator.clipboard.writeText(code).then(ok, function () {
if (document.execCommand('copy')) { ok(); } else { showTip(e.clientX, e.clientY, '复制失败，请手动复制'); }
});
} else {
var ta = document.createElement('textarea');
ta.value = code; ta.style.position = 'fixed'; ta.style.opacity = '0';
document.body.appendChild(ta); ta.select();
try { document.execCommand('copy'); ok(); } catch (err) { showTip(e.clientX, e.clientY, '复制失败，请手动复制'); }
document.body.removeChild(ta);
}
});
decorate(document.body);
var timer = null;
var mo = new MutationObserver(function () {
clearTimeout(timer);
timer = setTimeout(function () {
var c = document.querySelector('.msg-center') || document.body;
decorate(c);
}, 300);
});
mo.observe(document.body, { childList: true, subtree: true });
}
zhijiCpBoot();
})();
ZHIJI_CP_JS;

    // 两个 heredoc 均为单引号记法（nowdoc），内容不含 PHP 插值，直接输出安全
    echo '<style id="zhiji-cp-css">' . $css . '</style>' . "\n";
    echo '<script id="zhiji-cp-js">' . $js . '</script>' . "\n";
}
add_action('wp_enqueue_scripts', 'zhiji_coupon_highlight_assets', 99);

/* ============================================================
 * 后台字段
 * ============================================================ */
add_action('after_setup_theme', function () {
    Zhiji_Registry::csf_section_for('coupon_highlight', array(
        array(
            'id'      => 'coupon_highlight_enabled',
            'type'    => 'switcher',
            'title'   => '启用优惠码高亮复制',
            'default' => true,
            'desc'    => '消息中心通知正文中的优惠码自动高亮为可点击复制的标签。',
        ),
    ));
}, 20);
