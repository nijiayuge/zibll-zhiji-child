<?php
/*
 * @Author        : Qinver
 * @Url           : zibll.com
 * @Date          : 2020-11-01 17:08:02
 * @LastEditTime : 2026-06-22 22:48:38
 * @Email         : 770349780@qq.com
 * @Project       : Zibll子比主题
 * @Description   : 一款极其优雅的Wordpress主题->后台提现管理模板
 * @Read me       : 感谢您使用子比主题，主题源码有详细的注释，支持二次开发。
 * @Remind        : 使用盗版主题会存在各种未知风险。支持正版，从我做起！
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!is_super_admin()) {
    wp_die(__('您不能访问此页面', 'zib_language'), __('权限不足', 'zib_language'));
    exit;
}


$user_Info = wp_get_current_user();
if (!is_user_logged_in()) {
    exit;
}
$action = !empty($_REQUEST['action']) ? $_REQUEST['action'] : false;

if ('process_submit' == $action) {
    $is_allow = ($_REQUEST['process'] == 1);
    $payout_args = array();
    if ($is_allow && !empty($_REQUEST['payout_method']) && $_REQUEST['payout_method'] === 'api') {
        $payout_args = array(
            'method'  => 'api',
            'channel' => !empty($_REQUEST['payout_channel']) ? sanitize_text_field($_REQUEST['payout_channel']) : '',
        );
    }
    if (zibpay_withdraw_process($_REQUEST['process_id'], $is_allow, $_REQUEST['msg'], $payout_args)) {
        $process_action = $is_allow ? __('批准', 'zib_language') : __('拒绝', 'zib_language');
        echo '<div class="updated notice-alt"><h4 style="color: #0aaf19;">' . sprintf(esc_html__('提现处理成功，已%s该提现申请', 'zib_language'), esc_html($process_action)) . '</h4></div>';
    } else {
        echo '<div class="updated notice-alt"><h4 style="color: #ed2273;">' . esc_html__('提现处理失败', 'zib_language') . '</h4>';
        if ($is_allow && !empty($payout_args['method']) && $payout_args['method'] === 'api') {
            $withdraw_row = (array) ZibMsg::get_row(array('id' => (int) $_REQUEST['process_id'], 'type' => 'withdraw'));
            if (!empty($withdraw_row['meta']['api_payout']['msg'])) {
                echo '<p>' . esc_html($withdraw_row['meta']['api_payout']['msg']) . '</p>';
            }
        }
        echo '</div>';
    }
}

//准备参数
$page_url = add_query_arg('page', 'zibpay_withdraw', admin_url('admin.php'));
$s        = !empty($_REQUEST['s']) ? $_REQUEST['s'] : false;
if ($s) {
    $s = sanitize_text_field(wp_unslash($s));

    if ($action && !empty($_REQUEST['id'])) {
        $s = '';
    }
}

$WHERE = array('type' => 'withdraw');

//状态
if (isset($_REQUEST['status'])) {
    $WHERE['status'] = (int) $_REQUEST['status'];
}
//用户
if (isset($_REQUEST['send_user'])) {
    $WHERE['send_user'] = (int) $_REQUEST['send_user'];
}
//id
if (isset($_REQUEST['id'])) {
    $WHERE['id'] = (int) $_REQUEST['id'];
}

//搜索
if ($s) {
    $WHERE = "
    `type` = 'withdraw' and (
    `title` LIKE '%$s%' OR
    `content` LIKE '%$s%' OR
    `meta` LIKE '%$s%')";

    $page_url = $page_url . '&amp;s=' . $s;
}

global $wpdb;
//统计数据
$all_count = ZibMsg::get_count($WHERE);

//分页计算
$ice_perpage = 20;
$pages       = ceil($all_count / $ice_perpage);
$page        = isset($_REQUEST['paged']) ? intval($_REQUEST['paged']) : 1;
$offset      = $ice_perpage * ($page - 1);
//排序
$order = !empty($_REQUEST['orderby']) ? $_REQUEST['orderby'] : 'id';
$desc  = !empty($_REQUEST['desc']) ? $_REQUEST['desc'] : 'DESC';

$list = ZibMsg::get($WHERE, $order, $offset, $ice_perpage, $desc);

//echo json_encode($list);
?>
<style>
    .table-box>table {
        min-width: 1000px;
    }
</style>

<div class="wrap">
    <h2><?php echo esc_html__('提现管理', 'zib_language'); ?></h2>
    <?php
    echo $s ? '<div>"' . esc_attr($s) . '" ' . esc_html__('的搜索结果', 'zib_language') . '</div>' : '';
    ?>
    <div class="order-header">
        <ul class="subsubsub">
            <li class="all"><a href="<?php echo $page_url; ?>"><?php echo esc_html__('全部', 'zib_language'); ?></a> |</li>
            <li class="all"><a href="<?php echo add_query_arg('status', '0', $page_url); ?>"><?php echo esc_html__('待处理', 'zib_language'); ?></a> |</li>
            <li class="all"><a href="<?php echo add_query_arg('status', '1', $page_url); ?>"><?php echo esc_html__('已批准', 'zib_language'); ?></a> |</li>
            <li class="all"><a href="<?php echo add_query_arg('status', '2', $page_url); ?>"><?php echo esc_html__('已拒绝', 'zib_language'); ?></a></li>
        </ul>
        <form class="form-inline form-order" method="post" action="<?php echo $page_url; ?>" style="margin: 6px 0 12px;float: right;">
            <div class="form-group">
                <input type="text" class="form-control" name="s" placeholder="<?php echo esc_attr__('搜索记录', 'zib_language'); ?>">
                <button type="submit" class="button button-primary"><?php echo esc_html__('提交', 'zib_language'); ?></button>
            </div>
        </form>
    </div>

    <div class="table-box" style="overflow-y: auto;width: 100%;">
        <table class="widefat fixed striped posts" style="min-width: 1000px;">
            <thead>
                <tr>
                    <?php
                    $theads   = array();
                    $theads[] = array('width' => '8%', 'orderby' => 'status', 'name' => __('状态', 'zib_language'));
                    $theads[] = array('width' => '10%', 'orderby' => 'send_user', 'name' => __('申请用户', 'zib_language'));
                    $theads[] = array('width' => '10%', 'orderby' => '', 'name' => __('提现金额', 'zib_language'));
                    $theads[] = array('width' => '10%', 'orderby' => '', 'name' => __('提现详情', 'zib_language'));
                    $theads[] = array('width' => '20%', 'orderby' => '', 'name' => __('说明', 'zib_language'));
                    $theads[] = array('width' => '7%', 'orderby' => 'create_time', 'name' => __('申请时间', 'zib_language'));
                    $theads[] = array('width' => '7%', 'orderby' => 'modified_time', 'name' => __('更新时间', 'zib_language'));

                    foreach ($theads as $thead) {
                        $orderby = '';
                        if ($thead['orderby']) {
                            $orderby_url = add_query_arg('orderby', $thead['orderby'], $page_url);
                            $orderby .= '<a title="' . esc_attr__('降序', 'zib_language') . '" href="' . add_query_arg('desc', 'ASC', $orderby_url) . '"><span class="dashicons dashicons-arrow-up"></span></a>';
                            $orderby .= '<a title="' . esc_attr__('升序', 'zib_language') . '" href="' . add_query_arg('desc', 'DESC', $orderby_url) . '"><span class="dashicons dashicons-arrow-down"></span></a>';
                            $orderby = '<span class="orderby-but">' . $orderby . '</span>';
                        }
                        echo '<th class="" width="' . $thead['width'] . '">' . $thead['name'] . $orderby . '</th>';
                    } ?>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($list) {
                    $ii = 1;
                    foreach ($list as $value) {
                        //整理数据
                        $user_data = get_userdata((int) $value->send_user);
                        $user_name = $user_data->display_name;
                        $user_name = '<a href="' . add_query_arg('send_user', (int) $value->send_user, $page_url) . '">' . $user_name . '</a>';

                        $user_name .= '<div class="row-actions">';
                        $user_name .= '<a href="' . get_edit_user_link((int) $value->send_user) . '">' . esc_html__('管理', 'zib_language') . '</a> | ';
                        $user_name .= '<a href="' . zib_get_user_home_url((int) $value->send_user) . '">' . esc_html__('查看', 'zib_language') . '</a>';
                        $user_name .= '</div>';

                        $meta             = (array) maybe_unserialize($value->meta);
                        $withdraw_message = !empty($meta['withdraw_message']) ? esc_html__('用户留言：', 'zib_language') . esc_attr($meta['withdraw_message']) : '';
                        $withdraw_message .= !empty($meta['admin_message']) ? '<div style="color: #3437f7;">' . esc_html__('处理留言：', 'zib_language') . esc_attr($meta['admin_message']) . '</div>' : '';
                        $__withdraw_price = $meta['withdraw_price'];
                        $__service_price  = isset($meta['service_price']) ? $meta['service_price'] : 0;

                        $__rebate_sum  = isset($meta['withdraw_detail']['rebate']) ? $meta['withdraw_detail']['rebate'] : 0;
                        $__income_sum  = isset($meta['withdraw_detail']['income']) ? $meta['withdraw_detail']['income'] : 0;
                        $__balance_sum = isset($meta['withdraw_detail']['balance']) ? $meta['withdraw_detail']['balance'] : 0;

                        $withdraw_price_count = '<b style="color: #4066fb;font-weight: bold;font-size:1.2em;">' . zibpay_format_local_price($__withdraw_price) . '</b>' . ($__service_price > 0 ? '<div style="font-size: 12px;opacity: .8;">' . sprintf(esc_html__('手续费%s', 'zib_language'), zibpay_format_local_price($__service_price) ) . '</div>' : '');
                        $withdraw_details     = '<div style="font-size: 12px;">' . ($__rebate_sum ? sprintf(esc_html__('推广佣金%s', 'zib_language'), zibpay_format_local_price($__rebate_sum)) . '<br>' : '') . ($__income_sum ? sprintf(esc_html__('创作分成%s', 'zib_language'), zibpay_format_local_price($__income_sum)) . '<br>' : '') . ($__balance_sum > 0 ? sprintf(esc_html__('余额%s', 'zib_language'), zibpay_format_local_price($__balance_sum)) : '') . ($__balance_sum < 0 ? '<div style="color: #e8720a;">' . sprintf(esc_html__('其中%s转入余额', 'zib_language'), zibpay_format_local_price(abs($__balance_sum))) . '</div>' : '') . '</div>';

                        $status     = $value->status;
                        $status_but = $status;
                        if (1 == $status) {
                            $status_but = '<span style=" color: #0989fd; ">' . esc_html__('处理完成', 'zib_language') . '</span>';
                        } elseif (2 == $status) {
                            $status_but = '<span style=" color: #fb4444; ">' . esc_html__('已拒绝', 'zib_language') . '</span>';
                        } elseif (0 == $status) {
                            $status_but = '<a class="button" href="' . add_query_arg(array('action' => 'process', 'id' => $value->id), $page_url) . '">' . esc_html__('立即处理', 'zib_language') . '</a>';
                        }
                        if ('process' == $action && $WHERE['id'] == $value->id) {
                            $status_but = '<span style=" color: #fb4444; ">' . esc_html__('正在处理', 'zib_language') . '</span>';
                        }
                        echo "<tr>\n";
                        echo "<td>$status_but</td>\n";
                        echo "<td>$user_name</td>\n";
                        echo "<td>$withdraw_price_count</td>\n";
                        echo "<td>$withdraw_details</td>\n";
                        echo "<td><div style=\"max-height:39px;overflow:hidden;\">$withdraw_message</div></td>\n";
                        echo "<td>$value->create_time</td>\n";
                        echo "<td>$value->modified_time</td>\n";

                        echo '</tr>';
                        $ii++;
                        // 构建处理函数
                        if ('process' == $action && $WHERE['id'] == $value->id) {

                            $rewards_img_urls = zib_get_user_rewards_img_urls($value->send_user, 'full');
                            $weixin           = $rewards_img_urls['weixin'];
                            $alipay           = $rewards_img_urls['alipay'];

                            $weixin_img = $weixin ? '<span style="display: inline-block; text-align: center;margin-right: 20px; "><img style="max-height: 240px;max-width: 300px;vertical-align: top;" src="' . $weixin . '" alt="' . esc_attr__('微信收款码', 'zib_language') . '"><p>' . esc_html__('微信收款码', 'zib_language') . '</p></span>' : '';
                            $alipay_img = $alipay ? '<span style="display: inline-block; text-align: center; "><img style="max-height: 240px;max-width: 300px;vertical-align: top;" src="' . $alipay . '" alt="' . esc_attr__('支付宝收款码', 'zib_language') . '"><p>' . esc_html__('支付宝收款码', 'zib_language') . '</p></span>' : '';

                            $html_args   = array();
                            $html_args[] = array(
                                'title' => __('提现金额', 'zib_language'),
                                'con'   => zibpay_format_local_price($__withdraw_price) . zibpay_get_currency_unit(),
                            );

                            $html_args[] = array(
                                'title' => __('支付金额', 'zib_language'),
                                'con'   => ($__service_price > 0 ? '<div style="color: #fb4040;font-weight: bold;font-size:1.5em;">' . zibpay_format_local_price($__withdraw_price - $__service_price) . zibpay_get_currency_unit() . '</div><div>' . sprintf(esc_html__('已扣除手续费%s', 'zib_language'), zibpay_format_local_price($__service_price)) . '</div>' : '<div style="color: #4066fb;font-weight: bold;font-size:1.2em;">' . zibpay_format_local_price($__withdraw_price) . zibpay_get_currency_unit() . '</div>'),
                            );

                            $withdraw_orders      = !empty($meta['withdraw_orders']) ? $meta['withdraw_orders'] : '';
                            $withdraw_order_links = '';

                            if (!empty($withdraw_orders['rebate'])) {
                                $order_link_url = add_query_arg('page', 'zibpay_rebate_page', admin_url('admin.php')); //前缀
                                $ids            = is_array($withdraw_orders['rebate']) ? implode(',', $withdraw_orders['rebate']) : $withdraw_orders['rebate'];
                                $order_db       = $wpdb->get_results("SELECT id,order_num FROM {$wpdb->zibpay_order} WHERE id IN ($ids)");
                                if ($order_db) {
                                    $withdraw_order_links .= esc_html__('佣金订单：', 'zib_language');
                                    foreach ($order_db as $order_v) {
                                        $withdraw_order_links .= '[<a target="_blank" href="' . add_query_arg('s', $order_v->order_num, $order_link_url) . '">' . $order_v->id . '</a>] ';
                                    }
                                }
                            }
                            
                            if (!empty($withdraw_orders['income'])) {
                                $order_link_url = add_query_arg('page', 'zibpay_income_page', admin_url('admin.php')); //前缀
                                $ids            = is_array($withdraw_orders['income']) ? implode(',', $withdraw_orders['income']) : $withdraw_orders['income'];
                                $order_db       = $wpdb->get_results("SELECT id,order_num FROM {$wpdb->zibpay_order} WHERE id IN ($ids)");
                                if ($order_db) {
                                    $withdraw_order_links .= '<br>' . esc_html__('分成订单：', 'zib_language');
                                    foreach ($order_db as $order_v) {
                                        $withdraw_order_links .= '[<a target="_blank" href="' . add_query_arg('s', $order_v->order_num, $order_link_url) . '">' . $order_v->id . '</a>] ';
                                    }
                                }
                            }

                            if ($withdraw_order_links) {
                                $html_args[] = array(
                                    'title' => __('提现订单', 'zib_language'),
                                    'con'   => $withdraw_order_links,
                                );
                            }
                            if ($withdraw_message) {
                                $html_args[] = array(
                                    'title' => __('用户留言', 'zib_language'),
                                    'con'   => $withdraw_message,
                                );
                            }
                            $html_args[] = array(
                                'title' => __('收款码', 'zib_language'),
                                'con'   => $weixin_img . $alipay_img . '<p class="description">' . esc_html__('请注意本地货币与付款货币是否一致,如果不一致，扫码付款时请自行按您设置的汇率自行转换。', 'zib_language') . '</p>',
                            );

                            $payout_accounts_html = '';
                            foreach (zibpay_payout_get_ready_channels() as $_payout_channel) {
                                $_account = zibpay_get_user_payout_account($value->send_user, $_payout_channel);
                                if ($_account) {
                                    $payout_accounts_html .= '<div class="copy-text mb6" data-clipboard-text="' . esc_attr($_account) . '"><b>' . esc_html(zibpay_payout_get_channel_name($_payout_channel)) . '</b>：' . esc_html($_account) . '</div>';
                                    if (zibpay_payout_need_rate_hint($_payout_channel)) {
                                        $payout_accounts_html .= '<div class="badg badg-sm c-yellow mb6">' . __('汇率', 'zib_language') . ' ' . esc_html(zibpay_payout_get_rate_rule_text($_payout_channel)) . '</div>';
                                    }
                                }
                            }

                            $rate_rule = '';
                            $weixin_rate_rule = zibpay_payout_get_rate_rule_text('wechat');
                            $alipay_rate_rule = zibpay_payout_get_rate_rule_text('alipay');
                            if ($weixin_rate_rule) {
                                $rate_rule .= '<div class="mb6">' . __('微信汇率', 'zib_language') . ' ' . esc_html($weixin_rate_rule) . '</div>';
                                $rate_rule .= '<div class="mb6" style="color: #4066fb;font-weight: bold;font-size:1.2em;">' . __('微信待付款金额', 'zib_language') . ': ' . zib_floatval_round(($__withdraw_price - $__service_price) * zibpay_payout_get_rate('wechat')) . '</div>';
                            }
                            if ($alipay_rate_rule) {
                                $rate_rule .= '<div class="mb6">' . __('支付宝汇率', 'zib_language') . ' ' . esc_html($alipay_rate_rule) . '</div>';
                                $rate_rule .= '<div class="mb6" style="color: #4066fb;font-weight: bold;font-size:1.2em;">' . __('支付宝待付款金额', 'zib_language') . ': ' . zib_floatval_round(($__withdraw_price - $__service_price) * zibpay_payout_get_rate('alipay')) . '</div>';
                            }

                            if ($rate_rule) {
                                $html_args[] = array(
                                    'title' => __('汇率提示', 'zib_language'),
                                    'con'   => $rate_rule,
                                );
                            }
                            
                            if ($payout_accounts_html) {
                                $html_args[] = array(
                                    'title' => __('收款账户', 'zib_language'),
                                    'con'   => $payout_accounts_html,
                                );
                            }

                            $pay_amount = $__service_price > 0 ? zib_floatval_round($__withdraw_price - $__service_price) : $__withdraw_price;
                            $payout_form = zibpay_withdraw_admin_process_form($value->send_user, $pay_amount);
                            if ($payout_form) {
                                $html_args[] = array(
                                    'title' => __('API 打款', 'zib_language'),
                                    'con'   => $payout_form,
                                );
                            }

                            $html_args[] = array(
                                'title' => __('处理留言', 'zib_language'),
                                'con'   => '<input style=" width: 95%; max-width: 500px; " name="msg" type="text" value="" placeholder="' . esc_attr__('给用户留言', 'zib_language') . '"><p class="description">' . esc_html__('如需给用户留言请填写此处，如果拒绝提现请填写拒绝原因', 'zib_language') . '</p>',
                            );

                            $process = '';
                            if ($payout_form) {
                                $process .= '<p class="description">' . esc_html__('选择 API 自动打款时，系统将调用官方接口向用户账户付款；失败则不会完成提现。', 'zib_language') . '</p>';
                            } else {
                                $process .= '<p class="description">' . esc_html__('如批准此申请，请通过收款码付款后，选择已付款并提交。', 'zib_language') . '</p>';
                            }
                            $process .= '<p><input type="radio" name="process" id="process_1" value="1" checked="checked"><label for="process_1" style=" color: #036ee2; ">' . esc_html__('批准提现', 'zib_language') . '</label></p>';
                            $process .= '<p><input type="radio" name="process" id="process_2" value="2"><label for="process_2" style=" color:#eb1b65; ">' . esc_html__('拒绝提现', 'zib_language') . '</label></p>';
                            $process .= '<p class="description">' . esc_html__('如拒绝此申请，建议给用户留言告知原因，用户可在用户中心重新申请', 'zib_language') . '</p>';
                            $process .= '<input name="process_id" type="hidden" value="' . esc_attr($value->id) . '">';
                            $process .= '<input name="action" type="hidden" value="process_submit">';
                            $html_args[] = array(
                                'title' => '',
                                'con'   => $process,
                            );
                            $html_args[] = array(
                                'title' => '',
                                'con'   => '<p><button type="submit" class="button button-primary process-submit">' . esc_html__('确认提交', 'zib_language') . '</button></p>',
                            );
                            $html = '';

                            foreach ($html_args as $html_arg) {
                                $html .= '<tr>';
                                $html .= '<th>' . $html_arg['title'] . '</th>';
                                $html .= '<td>';
                                $html .= $html_arg['con'];
                                $html .= '</td>';
                                $html .= '</tr>';
                            }
                            echo '<form action="' . add_query_arg('page', 'zibpay_withdraw', admin_url('admin.php')) . '" method="post"><table class="form-table"><tbody>' . $html . '</tbody></table></form>';
                        }
                    }
                } else {
                    echo '<tr><td colspan="7" align="center"><strong>' . esc_html__('暂无提现记录', 'zib_language') . '</strong></td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>

    <?php
    if (!empty($list[0]) && 'process' == $action) { ?>



    <?php } ?>
    <?php echo zibpay_admin_pagenavi($all_count, $ice_perpage); ?>
</div>