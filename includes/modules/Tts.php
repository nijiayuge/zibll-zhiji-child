<?php
/**
 * @module  Tts
 * @desc    文章 TTS 朗读：浏览器原生 SpeechSynthesis（零外部依赖、零 API 费用）。
 *          文章页浮动按钮，支持 开始/继续、暂停、停止 三态控制；长文按句切分队列播报，
 *          规避 Chrome 对超长 utterance 的截断问题。
 * @option  tts_enabled  总开关
 *          tts_rate     语速（0.5~2，默认 1）
 * @hook    wp_enqueue_scripts · 仅 is_singular('post') 时加载 assets/zhiji/js/tts.js
 * @since   2.0.0（2026-09-29 新增）
 */

defined('ABSPATH') || exit;

Zhiji_Registry::register_module('tts', array(
    'title'    => '文章朗读',
    'parent'   => 'zhiji_post',
    'priority' => 160,
    'option'   => 'tts_enabled',
));

/* ============================================================
 * 后台 CSF 设置：文章内容 → 文章朗读
 * ============================================================ */
Zhiji_Registry::register_options('tts', array(
    array(
        'type'    => 'submessage',
        'style'   => 'info',
        'content' => __('使用浏览器原生语音合成（SpeechSynthesis），无任何外部服务与费用。'
            . '仅文章页显示朗读按钮，访客浏览器不支持时按钮自动隐藏。', 'zhiji'),
    ),
    array(
        'id'         => 'tts_enabled',
        'type'       => 'switcher',
        'title'      => '启用文章朗读',
        'default'    => false,
    ),
    array(
        'id'         => 'tts_rate',
        'type'       => 'number',
        'title'      => '朗读语速',
        'default'    => 1,
        'min'        => 0.5,
        'max'        => 2,
        'step'       => 0.1,
        'desc'       => __('1 = 正常语速，0.5 = 慢速，2 = 快速。', 'zhiji'),
        'dependency' => array('tts_enabled', '==', '1'),
    ),
), 160);

/* ============================================================
 * 前台资源（JS 独立文件：assets/zhiji/js/tts.js）
 *
 * JS 独立文件纪律（同 quiz.js/ops-drawer.js 结论）：head 输出、
 * node --check 可校验；配置经 wp_add_inline_script 'before' 注入。
 * ============================================================ */

/**
 * 前台资源注册（仅文章页）
 *
 * @return void
 */
function zhiji_tts_enqueue()
{
    if (is_admin() || !function_exists('zhiji_is_enabled')) {
        return;
    }
    if (!zhiji_is_enabled('tts_enabled', false)) {
        return;
    }
    // 仅文章页（页面/附件无正文朗读场景）
    if (!is_singular('post')) {
        return;
    }

    wp_enqueue_script('zhiji-tts', zhiji_asset_url('js/tts.js'), array(), ZHIJI_VERSION, false);
    wp_add_inline_script('zhiji-tts', 'window.ZHIJI_TTS_CFG=' . wp_json_encode(array(
        'rate' => max(0.5, min(2, (float) zhiji_get_option('tts_rate', 1))),
    )) . ';', 'before');
}
add_action('wp_enqueue_scripts', 'zhiji_tts_enqueue', 20);
