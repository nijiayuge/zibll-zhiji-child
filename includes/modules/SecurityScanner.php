<?php
/**
 * @module  SecurityScanner
 * @desc    安全扫描：扫描 wp-content 下 PHP 文件的危险函数与混淆特征，只报告不删除；
 *          分批 AJAX 扫描避免超时，命中可加白名单。后台「工具 → 安全扫描」。
 * @option  security_scan_enabled  总开关
 *          security_scan_dirs     扫描目录（themes/plugins/uploads）
 * @hook    admin_menu / wp_ajax_zhiji_scan_* 
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/SecurityScanner.php`
 */

defined('ABSPATH') || exit;

define('ZHIJI_SCAN_RESULT_KEY', 'zhiji_scan_result');
define('ZHIJI_SCAN_IGNORE_KEY', 'zhiji_scan_ignore');
define('ZHIJI_SCAN_BATCH', 200);

/* ============================================================
 * 模块自注册
 * ============================================================ */
Zhiji_Registry::register_module('security_scanner', array(
    'title'    => '安全扫描',
    'parent'   => 'zhiji_over',
    'priority' => 70,
    'option'   => 'security_scan_enabled',
));

/* ============================================================
 * 规则
 * ============================================================ */

/**
 * 检测规则（规则名 => 正则）
 *
 * @return array
 */
function zhiji_security_scan_rules()
{
    return array(
        'eval'            => '/\beval\s*\(/i',
        'assert'          => '/\bassert\s*\(/i',
        '系统命令'        => '/\b(?:system|exec|shell_exec|passthru|proc_open|popen|pcntl_exec)\s*\(/i',
        'create_function' => '/\bcreate_function\s*\(/i',
        '后门组合'        => '/\beval\s*\(\s*(?:\$_POST|\$_GET|\$_REQUEST|\$_COOKIE)/i',
        'gz解压混淆'      => '/\b(?:gzuncompress|gzinflate|str_rot13|base64_decode)\s*\(\s*[^)]{200,}/i',
    );
}

/**
 * 收集待扫 PHP 文件（相对 wp-content）
 *
 * @return array
 */
function zhiji_security_scan_collect_files()
{
    $dirs = (array) zhiji_get_option('security_scan_dirs', array('themes', 'plugins', 'uploads'));
    if (empty($dirs)) {
        $dirs = array('themes', 'plugins', 'uploads');
    }
    $files = array();
    foreach ($dirs as $dir) {
        $base = WP_CONTENT_DIR . '/' . $dir;
        if (!is_dir($base)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            if ($file->isFile() && 'php' === strtolower($file->getExtension())) {
                $files[] = $dir . '/' . ltrim(substr($file->getPathname(), strlen(WP_CONTENT_DIR) + 1), '/\\');
            }
        }
    }
    sort($files);
    return $files;
}

/**
 * 扫描单个文件
 *
 * @param string $rel 相对 wp-content 的路径
 * @return array 规则名 => 命中片段
 */
function zhiji_security_scan_file($rel)
{
    $path = WP_CONTENT_DIR . '/' . $rel;
    if (!is_file($path) || filesize($path) > 2 * MB_IN_BYTES) {
        return array();
    }
    $content = @file_get_contents($path);
    if (false === $content) {
        return array();
    }
    $hits = array();
    foreach (zhiji_security_scan_rules() as $rule => $pattern) {
        if (preg_match($pattern, $content, $m)) {
            $hits[$rule] = mb_strimwidth(preg_replace('/\s+/', ' ', $m[0]), 0, 80, '…');
        }
    }
    return $hits;
}

/* ============================================================
 * 管理页
 * ============================================================ */
add_action('admin_menu', function () {
    if (!zhiji_is_enabled('security_scan_enabled')) {
        return;
    }
    add_management_page(
        '安全扫描',
        '安全扫描',
        'manage_options',
        'zhiji-security-scan',
        'zhiji_security_scan_render_page'
    );
});

/**
 * 渲染扫描管理页
 *
 * @return void
 */
