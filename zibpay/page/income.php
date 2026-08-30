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

$order_url = admin_url('admin.php?page=zibpay_income_page');
$desc_url  = $order_url;
$s         = !empty($_POST['s']) ? $_POST['s'] : (!empty($_GET['s']) ? $_GET['s'] : false);
$s         = esc_sql($s);
$WHERE     = '';

if ($s) {
     $WHERE = "WHERE
     `pay_num` LIKE '%$s%' OR
     `order_num` LIKE '%$s%' OR
     `other` LIKE '%$s%' OR
     `user_id` LIKE '%$s%' OR
     `post_id` LIKE '%$s%'";
     $desc_url = $order_url . '&amp;s=' . $s;
} else {
}

$WHERE_status = !empty($_GET['status']) ? (int) $_GET['status'] : false;
if ($WHERE_status) {
     $WHERE = "WHERE
     `status` = $WHERE_status";
     $desc_url = $order_url . '&amp;status=' . $WHERE_status;
}
$WHERE_order_type = !empty($_GET['order_type']) ? esc_sql($_GET['order_type']) : false;
if ($WHERE_order_type) {
     $WHERE = "WHERE
     `order_type` = $WHERE_order_type";
     $desc_url = $order_url . '&amp;order_type=' . $WHERE_order_type;
}

if (isset($_GET['income_status'])) {
     $income_status = (int) $_GET['income_status'];
     $WHERE         = $WHERE ? $WHERE . " and `income_status` = $income_status" : "WHERE `income_status` = $income_status";
}

if (isset($_GET['post_author'])) {
     $post_author = (int) $_GET['post_author'];
     $WHERE       = $WHERE ?: 'WHERE 1=1';
     $WHERE .= " and `post_author` = $post_author";
}

if (isset($_GET['referrer_id'])) {
     $post_author = (int) $_GET['referrer_id'];
     $WHERE       = $WHERE ?: 'WHERE 1=1';
     $WHERE .= " and `referrer_id` = $referrer_id";
}

if (isset($_GET['user_id'])) {
     $user_id = (int) $_GET['user_id'];
     $WHERE   = $WHERE ?: 'WHERE 1=1';
     $WHERE .= " and `referrer_id` = $user_id";
}

//////////
global $wpdb;
$WHERE = $WHERE ? $WHERE . ' and (`income_price` > 0 or `income_detail` like \'%points%\' ) and `status` = 1' : 'WHERE (`income_price` > 0 or `income_detail` like \'%points%\' ) and `status` = 1';

//统计数据
$total_trade = $wpdb->get_var("SELECT COUNT(id) FROM $wpdb->zibpay_order $WHERE");

//分页计算
$ice_perpage = 20;
$pages       = ceil($total_trade / $ice_perpage);
$page        = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
$offset      = $ice_perpage * ($page - 1);
$order       = !empty($_GET['orderby']) ? $_GET['orderby'] : 'pay_time';
$desc        = !empty($_GET['desc']) ? $_GET['desc'] : 'DESC';

$list = $wpdb->get_results("SELECT * FROM $wpdb->zibpay_order $WHERE order by $order $desc limit $offset,$ice_perpage");

//echo  json_encode($list);
//echo "SELECT * FROM $wpdb->zibpay_order $WHERE order by $order $desc limit $offset,$ice_perpage";

$all_c   = $wpdb->get_var("SELECT COUNT(id) FROM $wpdb->zibpay_order WHERE `income_price` > 0 and `status` = 1");
$all_1_c = $wpdb->get_var("SELECT COUNT(id) FROM $wpdb->zibpay_order WHERE `income_price` > 0 and `order_type` = 1 and `status` = 1");
$all_2_c = $wpdb->get_var("SELECT COUNT(id) FROM $wpdb->zibpay_order WHERE `income_price` > 0 and `order_type` = 2 and `status` = 1");
$all_4_c = $wpdb->get_var("SELECT COUNT(id) FROM $wpdb->zibpay_order WHERE `income_price` > 0 and `order_type` = 4 and `status` = 1");
$all_5_c = $wpdb->get_var("SELECT COUNT(id) FROM $wpdb->zibpay_order WHERE `income_price` > 0 and `status` = 1 and `income_status` = 0");
$all_6_c = $wpdb->get_var("SELECT COUNT(id) FROM $wpdb->zibpay_order WHERE `income_price` > 0 and `status` = 1 and `income_status` = 1");

