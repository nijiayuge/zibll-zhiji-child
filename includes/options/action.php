<?php

// 批量提交链接到必应
function zhiji_bing_bulk_submit()
{
    // 验证是否为POST请求
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo (json_encode(array('error' => 1, 'ys' => 'danger', 'msg' => '无效的请求方法')));
        exit();
    }

    if (zhiji_get_option('bing_post_token') == '') {
        echo (json_encode(array('error' => 1, 'ys' => 'danger', 'msg' => '请填写API密钥后保存并重试')));
        exit();
    }

    // 获取并清理URL列表
    $urls = isset($_POST['urls']) ? array_map('trim', explode(PHP_EOL, sanitize_textarea_field($_POST['urls']))) : [];
    $urls = array_filter($urls); // 移除任何空的URL条目

    if (empty($urls)) {
        echo (json_encode(array('error' => 1, 'ys' => 'danger', 'msg' => '请输入有效的链接')));
        exit();
    }

    // 调用之前定义的函数进行提交
    $result = zhiji_bing_resource_submission($urls);

    // 根据提交结果返回JSON响应
    if (isset($result['success']) && $result['success']) {
        echo (json_encode(array('error' => 0, 'msg' => 'URL提交成功！')));
    } else {
        echo (json_encode(array('error' => 1, 'msg' => $result['message'])));
    }
    exit();
}
add_action('wp_ajax_zhiji_bing_bulk_submit', 'zhiji_bing_bulk_submit');

//导入主题设置
function zhiji_ajax_options_import()
{
    if (!is_super_admin()) {
        echo(json_encode(array('error' => 1, 'ys' => 'danger', 'msg' => '操作权限不足')));
        exit();
    }

    $data = !empty($_REQUEST['import_data']) ? $_REQUEST['import_data'] : '';

    if (!$data) {
        echo(json_encode(array('error' => 1, 'ys' => 'danger', 'msg' => '请粘贴需导入配置的json代码')));
        exit();
    }

    $import_data = json_decode(wp_unslash(trim($data)), true);

    if (empty($import_data) || !is_array($import_data)) {
        echo(json_encode(array('error' => 1, 'ys' => 'danger', 'msg' => 'json代码格式错误，无法导入')));
        exit();
    }

    zhiji_options_backup('导入配置 自动备份');

    $prefix = 'zhiji_options';
    update_option($prefix, $import_data);
    echo(json_encode(array('error' => 0, 'reload' => 1, 'msg' => '主题设置已导入，请刷新页面')));
    exit();
}
add_action('wp_ajax_zhiji_options_import', 'zhiji_ajax_options_import');

//备份主题设置
function zhiji_ajax_options_backup()
{

    $type   = !empty($_REQUEST['type']) ? $_REQUEST['type'] : '手动备份';
    $backup = zhiji_options_backup($type);
    echo(json_encode(array('error' => 0, 'reload' => 1, 'msg' => '当前配置已经备份')));
    exit();
}
add_action('wp_ajax_zhiji_options_backup', 'zhiji_ajax_options_backup');

function zhiji_ajax_options_backup_delete()
{

    if (!is_super_admin()) {
        echo(json_encode(array('error' => 1, 'ys' => 'danger', 'msg' => '操作权限不足')));
        exit();
    }
    if (empty($_REQUEST['key'])) {
        echo(json_encode(array('error' => 1, 'ys' => 'danger', 'msg' => '参数传入错误')));
        exit();
    }

    $prefix = 'zhiji_options';
    if ('zhiji_options_backup_delete_all' == $_REQUEST['action']) {
        update_option($prefix . '_backup', false);
        echo(json_encode(array('error' => 0, 'reload' => 1, 'msg' => '已删除全部备份数据')));
        exit();
    }

    $options_backup = get_option($prefix . '_backup');

    if ('zhiji_options_backup_delete_surplus' == $_REQUEST['action']) {
        if ($options_backup) {
            $options_backup = array_reverse($options_backup);
            update_option($prefix . '_backup', array_reverse(array_slice($options_backup, 0, 3)));
            echo(json_encode(array('error' => 0, 'reload' => 1, 'msg' => '已删除多余备份数据，仅保留最新3份')));
            exit();
        }
        echo(json_encode(array('error' => 1, 'ys' => 'danger', 'msg' => '暂无可删除的数据')));
    }

    if (isset($options_backup[$_REQUEST['key']])) {
        unset($options_backup[$_REQUEST['key']]);

        update_option($prefix . '_backup', $options_backup);
        echo(json_encode(array('error' => 0, 'reload' => 1, 'msg' => '所选备份已删除')));
    } else {
        echo(json_encode(array('error' => 1, 'ys' => 'danger', 'msg' => '此备份已删除')));
    }
    exit();
}
add_action('wp_ajax_zhiji_options_backup_delete', 'zhiji_ajax_options_backup_delete');
add_action('wp_ajax_zhiji_options_backup_delete_all', 'zhiji_ajax_options_backup_delete');
add_action('wp_ajax_zhiji_options_backup_delete_surplus', 'zhiji_ajax_options_backup_delete');

function zhiji_ajax_options_backup_restore()
{
    if (!is_super_admin()) {
        echo(json_encode(array('error' => 1, 'ys' => 'danger', 'msg' => '操作权限不足')));
        exit();
    }
    if (empty($_REQUEST['key'])) {
        echo(json_encode(array('error' => 1, 'ys' => 'danger', 'msg' => '参数传入错误')));
        exit();
    }

    $prefix         = 'zhiji_options';
    $options_backup = get_option($prefix . '_backup');
    if (isset($options_backup[$_REQUEST['key']]['data'])) {
        update_option($prefix, $options_backup[$_REQUEST['key']]['data']);
        echo(json_encode(array('error' => 0, 'reload' => 1, 'msg' => '主题设置已恢复到所选备份[' . $_REQUEST['key'] . ']')));
    } else {
        echo(json_encode(array('error' => 1, 'ys' => 'danger', 'msg' => '备份恢复失败，未找到对应数据')));
    }
    exit();
}
add_action('wp_ajax_zhiji_options_backup_restore', 'zhiji_ajax_options_backup_restore');
