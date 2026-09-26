<?php
/**
 * @module  HomeSearchBox
 * @desc    首页大搜索框：渐变/毛玻璃/暗黑三种背景，含热门搜索标签；
 *          通过 [zhiji_search_box] 短代码插入（可放子比模块化首页的自定义 HTML 模块）
 * @option  home_search_enabled     总开关
 *          home_search_bg_style    背景 gradient|glass|dark
 *          home_search_placeholder 提示文字
 *          home_search_hot         显示热门搜索
 *          home_search_hot_tags    热门标签
 * @shortcode [zhiji_search_box]
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/HomeSearchBox.php`
 *          ⚠️ 2026-09-20 经蒸馏索引交叉验证：v1 挂的 zib_body_before 在父主题
 *          zibll 9.1 中不存在（首页为模块化布局，无 body 顶部扩展点），
 *          「首页自动插入」从未生效，死代码不迁移；home_search_auto 选项一并移除。
 */

defined('ABSPATH') || exit;

/* ============================================================
 * 模块自注册
 * ============================================================ */
Zhiji_Registry::register_module('home_search_box', array(
    'title'    => '首页大搜索框',
    'parent'   => 'zhiji_element',
    'priority' => 10,
    'option'   => 'home_search_enabled',
));

/* ============================================================
 * 渲染
 * ============================================================ */

/**
 * 输出搜索框 HTML（短代码与自动插入共用）
 *
 * @return void
 */
