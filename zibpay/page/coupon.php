<?php
/*
 * @Author: Qinver
 * @Url: zibll.com
 * @Date: 2024-06-26 11:52:43
 * @LastEditTime : 2026-05-05 11:36:39
 * @Email: 770349780@qq.com
 * @Project: Zibll子比主题
 * @Description: 更优雅的Wordpress主题 | 后台优惠码页面
 * @Read me: 感谢您使用子比主题，主题源码有详细的注释，支持二次开发
 * @Remind: 使用盗版主题会存在各种未知风险。支持正版，从我做起！
 * Copyright (c) 2024 by Qinver, All Rights Reserved.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!is_super_admin()) {
    wp_die(__('您不能访问此页面', 'zib_language'), __('权限不足', 'zib_language'));
    exit;
}

$this_url  = esc_url(admin_url('admin.php?page=zibpay_coupon_page'));
$tab       = !empty($_GET['tab']) ? $_GET['tab'] : '';
$action    = !empty($_REQUEST['action']) ? $_REQUEST['action'] : '';
$s         = !empty($_REQUEST['s']) ? esc_sql($_REQUEST['s']) : '';
$this_name = __('优惠码', 'zib_language');

if ($action) {
    switch ($action) {
        case 'add':

            $add_type  = !empty($_REQUEST['add_type']) ? $_REQUEST['add_type'] : 'auto';
            $pass_type = !empty($_REQUEST['type']) || $_REQUEST['type'] !== 'vip_coupon' ? $_REQUEST['type'] : 'coupon';

            if ($add_type === 'import') {
                $import_data     = !empty($_REQUEST['import_data']) ? $_REQUEST['import_data'] : '';
                $import_division = !empty($_REQUEST['import_division']) ? wp_unslash($_REQUEST['import_division']) : ' ';

                if (!$import_data) {
                    zib_admin_page_notice(__('错误！', 'zib_language'), __('请粘贴您需要导入的数据', 'zib_language'), 'error');
                    break;
                }

                $import_data_array = explode("\r\n", $import_data);

                if (!$import_data_array) {
                    zib_admin_page_notice(__('错误！', 'zib_language'), __('未找到需要导入的数据', 'zib_language'), 'error');
                    break;
                }

                $success_i = 0;
                $error_i   = 0;
                foreach ($import_data_array as $v) {
                    $v = explode($import_division, $v);

                    if (isset($v[6]) && in_array($v[2], ['multiply', 'subtract']) && is_numeric($v[3])) {

                        if ($pass_type === 'vip_exchange') {

                        } else {
                            //时间格式转换为年月日 23:59:59
                            $expire_time = $v[4] ? $v[4] : 0;
                            if ($expire_time) {
                                $expire_time = date('Y-m-d 23:59:59', strtotime($expire_time));
                            }

                            $discount = array(
                                'type' => $v[2],
                                'val'  => $v[3],
                            );
                            $meta = array(
                                'discount'    => $discount,
                                'title'       => $v[5],
                                'reuse'       => $v[6],
                                'expire_time' => $expire_time,
                            );
                        }

                        $success_i++;
                        ZibCardPass::add(array(
                            'password' => $v[0],
                            'post_id'  => (int) $v[1],
                            'type'     => $pass_type,
                            'status'   => '0', //正常
                            'meta'     => $meta,
                            'other'    => !empty($v[7]) ? $v[7] : '',
                        ));
                    } else {
                        $error_i++;
                    }
                }

                if ($success_i) {
                    zib_admin_page_notice(__('导入完成', 'zib_language'), sprintf(__('成功导入%s个卡密%s', 'zib_language'), $success_i, ($error_i ? sprintf(__('，%s个导入失败', 'zib_language'), $error_i) : '')));
                    break;
                } else {
                    zib_admin_page_notice(__('导入失败', 'zib_language'), __('数据格式错误', 'zib_language'), 'error');
                    break;
                }

            } else {
                //自动生成
                $auto_num    = !empty($_REQUEST['auto_num']) ? (int) $_REQUEST['auto_num'] : 0;
                $post_id     = !empty($_REQUEST['post_id']) ? (int) $_REQUEST['post_id'] : 0;
                $discount    = !empty($_REQUEST['discount']) ? (array) $_REQUEST['discount'] : array();
                $title       = !empty($_REQUEST['title']) ? esc_sql($_REQUEST['title']) : ''; //标题，过滤为纯文字
                $reuse       = isset($_REQUEST['reuse']) ? (int) $_REQUEST['reuse'] : 1; //标题，过滤为纯文字
                $expire_time = !empty($_REQUEST['expire_time']) ? esc_sql($_REQUEST['expire_time']) : 0;
                $mate        = array();

                if (!$auto_num) {
                    zib_admin_page_notice(__('错误！', 'zib_language'), __('请输入需要生成的数量', 'zib_language'), 'error');
                    break;
                }

                if (empty($discount['type']) || empty($discount['val'])) {
                    zib_admin_page_notice(__('错误！', 'zib_language'), __('请设置优惠券的优惠折扣', 'zib_language'), 'error');
                    break;
                }

                if ($pass_type === 'vip_coupon') {
                } else {
                    $mate = array(
                        'discount'    => $discount,
                        'title'       => $title,
                        'reuse'       => $reuse,
                        'expire_time' => $expire_time,
                    );
                }

                $rand_password = 8;
                if (!empty($_REQUEST['auto_top_s'])) {
                    $rand_password = !empty($_REQUEST['auto_rand_password_limit']) ? (int) $_REQUEST['auto_rand_password_limit'] : 8;
                }

                $remarks = !empty($_REQUEST['auto_remarks']) ? $_REQUEST['auto_remarks'] : '';

                zibpay_generate_coupon($pass_type, $auto_num, $post_id, $mate, $rand_password, $remarks);

                zib_admin_page_notice(__('完成！', 'zib_language'), sprintf(__('已自动生成%s个优惠码', 'zib_language'), $auto_num));
                break;
            }

            zib_admin_page_notice(__('错误！', 'zib_language'), __('参数传入错误', 'zib_language'), 'error');

            break;

        case 'delete':
            $delete_ids = !empty($_REQUEST['action_id']) ? $_REQUEST['action_id'] : 0;
            if (!$delete_ids) {
                zib_admin_page_notice(__('错误！', 'zib_language'), __('未选择需要删除的内容', 'zib_language'), 'error');
                break;
            }
            $delete_i = ZibCardPass::delete(array(
                'id'   => $delete_ids,
                'type' => ['coupon', 'vip_coupon'],
            ));

            zib_admin_page_notice(__('删除完成', 'zib_language'), sprintf(__('已删除%s个卡密', 'zib_language'), $delete_i));
            break;
    }
}

function zib_admin_page_notice($title = '', $msg = '', $type = 'success')
{
    $html = '';
    $html .= $title ? '<h3>' . $title . '</h3>' : '';
    $html .= $msg ? '<p>' . $msg . '</p>' : '';

    if ($html) {
        echo '<div class="notice notice-' . $type . '">' . $html . '</div>';
    }
}

$page_title = sprintf(__('%s管理', 'zib_language'), $this_name);
$head_but   = '<a href="' . add_query_arg('tab', 'add', $this_url) . '" class="page-title-action">' . sprintf(__('添加%s', 'zib_language'), $this_name) . '</a>';
$sub_but    = array();

//准备查询参数
$msg_type    = !empty($_REQUEST['msg_type']) ? $_REQUEST['msg_type'] : 0;
$user_id     = !empty($_REQUEST['user_id']) ? $_REQUEST['user_id'] : 0;
$orderby     = !empty($_REQUEST['orderby']) ? $_REQUEST['orderby'] : 'modified_time';
$paged       = !empty($_REQUEST['paged']) ? $_REQUEST['paged'] : 1;
$ice_perpage = !empty($_REQUEST['ice_perpage']) ? $_REQUEST['ice_perpage'] : 30;
$desc        = !empty($_REQUEST['desc']) ? $_REQUEST['desc'] : 'DESC';
$offset      = $ice_perpage * ($paged - 1);

$count_all = 0;
$db_data   = false;
$csf_args  = false;
$table     = false;
$pagenavi  = false;
$search    = false;
$page_html = false;

switch ($tab) {

    case 'add':
        $page_title = sprintf(__('添加%s', 'zib_language'), $this_name);
        $head_but   = '<a href="' . $this_url . '" class="page-title-action">' . esc_html__('返回列表', 'zib_language') . '</a>';

        $csf_fields = array();

        $csf_fields[] = array(
            'content' => '<p><b>' . esc_html__('在此添加优惠码', 'zib_language') . '</b></p>
            <li>' . esc_html__('如果您已经准备好了相关数据，请选择导入的方式添加', 'zib_language') . '</li>
            <li>' . esc_html__('您也可以采用系统生成的方式，自动批量添加', 'zib_language') . '</li>',
            'style'   => 'warning',
            'type'    => 'submessage',
        );

        $csf_fields[] = array(
            'id'      => 'type',
            'type'    => 'button_set',
            'class'   => 'hide', //暂时隐藏
            'title'   => __('卡密类型', 'zib_language'),
            'inline'  => true,
            'options' => array(
                'coupon'     => __('购买商品优惠券', 'zib_language'),
                'vip_coupon' => __('购买会员优惠券', 'zib_language'),
            ),
            'default' => 'coupon',
        );

        $csf_fields[] = array(
            'id'      => 'add_type',
            'type'    => 'button_set',
            'title'   => __('添加方式', 'zib_language'),
            'inline'  => true,
            'options' => array(
                'auto'   => __('系统生成', 'zib_language'),
                'import' => __('导入', 'zib_language'), //导入
            ),
            'default' => 'auto',
        );

        $csf_fields[] = array(
            'dependency' => array('add_type', '==', 'auto'),
            'title'      => __('生成数量', 'zib_language'),
            'id'         => 'auto_num',
            'default'    => 10,
            'min'        => 1,
            'max'        => 1000,
            'step'       => 10,
            'unit'       => __('张', 'zib_language'),
            'desc'       => __('需要生成多少（单次生成数量太多可能会对服务器性能造成影响）', 'zib_language'),
            'type'       => 'spinner',
        );

        $csf_fields[] = array(
            'dependency'  => array('add_type', '==', 'auto'),
            'title'       => __('绑定文章', 'zib_language'),
            'id'          => 'post_id',
            'default'     => '',
            'options'     => 'post',
            'type'        => 'select',
            'placeholder' => __('输入关键词以搜索', 'zib_language'),
            'chosen'      => true,
            'desc'        => __('选择一个文章，绑定优惠券到该文章，如果不绑定则所有商品都可以使用', 'zib_language'),
            'multiple'    => false,
            'sortable'    => false,
            'ajax'        => true,
            'settings'    => array(
                'min_length' => 2,
            ),
            'query_args'  => array(
                'post_type' => array('plate', 'forum_post', 'post', 'page'),
            ),
        );

        $csf_fields[] = array(
            'dependency' => array('add_type', '==', 'auto'),
            'title'      => __('优惠折扣', 'zib_language'),
            'id'         => 'discount',
            'type'       => 'fieldset',
            'fields'     => array(
                array(
                    'title'   => __('优惠方式(必填)', 'zib_language'),
                    'id'      => 'type',
                    'type'    => 'radio',
                    'default' => 'multiply',
                    'inline'  => true,
                    'options' => array(
                        'multiply' => __('打折', 'zib_language'),
                        'subtract' => __('减价', 'zib_language'),
                    ),
                ),
                array(
                    'title'   => __('优惠数据(必填)', 'zib_language'),
                    'desc'    => __('折扣比例或者减价金额', 'zib_language') . '<br>' . __('选择打折时候，填0.01-0.99，对应0.1折到9.9折', 'zib_language'),
                    'id'      => 'val',
                    'default' => '',
                    'type'    => 'text',
                ),
            ),
        );

        $csf_fields[] = array(
            'title'    => __('优惠券有效期', 'zib_language'),
            'id'       => 'expire_time',
            'type'     => 'date',
            'desc'     => __('优惠券的有效期，过期后将无法使用。留空则不限制', 'zib_language'),
            'default'  => '',
            'settings' => array(
                'dateFormat'  => 'yy-mm-dd 23:59:59',
                'changeMonth' => true,
                'changeYear'  => true,
            ),
        );

        $csf_fields[] = array(
            'dependency' => array('add_type', '==', 'auto'),
            'title'      => __('优惠码名称', 'zib_language'),
            'desc'       => __('一句话描述这个优惠码的作用，例如：国庆大促 限时8折', 'zib_language'),
            'id'         => 'title',
            'default'    => '',
            'type'       => 'text',
        );

        $csf_fields[] = array(
            'dependency' => array('add_type', '==', 'auto'),
            'title'      => __('重复使用', 'zib_language'),
            'desc'       => __('一个优惠码可以被使用的次数，0为无限次，默认1次', 'zib_language'),
            'id'         => 'reuse',
            'default'    => 1,
            'min'        => 0,
            'max'        => 100,
            'step'       => 1,
            'unit'       => __('次', 'zib_language'),
            'type'       => 'spinner',
        );

        $csf_fields[] = array(
            'dependency' => array('add_type', '==', 'auto'),
            'title'      => __('高级选项', 'zib_language'),
            'id'         => 'auto_top_s',
            'default'    => false,
            'type'       => 'switcher',
        );

        $csf_fields[] = array(
            'dependency' => array('add_type|auto_top_s', '==|!=', 'auto|'),
            'title'      => __('自定义位数', 'zib_language'),
            'desc'       => __('自定义自动生成的长度（不能太短，太短可能会出现重复）', 'zib_language'),
            'id'         => 'auto_rand_password_limit',
            'default'    => 8,
            'min'        => 1,
            'max'        => 50,
            'step'       => 2,
            'unit'       => __('位数', 'zib_language'),
            'type'       => 'spinner',
        );
        $csf_fields[] = array(
            'dependency' => array('add_type', '==', 'auto'),
            'title'      => __('标识', 'zib_language'),
            'desc'       => __('对生成的数据做标记标识，方便后期查找管理', 'zib_language'),
            'id'         => 'auto_remarks',
            'default'    => 'coupon_' . current_time('YmdHis'),
            'type'       => 'text',
        );
        //导入优惠码
        $csf_fields[] = array(
            'dependency' => array('add_type|type', '!=', 'auto'),
            'content'    => '<p><b>' . esc_html__('导入卡密', 'zib_language') . '</b></p>
            <li>' . esc_html__('一行一个卡密，单行格式为：', 'zib_language') . '<code>' . esc_html__('优惠码 绑定文章ID 折扣方式 折扣数额 有效期 优惠码名称 重复使用次数 标识', 'zib_language') . '</code></li>
            <li>' . esc_html__('默认使用空格分割，您可以在下方自定义分割符号，与您的数据对应即可', 'zib_language') . '</li>
            <li>' . esc_html__('绑定文章ID填写需要绑定的文章、帖子的ID，如需全部商品可用，填0即可', 'zib_language') . '</li>
            <li>' . esc_html__('折扣方式只有两种可选：multiply和subtract，对应打折和减价', 'zib_language') . '</li>
            <li>' . esc_html__('折扣数额为折扣比例或者减价金额', 'zib_language') . '</li>
            <li>' . esc_html__('有效期仅支持年月日，不支持时间格式为 2024-08-08，填0则为不限制', 'zib_language') . '</li>
            <li>' . esc_html__('优惠码名称为可选，如不需要填0', 'zib_language') . '</li>
            <li>' . esc_html__('重复使用次数填0则可无限重复使用', 'zib_language') . '</li>
            <li></li>
            <li>' . esc_html__('单次导入数量太多可能会对服务器性能造成影响', 'zib_language') . '</li>
            <p><b>' . esc_html__('数据示例', 'zib_language') . '</b></p>
            <div>oPYum6KSA 0 multiply 0.88 0 0 0 ' . esc_html__('所有商品打8.8折扣永久无限重复使用', 'zib_language') . '</div>
            <div>LPWbpmtTG 0 subtract 50 2024-12-31 ' . sprintf(esc_html__('24年%1$s优惠券', 'zib_language'), zibpay_format_local_price_text(50)) . ' 10 ' . sprintf(esc_html__('所有商品立减%1$s24年有效仅10可使用10次', 'zib_language'), zibpay_format_local_price_text(50)) . '</div>',
            'style'      => 'warning',
            'type'       => 'submessage',
        );

        $csf_fields[] = array(
            'dependency' => array('add_type', '==', 'import'),
            'title'      => __('卡密数据', 'zib_language'),
            'id'         => 'import_data',
            'default'    => '',
            'attributes' => array(
                'rows'  => 10,
                'style' => 'resize: both;max-width: none;',
            ),
            'sanitize'   => false,
            'type'       => 'textarea',
        );
        $csf_fields[] = array(
            'dependency' => array('add_type', '==', 'import'),
            'id'         => 'import_division', //分割
            'title'      => __('自定义分隔符号', 'zib_language'),
            'subtitle'   => '',
            'class'      => 'mini-input',
            'default'    => ' ',
            'desc'       => __('卡号和密码之间分割符号（默认为空格分割）', 'zib_language'),
            'type'       => 'text',
        );
        $csf_fields[] = array(
            'title'   => ' ',
            'type'    => 'content',
            'content' => '<button type="submit" class="but jb-blue">' . esc_html__('确认提交', 'zib_language') . '</button>',
        );

        $csf_args = array(
            'class'  => 'csf-profile-options',
            'method' => 'post',
            'value'  => array(),
            'hidden' => array(
                array(
                    'name'  => 'action',
                    'value' => 'add',
                ),
            ),
            'fields' => $csf_fields,
        );

        break;

    case 'export':

        $page_title = sprintf(__('导出%s', 'zib_language'), $this_name);
        $head_but   = '<a href="' . $this_url . '" class="page-title-action">' . esc_html__('返回列表', 'zib_language') . '</a>';

        $csf_fields   = array();
        $csf_fields[] = array(
            'id'      => 'type',
            'type'    => 'button_set',
            'class'   => 'hide', //暂时隐藏
            'title'   => sprintf(__('%s类型', 'zib_language'), $this_name),
            'inline'  => true,
            'options' => array(
                'coupon'     => __('商品优惠码', 'zib_language'),
                'vip_coupon' => __('会员优惠码', 'zib_language'), //导入
            ),
            'default' => 'coupon',
        );
        $csf_fields[] = array(
            'id'      => 'status',
            'type'    => 'radio',
            'title'   => __('选择状态', 'zib_language'),
            'inline'  => true,
            'options' => array(
                'all'  => __('全部', 'zib_language'),
                '0'    => __('可用', 'zib_language'), //导入
                'used' => __('不可用', 'zib_language'), //导入
            ),
            'default' => 'all',
        );
        $csf_fields[] = array(
            'id'      => 'export_format',
            'type'    => 'radio',
            'title'   => __('导出格式', 'zib_language'),
            'inline'  => true,
            'options' => array(
                'text' => __('文本文档', 'zib_language'),
                'xls'  => __('Excel表格', 'zib_language'), //导入
            ),
            'default' => 'xls',
        );

        $csf_fields[] = array(
            'dependency' => array('export_format', '==', 'text'),
            'id'         => 'text_division', //分割
            'title'      => ' ',
            'subtitle'   => __('分隔符号', 'zib_language'),
            'class'      => 'mini-input',
            'default'    => ' ',
            'desc'       => __('卡号和密码之间分割符号（默认为空格分割）', 'zib_language'),
            'type'       => 'text',
        );

        $csf_fields[] = array(
            'title'   => ' ',
            'type'    => 'content',
            'content' => '<button type="submit" class="but jb-blue">' . esc_html__('确认提交', 'zib_language') . '</button>',
        );

        $csf_args = array(
            'class'  => 'csf-profile-options',
            'method' => 'post',
            'action' => admin_url('admin-ajax.php'),
            'value'  => array(),
            'hidden' => array(
                array(
                    'name'  => 'action',
                    'value' => 'card_pass_export',
                ),
            ),
            'fields' => $csf_fields,
        );

        break;

    default:
        //默认页面，展示卡密列表
        $pagenavi  = true;
        $sub_but[] = array(
            'name' => __('全部', 'zib_language'),
            'href' => $this_url,
        );

        $head_but .= '<a href="' . add_query_arg(['tab' => 'export'], $this_url) . '" class="page-title-action">' . esc_html__('导出', 'zib_language') . '</a>';

        if ($s) {
            $head_but .= '<div><div class="update-nag" style="margin: 10px 0 0;">' . sprintf(esc_html__('搜索 “%s” 的内容', 'zib_language'), esc_html($s)) . ' </div></div>';
        } else {
            $sub_but[] = array(
                'name' => __('可用', 'zib_language'),
                'href' => add_query_arg('status', '0', $this_url),
            );

            $sub_but[] = array(
                'name' => __('不可用', 'zib_language'),
                'href' => add_query_arg('status', 'used', $this_url),
            );

            /**
             *  //暂时不开放

            $sub_but[] = array(
            'name' => '商品优惠券',
            'href' => add_query_arg('type', 'coupon'),
            );

            $sub_but[] = array(
            'name' => '购买会员优惠券',
            'href' => add_query_arg('type', 'vip_coupon'),
            );
             */
        }

        $where = array(
            'type' => ['coupon', 'vip_coupon'],
        );

        if (isset($_GET['type'])) {
            $where['type'] = $_GET['type'];
        }

        if (isset($_GET['status'])) {
            $where['status'] = $_GET['status'];
        }
        if (isset($_GET['other'])) {
            $where['other'] = $_GET['other'];
        }
        if (isset($_GET['post_id'])) {
            $where['post_id'] = (int) $_GET['post_id'];
        }

        $db = ZibDB::name(ZibCardPass::$table_name);
        $db->where($where)->order($orderby, $desc)->page($paged, $ice_perpage);
        if ($s) {
            $db->whereLike(['card', 'password', 'other', 'meta'], $s);
        }

        $db_data   = $db->select()->result();
        $count_all = $db->count();

        $table = '<thead><tr><td style="color: #ff4a4a;text-align: center;">' . esc_html__('未找到对应内容，或暂无内容', 'zib_language') . '</td></tr></thead>';
        if ($db_data) {
            $table    = '';
            $theads[] = array('width' => '12%', 'orderby' => 'password', 'name' => __('优惠码', 'zib_language'));
            $theads[] = array('width' => '10%', 'orderby' => 'post_id', 'name' => __('绑定商品', 'zib_language'));
            $theads[] = array('width' => '8%', 'orderby' => '', 'name' => __('使用限制', 'zib_language'));
            $theads[] = array('width' => '8%', 'orderby' => '', 'name' => __('状态', 'zib_language'));
            $theads[] = array('width' => '10%', 'orderby' => 'create_time', 'name' => __('创建时间', 'zib_language'));
            $theads[] = array('width' => '10%', 'orderby' => 'modified_time', 'name' => __('更新时间', 'zib_language'));
            $theads[] = array('width' => '10%', 'orderby' => '', 'name' => __('优惠码名称', 'zib_language'));
            $theads[] = array('width' => '15%', 'orderby' => 'other', 'name' => __('标识', 'zib_language'));

            $thead_th = '<td id="cb" class="manage-column column-cb check-column" style="width: 2%;"><label class="screen-reader-text" for="cb-select-all-1">' . esc_html__('全选', 'zib_language') . '</label><input id="cb-select-all-1" type="checkbox"></td>';
            foreach ($theads as $thead) {
                $orderby = '';
                if ($thead['orderby']) {
                    $orderby_url = add_query_arg('orderby', $thead['orderby']);
                    $orderby .= '<a title="' . esc_attr__('降序', 'zib_language') . '" href="' . add_query_arg('desc', 'ASC', $orderby_url) . '"><span class="dashicons dashicons-arrow-up"></span></a>';
                    $orderby .= '<a title="' . esc_attr__('升序', 'zib_language') . '" href="' . add_query_arg('desc', 'DESC', $orderby_url) . '"><span class="dashicons dashicons-arrow-down"></span></a>';
                    $orderby = '<span class="orderby-but">' . $orderby . '</span>';
                }
                $thead_th .= '<th class="" width="' . $thead['width'] . '">' . $thead['name'] . $orderby . '</th>';
            }
            $table .= '<thead><tr>' . $thead_th . '</tr></thead>';

            $tbody = '';
            foreach ($db_data as $msg) {
                $coupon_data   = zibpay_filter_coupon_data($msg);
                $meta          = maybe_unserialize($msg->meta);
                $discount_text = $coupon_data['discount_text'];

                $limit_html = '';
                if ($coupon_data['reuse'] == 1) {
                    $limit_html .= '<span style="color: #975106;">' . esc_html__('单次使用', 'zib_language') . '</span>';
                } elseif (!$coupon_data['reuse']) {
                    $limit_html .= '<span style="color: #289d0f;">' . esc_html__('无限重复可用', 'zib_language') . '</span>';
                } else {
                    $limit_html .= '<span style="color: #3d7ffd;">' . sprintf(esc_html__('可重复使用%s次', 'zib_language'), $coupon_data['reuse']) . '</span>';
                }

                if ($coupon_data['expire_time']) {
                    if (current_time('timestamp') > strtotime($coupon_data['expire_time'])) {
                        $limit_html .= '<div style="color: #f93b3b;" title="' . esc_attr(sprintf(__('有效期%s', 'zib_language'), $coupon_data['expire_time'])) . '">' . esc_html__('【已过期】', 'zib_language') . '</div>';
                    } else {
                        $limit_html .= '<div style="color: #db8426;">' . sprintf(esc_html__('有效期%s', 'zib_language'), $coupon_data['expire_time']) . '</div>';
                    }
                }

                $status_html = '';
                if ($msg->status === 'used') {
                    $status_html .= '<sapn class="badg c-red">' . esc_html__('不可用', 'zib_language') . '</sapn>';
                } else {
                    $status_html .= '<sapn class="badg c-green">' . esc_html__('可用', 'zib_language') . '</sapn>';
                }

                if ($coupon_data['used_count']) {
                    $status_html .= '<sapn style="color: #db8426;">' . sprintf(esc_html__('已用%s次', 'zib_language'), $coupon_data['used_count']) . '</sapn>';
                    foreach ($coupon_data['used_order_num'] as $i => $order_num) {
                        $order_link_url = zibpay_get_admin_shop_order_url('search=' . $order_num);
                        $status_html .= ($i === 0 ? '<br>' : '') . '<a target="_blank" href="' . $order_link_url . '">[' . ($i + 1) . ']</a> ';
                    }
                } else {
                    $status_html .= '<sapn style="color: #3d7ffd;">' . esc_html__('未使用', 'zib_language') . '</sapn>';
                }

                $other_a = '';
                $name_a  = '';
                if ($coupon_data['title']) {
                    $name_a = '<div><a style="color: #269e95;font-weight: bold;" href="' . add_query_arg('s', $coupon_data['title'], $this_url) . '">' . $coupon_data['title'] . '</a></div>';
                }
                if ($msg->other) {
                    $other_a = '<a style=" color:#4f647b " href="' . add_query_arg('other', $msg->other, $this_url) . '">' . $msg->other . '</a>';
                }

                if ($msg->type === 'vip_coupon') {
                    $post_a = esc_html__('购买会员', 'zib_language');

                    $type_html = '<a style="color:#975106" href="' . add_query_arg('type', 'vip_coupon', $this_url) . '">' . esc_html__('购买会员', 'zib_language') . '</a>';
                } else {
                    $post_a    = esc_html__('全部商品', 'zib_language');
                    $type_html = '<a style="color:#289d0f" href="' . add_query_arg('type', 'coupon', $this_url) . '">' . esc_html__('购买商品', 'zib_language') . '</a>';
                }

                $post_a = '<a href="' . $this_url . '&post_id=0">' . esc_html__('全部商品', 'zib_language') . '</a>';
                if ($msg->post_id) {
                    $the_title = get_the_title($msg->post_id);
                    $post_a    = '<div title="' . esc_attr($the_title) . '"  style="overflow: hidden; text-overflow:ellipsis; white-space: nowrap; display: block;" ><a href="' . $this_url . '&post_id=' . $msg->post_id . '">' . $the_title . '</a></div><a style="color: #6e6a6f;" target="_blank" href="' . get_permalink($msg->post_id) . '">[' . esc_html__('查看', 'zib_language') . '] </a>';
                }

                $tbody .= '<tr>';
                $tbody .= '<th scope="row" class="check-column"><label class="screen-reader-text" for="cb-select-232">' . esc_html__('选择', 'zib_language') . '</label>
                <input id="cb-select-232" type="checkbox" name="action_id[]" value="' . $msg->id . '">
                    </th>';
                $tbody .= "<td><a class=\"badg c-blue mr6\" href=\"javascript:;\" data-clipboard-text='$msg->password' data-clipboard-tag='" . esc_attr__('优惠码', 'zib_language') . "'>$msg->password</a><span class=\"badg c-yellow\">$discount_text</span></td>";
                $tbody .= "<td>$post_a</td>";
                // $tbody .= "<td>$type_html</td>";
                $tbody .= "<td>$limit_html</td>"; //限制使用次数
                $tbody .= "<td>$status_html</td>";
                $tbody .= "<td>$msg->create_time</td>";
                $tbody .= "<td>$msg->modified_time</td>";
                $tbody .= "<td>$name_a</td>";
                $tbody .= "<td>$other_a</td>";
                $tbody .= '</tr>';
            }
            $table .= '<tbody>' . $tbody . '</tbody>';
        }

        $search = '<form class="form-inline form-order" method="post">
                    <div class="form-group" style="float: right;">
                        <input type="text" class="form-control" name="s" placeholder="' . esc_attr__('搜索优惠码', 'zib_language') . '">
                        <button type="submit" class="button">' . esc_html__('提交', 'zib_language') . '</button>
                    </div>
                </form>';

        break;
}