function zhiji_security_scan_render_page()
{
    if (!current_user_can('manage_options')) {
        wp_die('权限不足');
    }
    $result = get_transient(ZHIJI_SCAN_RESULT_KEY);
    $result = is_array($result) ? $result : array();
    $ignore = (array) get_option(ZHIJI_SCAN_IGNORE_KEY, array());

    echo '<div class="wrap"><h1>安全扫描（木马检测）</h1>'
        . '<p class="description">扫描 wp-content/themes、plugins、uploads 下的 PHP 文件，检测危险函数与混淆特征。<strong>只报告不删除</strong>，命中项请人工确认后处置。点击「忽略」可将文件加入白名单。</p>'
        . '<p><button class="button button-primary" id="zhiji-scan-start">开始扫描</button> '
        . '<button class="button" id="zhiji-scan-reset">清空结果</button></p>'
        . '<div id="zhiji-scan-progress" class="notice notice-info" style="display:none;"><p>扫描中：<span id="zhiji-scan-status">0</span> 个文件已检查…</p></div>';
    echo '<h2>扫描结果（' . count($result) . ' 项命中）</h2><table class="widefat striped"><thead><tr>'
        . '<th>文件</th><th>命中规则</th><th>大小</th><th>修改时间</th><th>操作</th></tr></thead><tbody id="zhiji-scan-rows">';
    if (empty($result)) {
        echo '<tr><td colspan="5">暂无结果，点击「开始扫描」。</td></tr>';
    }
    foreach ($result as $file => $hits) {
        if (in_array($file, $ignore, true)) {
            continue;
        }
        $stat = @stat(WP_CONTENT_DIR . '/' . $file);
        echo '<tr><td><code>' . esc_html($file) . '</code></td>'
            . '<td>' . esc_html(implode(' / ', array_keys($hits))) . '</td>'
            . '<td>' . ($stat ? size_format($stat['size']) : '—') . '</td>'
            . '<td>' . ($stat ? esc_html(gmdate('Y-m-d H:i', $stat['mtime'])) : '—') . '</td>'
            . '<td><a href="javascript:;" class="zhiji-scan-ignore button button-small" data-file="' . esc_attr($file) . '">忽略</a></td></tr>';
    }
    echo '</tbody></table>';

    // 前端 JS：分批扫描 + 忽略 + 重置（全部经 wp_ajax，nonce 校验）
    echo '<script>
jQuery(function($){
var offset = 0, scanning = false, total = 0;
$("#zhiji-scan-start").on("click", function(){
if(scanning) return; scanning = true; offset = 0; total = 0;
$("#zhiji-scan-progress").show(); $("#zhiji-scan-rows").empty();
nextBatch();
});
function nextBatch(){
$.post(ajaxurl, { action: "zhiji_scan_batch", offset: offset, limit: ' . ZHIJI_SCAN_BATCH . ', nonce: "' . esc_js(wp_create_nonce('zhiji_scan_batch')) . '" }, function(res){
if(res.error){ $("#zhiji-scan-status").text(res.msg); scanning = false; return; }
offset = res.next; total += res.scanned;
$("#zhiji-scan-status").text(total);
if(res.done){
scanning = false;
$("#zhiji-scan-progress").find("p").text("扫描完成：共检查 " + total + " 个 PHP 文件");
location.reload();
} else { nextBatch(); }
});
}
$(document).on("click", ".zhiji-scan-ignore", function(){
var a = $(this);
$.post(ajaxurl, { action: "zhiji_scan_ignore", file: a.data("file"), nonce: "' . esc_js(wp_create_nonce('zhiji_scan_ignore')) . '" }, function(res){
if(!res.error) a.closest("tr").remove();
});
});
$("#zhiji-scan-reset").on("click", function(){
$.post(ajaxurl, { action: "zhiji_scan_reset", nonce: "' . esc_js(wp_create_nonce('zhiji_scan_reset')) . '" }, function(){ location.reload(); });
});
});
</script>';
    echo '</div>';
}

/* ============================================================
 * AJAX：分批扫描 / 忽略 / 重置
 * ============================================================ */
add_action('wp_ajax_zhiji_scan_batch', function () {
    check_ajax_referer('zhiji_scan_batch', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json(array('error' => 1, 'msg' => '权限不足'));
    }
    $offset = isset($_POST['offset']) ? max(0, (int) $_POST['offset']) : 0;
    $limit  = isset($_POST['limit']) ? min(500, max(1, (int) $_POST['limit'])) : ZHIJI_SCAN_BATCH;
    $files  = zhiji_security_scan_collect_files();
    $batch  = array_slice($files, $offset, $limit);

    $result = get_transient(ZHIJI_SCAN_RESULT_KEY);
    $result = is_array($result) ? $result : array();
    foreach ($batch as $file) {
        $hits = zhiji_security_scan_file($file);
        if (!empty($hits)) {
            $result[$file] = $hits;
        }
    }
    set_transient(ZHIJI_SCAN_RESULT_KEY, $result, HOUR_IN_SECONDS);

    wp_send_json(array(
        'error'   => 0,
        'scanned' => count($batch),
        'next'    => $offset + count($batch),
        'done'    => ($offset + count($batch)) >= count($files),
    ));
});

add_action('wp_ajax_zhiji_scan_ignore', function () {
    check_ajax_referer('zhiji_scan_ignore', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json(array('error' => 1));
    }
    $file = isset($_POST['file']) ? sanitize_text_field(wp_unslash($_POST['file'])) : '';
    if (!$file) {
        wp_send_json(array('error' => 1));
    }
    $ignore = (array) get_option(ZHIJI_SCAN_IGNORE_KEY, array());
    if (!in_array($file, $ignore, true)) {
        $ignore[] = $file;
        update_option(ZHIJI_SCAN_IGNORE_KEY, $ignore, false);
    }
    $result = get_transient(ZHIJI_SCAN_RESULT_KEY);
    if (is_array($result)) {
        unset($result[$file]);
        set_transient(ZHIJI_SCAN_RESULT_KEY, $result, HOUR_IN_SECONDS);
    }
    wp_send_json(array('error' => 0));
});

add_action('wp_ajax_zhiji_scan_reset', function () {
    check_ajax_referer('zhiji_scan_reset', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json(array('error' => 1));
    }
    delete_transient(ZHIJI_SCAN_RESULT_KEY);
    wp_send_json(array('error' => 0));
});

/* ============================================================
 * 后台字段
 * ============================================================ */
add_action('after_setup_theme', function () {
    Zhiji_Registry::csf_section_for('security_scanner', array(
        array(
            'id'      => 'security_scan_enabled',
            'type'    => 'switcher',
            'title'   => '启用安全扫描',
            'default' => false,
            'desc'    => '扫描 wp-content/themes、plugins、uploads 下的 PHP 文件，检测危险函数与混淆特征。只报告不删除。后台「工具 → 安全扫描」启动扫描。',
        ),
        array(
            'id'         => 'security_scan_dirs',
            'type'       => 'checkbox',
            'title'      => '扫描目录',
            'desc' => __( '需要扫描的目录范围（用于检测可疑文件）。', 'zhiji' ),
            'default'    => array('themes', 'plugins', 'uploads'),
            'options'    => array(
                'themes'  => '主题目录 (themes)',
                'plugins' => '插件目录 (plugins)',
                'uploads' => '上传目录 (uploads)',
            ),
            'dependency' => array('security_scan_enabled', '==', '1'),
        ),
    ));
}, 20);
