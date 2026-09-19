<?php

if (is_admin()) {
    zib_require(array(
        'options-module',
        'action',
        'admin-options',
    ), false, 'includes/options/');
}

//使用Font Awesome 4
add_filter('csf_fa4', '__return_true');


// add_action('csf_enqueue', 'zhiji_csf_add_custom_wp_enqueue');



//获取主题设置链接
function zhiji_get_admin_csf_url($tab = '')
{
    $tab                = trim(strip_tags($tab));
    $tab_array          = explode('/', $tab);
    $tab_array_sanitize = array();
    foreach ($tab_array as $tab_i) {
        $tab_array_sanitize[] = sanitize_title($tab_i);
    }
    $tab_attr = esc_attr(implode('/', $tab_array_sanitize));
    $url      = add_query_arg('page', 'zhiji_options', admin_url('admin.php'));
    $url      = $tab ? $url . '#tab=' . $tab_attr : $url;
    return esc_url($url);
}

//备份主题数据
function zhiji_options_backup($type = '自动备份')
{
    $prefix  = 'zhiji_options';
    $options = get_option($prefix);

    $options_backup = get_option($prefix . '_backup');
    if (!$options_backup) {
        $options_backup = array();
    }

    $time                  = current_time('Y-m-d H:i:s');
    $options_backup[$time] = array(
        'time' => $time,
        'type' => $type,
        'data' => $options,
    );

    //保留20次数据，删除多余的
    if (count($options_backup) > 20) {
        $options_backup = array_slice($options_backup, -20);
    }

    return update_option($prefix . '_backup', $options_backup);
}

function zhiji_csf_reset_to_backup()
{
    zhiji_options_backup('重置全部 自动备份');
}

add_action('csf_zhiji_options_reset_before', 'zhiji_csf_reset_to_backup');

function zhiji_csf_reset_section_to_backup()
{
    zhiji_options_backup('重置选区 自动备份');
}

add_action('csf_zhiji_options_reset_section_before', 'zhiji_csf_reset_section_to_backup');

//主题更新自动备份
function zhiji_new_zhiji_to_backup()
{
    $prefix         = 'zhiji_options';
    $options_backup = get_option($prefix . '_backup');
    $time           = false;

    if ($options_backup) {
        $options_backup = array_reverse($options_backup);
        foreach ($options_backup as $key => $val) {
            if ('更新主题 自动备份' == $val['type']) {
                $time = $key;
                break;
            }
        }
    }

    if (!$time || strtotime($time) < strtotime('-30 minutes', current_time('timestamp'))) {
        zhiji_options_backup('更新主题 自动备份');

        //更新主题刷新所有缓存
        wp_cache_flush();

        //更新主题，删除更新
        delete_option('zhiji_new_version');
    }
}
add_action('zhiji_update_notices', 'zhiji_new_zhiji_to_backup');

//定期自动备份
function zhiji_csf_save_section_to_backup()
{
    $prefix         = 'zhiji_options';
    $options_backup = get_option($prefix . '_backup');
    $time           = false;

    if ($options_backup) {
        $options_backup = array_reverse($options_backup);
        foreach ($options_backup as $key => $val) {
            if ('定期自动备份' == $val['type']) {
                $time = $key;
                break;
            }
        }
    }
    if (!$time || (floor((strtotime(current_time('Y-m-d H:i:s')) - strtotime($time)) / 3600) > 600)) {
        zhiji_options_backup('定期自动备份');
    }
}

add_action('csf_zhiji_options_saved', 'zhiji_csf_save_section_to_backup');