?>


<div class="wrap">
    <style>
        .orderby-but {
            position: relative;
        }

        .orderby-but>a {
            opacity: .4;
            position: absolute;
            transform: translateY(-3px);
            transition: .3s;
        }

        .orderby-but>a+a {
            transform: translateY(6px);
        }

        .orderby-but:hover a {
            opacity: .6;
        }

        .orderby-but>a:hover {
            opacity: 1;
        }
    </style>
    <h1 class="wp-heading-inline"><?php echo $page_title; ?></h1>
    <?php echo $head_but; ?>
    <?php
$but_html = '';
if ($sub_but) {
    foreach ($sub_but as $but) {
        $but_html .= '<li><a href="' . $but['href'] . '">' . $but['name'] . '</a></li> | ';
    }
}

echo '<div class="order-header"><ul class="subsubsub">' . substr($but_html, 0, -2) . '</ul>' . $search . '</div>';

if ($table) {

    echo '<div class="clear"></div>';
    echo '<form class="" method="post">';
    echo '<div class="bulkactions" style="margin: 10px 0;">
			<label for="bulk-action-selector-top" class="screen-reader-text">' . esc_html__('选择批量操作', 'zib_language') . '</label><select name="action" id="bulk-action-selector-top">
                <option value="-1">' . esc_html__('批量操作', 'zib_language') . '</option>
                    <option value="delete">' . esc_html__('删除', 'zib_language') . '</option>
                </select>
                <input type="submit" class="button action" value="' . esc_attr__('应用', 'zib_language') . '">
		</div>';

    echo '<div style="overflow-y: auto;width: 100%;">';
    echo '<table class="widefat fixed striped posts table table-bordered" style="min-width: 1000px;">';
    echo $table;
    echo '</table>';
    echo '</div>';
    echo '</form>';
    echo '<div class="clear"></div>';

} elseif ($csf_args) {
    ZCSF::instance('add_msg', $csf_args);
}
if ($page_html) {
    echo $page_html;
}
if ($pagenavi) {
    zibpay_admin_pagenavi($count_all, $ice_perpage);
}

?>


</div>