<?php
/**
 * @module  WebPConverter
 * @desc    WebP 图片转换：上传 JPG/PNG 时经 GD 自动转 WebP（可选保留原图），展示层替换为 WebP
 * @option  webp_enabled        总开关
 *          webp_quality        转换质量 1-100
 *          webp_keep_original  是否保留原图
 * @hook    wp_handle_upload(20) / wp_get_attachment_image_src(20)
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/WebPConverter.php`
 *          （v1 的输出缓冲 HTML 替换已确认会破坏 zibll 懒加载结构，属死代码，未迁移；
 *            仅保留 wp_get_attachment_image_src 过滤器替换路径）
 */

defined('ABSPATH') || exit;

/* ============================================================
 * 模块自注册
 * ============================================================ */
Zhiji_Registry::register_module('webp_converter', array(
    'title'    => 'WebP 图片转换',
    'parent'   => 'zhiji_basic',
    'priority' => 50,
    'option'   => 'webp_enabled',
));

/* ============================================================
 * 能力检测
 * ============================================================ */

/**
 * 服务器是否支持 WebP 转换（GD + imagewebp）
 *
 * @return bool
 */
function zhiji_webp_supported()
{
    return function_exists('imagewebp') && extension_loaded('gd');
}

/**
 * 浏览器是否声明支持 WebP（按 Accept 头，进程内缓存）
 *
 * @return bool
 */
function zhiji_webp_browser_supports()
{
    static $supports = null;
    if (null !== $supports) {
        return $supports;
    }
    $supports = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'image/webp') !== false;
    return $supports;
}

/* ============================================================
 * 转换核心
 * ============================================================ */

/**
 * 将 JPG/PNG 转换为 WebP
 *
 * @param string $file_path     源文件绝对路径
 * @param int    $quality       质量 1-100
 * @param bool   $keep_original 是否保留原图
 * @return array|false 成功返回 [path, url]
 */
function zhiji_webp_convert($file_path, $quality = 80, $keep_original = true)
{
    if (!file_exists($file_path)) {
        return false;
    }
    $info = getimagesize($file_path);
    if (!$info) {
        return false;
    }
    $mime = $info['mime'];

    switch ($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($file_path);
            break;
        case 'image/png':
            $image = imagecreatefrompng($file_path);
            break;
        default:
            return false;
    }
    if (!$image) {
        return false;
    }

    // PNG 透明通道
    if ('image/png' === $mime) {
        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);
    }

    $webp_path = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $file_path);
    $success   = imagewebp($image, $webp_path, $quality);
    imagedestroy($image);

    if (!$success || !file_exists($webp_path)) {
        return false;
    }
    if (!$keep_original && file_exists($file_path)) {
        wp_delete_file($file_path);
    }

    $upload_dir = wp_upload_dir();
    return array(
        'path' => $webp_path,
        'url'  => str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $webp_path),
    );
}

/* ============================================================
 * 上传转换
 * ============================================================ */
add_filter('wp_handle_upload', function ($file) {
    if (!zhiji_is_enabled('webp_enabled') || !zhiji_webp_supported()) {
        return $file;
    }
    $type = isset($file['type']) ? $file['type'] : '';
    if (!in_array($type, array('image/jpeg', 'image/png'), true)) {
        return $file;
    }
    $file_path = isset($file['file']) ? $file['file'] : '';
    if (!$file_path || !file_exists($file_path)) {
        return $file;
    }

    $quality = max(1, min(100, (int) zhiji_get_option('webp_quality', 80)));
    $keep    = zhiji_is_enabled('webp_keep_original', true);

    $result = zhiji_webp_convert($file_path, $quality, $keep);
    if ($result && !is_wp_error($result)) {
        $file['file'] = $result['path'];
        $file['url']  = $result['url'];
        $file['type'] = 'image/webp';
    }
    return $file;
}, 20);

/**
 * 展示层：附件图 src 替换为已存在的 WebP
 */
add_filter('wp_get_attachment_image_src', function ($image) {
    if (!zhiji_is_enabled('webp_enabled') || !zhiji_webp_supported()) {
        return $image;
    }
    if (!$image || empty($image[0]) || !zhiji_webp_browser_supports()) {
        return $image;
    }
    $url = $image[0];
    if (!preg_match('/\.(jpg|jpeg|png)$/i', $url)) {
        return $image;
    }
    $upload_dir = wp_upload_dir();
    $webp_url   = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $url);
    $webp_path  = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $webp_url);
    if (file_exists($webp_path)) {
        $image[0] = $webp_url;
    }
    return $image;
}, 20);

/* ============================================================
 * 后台字段
 * ============================================================ */
add_action('after_setup_theme', function () {
    $supported = zhiji_webp_supported();
    Zhiji_Registry::csf_section_for('webp_converter', array(
        array(
            'id'      => 'webp_enabled',
            'type'    => 'switcher',
            'title'   => '启用 WebP 自动转换',
            'desc'    => '上传 JPG/PNG 图片时自动转换为 WebP 格式，减小图片体积 30%-50%',
            'default' => false,
        ),
        array(
            'id'         => 'webp_quality',
            'type'       => 'slider',
            'title'      => 'WebP 转换质量',
            'desc'       => '数值越大质量越好但文件越大，推荐 75-85',
            'default'    => 80,
            'min'        => 1,
            'max'        => 100,
            'step'       => 1,
            'dependency' => array('webp_enabled', '==', '1'),
        ),
        array(
            'id'         => 'webp_keep_original',
            'type'       => 'switcher',
            'title'      => '保留原图',
            'desc'       => '转换 WebP 后是否保留原始 JPG/PNG 文件（建议保留，兼容不支持 WebP 的浏览器）',
            'default'    => true,
            'dependency' => array('webp_enabled', '==', '1'),
        ),
        array(
            'type'    => 'submessage',
            'style'   => $supported ? 'success' : 'warning',
            'content' => $supported
                ? '服务器支持 WebP 转换（GD 库 + imagewebp 函数）。启用后新上传的 JPG/PNG 图片将自动转换，已上传的图片不会自动转换。'
                : '服务器不支持 WebP 转换（需要 GD 库 + imagewebp 函数）。请联系主机商启用 GD WebP 支持。',
        ),
    ));
}, 20);
