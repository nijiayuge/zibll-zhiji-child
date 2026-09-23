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
  // 2026-09-23 改造：原实现「初始页面没有 .msg-center 就直接 return」，导致
  // 用户中心用 AJAX 切 tab / 异步加载消息内容时，高亮永不启动（用户反馈"消息里优惠码没高亮"）。
  // 现改为：识别到消息页 → 启动；否则轮询等待容器出现（最多 20s），并对后续 DOM 变化保持监听。
  var started = false;
  var mo = null;
  var timer = null;
  function isMsgPage() {
      return !!document.querySelector('.msg-center, .msg-content, .msg-box, .msg-list, [class*="msg-center"]') ||
          /\/message(\/|$)/.test(window.location.pathname) ||
          (window.location.href.indexOf('user_center=msg') !== -1);
  }
  function start() {
      if (started) return true;
      if (!isMsgPage()) return false;
      started = true;
      decorate(document.querySelector('.msg-center, .msg-content') || document.documentElement);
      timer = null;
      mo = new MutationObserver(function () {
          clearTimeout(timer);
          timer = setTimeout(function () {
              var c = document.querySelector('.msg-center, .msg-content') || document.body;
              decorate(c);
          }, 300);
      });
      mo.observe(document.documentElement, { childList: true, subtree: true });
      return true;
  }
  function isCoupon(s) { return /[A-Za-z]/.test(s) && /[0-9]/.test(s); }
function decorate(root) {
if (!root) return;
var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null);
var nodes = [];
while (walker.nextNode()) { nodes.push(walker.currentNode); }
nodes.forEach(function (node) {
var parent = node.parentElement;
if (!parent || parent.closest('.zhiji-cp')) return;
// 2026-09-23 修复：跳过脚本/样式/代码块/链接等容器内的文本节点，
// 防止 JS 源码里的字符串与文件名被误高亮（实测 l2d+'live2dcubismcore.min.js' 被误判）。
// 注意排除时放行本站的券码容器 .zhiji-copy-code。
if (parent.closest('script, style, pre, a, textarea, noscript, svg, iframe')) return;
if (parent.closest('code') && !parent.closest('.zhiji-copy-code')) return;
var text = node.nodeValue;
if (!text || !/[A-Za-z0-9]{12,}/.test(text)) return;
var re = /\b([A-Za-z0-9]{12,64})\b/g, m, out = null, last = 0;
while ((m = re.exec(text))) {
var code = m[1];
if (!isCoupon(code)) continue;
// 2026-09-23 修复（用户反馈）：订单号等编号被误判为优惠码并高亮（如「订单号【TEST1789904754】」）
// ① 上下文排除：紧邻前方出现订单/编号类关键词时跳过
var ctxPrev = text.slice(Math.max(0, m.index - 16), m.index);
if (/(订单|单号|编号|序号|流水|凭证|ID|id)/i.test(ctxPrev)) continue;
// ② 前缀排除：常见订单号/测试编号前缀（真优惠码由 ZibCardPass::rand_password 生成，无此类前缀）
if (/^(TEST|ORD|ORDER|NO|ID|TMP|DEMO|PAY)/i.test(code)) continue;
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
  if (!start()) {
      // 容器尚未出现（用户中心 AJAX 切 tab / 消息异步加载）→ 轮询等待，最多 20 秒
      var wt = setInterval(function () { if (start()) { clearInterval(wt); } }, 500);
      setTimeout(function () { clearInterval(wt); }, 20000);
  }
  }
  // 脚本在 <head> 输出，此时 DOM 未就绪 → 等 DOMContentLoaded 再启动
  // （2026-09-23 修复：原先直接执行导致 document.body 为 null，observe 抛 TypeError，高亮永不启动）
  if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', zhijiCpBoot);
  } else {
      zhijiCpBoot();
  }
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
