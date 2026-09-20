<?php
/**
 * @module  AutoKeywordLink
 * @desc    文章自动关键词内链：把文章标签与自定义关键词转成站内链接（SEO）
 * @option  auto_keyword_link_enabled  总开关
 *          auto_keyword_link_max      每个关键词最大替换次数
 *          auto_keyword_link_custom   自定义关键词映射（repeater）
 * @hook    the_content(20)
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/AutoKeywordLink.php`
 *          （修复：v1 把 <a>/<pre>/<code>/<script>/<style> 替换成占位符注释后
 *            **从未恢复**，启用后文章里所有链接与代码块会消失；v2 用索引占位并在替换后还原）
 */

defined('ABSPATH') || exit;

/* ============================================================
 * 模块自注册
 * ============================================================ */
Zhiji_Registry::register_module('auto_keyword_link', array(
    'title'    => '文章自动关键词内链',
    'parent'   => 'zhiji_post',
    'priority' => 60,
    'option'   => 'auto_keyword_link_enabled',
));

/* ============================================================
 * 正文处理
 * ============================================================ */

/**
 * 关键词转内链
 *
 * @param string $content
 * @return string
 */
function zhiji_auto_keyword_link_process($content)
{
    if (!zhiji_is_enabled('auto_keyword_link_enabled')) {
        return $content;
    }
    if (!is_singular('post')) {
        return $content;
    }
    $post = get_post();
    if (!$post) {
        return $content;
    }

    $max_replace = max(1, (int) zhiji_get_option('auto_keyword_link_max', 1));

    // 关键词 = 文章标签（≥2 字）+ 自定义映射
    $keywords = array();
    $tags = get_the_tags($post->ID);
    if ($tags) {
        foreach ($tags as $tag) {
            if (mb_strlen($tag->name) >= 2) {
                $link = get_tag_link($tag->term_id);
                if (!is_wp_error($link)) {
                    $keywords[$tag->name] = $link;
                }
            }
        }
    }
    $custom = (array) zhiji_get_option('auto_keyword_link_custom', array());
    foreach ($custom as $item) {
        if (!empty($item['keyword']) && !empty($item['url'])) {
            $keywords[(string) $item['keyword']] = esc_url_raw((string) $item['url']);
        }
    }
    if (empty($keywords)) {
        return $content;
    }

    // 长关键词优先替换
    uksort($keywords, function ($a, $b) {
        return mb_strlen($b) - mb_strlen($a);
    });

    // 1) 保护已有链接与代码块（存入数组，替换为索引占位符）
    $protected = array();
    $content = preg_replace_callback(
        '/<a\s[^>]*>.*?<\/a>|<pre[^>]*>.*?<\/pre>|<code[^>]*>.*?<\/code>|<script[^>]*>.*?<\/script>|<style[^>]*>.*?<\/style>/is',
        function ($m) use (&$protected) {
            $i = count($protected);
            $protected[$i] = $m[0];
            return '<!--ZHIJI_KEEP_' . $i . '-->';
        },
        $content
    );

    // 2) 关键词替换。
    //    ⚠️ 不能用 \w 做边界断言：/u 模式下 PCRE 的 \w 包含中文，
    //    「这是占位标题测试」里前后都是中文会导致永远不匹配（v1 因此从未生效过）。
    //    改用 ASCII 字符类：只避免切进英文单词/标签名；`(?![^<]*>)` 防止命中标签属性内部。
    foreach ($keywords as $keyword => $url) {
        $pattern = '/(?<![a-zA-Z0-9_>])(' . preg_quote($keyword, '/') . ')(?![a-zA-Z0-9_<])(?![^<]*>)/u';
        $replacement = '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer" class="zhiji-keyword-link">$1</a>';
        $content = preg_replace($pattern, $replacement, $content, $max_replace);
    }

    // 3) 还原被保护的内容（v1 缺失这一步，导致链接/代码块丢失）
    if ($protected) {
        $content = preg_replace_callback(
            '/<!--ZHIJI_KEEP_(\d+)-->/',
            function ($m) use ($protected) {
                $i = (int) $m[1];
                return isset($protected[$i]) ? $protected[$i] : '';
            },
            $content
        );
    }

    return $content;
}
add_filter('the_content', 'zhiji_auto_keyword_link_process', 20);

/* ============================================================
 * 后台字段
 * ============================================================ */
add_action('after_setup_theme', function () {
    Zhiji_Registry::csf_section_for('auto_keyword_link', array(
        array(
            'id'      => 'auto_keyword_link_enabled',
            'type'    => 'switcher',
            'title'   => '启用自动关键词内链',
            'label'   => '文章详情页自动将标签名称转换为内链（SEO优化）',
            'default' => false,
        ),
        array(
            'id'         => 'auto_keyword_link_max',
            'type'       => 'number',
            'title'      => '每个关键词最大替换次数',
            'desc'       => '避免过度SEO，建议1-2次',
            'default'    => 1,
            'min'        => 1,
            'max'        => 5,
            'dependency' => array('auto_keyword_link_enabled', '==', '1'),
        ),
        array(
            'id'         => 'auto_keyword_link_custom',
            'type'       => 'repeater',
            'title'      => '自定义关键词映射',
            'desc'       => '除文章标签外，可添加自定义关键词=>URL映射',
            'dependency' => array('auto_keyword_link_enabled', '==', '1'),
            'fields'     => array(
                array('id' => 'keyword', 'type' => 'text', 'title' => '关键词'),
                array('id' => 'url',     'type' => 'text', 'title' => '链接URL'),
            ),
        ),
    ));
}, 20);
