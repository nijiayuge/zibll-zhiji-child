<?php
/*
订单中心
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

$order_url = admin_url('admin.php?page=zibpay_product_page');
$desc_url  = $order_url;

$WHERE            = '';
$WHERE_order_type = !empty($_GET['order_type']) ? $_GET['order_type'] : false;
if ($WHERE_order_type) {
    $WHERE = "WHERE
     `order_type` = $WHERE_order_type and `post_id` > 0";
    $desc_url = $order_url . '&order_type=' . $WHERE_order_type;
}

global $wpdb;
//统计数据
$total_trade = COUNT($wpdb->get_col("SELECT distinct post_id FROM $wpdb->zibpay_order $WHERE"));
//分页计算
$ice_perpage = 20;
$pages       = ceil($total_trade / $ice_perpage);
$page        = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
$offset      = $ice_perpage * ($page - 1);

$db_post_id = $wpdb->get_col("SELECT distinct post_id FROM $wpdb->zibpay_order $WHERE limit $offset,$ice_perpage");

$list = array();
if ($db_post_id) {
    foreach ($db_post_id as $pid) {
        $list[] = $wpdb->get_row("SELECT SUM(count),AVG(order_price),SUM(pay_price),post_id,order_type FROM $wpdb->zibpay_order WHERE post_id = $pid");
    }
}

$all_c = COUNT($wpdb->get_col("SELECT distinct post_id FROM $wpdb->zibpay_order"));
$o1_c  = COUNT($wpdb->get_col("SELECT distinct post_id FROM $wpdb->zibpay_order WHERE `order_type` =1"));
$o2_c  = COUNT($wpdb->get_col("SELECT distinct post_id FROM $wpdb->zibpay_order WHERE `order_type` =2"));

?>
<div class="wrap">
    <h2><?php echo esc_html__('商品明细', 'zib_language'); ?></h2>
    <div class="notice notice-warning is-dismissible"> <p><?php echo esc_html__('注意：当前页面为旧版统计，未来会删除', 'zib_language'); ?></p></div>
    <div class="order-header">
        <ul class="subsubsub">
            <li class=""><a class="" href="<?php echo $order_url; ?>"><?php echo esc_html__('全部订单', 'zib_language'); ?></a>(<?php echo $all_c ?>)</li> |
            <li class=""><a class="" href="<?php echo $order_url . '&order_type=1'; ?>"><?php echo esc_html__('付费阅读', 'zib_language'); ?></a>(<?php echo $o1_c ?>)</li> |
            <li class=""><a class="" href="<?php echo $order_url . '&order_type=2'; ?>"><?php echo esc_html__('付费资源', 'zib_language'); ?></a>(<?php echo $o2_c ?>)</li>
        </ul>
    </div>

    <div class="table-box" style="overflow-y: auto;width: 100%;">
        <table class="widefat fixed striped posts" style="min-width: 1000px;">
            <thead>
                <tr>
                    <?php

                    $theads   = array();
                    $theads[] = array('width' => '30%', 'type' => 'post_id', 'name' => __('商品', 'zib_language'));
                    $theads[] = array('width' => '30%', 'type' => 'user_id', 'name' => __('付费类型', 'zib_language'));
                    $theads[] = array('width' => '20%', 'type' => 'user_id', 'name' => __('销售均价', 'zib_language'));
                    $theads[] = array('width' => '20%', 'type' => 'user_id', 'name' => __('销售量', 'zib_language'));
                    $theads[] = array('width' => '20%', 'type' => 'user_id', 'name' => __('销售总金额', 'zib_language'));

                    foreach ($theads as $thead) {
                        $href     = $desc_url . '&order=' . $thead['type'];
                        $href_acs = $desc_url . '&order=' . $thead['type'] . '&desc=asc';
                        echo '<th width="' . $thead['width'] . '"><b>' . $thead['name'] . '</b></th>';
                    } ?>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($list) {
                    $ii = 1;
                    foreach ($list as $value) {
                        $order_type = zibpay_get_pay_type_name($value->order_type);

                        $link = get_edit_post_link($value->post_id);
                        if ($link) {
                            $order_type .= $value->order_type == 2 ? zibpay_get_paydown_log_admin_link('post', $value->post_id, 'px12', $con = '[' . __('查看下载记录', 'zib_language') . ']') : '';

                            $post_a = '<a style=" overflow: hidden; text-overflow:ellipsis; white-space: nowrap; display: block; " target="_blank" href="' . $link . '">' . get_the_title($value->post_id) . '</a>';
                        } else {
                            $post_a = $order_type;
                        }

                        echo "<tr>\n";
                        $value_a = (array) $value;
                        echo "<td>$post_a</td>\n";
                        echo "<td>$order_type</td>\n";
                        echo '<td>' . ($value_a['AVG(order_price)'] ? zib_floatval_round($value_a['AVG(order_price)']) : 0) . "</td>\n";
                        echo '<td>' . $value_a['SUM(count)'] . "</td>\n";
                        echo '<td>' . ($value_a['SUM(pay_price)'] ? zib_floatval_round($value_a['SUM(pay_price)']) : 0) . "</td>\n";
                        echo '</tr>';
                        $ii++;
                    }
                } else {
                    echo '<tr><td colspan="12" align="center"><strong>' . esc_html__('暂无订单', 'zib_language') . '</strong></td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php echo zibpay_admin_pagenavi($total_trade, $ice_perpage); ?>

</div>