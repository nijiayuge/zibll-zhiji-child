<?php
/**
 * @module  ArticleExpire
 * @desc    文章过期提示：发布超过指定天数后，在文章顶部显示「内容可能过时」提示
 * @option  article_expire_enabled     总开关
 *          article_expire_days        过期天数
 *          article_expire_text        提示文字（支持 {days}）
 *          article_expire_bg          提示框背景色
 *          article_expire_text_color  提示文字颜色
 * @hook    the_content(5)
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/ArticleExpire.php`
 */

defined('ABSPATH') || exit;

/* ============================================================
 * 模块自注册
 * ============================================================ */
Zhiji_Registry::register_module('article_expire', array(
    'title'    => '文章过期提示',
    'parent'   => 'zhiji_post',
    'priority' => 10,
    'option'   => 'article_expire_enabled',
));

/* ============================================================
 * 前台输出
 * ============================================================ */

/**
 * 在文章正文前插入过期提示
 *
 * @param string $content
 * @return string
 */
function zhiji_article_expire_notice($content)
{
    if (!zhiji_is_enabled('article_expire_enabled')) {
        return $content;
    }
    // ⚠️ 只判断 is_singular：实测父主题 zibll 渲染文章时 the_content 触发点不在主循环里
    //    （in_the_loop() 恒为 false），若再加 in_the_loop/is_main_query 检查会把正常文章也挡掉
    if (!is_singular('post')) {
        return $content;
    }

    $post = get_post();
    if (!$post) {
        return $content;
    }

    $days   = max(1, (int) zhiji_get_option('article_expire_days', 180));
    $passed = floor((current_time('timestamp') - strtotime($post->post_date)) / DAY_IN_SECONDS);
    if ($passed < $days) {
        return $content;
    }

    $text = (string) zhiji_get_option(
        'article_expire_text',
        '本文发布已超过 {days} 天，部分内容可能已过时，请谨慎参考。'
    );
    $text = str_replace('{days}', (string) $passed, $text);

    $bg    = (string) zhiji_get_option('article_expire_bg', '#fff3cd');
    $color = (string) zhiji_get_option('article_expire_text_color', '#856404');

    // 样式仅在文章页随提示一起输出（量小且只出现一次，避免为几行 CSS 多一个请求）。
    // 关闭按钮不用内联 onclick：改用 data 属性 + 下面这段脚本统一绑定，避免内联事件处理器。
    $style = '<style id="zhiji-article-expire-css">'
        . '.zhiji-article-expire-notice{display:flex;align-items:center;gap:10px;padding:12px 16px;'
        . 'border-radius:8px;margin-bottom:20px;font-size:14px;line-height:1.6;position:relative}'
        . '.zhiji-expire-icon{font-size:18px}.zhiji-expire-text{flex:1}'
        . '.zhiji-expire-close{background:none;border:none;font-size:20px;cursor:pointer;'
        . 'opacity:.6;padding:0 4px;line-height:1;color:inherit}'
        . '.zhiji-expire-close:hover{opacity:1}'
        . '</style>';

    $script = '<script>(function(){document.addEventListener("click",function(e){'
        . 'var b=e.target.closest&&e.target.closest(".zhiji-expire-close");'
        . 'if(b&&b.parentElement){b.parentElement.style.display="none";}});})();</script>';

    $notice = '<div class="zhiji-article-expire-notice" style="background:' . esc_attr($bg) . ';color:' . esc_attr($color) . ';">'
        . '<span class="zhiji-expire-icon">&#9888;</span>'
        . '<span class="zhiji-expire-text">' . esc_html($text) . '</span>'
        . '<button type="button" class="zhiji-expire-close" aria-label="' . esc_attr__('关闭提示', 'zhiji') . '">&times;</button>'
        . '</div>';

    return $style . $notice . $script . $content;
}
add_filter('the_content', 'zhiji_article_expire_notice', 5);

/* ============================================================
 * 后台字段
 * ============================================================ */
add_action('after_setup_theme', function () {
    Zhiji_Registry::csf_section_for('article_expire', array(
        array(
            'id'      => 'article_expire_enabled',
            'type'    => 'switcher',
            'title'   => '启用文章过期提示',
            'default' => false,
        ),
        array(
            'id'         => 'article_expire_days',
            'type'       => 'number',
            'title'      => '过期天数',
            'desc'       => '文章发布超过此天数后显示提示',
            'default'    => 180,
            'min'        => 7,
            'max'        => 3650,
            'dependency' => array('article_expire_enabled', '==', 'true'),
        ),
        array(
            'id'         => 'article_expire_text',
            'type'       => 'textarea',
            'title'      => '提示文字',
            'desc'       => '支持 {days} 变量显示实际天数',
            'default'    => '本文发布已超过 {days} 天，部分内容可能已过时，请谨慎参考。',
            'dependency' => array('article_expire_enabled', '==', 'true'),
        ),
        array(
            'id'         => 'article_expire_bg',
            'type'       => 'color',
            'title'      => '提示框背景色',
            'default'    => '#fff3cd',
            'dependency' => array('article_expire_enabled', '==', 'true'),
        ),
        array(
            'id'         => 'article_expire_text_color',
            'type'       => 'color',
            'title'      => '提示文字颜色',
            'default'    => '#856404',
            'dependency' => array('article_expire_enabled', '==', 'true'),
        ),
    ));
}, 20);
