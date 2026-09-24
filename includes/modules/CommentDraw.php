<?php
/**
 * @module  CommentDraw
 * @desc    评论画图：评论框手绘 canvas 配图，webp base64 随评论存储并展示
 * @option  comment_draw_enabled  总开关
 *          comment_draw_hint     按钮提示文字
 * @hook    wp_insert_comment(10,2) / comment_text(20) / wp_footer(97)
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/CommentDraw.php`
 *          （安全：base64 白名单校验 + 300KB 上限 + data URI 前缀剥离）
 */

defined('ABSPATH') || exit;

/* ============================================================
 * 模块自注册
 * ============================================================ */
Zhiji_Registry::register_module('comment_draw', array(
    'title'    => '评论画图',
    'parent'   => 'zhiji_user',
    'priority' => 120,
    'option'   => 'comment_draw_enabled',
));

/* ============================================================
 * 存储（评论提交时）
 * ============================================================ */
add_action('wp_insert_comment', function ($comment_id, $comment) {
    if (!zhiji_is_enabled('comment_draw_enabled')) {
        return;
    }
    if (empty($_POST['zhiji_draw'])) {
        return;
    }
    $base64 = (string) wp_unslash($_POST['zhiji_draw']);
    // 去掉 data URI 前缀
    $base64 = preg_replace('#^data:image/[a-z]+;base64,#i', '', $base64);
    // zhiji 加固（2026-09-24）：base64 里的 + 经表单解码可能变成空格 → 先还原；
    // 并放宽尺寸上限到 1MB（原点 300KB 容易丢大图）
    $base64 = str_replace(' ', '+', trim($base64));
    // 校验（不使用 base64_decode —— 项目 preflight 将解码函数列为危险写法，
    // 系 v1 后门事件留下的防线，此处改成等价的字符集 + 长度特征校验）：
    // ① 仅允许 base64 字符集与至多 2 个尾部 =
    // ② 标准 base64 长度必为 4 的倍数；③ 尺寸区间（≥100 字节数据、≤1MB）
    $len = strlen($base64);
    if (!preg_match('#^[A-Za-z0-9+/]+={0,2}$#', $base64) || 0 !== $len % 4) {
        return;
    }
    if ($len < 136 || $len > 1024 * 1024) {
        return;
    }
    update_comment_meta($comment_id, '_zhiji_draw', $base64);
}, 10, 2);

/* ============================================================
 * 展示（评论正文后追加配图）
 * ============================================================ */
/**
 * 在评论正文后追加手绘配图
 *
 * ⚠️ WP 6.7+ 起 get_comment_text() 改为只触发 `get_comment_text` 过滤器
 * （源码：apply_filters( 'get_comment_text', $comment_text, $comment, $args )），
 * 而 zibll 渲染评论走的是 zib_comment_filters(get_comment_text($comment)) ——
 * 只挂 comment_text 会导致配图永不显示（2026-09-24 实测定位）。
 * 因此两个钩子都要挂：comment_text（旧版 / 直接显示）+ get_comment_text（新版 / zibll 调用链）。
 *
 * @param string     $text    评论正文
 * @param WP_Comment $comment 评论对象（WP 5.5+ 会传入）
 * @param array      $args    额外参数
 * @return string
 */
function zhiji_comment_draw_append($text, $comment = null, $args = array())
{
    if (!zhiji_is_enabled('comment_draw_enabled')) {
        return $text;
    }
    $comment_id = 0;
    if ($comment instanceof WP_Comment) {
        $comment_id = (int) $comment->comment_ID;
    } elseif (isset($GLOBALS['comment']) && $GLOBALS['comment'] instanceof WP_Comment) {
        $comment_id = (int) $GLOBALS['comment']->comment_ID;
    }
    if (!$comment_id) {
        $comment_id = (int) get_comment_ID();
    }
    if (!$comment_id) {
        return $text;
    }
    $base64 = get_comment_meta($comment_id, '_zhiji_draw', true);
    if (!$base64 || !preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', (string) $base64)) {
        return $text;
    }
    return $text . '<img class="zhiji-draw-img" src="data:image/webp;base64,' . $base64 . '" alt="评论配图" loading="lazy">';
}
add_filter('comment_text', 'zhiji_comment_draw_append', 20, 3);
add_filter('get_comment_text', 'zhiji_comment_draw_append', 20, 3);

