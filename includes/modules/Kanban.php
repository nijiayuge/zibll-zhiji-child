<?php
/**
 * @module  Kanban
 * @desc    看板娘：Live2D 模型悬浮于页面左下/右下角，懒加载不阻塞首屏
 * @option  kanban_enabled   总开关
 *          kanban_position  显示位置 left|right
 * @hook    wp_footer · 输出容器与懒加载脚本
 * @event   监听/派发 zhiji_kanban_event（供 ExitIntent 等模块联动说话）
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/Kanban.php`
 *          ⚠️ 修复：v1 硬编码旧域名 zhiji.bbroot.com + 旧主题路径 zhiji-child，
 *          v2 改用 ZHIJI_ASSETS_URL 常量（资源随迁 assets/zhiji/live2d/）
 */

defined('ABSPATH') || exit;

/* ============================================================
 * 模块自注册
 * ============================================================ */
Zhiji_Registry::register_module('kanban', array(
    'title'    => '看板娘',
    'parent'   => 'zhiji_page',
    'priority' => 70,
    'option'   => 'kanban_enabled',
));

/* ============================================================
 * 前台输出（钩子常注册，回调内判开关）
 * ============================================================ */
zhiji_footer_add( 'kanban', function () {
    if (!zhiji_is_enabled('kanban_enabled')) {
        return;
    }
    $position  = ('left' === (string) zhiji_get_option('kanban_position', 'right')) ? 'left' : 'right';
    $alignment = $position;
    $inline    = ('left' === $position) ? 'z-index:9999' : 'z-index:9999;right:74px';
    $l2d       = ZHIJI_ASSETS_URL . 'live2d/';
    ?>
    <div class="pio-container" id="zhiji-pio-container" aria-label="看板娘" style="<?php echo esc_attr($inline); ?>"><div class="pio-action"></div><canvas id="pio"></canvas></div>
    <script>
    (function(){
        function move(){ var c=document.getElementById('zhiji-pio-container'); if(c&&document.body&&c.parentNode!==document.body){ document.body.appendChild(c); } }
        move();
        var loaded=false;
        function boot(){
            if(loaded){ return; }
            loaded=true;
            var head=document.head||document.getElementsByTagName('head')[0];
            var l2d=<?php echo wp_json_encode($l2d); ?>;
            var libs=[
                l2d+'TweenLite.js',
                l2d+'live2dcubismcore.min.js',
                l2d+'pixi.min.js',
                l2d+'cubism4.min.js',
                l2d+'pio.js',
                l2d+'pio_sdk4.js'
            ];
            var i=0;
            function next(){
                if(i<libs.length){
                    var s=document.createElement('script');
                    s.src=libs[i++]; s.onload=next; s.onerror=next;
                    head.appendChild(s);
                    return;
                }
                var l=document.createElement('link');
                l.rel='stylesheet'; l.href=l2d+'pio.css';
                head.appendChild(l);
                var st=document.createElement('style');
                st.textContent='#zhiji-pio-container #pio{width:240px !important;height:240px !important}@media (max-width:768px){#zhiji-pio-container #pio{width:8em !important;height:8em !important}}';
                head.appendChild(st);
                  var a=document.createElement('script');
                  a.text="window.__zhiji_pio_alignment='<?php echo esc_js( $alignment ); ?>';window.__zhiji_live2d_base="+JSON.stringify(l2d)+";";
                  head.appendChild(a);
                  var m=document.createElement('script');
                  m.src=l2d+'load.js?v=1.8.3';
                head.appendChild(m);
            }
            next();
        }
        if(window.addEventListener){
            window.addEventListener('load',function(){ setTimeout(boot,2500); });
        } else {
            window.onload=function(){ setTimeout(boot,2500); };
        }
        setTimeout(boot,4500);
        var box=document.getElementById('zhiji-pio-container');
        if(box){ box.addEventListener('click',boot); }
    })();
    </script>
    <?php
}, 15 );

/* ============================================================
 * 后台字段
 * ============================================================ */
    // 2026-09-26：改为 Registry 统一登记（P3-⑨），钩子由核心统一挂载
    Zhiji_Registry::register_options('kanban', array(
        array(
            'id'      => 'kanban_enabled',
            'type'    => 'switcher',
            'title'   => '启用看板娘',
            'desc'    => '启用后页面右下角出现 Live2D 看板娘，可拖动、点击互动。',
            'default' => false,
        ),
        array(
            'id'         => 'kanban_position',
            'type'       => 'button_set',
            'title'      => '显示位置',
            'default'    => 'right',
            'options'    => array('left' => '左侧', 'right' => '右侧'),
            'desc'       => '看板娘悬浮在页面左下角或右下角。',
            'dependency' => array('kanban_enabled', '==', '1'),
        ),
    ), 20);
