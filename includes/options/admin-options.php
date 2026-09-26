<?php
/**
 * @module  AdminOptions
 * @desc    后台设置页注册：10 大分类骨架 + 懒构建
 *          - 顶层分类：所有后台请求都注册（保证左侧菜单可见）
 *          - 分节与字段：仅在本主题设置页或 CSF 自身 ajax 时注册（避免后台整体变慢、
 *            也避免默认值在不相关请求中被提前写库）
 * @since   2.0.0
 */

defined('ABSPATH') || exit;

function zhiji_csf_admin_options()
{
    if (!is_admin() || !class_exists('CSF')) {
        return;
    }

    $prefix = ZHIJI_OPTION_KEY;                 // zhiji_options
    $own    = Zhiji_Registry::is_own_context(); // 懒构建判据

    CSF::createOptions($prefix, array(
        'menu_title'         => '知集主题设置',
        'menu_slug'          => $prefix,
        'framework_title'    => '知集主题 <small>v' . ZHIJI_VERSION . '</small>',
        'show_in_customizer' => false,
        'save_defaults'      => $own,           // 只在自身上下文写默认值
        'footer_text'        => '知集主题 v' . ZHIJI_VERSION,
        'footer_credit'      => '',
        'theme'              => 'light',
    ));

    /**
     * 9 大顶层分类（顺序即后台菜单顺序；分类内分节顺序由各分节的 priority 决定）
     * 2026-09-26 重构：拆分过大的 zhiji_user(12)/zhiji_page(12)，
     * 新增 zhiji_comment（评论&互动）、zhiji_element（页面元素）；
     * 合并单模块分类 zhiji_shop → zhiji_pay、zhiji_forum → zhiji_page；
     * 移除空分类 zhiji_cap。
     */
    $cats = array(
        'zhiji_basic'    => array('全局&功能', 'fa fa-fw fa-bullseye'),
        'zhiji_page'     => array('页面&显示', 'fa fa-fw fa-desktop'),
        'zhiji_element'  => array('页面元素', 'fa fa-fw fa-th-large'),
        'zhiji_post'     => array('文章&列表', 'fa fa-fw fa-file-text-o'),
        'zhiji_beautify' => array('美化效果', 'fa fa-fw fa-paint-brush'),
        'zhiji_user'     => array('用户&互动', 'fa fa-fw fa-users'),
        'zhiji_comment'  => array('评论&互动', 'fa fa-fw fa-comments-o'),
        'zhiji_pay'      => array('支付&付费', 'fa fa-fw fa-credit-card'),
        'zhiji_over'     => array('扩展&增强', 'fa fa-fw fa-cubes'),
    );
    foreach ($cats as $id => $c) {
        CSF::createSection($prefix, array(
            'id'    => $id,
            'title' => $c[0],
            'icon'  => $c[1],
        ));
    }

    // 懒构建：非自身上下文不再注册任何分节与字段
    if (!$own) {
        return;
    }

    // 基础设施分节：配置备份 / 导入 / 恢复（框架既有能力，属基础设施而非业务功能）
    CSF::createSection($prefix, array(
        'parent'   => 'zhiji_basic',
        'title'    => '备份&导入',
        'icon'     => 'fa fa-fw fa-copy',
        'priority' => 90,
        'fields'   => Zhiji_Module::backup(),
    ));
}
zhiji_csf_admin_options();