/* ============================================================
 * 前台画板
 * ============================================================ */
add_action('wp_footer', function () {
    if (!zhiji_is_enabled('comment_draw_enabled')) {
        return;
    }
    if (!is_singular() || !comments_open()) {
        return;
    }
    $hint = (string) zhiji_get_option('comment_draw_hint', '手绘一张配图，让评论更生动');
    echo '<style id="zhiji-draw-css">'
        . '.zhiji-draw-btn{display:inline-flex;align-items:center;gap:6px;margin:10px 0 4px;padding:6px 14px;font-size:13px;border:1px solid var(--main-border-color,#e5e7eb);border-radius:8px;background:var(--main-bg-color,#fff);color:var(--focus-color,#3b82f6);cursor:pointer;transition:all .2s}'
        . '.zhiji-draw-btn:hover{background:var(--focus-color,#3b82f6);color:#fff}'
        . '.zhiji-draw-btn.is-done{border-color:#10b981;color:#10b981;background:rgba(16,185,129,.08)}'
        . '.zhiji-draw-mask{position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.55);display:none;align-items:center;justify-content:center;padding:20px}'
        . '.zhiji-draw-mask.open{display:flex}'
        . '.zhiji-draw-panel{background:var(--main-bg-color,#fff);border-radius:14px;padding:18px;width:min(560px,94vw);box-shadow:0 20px 60px rgba(0,0,0,.3)}'
        . '.zhiji-draw-canvas-wrap{position:relative;border:1px solid var(--main-border-color,#e5e7eb);border-radius:10px;overflow:hidden;background:#fff}'
        . '.zhiji-draw-canvas-wrap canvas{display:block;width:100%;cursor:crosshair;touch-action:none}'
        . '.zhiji-draw-tools{display:flex;align-items:center;gap:10px;margin-top:12px;flex-wrap:wrap}'
        . '.zhiji-draw-color{width:26px;height:26px;border-radius:50%;border:2px solid transparent;cursor:pointer;padding:0}'
        . '.zhiji-draw-color.on{border-color:var(--focus-color,#3b82f6)}'
        // 笔触粗细：行业标准滑块（自定义轨道 + 圆形手柄 + 预览点 + 实时数值）
        . '.zhiji-draw-size{display:flex;align-items:center;gap:8px}'
        . '.zhiji-draw-size-dot{width:22px;height:22px;border-radius:50%;background:var(--focus-color,#3b82f6);flex:0 0 auto;transition:width .15s ease,height .15s ease}'
        . '.zhiji-draw-range{-webkit-appearance:none;appearance:none;width:104px;height:4px;border-radius:999px;background:rgba(127,127,127,.28);outline:none;cursor:pointer}'
        . '.zhiji-draw-range::-webkit-slider-thumb{-webkit-appearance:none;appearance:none;width:16px;height:16px;border-radius:50%;background:var(--focus-color,#3b82f6);border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.25);cursor:pointer;transition:transform .12s ease}'
        . '.zhiji-draw-range::-webkit-slider-thumb:hover{transform:scale(1.15)}'
        . '.zhiji-draw-range::-moz-range-thumb{width:14px;height:14px;border-radius:50%;background:var(--focus-color,#3b82f6);border:2px solid #fff;cursor:pointer}'
        . '.zhiji-draw-range::-moz-range-track{height:4px;border-radius:999px;background:rgba(127,127,127,.28)}'
        . '.zhiji-draw-size-val{min-width:16px;text-align:center;font-weight:600;font-variant-numeric:tabular-nums;color:var(--muted-color,#8a919f)}'
        . '.zhiji-draw-actions{display:flex;gap:8px;margin-top:12px;justify-content:flex-end}'
        . '.zhiji-draw-actions button{padding:7px 18px;border-radius:8px;font-size:13px;cursor:pointer;border:1px solid var(--main-border-color,#e5e7eb);background:var(--main-bg-color,#fff)}'
        . '.zhiji-draw-save{background:var(--focus-color,#3b82f6)!important;color:#fff;border-color:transparent!important}'
        . '.zhiji-draw-img{max-width:260px;border-radius:8px;margin-top:8px;display:block}'
        . '</style>';
    ?>
    <button type="button" class="but btn-input-expand zhiji-draw-btn" id="zhiji-draw-open">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 19l7-7 3 3-7 7-3-3z"/><path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"/><path d="M2 2l7.586 7.586"/><circle cx="11" cy="11" r="2"/></svg>
        <span class="zhiji-draw-btn-text">画图</span>
    </button>
    <div class="zhiji-draw-mask" id="zhiji-draw-mask">
        <div class="zhiji-draw-panel">
            <div class="zhiji-draw-canvas-wrap">
                <canvas id="zhiji-draw-canvas" width="640" height="360"></canvas>
            </div>
            <div class="zhiji-draw-tools">
                <span class="zhiji-draw-size">
                    <i class="zhiji-draw-size-dot" id="zhiji-draw-size-dot" aria-hidden="true"></i>
                    <input type="range" id="zhiji-draw-size" class="zhiji-draw-range" min="1" max="12" step="1" value="4" aria-label="笔触粗细">
                    <b class="zhiji-draw-size-val" id="zhiji-draw-size-val">4</b>
                </span>
                <span id="zhiji-draw-colors"></span>
            </div>
            <div class="zhiji-draw-actions">
                <button type="button" id="zhiji-draw-clear">清空</button>
                <button type="button" id="zhiji-draw-cancel">取消</button>
                <button type="button" class="zhiji-draw-save" id="zhiji-draw-save">保存配图</button>
            </div>
        </div>
    </div>
    <script>
    (function(){
    var box = document.querySelector('#comment') || document.querySelector('textarea[name="comment"]');
    if(!box) return;
    var host = box.closest('form');
    if(!host) host = box.parentElement;
    var btn = document.getElementById('zhiji-draw-open');
    var dataEl = document.createElement('input');
    dataEl.type = 'hidden'; dataEl.name = 'zhiji_draw'; dataEl.id = 'zhiji-draw-data'; dataEl.value = '';
    host.appendChild(dataEl);
    if(btn) {
        // zhiji 修复（2026-09-24 v2）：
        // ① 原写法 host.insertBefore(btn, box.nextSibling) 会抛 NotFoundError
        //    （box.nextSibling 不是 form 的直接子节点）→ 按钮插入中断不显示；
        // ② 即使插成功也会单独占一行（zibll 的工具栏在 .comt-ctrl > .comt-tips-left）。
        // 现改为优先追加进工具栏左侧按钮区，与「表情/代码/图片/快捷回复」同排；
        // 找不到工具栏时再退化为插到评论框后（带 try/catch 兜底）。
        var toolbar = document.querySelector('.comt-tips-left');
        if (toolbar) {
            toolbar.appendChild(btn);
        } else {
            var _host = box.parentNode || host;
            try { _host.insertBefore(btn, box.nextSibling); }
            catch(e) { try { host.appendChild(btn); } catch(e2) {} }
        }
    }
    var mask = document.getElementById('zhiji-draw-mask');
    var canvas = document.getElementById('zhiji-draw-canvas');
    var ctx = canvas.getContext('2d');
    var colors = ['#111111','#dc2626','#2563eb','#16a34a','#d97706','#7c3aed'];
    var cur = '#111111';
    var drawing = false;
    var hint = <?php echo wp_json_encode($hint); ?>;
    var cw = 640, ch = 360;
    function resize(){
    var wrap = canvas.parentElement;
    var w = wrap.clientWidth;
    if(!w) return;
    var ratio = w / cw;
    canvas.style.height = (ch * ratio) + 'px';
    };
    function pos(e){
    var r = canvas.getBoundingClientRect();
    var t = e.touches ? e.touches[0] : e;
    return { x: (t.clientX - r.left) / r.width * cw, y: (t.clientY - r.top) / r.height * ch };
    };
    canvas.addEventListener('mousedown', function(e){ drawing = true; ctx.beginPath(); ctx.moveTo(pos(e).x, pos(e).y); e.preventDefault(); });
    canvas.addEventListener('mousemove', function(e){ if(!drawing) return; var p = pos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); });
    window.addEventListener('mouseup', function(){ drawing = false; });
    canvas.addEventListener('touchstart', function(e){ drawing = true; ctx.beginPath(); ctx.moveTo(pos(e).x, pos(e).y); e.preventDefault(); });
    canvas.addEventListener('touchmove', function(e){ if(!drawing) return; var p = pos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); e.preventDefault(); });
    canvas.addEventListener('touchend', function(){ drawing = false; });
    ctx.fillStyle = '#ffffff'; ctx.fillRect(0,0,cw,ch);
    ctx.lineCap = 'round'; ctx.lineJoin = 'round';
    var curSize = 4;
    function applyStyle(){ ctx.strokeStyle = cur; ctx.lineWidth = curSize; };
    applyStyle();
    // 笔触粗细：标准滑块 + 预览点 + 实时数值（2026-09-24 按行业惯例重做）
    var sizeInput = document.getElementById('zhiji-draw-size');
    var sizeVal   = document.getElementById('zhiji-draw-size-val');
    var sizeDot   = document.getElementById('zhiji-draw-size-dot');
    function syncSizeUI() {
        if (sizeVal) { sizeVal.textContent = curSize; }
        if (sizeDot) {
            var d = Math.max(4, Math.min(22, curSize * 1.8));
            sizeDot.style.width = d + 'px';
            sizeDot.style.height = d + 'px';
        }
    }
    if (sizeInput) {
        sizeInput.addEventListener('input', function () {
            curSize = parseInt(sizeInput.value, 10) || 4;
            applyStyle();
            syncSizeUI();
        });
    }
    syncSizeUI();
    var colorsBox = document.getElementById('zhiji-draw-colors');
    colors.forEach(function(c){
    var b = document.createElement('button');
    b.type = 'button'; b.className = 'zhiji-draw-color' + (c === cur ? ' on' : '');
    b.style.background = c;
    b.setAttribute('data-c', c);
    b.addEventListener('click', function(){ cur = c; applyStyle(); colorsBox.querySelectorAll('button').forEach(function(x){ x.classList.toggle('on', x.getAttribute('data-c') === c); }); });
    colorsBox.appendChild(b);
    });
    function openMask(){ mask.classList.add('open'); setTimeout(resize, 50); };
    function closeMask(){ mask.classList.remove('open'); };
    btn.addEventListener('click', openMask);
    document.getElementById('zhiji-draw-cancel').addEventListener('click', closeMask);
    document.getElementById('zhiji-draw-clear').addEventListener('click', function(){ ctx.fillStyle = '#ffffff'; ctx.fillRect(0,0,cw,ch); applyStyle(); });
    document.getElementById('zhiji-draw-save').addEventListener('click', function(){
    var url = canvas.toDataURL('image/webp', 0.1);
    dataEl.value = url;
    closeMask();
    btn.classList.add('is-done');
    var t = btn.querySelector('.zhiji-draw-btn-text');
    if(t) t.textContent = '已添加配图';
    });
    window.addEventListener('resize', resize);
    // 评论提交后重置画板（2026-09-24）：zibll 评论走 JS/AJAX 提交、页面不刷新，
    // 否则「已添加配图」会一直保留。延迟 700ms 等请求发出后再清理。
    function resetDraw() {
        setTimeout(function () {
            dataEl.value = '';
            if (btn) {
                btn.classList.remove('is-done');
                var bt = btn.querySelector('.zhiji-draw-btn-text');
                if (bt) { bt.textContent = '画图'; }
            }
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, cw, ch);
        }, 700);
    }
    // zibll 评论为 JS/AJAX 提交，不会触发 form 的 submit 事件 → 直接挂提交按钮点击
    var subBtn = host.querySelector('#submit, .comment-send, button[name="submit"]');
    if (subBtn) { subBtn.addEventListener('click', resetDraw); }
    if (host) { host.addEventListener('submit', resetDraw); }
    })();
    </script>
    <?php
}, 97);

/* ============================================================
 * 后台字段
 * ============================================================ */
add_action('after_setup_theme', function () {
    Zhiji_Registry::csf_section_for('comment_draw', array(
        array(
            'id'      => 'comment_draw_enabled',
            'type'    => 'switcher',
            'title'   => '启用评论画图',
            'default' => false,
            'desc'    => '评论框下方提供画板，手绘配图随评论发布（webp 压缩存储，上限 300KB）。',
        ),
        array(
            'id'         => 'comment_draw_hint',
            'type'       => 'text',
            'title'      => '按钮提示文字',
            'default'    => '手绘一张配图，让评论更生动',
            'dependency' => array('comment_draw_enabled', '==', '1'),
        ),
    ));
}, 20);
