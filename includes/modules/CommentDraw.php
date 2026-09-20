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
    if (!preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', $base64)) {
        return;
    }
    if (strlen($base64) > 300 * 1024) {
        return;
    }
    update_comment_meta($comment_id, '_zhiji_draw', $base64);
}, 10, 2);

/* ============================================================
 * 展示（评论正文后追加配图）
 * ============================================================ */
add_filter('comment_text', function ($text) {
    if (!zhiji_is_enabled('comment_draw_enabled')) {
        return $text;
    }
    $comment_id = get_comment_ID();
    if (!$comment_id) {
        return $text;
    }
    $base64 = get_comment_meta($comment_id, '_zhiji_draw', true);
    if (!$base64 || !preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', $base64)) {
        return $text;
    }
    return $text . '<img class="zhiji-draw-img" src="data:image/webp;base64,' . $base64 . '" alt="评论配图" loading="lazy">';
}, 20);

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
        . '.zhiji-draw-size{display:flex;align-items:center;gap:4px;font-size:12px;color:var(--muted-color,#8a919f)}'
        . '.zhiji-draw-size input{width:60px}'
        . '.zhiji-draw-actions{display:flex;gap:8px;margin-top:12px;justify-content:flex-end}'
        . '.zhiji-draw-actions button{padding:7px 18px;border-radius:8px;font-size:13px;cursor:pointer;border:1px solid var(--main-border-color,#e5e7eb);background:var(--main-bg-color,#fff)}'
        . '.zhiji-draw-save{background:var(--focus-color,#3b82f6)!important;color:#fff;border-color:transparent!important}'
        . '.zhiji-draw-img{max-width:260px;border-radius:8px;margin-top:8px;display:block}'
        . '</style>';
    ?>
    <button type="button" class="zhiji-draw-btn" id="zhiji-draw-open">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 19l7-7 3 3-7 7-3-3z"/><path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"/><path d="M2 2l7.586 7.586"/><circle cx="11" cy="11" r="2"/></svg>
        <span class="zhiji-draw-btn-text">画图</span>
    </button>
    <div class="zhiji-draw-mask" id="zhiji-draw-mask">
        <div class="zhiji-draw-panel">
            <div class="zhiji-draw-canvas-wrap">
                <canvas id="zhiji-draw-canvas" width="640" height="360"></canvas>
            </div>
            <div class="zhiji-draw-tools">
                <span class="zhiji-draw-size">粗细 <input type="range" id="zhiji-draw-size" min="1" max="12" value="3"></span>
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
    if(btn) host.insertBefore(btn, box.nextSibling);
    var mask = document.getElementById('zhiji-draw-mask');
    var canvas = document.getElementById('zhiji-draw-canvas');
    var ctx = canvas.getContext('2d');
    var colors = ['#111111','#dc2626','#2563eb','#16a34a','#d97706','#7c3aed'];
    var cur = '#111111';
    var drawing = false;
    var hint = <?php echo wp_json_encode($hint); ?>;
    var cw = 640, ch = 360;
    var resize=function(){
    var wrap = canvas.parentElement;
    var w = wrap.clientWidth;
    if(!w) return;
    var ratio = w / cw;
    canvas.style.height = (ch * ratio) + 'px';
    };
    var pos=function(e){
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
    var applyStyle=function(){ ctx.strokeStyle = cur; ctx.lineWidth = document.getElementById('zhiji-draw-size').value; };
    applyStyle();
    document.getElementById('zhiji-draw-size').addEventListener('input', applyStyle);
    var colorsBox = document.getElementById('zhiji-draw-colors');
    colors.forEach(function(c){
    var b = document.createElement('button');
    b.type = 'button'; b.className = 'zhiji-draw-color' + (c === cur ? ' on' : '');
    b.style.background = c;
    b.setAttribute('data-c', c);
    b.addEventListener('click', function(){ cur = c; applyStyle(); colorsBox.querySelectorAll('button').forEach(function(x){ x.classList.toggle('on', x.getAttribute('data-c') === c); }); });
    colorsBox.appendChild(b);
    });
    var openMask=function(){ mask.classList.add('open'); setTimeout(resize, 50); };
    var closeMask=function(){ mask.classList.remove('open'); };
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
