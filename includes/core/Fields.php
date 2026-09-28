<?php
/**
 * @module  Fields
 * @desc    后台字段构件库 —— 声明式 CSF 字段的构造函数集合。
 *          把「一个 switcher 要手写 5~6 行数组」压成一行，并**统一 dependency 表达式写法**。
 * @since   2.0.0（2026-09-28 由《后台设置模块化审查与方案》P2-A 落地）
 *
 * ─────────────────────────────────────────────────────────────
 * 使用边界（务必遵守，否则会踩「删字段回退默认值」等坑）
 *
 * ✅ **允许**：① 新增模块的字段定义；② **等价改写**存量字段（改写后必须做
 *      `tools/zhiji_settings_snapshot.py --diff`，**零差异**才算等价 ——
 *      ③ 源码字段指纹已含 `id|默认值|标题`，能证明不只是 id 没变）。
 * ❌ **禁止**：借重构之名顺便**改**标题 / 默认值 / 字段顺序 / 删除字段 —— 那是产品变更，
 *      不是风格统一（参见交接文档坑 18：删 CSF 字段会回退注册默认值）。
 *
 * 与 `core/RewardFields.php` 的关系：后者是**奖励类专用**字段组（五类权重/数值区间/会员天数），
 * 本文件是**通用**构件；两者互补，新增奖励类模块可同时使用。
 * ─────────────────────────────────────────────────────────────
 * ⚠️ 加载顺序：本文件由 includes/index.php 的 core 加载列表引入，**必须早于业务模块**
 *    （模块在加载期就会调用这里的函数来构造字段数组）。
 */

defined('ABSPATH') || exit;

/**
 * 组装字段数组：统一「空值不落键」的规则
 *
 * 为什么要有这一步：CSF 对 `'desc' => ''`、`'dependency' => array()` 这类空值会输出多余节点
 * 或产生意外行为，所以只在**确实有值**时才把键写进去。
 *
 * @param array $base  必填键
 * @param array $opt   可选键（值为 null/'' /array() 时跳过）
 * @return array
 */
function zhiji_field_merge(array $base, array $opt = array())
{
    foreach ($opt as $k => $v) {
        if (null === $v || '' === $v || array() === $v) {
            continue;
        }
        $base[$k] = $v;
    }
    return $base;
}

/**
 * 开关字段（switcher）
 *
 * @param string $id      字段键
 * @param string $title   标题（建议传 __( '…', 'zhiji' ) 或原样中文，保持与存量一致）
 * @param bool   $default 默认值（**注意**：CSF 存的是字符串 '0'/'1'，此处传布尔即可）
 * @param string $desc    说明（可空）
 * @param array  $dep     dependency 表达式，如 array('parent_switch', '==', '1')；可空
 * @return array
 */
function zhiji_field_switch($id, $title, $default = false, $desc = '', $dep = array())
{
    return zhiji_field_merge(array(
        'id'      => $id,
        'type'    => 'switcher',
        'title'   => $title,
        'default' => $default,
    ), array('desc' => $desc, 'dependency' => $dep));
}

/**
 * 数字字段（number）
 *
 * @param string   $id
 * @param string   $title
 * @param int      $default
 * @param string   $desc
 * @param int|null $min   下限（null = 不限制）
 * @param int|null $max   上限（null = 不限制）
 * @param array    $dep
 * @return array
 */
function zhiji_field_number($id, $title, $default = 0, $desc = '', $min = null, $max = null, $dep = array())
{
    return zhiji_field_merge(array(
        'id'      => $id,
        'type'    => 'number',
        'title'   => $title,
        'default' => $default,
    ), array('desc' => $desc, 'min' => $min, 'max' => $max, 'dependency' => $dep));
}

/**
 * 单行文本（text）
 *
 * @param string $id
 * @param string $title
 * @param string $default
 * @param string $desc
 * @param array  $dep
 * @return array
 */
function zhiji_field_text($id, $title, $default = '', $desc = '', $dep = array())
{
    return zhiji_field_merge(array(
        'id'      => $id,
        'type'    => 'text',
        'title'   => $title,
        'default' => $default,
    ), array('desc' => $desc, 'dependency' => $dep));
}

/**
 * 多行文本（textarea）
 *
 * @param string $id
 * @param string $title
 * @param string $default
 * @param string $desc
 * @param int    $rows
 * @param array  $dep
 * @return array
 */
function zhiji_field_textarea($id, $title, $default = '', $desc = '', $rows = 5, $dep = array())
{
    return zhiji_field_merge(array(
        'id'      => $id,
        'type'    => 'textarea',
        'title'   => $title,
        'default' => $default,
        'rows'    => (int) $rows,
    ), array('desc' => $desc, 'dependency' => $dep));
}

/**
 * 下拉选择（select）
 *
 * @param string $id
 * @param string $title
 * @param array  $options 选项：值 => 显示文案
 * @param mixed  $default
 * @param string $desc
 * @param array  $dep
 * @return array
 */
function zhiji_field_select($id, $title, array $options, $default = '', $desc = '', $dep = array())
{
    return zhiji_field_merge(array(
        'id'      => $id,
        'type'    => 'select',
        'title'   => $title,
        'options' => $options,
        'default' => $default,
    ), array('desc' => $desc, 'dependency' => $dep));
}

/**
 * 说明型提示（submessage）—— **不可配置**的纯展示块
 *
 * 典型用途：某个开关被移除、改为「常驻启用」后，在原位置留一条明确的说明，
 * 让用户知道**功能没丢、只是不再可配**（而非静默消失）。
 *
 * @param string $content HTML 内容（⚠️ 禁止裸 `<style>`/`</head>`，会吞掉后续 section，见坑 22）
 * @param string $style   info | success | warning | danger
 * @param array  $dep
 * @return array
 */
function zhiji_notice($content, $style = 'info', $dep = array())
{
    return zhiji_field_merge(array(
        'type'    => 'submessage',
        'style'   => $style,
        'content' => $content,
    ), array('dependency' => $dep));
}
