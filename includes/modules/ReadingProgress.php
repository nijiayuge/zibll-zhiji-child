<?php
/**
 * @module  ReadingProgress
 * @desc    阅读进度条：文章页顶部随滚动实时更新，可选百分比文字
 * @option  reading_progress_enabled     总开关（默认开启）
 *          reading_progress_show_text   显示百分比（默认开启，移动端自动隐藏）
 * @hook    wp_footer(5) / wp_footer(10)
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/ReadingProgress.php`
 */

defined('ABSPATH') || exit;

/* ============================================================
 * 模块自注册
 * ============================================================ */
Zhiji_Registry::register_module('reading_progress', array(
    'title'    => '阅读进度条',
    'parent'   => 'zhiji_post',
    'priority' => 40,
    'option'   => 'reading_progress_enabled',
));

/* ============================================================
 * 生效判断（仅单篇文章页）
 * ============================================================ */

/**
 * 当前页面是否应显示进度条
 *
 * @return bool
 */
function zhiji_reading_progress_active()
{
    if (!is_singular('post')) {
        return false;
    }
    return zhiji_is_enabled('reading_progress_enabled', true);
}

/* ============================================================
 * 前台输出
 * ============================================================ */

/**
 * 进度条 DOM（wp_footer）
 */
add_action('wp_footer', function () {
    if (!zhiji_reading_progress_active()) {
        return;
    }
    $show_text = zhiji_is_enabled('reading_progress_show_text', true);

    echo '<div id="zhiji-reading-progress" class="zhiji-reading-progress" role="progressbar"'
        . ' aria-label="阅读进度" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">'
        . '<div class="zhiji-reading-progress-bar"></div>';
    if ($show_text) {
        echo '<span class="zhiji-reading-progress-text">0%</span>';
    }
    echo '</div>';
}, 5);

/**
 * 进度条样式与脚本（wp_footer 直接输出；量小仅一段）
 */
add_action('wp_footer', function () {
    if (!zhiji_reading_progress_active()) {
        return;
    }
    echo '<style id="zhiji-reading-progress-css">'
        . '.zhiji-reading-progress{position:fixed;top:0;left:0;width:100%;height:3px;background:rgba(0,0,0,.06);z-index:99999;transition:opacity .3s ease}'
        . '.zhiji-reading-progress-bar{height:100%;width:0%;background:var(--zhiji-brand,#2e7cf6);transition:width .1s ease-out;box-shadow:0 0 8px var(--zhiji-brand,#2e7cf6)}'
        . '.zhiji-reading-progress-text{position:absolute;right:12px;top:8px;font-size:11px;color:#999;background:rgba(255,255,255,.92);'
        . 'padding:2px 8px;border-radius:10px;line-height:1.4;font-weight:600;box-shadow:0 1px 4px rgba(0,0,0,.08)}'
        . '@media (max-width:768px){.zhiji-reading-progress-text{display:none}}'
        . '</style>'
        . '<script id="zhiji-reading-progress-js">'
        . '(function(){'
        . 'var bar=document.querySelector(".zhiji-reading-progress-bar");'
        . 'var textEl=document.querySelector(".zhiji-reading-progress-text");'
        . 'var container=document.getElementById("zhiji-reading-progress");'
        . 'if(!bar||!container) return;'
        . 'var ticking=false;'
        . 'function updateProgress(){'
        . 'var scrollTop=window.pageYOffset||document.documentElement.scrollTop;'
        . 'var docHeight=document.documentElement.scrollHeight-window.innerHeight;'
        . 'var progress=docHeight>0?Math.min(100,Math.max(0,(scrollTop/docHeight)*100)):0;'
        . 'bar.style.width=progress+"%";'
        . 'container.setAttribute("aria-valuenow",Math.round(progress));'
        . 'if(textEl){textEl.textContent=Math.round(progress)+"%";}'
        . 'ticking=false;}'
        . 'function onScroll(){if(!ticking){window.requestAnimationFrame(updateProgress);ticking=true;}}'
        . 'window.addEventListener("scroll",onScroll,{passive:true});'
        . 'window.addEventListener("resize",onScroll);'
        . 'updateProgress();'
        . '})();'
        . '</script>';
}, 10);

/* ============================================================
 * 后台字段
 * ============================================================ */
    // 2026-09-26：改为 Registry 统一登记（P3-⑨），钩子由核心统一挂载
    Zhiji_Registry::register_options('reading_progress', array(
        array(
            'id'      => 'reading_progress_enabled',
            'type'    => 'switcher',
            'title'   => '启用阅读进度条',
            'desc'    => '文章页面顶部显示阅读进度条，随滚动实时更新',
            'default' => true,
        ),
        array(
            'id'      => 'reading_progress_show_text',
            'type'    => 'switcher',
            'title'   => '显示百分比',
            'desc'    => '进度条右上角显示当前阅读百分比（移动端自动隐藏）',
            'default' => true,
        ),
        array(
            'type'    => 'submessage',
            'style'   => 'info',
            'content' => '进度条颜色自动使用主题品牌色（var(--zhiji-brand)），无需单独配置。',
        ),
    ), 20);