?>
<div class="wrap">
     <h2><?php echo esc_html__('全部订单', 'zib_language'); ?></h2>
     <?php echo $s ? '<div class="order-header">"' . esc_attr($s) . '" ' . esc_html__('的搜索结果', 'zib_language') . '</div>' : ''; ?>
     <div class="order-header">
          <ul class="subsubsub">
               <li class=""><a class="" href="<?php echo $order_url; ?>"><?php echo esc_html__('全部订单', 'zib_language'); ?></a>(<?php echo $all_c ?>)</li> |
               <li class=""><a class="" href="<?php echo $order_url . '&amp;order_type=1'; ?>"><?php echo esc_html__('付费阅读', 'zib_language'); ?></a>(<?php echo $all_1_c ?>)</li> |
               <li class=""><a class="" href="<?php echo $order_url . '&amp;order_type=2'; ?>"><?php echo esc_html__('付费资源', 'zib_language'); ?></a>(<?php echo $all_2_c ?>)</li> |
               <li class=""><a class="" href="<?php echo $order_url . '&amp;order_type=4'; ?>"><?php echo esc_html__('购买会员', 'zib_language'); ?></a>(<?php echo $all_4_c ?>)</li> |
               <li class=""><a class="" href="<?php echo $order_url . '&amp;income_status=0'; ?>"><?php echo esc_html__('未提现', 'zib_language'); ?></a>(<?php echo $all_5_c ?>)</li> |
               <li class=""><a class="" href="<?php echo $order_url . '&amp;income_status=1'; ?>"><?php echo esc_html__('已提现', 'zib_language'); ?></a>(<?php echo $all_6_c ?>)</li>
          </ul>

          <form class="form-inline form-order" method="post" action="<?php echo $order_url; ?>" style="margin: 6px 0 12px;float: right;">
               <div class="form-group">
                    <input type="text" class="form-control" name="s" placeholder="<?php echo esc_attr__('搜索订单', 'zib_language'); ?>">
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
                         $theads[] = array('width' => '8%', 'orderby' => 'order_num', 'name' => __('订单号', 'zib_language'));
                         $theads[] = array('width' => '4%', 'orderby' => 'order_price', 'name' => __('订单金额', 'zib_language'));
                         $theads[] = array('width' => '6%', 'orderby' => 'order_type', 'name' => __('订单类型', 'zib_language'));
                         $theads[] = array('width' => '9%', 'orderby' => 'pay_time', 'name' => __('订单时间', 'zib_language'));

                         $theads[] = array('width' => '6%', 'orderby' => 'user_id', 'name' => __('购买用户', 'zib_language'));
                         $theads[] = array('width' => '6%', 'orderby' => 'post_author', 'name' => __('分成作者', 'zib_language'));
                         $theads[] = array('width' => '4%', 'orderby' => 'income_price', 'name' => __('分成金额', 'zib_language'));
                         $theads[] = array('width' => '5%', 'orderby' => 'income_status', 'name' => __('分成提现状态', 'zib_language'));
                         $theads[] = array('width' => '5%', 'orderby' => '', 'name' => __('提现详情', 'zib_language'));

                         foreach ($theads as $thead) {
                              $orderby = '';
                              if ($thead['orderby']) {
                                   $orderby_url = add_query_arg('orderby', $thead['orderby'], $desc_url);
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

                              $edit   = '<a class="" onclick="return confirm(\'' . esc_js(__('确认删除此内容?  删除后数据不可恢复!', 'zib_language')) . '\')" href="' . $order_url . '&amp;delete=' . $value->order_num . '">' . esc_html__('删除', 'zib_language') . '</a>';
                              $status = $value->status ? esc_html__('已支付', 'zib_language') : esc_html__('未支付', 'zib_language');

                              $order_type  = zibpay_get_pay_type_name($value->order_type);
                              $user_a      = $value->user_id ? '<a target="_blank" href="' . zib_get_user_home_url($value->user_id) . '">' . get_the_author_meta('display_name', $value->user_id) . '</a>' : esc_html__('未登录购买', 'zib_language');
                              $order_num_a = '<a href="' . zibpay_get_admin_shop_order_url($value->id) . '">' . $value->order_num . '</a>';

                              $post_author_name = get_the_author_meta('display_name', $value->post_author);
                              $post_author_a    = '<a href="' . admin_url('users.php?s=' . $post_author_name) . '">' . $post_author_name . '</a>';

                              $income_status = '<span style="color: #3d7ffd;">' . esc_html__('未提现', 'zib_language') . '</span>';
                              if ($value->income_status == 1) {
                                   $income_status = '<span style="color: #f93b3b;">' . esc_html__('已提现', 'zib_language') . '</span>';
                              }

                              if ($value->income_status == 3) {
                                   $income_status = '<span style="color: #e8720a;">' . esc_html__('提现待处理', 'zib_language') . '</span>';
                              }

                              $withdraw      = '';
                              $income_detail = maybe_unserialize($value->income_detail);
                              $order_price   = zibpay_format_local_price($value->order_price);
                              if (isset($income_detail['withdraw_id'])) {
                                   $withdraw      = esc_html__('提现时间：', 'zib_language') . '<br>' . $income_detail['withdraw_time'];
                                   $income_status = '<a href="' . add_query_arg(['page' => 'zibpay_withdraw', 'id' => $income_detail['withdraw_id']], admin_url('admin.php')) . '">' . $income_status . '</a>';
                              }

                              if ($value->pay_type === 'points') {
                                   $order_price     = zibpay_get_order_pay_points((array) $value) . __('积分', 'zib_language');
                                   $income_val      = zibpay_get_order_income_points($value);
                                   $income_val_text = $income_val . __('积分', 'zib_language');
                                   $income_status   = esc_html__('已转入', 'zib_language');
                              } else {
                                   $income_val      = $value->income_price;
                                   $income_val_text = zibpay_format_local_price($income_val) . zibpay_get_currency_unit();
                              }

                              echo "<tr>\n";
                              echo "<td>$order_num_a</td>\n";

                              //echo "<td>$value->ip_address</td>\n";
                              echo "<td>$order_price</td>\n";
                              echo "<td>$order_type</td>\n";
                              echo "<td>$value->pay_time</td>\n";
                              echo "<td>$user_a</td>\n";

                              echo "<td>$post_author_a</td>\n";
                              echo "<td>$income_val_text</td>\n";
                              echo "<td>$income_status</td>\n";
                              echo "<td>$withdraw</td>\n";

                              echo '</tr>';
                              $ii++;
                         }
                    } else {
                         echo '<tr><td colspan="8" align="center"><strong>' . esc_html__('暂无订单', 'zib_language') . '</strong></td></tr>';
                    }
                    ?>
               </tbody>
          </table>
     </div>
     <?php echo zibpay_admin_pagenavi($total_trade, $ice_perpage); ?>

</div>