<?php
/**
 * @module  RightFloatNav
 * @desc    右侧悬浮快捷导航（回到顶部等）
 * @option  right_float_nav_enabled  总开关
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/RightFloatNav.php`
 *          （v2 迁移：CSF 块转 csf_section_for_legacy、常量与命名规范对齐）
 */

defined('ABSPATH') || exit;

Zhiji_Registry::register_module('right_float_nav', array(
    'title'    => '右侧悬浮导航',
    'parent'   => 'zhiji_page',
    'priority' => 50,
    'option'   => 'right_float_nav_enabled',
));



if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 检查右侧悬浮导航是否启用
 */
function zhiji_right_float_nav_is_enabled() {
    $val = zhiji_get_option( 'right_float_nav_enabled', false );
    return filter_var( $val, FILTER_VALIDATE_BOOLEAN );
}

/**
 * 前端注入悬浮导航HTML
 */
add_action( 'wp_footer', 'zhiji_right_float_nav_render', 15 );
function zhiji_right_float_nav_render() {
    if ( ! zhiji_right_float_nav_is_enabled() ) {
        return;
    }

    $style = zhiji_get_option( 'right_float_nav_style', 'icon' );
    $show_backtop = filter_var( zhiji_get_option( 'right_float_nav_backtop', true ), FILTER_VALIDATE_BOOLEAN );
    $show_qq = filter_var( zhiji_get_option( 'right_float_nav_qq', false ), FILTER_VALIDATE_BOOLEAN );
    $show_wechat = filter_var( zhiji_get_option( 'right_float_nav_wechat', false ), FILTER_VALIDATE_BOOLEAN );
    $show_lottery = filter_var( zhiji_get_option( 'right_float_nav_lottery', false ), FILTER_VALIDATE_BOOLEAN );
    $show_danmu = filter_var( zhiji_get_option( 'right_float_nav_danmu', false ), FILTER_VALIDATE_BOOLEAN );
    $show_music = filter_var( zhiji_get_option( 'right_float_nav_music', false ), FILTER_VALIDATE_BOOLEAN );
    $qq_number = zhiji_get_option( 'right_float_nav_qq_number', '' );
    $wechat_qr = zhiji_get_option( 'right_float_nav_wechat_qr', '' );
    $position = zhiji_get_option( 'right_float_nav_position', 'right' );

    $nav_class = 'zhiji-right-float-nav style-' . $style . ' position-' . $position;

    ?>
    <!-- 知集子主题 - 右侧悬浮导航 -->
    <div class="<?php echo esc_attr( $nav_class ); ?>" id="zhiji-right-float-nav">
        <?php if ( $show_lottery ) : ?>
        <div class="zhiji-float-item zhiji-float-lottery" title="锦鲤抽奖" onclick="window.location.href='<?php echo esc_url( home_url( '/?zhiji_lottery=1' ) ); ?>'">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
            </svg>
            <span class="zhiji-float-label">抽奖</span>
        </div>
        <?php endif; ?>

        <?php if ( $show_danmu ) : ?>
        <div class="zhiji-float-item zhiji-float-danmu" id="zhiji-float-danmu-toggle" title="弹幕开关">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor">
                <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/>
            </svg>
            <span class="zhiji-float-label">弹幕</span>
        </div>
        <?php endif; ?>

        <?php if ( $show_music ) : ?>
        <div class="zhiji-float-item zhiji-float-music" id="zhiji-float-music-toggle" title="音乐播放器">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor">
                <path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/>
            </svg>
            <span class="zhiji-float-label">音乐</span>
        </div>
        <?php endif; ?>

        <?php if ( $show_qq && $qq_number ) : ?>
        <a class="zhiji-float-item zhiji-float-qq" href="tencent://message/?uin=<?php echo esc_attr( $qq_number ); ?>&Site=&Menu=yes" target="_blank" title="联系客服QQ">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/>
            </svg>
            <span class="zhiji-float-label">客服</span>
        </a>
        <?php endif; ?>

        <?php if ( $show_wechat && $wechat_qr ) : ?>
        <div class="zhiji-float-item zhiji-float-wechat" title="微信二维码">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor">
                <path d="M8.691 2.188C3.891 2.188 0 5.476 0 9.53c0 2.212 1.17 4.203 3.002 5.55a.59.59 0 0 1 .213.665l-.39 1.48c-.019.07-.048.141-.048.213 0 .163.13.295.29.295a.326.326 0 0 0 .167-.054l1.903-1.114a.864.864 0 0 1 .717-.098 10.16 10.16 0 0 0 2.837.403c.276 0 .543-.027.811-.05-.857-2.578.157-4.972 1.932-6.446 1.703-1.415 3.882-1.98 5.853-1.838-.576-3.583-4.196-6.348-8.596-6.348zM5.785 5.991c.642 0 1.162.529 1.162 1.18a1.17 1.17 0 0 1-1.162 1.178A1.17 1.17 0 0 1 4.623 7.17c0-.651.52-1.18 1.162-1.18zm5.813 0c.642 0 1.162.529 1.162 1.18a1.17 1.17 0 0 1-1.162 1.178 1.17 1.17 0 0 1-1.162-1.178c0-.651.52-1.18 1.162-1.18zm5.34 2.867c-1.797-.052-3.746.512-5.28 1.786-1.72 1.428-2.687 3.72-1.78 6.22.942 2.453 3.666 4.229 6.884 4.229.826 0 1.622-.12 2.361-.336a.722.722 0 0 1 .598.082l1.584.926a.272.272 0 0 0 .14.047c.134 0 .24-.111.24-.247 0-.06-.023-.12-.038-.177l-.327-1.233a.582.582 0 0 1-.023-.156.49.49 0 0 1 .201-.398C23.024 18.48 24 16.82 24 14.98c0-3.21-2.931-5.837-6.656-6.088V8.89c-.135-.01-.27-.027-.407-.03zm-2.53 3.274c.535 0 .969.44.969.982a.976.976 0 0 1-.969.983.976.976 0 0 1-.969-.983c0-.542.434-.982.97-.982zm4.844 0c.535 0 .969.44.969.982a.976.976 0 0 1-.969.983.976.976 0 0 1-.969-.983c0-.542.434-.982.969-.982z"/>
            </svg>
            <span class="zhiji-float-label">微信</span>
            <div class="zhiji-float-wechat-popup">
                <img src="<?php echo esc_url( $wechat_qr ); ?>" alt="微信二维码">
                <p>扫码添加微信</p>
            </div>
        </div>
        <?php endif; ?>

        <?php if ( $show_backtop ) : ?>
        <div class="zhiji-float-item zhiji-float-backtop" id="zhiji-float-backtop" title="返回顶部">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor">
                <path d="M4 12l1.41 1.41L11 7.83V20h2V7.83l5.58 5.59L20 12l-8-8-8 8z"/>
            </svg>
            <span class="zhiji-float-label">顶部</span>
        </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * 注入悬浮导航CSS
 */
add_action( 'wp_head', 'zhiji_right_float_nav_inject_css', 20 );
function zhiji_right_float_nav_inject_css() {
    if ( ! zhiji_right_float_nav_is_enabled() ) {
        return;
    }

    $nav_color = zhiji_get_option( 'right_float_nav_color', '' );
    if ( empty( $nav_color ) ) {
        $nav_color = 'var(--zhiji-brand, #2e7cf6)';
    }

    ?>
    <style id="zhiji-right-float-nav-css">
    /* 知集子主题 - 右侧悬浮导航 */
    .zhiji-right-float-nav {
        position: fixed;
        top: 50%;
        transform: translateY(-50%);
        z-index: 9998;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .zhiji-right-float-nav.position-right {
        right: 16px;
    }
    .zhiji-right-float-nav.position-left {
        left: 16px;
    }

    .zhiji-float-item {
        position: relative;
        width: 48px;
        height: 48px;
        background: #fff;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: #666;
        box-shadow: 0 2px 12px rgba(0,0,0,.1);
        transition: all .3s ease;
        text-decoration: none;
    }
    .zhiji-float-item:hover {
        background: <?php echo esc_attr( $nav_color ); ?>;
        color: #fff;
        transform: translateX(-4px);
        box-shadow: 0 4px 16px rgba(0,0,0,.15);
    }
    .zhiji-right-float-nav.position-left .zhiji-float-item:hover {
        transform: translateX(4px);
    }

    .zhiji-float-label {
        position: absolute;
        right: 100%;
        margin-right: 8px;
        background: #333;
        color: #fff;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 12px;
        white-space: nowrap;
        opacity: 0;
        visibility: hidden;
        transition: all .2s ease;
        pointer-events: none;
    }
    .zhiji-right-float-nav.position-left .zhiji-float-label {
        right: auto;
        left: 100%;
        margin-right: 0;
        margin-left: 8px;
    }
    .zhiji-float-item:hover .zhiji-float-label {
        opacity: 1;
        visibility: visible;
    }

    /* 卡片式样式 */
    .zhiji-right-float-nav.style-card .zhiji-float-item {
        width: 56px;
        height: 56px;
        border-radius: 16px;
        flex-direction: column;
        gap: 2px;
    }
    .zhiji-right-float-nav.style-card .zhiji-float-label {
        position: static;
        opacity: 1;
        visibility: visible;
        background: none;
        color: inherit;
        padding: 0;
        font-size: 10px;
        margin: 0;
    }
    .zhiji-right-float-nav.style-card .zhiji-float-item:hover .zhiji-float-label {
        color: #fff;
    }

    /* 展开式样式 */
    .zhiji-right-float-nav.style-expand .zhiji-float-item {
        width: 48px;
        overflow: hidden;
        justify-content: flex-start;
        padding: 0 14px;
        gap: 10px;
    }
    .zhiji-right-float-nav.style-expand .zhiji-float-item:hover {
        width: 120px;
    }
    .zhiji-right-float-nav.style-expand .zhiji-float-label {
        position: static;
        opacity: 0;
        visibility: hidden;
        background: none;
        color: inherit;
        padding: 0;
        font-size: 13px;
        margin: 0;
        white-space: nowrap;
        transition: opacity .2s ease .1s;
    }
    .zhiji-right-float-nav.style-expand .zhiji-float-item:hover .zhiji-float-label {
        opacity: 1;
        visibility: visible;
        color: #fff;
    }

    /* 微信弹窗 */
    .zhiji-float-wechat-popup {
        position: absolute;
        right: 100%;
        margin-right: 12px;
        background: #fff;
        border-radius: 12px;
        padding: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,.15);
        opacity: 0;
        visibility: hidden;
        transition: all .3s ease;
        text-align: center;
        width: 140px;
    }
    .zhiji-right-float-nav.position-left .zhiji-float-wechat-popup {
        right: auto;
        left: 100%;
        margin-right: 0;
        margin-left: 12px;
    }
    .zhiji-float-wechat:hover .zhiji-float-wechat-popup {
        opacity: 1;
        visibility: visible;
    }
    .zhiji-float-wechat-popup img {
        width: 100%;
        height: auto;
        border-radius: 8px;
        margin-bottom: 8px;
    }
    .zhiji-float-wechat-popup p {
        margin: 0;
        font-size: 12px;
        color: #666;
    }

    /* 返回顶部显示/隐藏 */
    .zhiji-float-backtop {
        opacity: 0;
        visibility: hidden;
        transform: translateY(20px);
        transition: all .3s ease;
    }
    .zhiji-float-backtop.show {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    /* 移动端适配 */
    @media (max-width: 768px) {
        .zhiji-right-float-nav {
            gap: 6px;
        }
        .zhiji-right-float-nav.position-right {
            right: 8px;
        }
        .zhiji-right-float-nav.position-left {
            left: 8px;
        }
        .zhiji-float-item {
            width: 40px;
            height: 40px;
            border-radius: 10px;
        }
        .zhiji-float-item svg {
            width: 18px;
            height: 18px;
        }
        .zhiji-float-label {
            display: none;
        }
        .zhiji-right-float-nav.style-card .zhiji-float-item {
            width: 48px;
            height: 48px;
        }
    }
    </style>
    <?php
}

/**
 * 注入悬浮导航JS
 */
add_action( 'wp_footer', 'zhiji_right_float_nav_inject_js', 25 );
function zhiji_right_float_nav_inject_js() {
    if ( ! zhiji_right_float_nav_is_enabled() ) {
        return;
    }

    ?>
    <script id="zhiji-right-float-nav-js">
    (function() {
        'use strict';

        // 返回顶部
        var backtop = document.getElementById('zhiji-float-backtop');
        if (backtop) {
            window.addEventListener('scroll', function() {
                if (window.scrollY > 300) {
                    backtop.classList.add('show');
                } else {
                    backtop.classList.remove('show');
                }
            });
            backtop.addEventListener('click', function() {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        }

        // 弹幕开关联动
        var danmuToggle = document.getElementById('zhiji-float-danmu-toggle');
        if (danmuToggle) {
            danmuToggle.addEventListener('click', function() {
                var danmuContainer = document.getElementById('zhiji-danmu-container');
                if (danmuContainer) {
                    var isHidden = danmuContainer.style.display === 'none';
                    danmuContainer.style.display = isHidden ? 'block' : 'none';
                    this.style.opacity = isHidden ? '1' : '0.4';
                }
            });
        }

        // 音乐播放器联动
        var musicToggle = document.getElementById('zhiji-float-music-toggle');
        if (musicToggle) {
            musicToggle.addEventListener('click', function() {
                var player = document.getElementById('zhiji-music-player');
                if (player) {
                    player.classList.toggle('expanded');
                }
            });
        }
    })();
    </script>
    <?php
}

/**
 * CSF设置注册
 */
add_action( 'after_setup_theme', function () {
    
    Zhiji_Registry::csf_section_for_legacy( 'right_float_nav', array(
        'parent' => 'zhiji_page',
        'priority' => 50,
        'title'  => '右侧悬浮导航',
        'icon'   => 'fa fa-anchor',
        'fields' => array(

            array(
                'id'      => 'right_float_nav_enabled',
                'type'    => 'switcher',
                'title'   => '启用右侧悬浮导航',
                'label'   => '页面右侧悬浮快捷导航（返回顶部/客服/抽奖/弹幕/音乐）',
                'default' => false,
            ),

            array(
                'id'         => 'right_float_nav_style',
                'type'       => 'radio',
                'title'      => '导航样式',
                'options'    => array(
                    'icon'   => '简约图标式',
                    'card'   => '卡片式（带文字）',
                    'expand' => '展开式（悬停展开）',
                ),
                'default'    => 'icon',
                'inline'     => true,
                'dependency' => array( 'right_float_nav_enabled', '==', '1' ),
            ),

            array(
                'id'         => 'right_float_nav_position',
                'type'       => 'radio',
                'title'      => '显示位置',
                'options'    => array(
                    'right' => '右侧',
                    'left'  => '左侧',
                ),
                'default'    => 'right',
                'inline'     => true,
                'dependency' => array( 'right_float_nav_enabled', '==', '1' ),
            ),

            array(
                'id'         => 'right_float_nav_color',
                'type'       => 'color',
                'title'      => '导航主题色',
                'desc'       => '留空则使用父主题主色',
                'dependency' => array( 'right_float_nav_enabled', '==', '1' ),
            ),

            array(
                'id'         => 'right_float_nav_backtop',
                'type'       => 'switcher',
                'title'      => '显示返回顶部',
                'default'    => true,
                'dependency' => array( 'right_float_nav_enabled', '==', '1' ),
            ),

            array(
                'id'         => 'right_float_nav_lottery',
                'type'       => 'switcher',
                'title'      => '显示抽奖入口',
                'default'    => false,
                'dependency' => array( 'right_float_nav_enabled', '==', '1' ),
            ),

            array(
                'id'         => 'right_float_nav_danmu',
                'type'       => 'switcher',
                'title'      => '显示弹幕开关',
                'default'    => false,
                'dependency' => array( 'right_float_nav_enabled', '==', '1' ),
            ),

            array(
                'id'         => 'right_float_nav_music',
                'type'       => 'switcher',
                'title'      => '显示音乐播放器',
                'default'    => false,
                'dependency' => array( 'right_float_nav_enabled', '==', '1' ),
            ),

            array(
                'id'         => 'right_float_nav_qq',
                'type'       => 'switcher',
                'title'      => '显示客服QQ',
                'default'    => false,
                'dependency' => array( 'right_float_nav_enabled', '==', '1' ),
            ),

            array(
                'id'         => 'right_float_nav_qq_number',
                'type'       => 'text',
                'title'      => '客服QQ号码',
                'dependency' => array( 'right_float_nav_qq', '==', '1' ),
            ),

            array(
                'id'         => 'right_float_nav_wechat',
                'type'       => 'switcher',
                'title'      => '显示微信二维码',
                'default'    => false,
                'dependency' => array( 'right_float_nav_enabled', '==', '1' ),
            ),

            array(
                'id'         => 'right_float_nav_wechat_qr',
                'type'       => 'upload',
                'title'      => '微信二维码图片',
                'dependency' => array( 'right_float_nav_wechat', '==', '1' ),
            ),

        ),
    ) );
}, 20 );