function zhiji_render_search_box()
{
    $placeholder = (string) zhiji_get_option('home_search_placeholder', '搜索你想要的内容...');
    $show_hot    = zhiji_is_enabled('home_search_hot', true);
    $hot_tags    = (string) zhiji_get_option('home_search_hot_tags', '');
    $bg_style    = (string) zhiji_get_option('home_search_bg_style', 'gradient');
    if (!in_array($bg_style, array('gradient', 'glass', 'dark'), true)) {
        $bg_style = 'gradient';
    }
    if ('' === $hot_tags) {
        $hot_tags = 'WordPress,子比主题,知集,教程,资源';
    }
    $hot_array = array_filter(array_map('trim', explode(',', $hot_tags)));
    ?>
    <div class="zhiji-home-search-box zhiji-search-bg-<?php echo esc_attr($bg_style); ?>">
        <div class="zhiji-search-inner">
            <h2 class="zhiji-search-title"><?php echo esc_html(get_bloginfo('name')); ?></h2>
            <p class="zhiji-search-subtitle"><?php echo esc_html(get_bloginfo('description')); ?></p>
            <form role="search" method="get" class="zhiji-search-form" action="<?php echo esc_url(home_url('/')); ?>">
                <input type="search" class="zhiji-search-input" placeholder="<?php echo esc_attr($placeholder); ?>" value="<?php echo get_search_query(); ?>" name="s" />
                <button type="submit" class="zhiji-search-button">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true">
                        <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                    </svg>
                    <span>搜索</span>
                </button>
            </form>
            <?php if ($show_hot && !empty($hot_array)) : ?>
            <div class="zhiji-hot-search">
                <span class="zhiji-hot-label">热门搜索：</span>
                <?php foreach ($hot_array as $tag) : ?>
                <a href="<?php echo esc_url(home_url('/?s=' . urlencode($tag))); ?>" class="zhiji-hot-tag"><?php echo esc_html($tag); ?></a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <style id="zhiji-home-search-css">
    .zhiji-home-search-box{padding:60px 20px;text-align:center;margin-bottom:30px;border-radius:16px;position:relative;overflow:hidden}
    .zhiji-search-bg-gradient{background:linear-gradient(135deg,var(--zhiji-brand,#2e7cf6),var(--zhiji-brand-dark,#16273f))}
    .zhiji-search-bg-glass{background:rgba(255,255,255,.1);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.2)}
    .zhiji-search-bg-dark{background:linear-gradient(135deg,#1a1a2e,#16213e)}
    .zhiji-search-inner{max-width:700px;margin:0 auto;position:relative;z-index:2}
    .zhiji-search-title{color:#fff;font-size:32px;font-weight:700;margin-bottom:8px;text-shadow:0 2px 10px rgba(0,0,0,.2)}
    .zhiji-search-subtitle{color:rgba(255,255,255,.8);font-size:15px;margin-bottom:24px}
    .zhiji-search-form{display:flex;gap:0;background:#fff;border-radius:50px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.15)}
    .zhiji-search-input{flex:1;border:none;padding:14px 24px;font-size:15px;outline:none;background:transparent}
    .zhiji-search-button{display:flex;align-items:center;gap:6px;background:linear-gradient(135deg,var(--zhiji-brand,#2e7cf6),var(--zhiji-brand-dark,#16273f));color:#fff;border:none;padding:14px 28px;font-size:15px;font-weight:600;cursor:pointer;transition:opacity .3s}
    .zhiji-search-button:hover{opacity:.9}
    .zhiji-hot-search{margin-top:16px;display:flex;align-items:center;justify-content:center;flex-wrap:wrap;gap:8px}
    .zhiji-hot-label{color:rgba(255,255,255,.7);font-size:13px}
    .zhiji-hot-tag{color:#fff;background:rgba(255,255,255,.2);padding:4px 12px;border-radius:20px;font-size:12px;text-decoration:none;transition:background .3s}
    .zhiji-hot-tag:hover{background:rgba(255,255,255,.35);color:#fff}
    @media (max-width:768px){.zhiji-home-search-box{padding:40px 16px}.zhiji-search-title{font-size:24px}.zhiji-search-form{border-radius:12px;flex-direction:column}.zhiji-search-button{border-radius:0 0 12px 12px;justify-content:center}}
    </style>
    <?php
}

add_shortcode('zhiji_search_box', function () {
    if (!zhiji_is_enabled('home_search_enabled')) {
        return '';
    }
    ob_start();
    zhiji_render_search_box();
    return ob_get_clean();
});

/* ============================================================
 * 后台字段
 * ============================================================ */
    // 2026-09-26：改为 Registry 统一登记（P3-⑨），钩子由核心统一挂载
    Zhiji_Registry::register_options('home_search_box', array(
        array(
            'id'      => 'home_search_enabled',
            'type'    => 'switcher',
            'title'   => '启用首页大搜索框',
            'default' => false,
        ),
        array(
            'id'         => 'home_search_bg_style',
            'type'       => 'radio',
            'title'      => '背景样式',
            'desc' => __( '首页大搜索框的背景样式。', 'zhiji' ),
            'options'    => array('gradient' => '渐变背景', 'glass' => '毛玻璃', 'dark' => '暗黑'),
            'default'    => 'gradient',
            'inline'     => true,
            'dependency' => array('home_search_enabled', '==', '1'),
        ),
        array(
            'id'         => 'home_search_placeholder',
            'type'       => 'text',
            'title'      => '搜索提示文字',
            'desc' => __( '搜索框内的提示文字。', 'zhiji' ),
            'default'    => '搜索你想要的内容...',
            'dependency' => array('home_search_enabled', '==', '1'),
        ),
        array(
            'id'         => 'home_search_hot',
            'type'       => 'switcher',
            'title'      => '显示热门搜索',
            'desc' => __( '是否在搜索框下方展示热门搜索词。', 'zhiji' ),
            'default'    => true,
            'dependency' => array('home_search_enabled', '==', '1'),
        ),
        array(
            'id'         => 'home_search_hot_tags',
            'type'       => 'text',
            'title'      => '热门搜索标签',
            'desc'       => '用逗号分隔',
            'default'    => 'WordPress,子比主题,知集,教程,资源',
            'dependency' => array('home_search_hot', '==', '1'),
        ),
    ), 20);
