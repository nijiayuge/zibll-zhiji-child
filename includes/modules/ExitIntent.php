<?php
/**
 * @module  ExitIntent
 * @desc    退出挽留弹窗：鼠标移出视口顶部时弹出，每次会话仅一次；支持优惠码复制与看板娘联动
 * @option  exit_intent_enabled       总开关
 *          exit_intent_title         弹窗标题
 *          exit_intent_desc          弹窗描述
 *          exit_intent_image         顶部图片 URL
 *          exit_intent_btn_text      按钮文字
 *          exit_intent_btn_url       按钮链接
 *          exit_intent_coupon_code   挽留优惠码
 *          exit_intent_coupon_desc   优惠码说明
 *          exit_intent_kanban_trigger 联动看板娘说话
 * @hook    wp_footer · 输出弹窗与脚本
 * @event   zhiji_kanban_event · 派发给看板娘（Kanban 模块监听，防御式派发）
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/ExitIntent.php`（CouponGive 邮箱领取区块为
 *          function_exists 防御式调用，CouponGive 迁移后自动生效）
 */

defined('ABSPATH') || exit;

/* ============================================================
 * 模块自注册
 * ============================================================ */
Zhiji_Registry::register_module('exit_intent', array(
    'title'    => '退出挽留弹窗',
    'parent'   => 'zhiji_element',
    'priority' => 80,
    'option'   => 'exit_intent_enabled',
));

/* ============================================================
 * 渲染（钩子常注册，回调内判开关）
 * ============================================================ */
zhiji_footer_add( 'exit-intent', function () {
    if (!zhiji_is_enabled('exit_intent_enabled')) {
        return;
    }
    $title          = (string) zhiji_get_option('exit_intent_title', '别走呀～');
    $desc           = (string) zhiji_get_option('exit_intent_desc', '还有超多优质资源等你发现，先逛逛再走吧！');
    $image          = (string) zhiji_get_option('exit_intent_image', '');
    $btn_text       = (string) zhiji_get_option('exit_intent_btn_text', '留下看看');
    $btn_url        = (string) zhiji_get_option('exit_intent_btn_url', '');
    $btn_url        = ('' === $btn_url) ? home_url('/') : $btn_url;
    $coupon         = trim((string) zhiji_get_option('exit_intent_coupon_code', ''));
    $coupon_desc    = (string) zhiji_get_option('exit_intent_coupon_desc', '专属优惠码，下单立减！');
    $kanban_trigger = (bool) zhiji_get_option('exit_intent_kanban_trigger', true);
    ?>
    <style>
    .zhiji-exit-mask{position:fixed;inset:0;z-index:99996;background:rgba(0,0,0,.5);display:none;align-items:center;justify-content:center;padding:16px}
    .zhiji-exit-mask.open{display:flex;animation:zhijiExitFade .2s ease}
    .zhiji-exit-box{position:relative;width:100%;max-width:420px;background:#fff;border-radius:18px;overflow:hidden;box-shadow:0 24px 70px rgba(0,0,0,.3);text-align:center;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"PingFang SC","Microsoft YaHei",sans-serif}
    .zhiji-exit-box img{width:100%;height:160px;object-fit:cover;display:block;background:var(--zhiji-surface-soft,#eaf2fe)}
    .zhiji-exit-body{padding:20px 22px 22px}
    .zhiji-exit-body h3{margin:0 0 8px;font-size:20px;color:var(--zhiji-brand, #2e7cf6)}
    .zhiji-exit-body p{margin:0 0 16px;color:#666;font-size:14px;line-height:1.7}
    .zhiji-exit-btn{display:inline-block;padding:11px 28px;border-radius:24px;background:var(--zhiji-brand, #2e7cf6);color:#fff;font-size:15px;font-weight:600;text-decoration:none;border:none;cursor:pointer}
    .zhiji-exit-close{position:absolute;top:10px;right:12px;width:30px;height:30px;border:none;border-radius:50%;background:rgba(255,255,255,.85);color:#555;font-size:18px;cursor:pointer;line-height:1;z-index:2}
    @keyframes zhijiExitFade{from{opacity:0}to{opacity:1}}
    .zhiji-exit-coupon{margin:0 0 16px;padding:12px 16px;background:linear-gradient(135deg,#fff7e6,#ffe8cc);border:1px dashed #faad14;border-radius:12px}
    .zhiji-exit-coupon-label{font-size:12px;color:#d48806;margin-bottom:4px}
    .zhiji-exit-coupon-code{display:flex;align-items:center;justify-content:center;gap:8px}
    .zhiji-exit-coupon-code span{font-size:20px;font-weight:700;color:#d46b08;letter-spacing:2px}
    .zhiji-exit-coupon-copy{padding:4px 12px;font-size:12px;border:1px solid #faad14;background:#fff;color:#d48806;border-radius:12px;cursor:pointer}
    .zhiji-exit-coupon-copy:hover{background:#fff7e6}
    </style>

    <div class="zhiji-exit-mask" id="zhijiExitMask" role="dialog" aria-modal="true">
        <div class="zhiji-exit-box">
            <button type="button" class="zhiji-exit-close" id="zhijiExitClose" aria-label="关闭">&times;</button>
            <?php if ($image) : ?><img src="<?php echo esc_url($image); ?>" alt=""><?php endif; ?>
            <div class="zhiji-exit-body">
                <h3><?php echo esc_html($title); ?></h3>
                <p><?php echo esc_html($desc); ?></p>
                <?php if ('' !== $coupon) : ?>
                <div class="zhiji-exit-coupon">
                    <div class="zhiji-exit-coupon-label"><?php echo esc_html($coupon_desc); ?></div>
                    <div class="zhiji-exit-coupon-code">
                        <span id="zhijiExitCouponCode"><?php echo esc_html($coupon); ?></span>
                        <button type="button" class="zhiji-exit-coupon-copy" id="zhijiExitCopyBtn">复制</button>
                    </div>
                </div>
                <?php endif; ?>
                <?php
                // 邮箱领取优惠码区块（CouponGive 模块提供；该模块迁移后自动生效）
                if (function_exists('zhiji_coupon_give_exit_block')) {
                    echo zhiji_coupon_give_exit_block(); // phpcs:ignore WordPress.Security.EscapeOutput -- 模块内部已转义
                }
                ?>
                <a class="zhiji-exit-btn" href="<?php echo esc_url($btn_url); ?>"><?php echo esc_html($btn_text); ?></a>
            </div>
        </div>
    </div>

    <script>
    (function(){
        var mask=document.getElementById('zhijiExitMask');
        if(!mask) return;
        var KEY='zhiji_exit_shown';
        var kanbanTrigger=<?php echo $kanban_trigger ? 'true' : 'false'; ?>;
        function show(){
            try { if(sessionStorage.getItem(KEY)) return; sessionStorage.setItem(KEY,'1'); } catch(err){}
            mask.classList.add('open');
            if(kanbanTrigger && typeof document.dispatchEvent==='function'){
                try { document.dispatchEvent(new CustomEvent('zhiji_kanban_event',{detail:{type:'404',text:'别走呀，再看看嘛～'}})); } catch(err){}
            }
        }
        function hide(){ mask.classList.remove('open'); }
        // 验收测试通道（2026-09-23 新增）：URL 带 ?zhiji_exit_test=1 时直接弹出，
        // 绕过 sessionStorage 去重，便于配置后立即验收弹窗效果
        try {
            if (new URLSearchParams(location.search).get('zhiji_exit_test') === '1') {
                setTimeout(function () { mask.classList.add('open'); }, 600);
            }
        } catch (err) {}
        document.addEventListener('mouseout',function(e){
            if(e.clientY<=0 && !e.relatedTarget && !e.toElement){ show(); }
        });
        document.getElementById('zhijiExitClose').onclick=hide;
        mask.addEventListener('click',function(e){ if(e.target===mask) hide(); });
        document.addEventListener('keydown',function(e){ if(e.key==='Escape') hide(); });
        var copyBtn=document.getElementById('zhijiExitCopyBtn');
        if(copyBtn){
            copyBtn.onclick=function(){
                var code=document.getElementById('zhijiExitCouponCode').textContent;
                if(navigator.clipboard){
                    navigator.clipboard.writeText(code).then(function(){ copyBtn.textContent='已复制'; setTimeout(function(){copyBtn.textContent='复制';},1500); });
                }else{
                    var ta=document.createElement('textarea'); ta.value=code; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
                    copyBtn.textContent='已复制'; setTimeout(function(){copyBtn.textContent='复制';},1500);
                }
            };
        }
    })();
    </script>
    <?php
}, 25 );

/* ============================================================
 * 后台字段
 * ============================================================ */
    // 2026-09-26：改为 Registry 统一登记（P3-⑨），钩子由核心统一挂载
    Zhiji_Registry::register_options('exit_intent', array(
        array(
            'id'      => 'exit_intent_enabled',
            'type'    => 'switcher',
            'title'   => '启用退出挽留弹窗',
            'default' => false,
            'desc'    => '鼠标移出视口顶部时弹出挽留层，每次会话仅弹一次。',
        ),
        array(
            'id'         => 'exit_intent_title',
            'type'       => 'text',
            'title'      => '弹窗标题',
            'desc'       => __( '弹窗大标题，建议简短有力。', 'zhiji' ),
            'default'    => '别走呀～',
            'dependency' => array('exit_intent_enabled', '==', '1'),
        ),
        array(
            'id'         => 'exit_intent_desc',
            'type'       => 'textarea',
            'title'      => '弹窗描述',
            'desc'       => __( '弹窗说明文字，用于解释挽留理由。', 'zhiji' ),
            'default'    => '还有超多优质资源等你发现，先逛逛再走吧！',
            'dependency' => array('exit_intent_enabled', '==', '1'),
        ),
        array(
            'id'          => 'exit_intent_image',
            'type'        => 'text',
            'title'       => '顶部图片 URL',
            'placeholder' => '留空则不显示图片',
            'desc'        => '展示在弹窗顶部的横幅图片（建议 4:1 左右）。',
            'dependency'  => array('exit_intent_enabled', '==', '1'),
        ),
        array(
            'id'         => 'exit_intent_btn_text',
            'type'       => 'text',
            'title'      => '按钮文字',
            'desc'       => __( '主按钮的文字（如「留下看看」）。', 'zhiji' ),
            'default'    => '留下看看',
            'dependency' => array('exit_intent_enabled', '==', '1'),
        ),
        array(
            'id'          => 'exit_intent_btn_url',
            'type'        => 'text',
            'title'       => '按钮链接',
            'desc'       => __( '主按钮跳转地址；留空则跳转首页。', 'zhiji' ),
            'placeholder' => '留空则指向首页',
            'dependency'  => array('exit_intent_enabled', '==', '1'),
        ),
        array(
            'id'          => 'exit_intent_coupon_code',
            'type'        => 'text',
            'title'       => '挽留优惠码',
            'placeholder' => '留空则不显示优惠码',
            'desc'        => '填写后弹窗内展示优惠码并支持一键复制。',
            'dependency'  => array('exit_intent_enabled', '==', '1'),
        ),
        array(
            'id'         => 'exit_intent_coupon_desc',
            'type'       => 'text',
            'title'      => '优惠码说明',
            'desc'       => __( '优惠码区域的说明文字。', 'zhiji' ),
            'default'    => '专属优惠码，下单立减！',
            'dependency' => array('exit_intent_enabled', '==', '1'),
        ),
        array(
            'id'         => 'exit_intent_kanban_trigger',
            'type'       => 'switcher',
            'title'      => '联动看板娘',
            'default'    => true,
            'desc'       => '弹窗出现时让看板娘说一句挽留台词（需启用看板娘模块）。',
            'dependency' => array('exit_intent_enabled', '==', '1'),
        ),
    ), 20);
