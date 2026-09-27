<?php
/*
 * @Author       : Qinver
 * @Url          : zibll.com
 * @Date         : 2025-04-07 19:37:04
 * @LastEditTime : 2025-11-28 10:59:47
 * @Project      : Zibll子比主题
 * @Description  : 更优雅的Wordpress主题
 * Copyright (c) 2025 by Qinver, All Rights Reserved.
 * @Email        : 770349780@qq.com
 * @Read me      : 感谢您使用子比主题，主题源码有详细的注释，支持二次开发
 * @Remind       : 使用盗版主题会存在各种未知风险。支持正版，从我做起！
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!is_super_admin()) {
    wp_die(__('您不能访问此页面', 'zib_language'), __('权限不足', 'zib_language'));
    exit;
}

//发货列表数据
$vue_data = array(
    'shipping_data' => array(
        'lits_data'                  => array(),
        'total'                      => 0,
        'current_page'               => 1,
        'page_size'                  => 20,
        'order'                      => '',
        'orderby'                    => 'shipping_time',
        'search'                     => '',
        'search_filter'              => '',
        'timefilter'                 => [
            'pay_time' => [],
        ],
        'filter'                     => array(
            'shipping_status' => [],
            'status'          => [],
            'id'              => '',
            'post_id'         => '',
            'user_id'         => '',
            'post_author'     => '',
        ),
        'search_filter_option'       => [
            'user'       => __('用户', 'zib_language'),
            'post'       => __('商品', 'zib_language'),
            'ip_address' => __('IP地址', 'zib_language'),
            'order_num'  => __('订单号', 'zib_language'),
            'pay_num'    => __('支付单号', 'zib_language'),
            'order_data' => __('订单数据', 'zib_language'),
        ],
        //查询通用结束
        'table_option'               => [
            'table_size'      => 'default',
            'table_rows_show' => [
                'user_id',
                'post_id',
                'shipping_time',
                'shipping_info',
                'action',
            ],
        ],
        'table_rows'                 => [
            'user_id'       => __('购买用户', 'zib_language'),
            'post_id'       => __('商品', 'zib_language'),
            'shipping_time' => __('发货时间', 'zib_language'),
            'shipping_info' => __('物流信息', 'zib_language'),
            'action'        => __('查看详情', 'zib_language'),
        ],
        'status_count'               => [
            '0' => 0,
            '1' => 0,
        ],
        'shipping_type_name'         => [
            'auto'    => __('自动发货', 'zib_language'),
            'manual'  => __('手动发货', 'zib_language'),
            'express' => __('快递发货', 'zib_language'),
        ],
        'delivery_type_name'         => [
            'invit_code' => __('邀请码', 'zib_language'),
            'card_pass'  => __('卡密', 'zib_language'),
            'express'    => __('快递', 'zib_language'),
            'no_express' => __('无需物流', 'zib_language'),
            'auto'       => __('自动发货', 'zib_language'),
            'fixed'      => __('虚拟资源', 'zib_language'),
            'opts'       => __('虚拟资源', 'zib_language'),
            'manual'     => __('手动发货', 'zib_language'),
        ],
        'manual_delivery_type'       => [
            'express'    => __('快递', 'zib_language'),
            'no_express' => __('无需物流', 'zib_language'),
        ],
        'shipping_dialog_show'       => false,
        'shipping_dialog_data'       => [],
        'express_companies'          => zib_shop_get_express_companies_data(),

        'details_drawer_show'        => false,
        'details_drawer_data'        => [],

        'selection_data'             => [],

        //批量发货弹窗
        'batch_shipping_dialog_show' => false,
        'batch_shipping_dialog_data' => [
            'express_orders'     => [],
            'manual_orders'     => [],
            'auto_orders'     => [],
            'delivery_content' => '',
            'manual_delivery_type' => 'express',
            'express_number' => '',
            'express_company_name' => '',
            'delivery_remark' => '',
        ],
    ),
);

zibpay_admin_page_vue_data_filter($vue_data);
?>

<!-- 发货列表搜索 -->
<div class="card-box mb10">
    <div class="flex mb6 hh">
        <el-input placeholder="<?php echo esc_attr__('输入关键词搜索', 'zib_language'); ?>" style="width: auto" class="mb6 mr6" clearable v-model="shipping_data.search">
            <template #prepend>
                <el-select style="max-width: 160px" v-model="shipping_data.search_filter" class="mr6 mb6" clearable multiple collapse-tags placeholder="<?php echo esc_attr__('搜索项目', 'zib_language'); ?>">
                    <el-option v-for="(item,index) in shipping_data.search_filter_option" :key="item" :label="item" :value="index">
                        <span class="em09">{{ item }}</span>
                        <span class="px12 float-right opacity5 ml10">{{ index }}</span>
                    </el-option>
                </el-select>
            </template>
        </el-input>
        <el-button class="mb6 mr6" type="primary" @click="dBSearch('shipping')"><?php echo esc_html__('搜索', 'zib_language'); ?></el-button>
    </div>
    <div class="flex mb6 hh">

        <el-select v-model="shipping_data.filter.shipping_status" class="mr6 mb6" clearable multiple collapse-tags placeholder="<?php echo esc_attr__('发货状态', 'zib_language'); ?>">
            <el-option v-for="(item,index) in shipping_status_name" :key="index + 'shipping_status'" :label="item" :value="~~index">
                <span class="em09">{{ item }}</span>
                <span class="px12 float-right opacity5 ml10">{{ index }}</span>
            </el-option>
        </el-select>

        <el-select v-model="shipping_data.filter.status" class="mr6 mb6" clearable multiple collapse-tags placeholder="<?php echo esc_attr__('订单状态', 'zib_language'); ?>">
            <el-option v-for="(item,index) in status_name" :key="index + 'status'" :label="item" :value="~~index">
                <span class="em09">{{ item }}</span>
                <span class="px12 float-right opacity5 ml10">{{ index }}</span>
            </el-option>
        </el-select>

        <el-input clearable class="mr6 mb6" style="width: 98px" v-model="shipping_data.filter.id" placeholder="<?php echo esc_attr__('订单ID', 'zib_language'); ?>" :formatter="(value) => ~~value || ''"></el-input>
        <el-input clearable class="mr6 mb6" style="width: 98px" v-model="shipping_data.filter.post_id" placeholder="<?php echo esc_attr__('商品ID', 'zib_language'); ?>" :formatter="(value) => ~~value || ''"></el-input>
        <el-input clearable class="mr6 mb6" style="width: 98px" v-model="shipping_data.filter.user_id" placeholder="<?php echo esc_attr__('用户ID', 'zib_language'); ?>" :formatter="(value) => ~~value || ''"></el-input>
        <el-input clearable class="mr6 mb6" style="width: 98px" v-model="shipping_data.filter.post_author" placeholder="<?php echo esc_attr__('商家ID', 'zib_language'); ?>" :formatter="(value) => ~~value || ''"></el-input>

        <div style="width: 203px" class="mr6 mb6 flex">
            <el-date-picker v-model="shipping_data.timefilter.create_time" format="YY-MM-DD" :shortcuts="date_shortcuts" type="daterange" range-separator="-" start-placeholder="<?php echo esc_attr__('下单时间', 'zib_language'); ?>" end-placeholder="" unlink-panels></el-date-picker>
        </div>

        <div style="width: 203px" class="mr20 mb6 flex">
            <el-date-picker v-model="shipping_data.timefilter.shipping_time" format="YY-MM-DD" :shortcuts="date_shortcuts" type="daterange" range-separator="-" start-placeholder="<?php echo esc_attr__('发货时间', 'zib_language'); ?>" end-placeholder="" unlink-panels></el-date-picker>
        </div>

        <div class="shrink0 table-right-but mb6 flex">
            <el-button @click="dbFilter('shipping')" type="primary"><?php echo esc_html__('查询', 'zib_language'); ?></el-button>
            <el-button @click="dBRefresh('shipping')"><?php echo esc_html__('重置', 'zib_language'); ?></el-button>
        </div>

    </div>
</div>

<!-- 发货列表表格 -->
<div class="card-box">
    <div class="flex jsb table-operation hh">
        <el-button-group class="shrink0 table-right-but mb20" v-if="shipping_data.selection_data.length == 0">
            <el-button @click="shippingStatusChange('')" :type="!isExist(shipping_data.filter.shipping_status) ? 'primary' : ''"><?php echo esc_html__('全部', 'zib_language'); ?></el-button>
            <el-button v-for="(item,index) in shipping_status_name" :type="shipping_data.filter.shipping_status && shipping_data.filter.shipping_status.includes(~~index) ? 'primary' : ''" :key="index" @click="shippingStatusChange(index)">
                {{item}}
                <badge class="ml3" v-if="shipping_data.status_count[index] > 0">{{shipping_data.status_count[index]}}</badge>
            </el-button>
        </el-button-group>
        <div class="shrink0 table-right-but mb20" v-else>
        <el-button @click="shippingSelectionDelivery()" type="primary" plain><?php echo esc_html__('批量发货', 'zib_language'); ?></el-button>
        <el-button @click="shippingSelectionClear()" plain><?php echo esc_html__('清空', 'zib_language'); ?></el-button>
        </div>

        <div class="shrink0 table-right-but mb20">
            <el-button-group>
                <el-button @click="dBRefresh('shipping')" v-html="svg.refresh"></el-button>
                <el-popover placement="bottom-end" :width="160" trigger="hover">
                    <template #reference>
                        <el-button class="em12 c-main-3" v-html="svg.table_option"></el-button>
                    </template>
                    <div class="text-center mb10">
                        <el-radio-group v-model="shipping_data.table_option.table_size" size="small" class="text-center">
                            <el-radio-button label="large"><?php echo esc_html__('大', 'zib_language'); ?></el-radio-button>
                            <el-radio-button label="default"><?php echo esc_html__('中', 'zib_language'); ?></el-radio-button>
                            <el-radio-button label="small"><?php echo esc_html__('小', 'zib_language'); ?></el-radio-button>
                        </el-radio-group>
                    </div>
                    <el-checkbox-group v-model="shipping_data.table_option.table_rows_show">
                        <el-checkbox v-for="(item,index) in shipping_data.table_rows" :label="index" :key="index">{{item}}</el-checkbox>
                    </el-checkbox-group>
                </el-popover>
            </el-button-group>
        </div>
    </div>

    <el-table :data="shipping_data.lits_data" ref="shippingTableRef" @selection-change="shippingSelectionChange" style="width: 100%" @sort-change="shippingDbSort" v-loading="loading.shipping_table_list" :size="shipping_data.table_option.table_size" border>
        <el-table-column type="selection" width="40" :selectable="shippingSelectionSelectable"></el-table-column>
        <el-table-column prop="shipping_status" label="<?php echo esc_attr__('状态', 'zib_language'); ?>" min-width="115" sortable="custom">
            <template #default="scope">
                <el-button type="primary" plain @click="showShippingDialog(scope.row)" v-if="scope.row.shipping_status == '0'"><?php echo esc_html__('立即发货', 'zib_language'); ?></el-button>
                <el-tag v-else :type="['primary','warning', 'success'][scope.row.shipping_status] || 'warning'">
                    {{ shipping_status_name[scope.row.shipping_status] || '<?php echo esc_js(__('未知', 'zib_language')); ?>' }}
                </el-tag>
            </template>
        </el-table-column>
        <el-table-column prop="user_id" label="<?php echo esc_attr__('购买用户', 'zib_language'); ?>" sortable="custom" min-width="160" v-if="shipping_data.table_option.table_rows_show.includes('user_id')">
            <template #default="scope">
                <el-tooltip placement="top" effect="light">
                    <div class="flex ac">
                        <el-avatar :size="30" class="flex0 mr6" :src="scope.row.user_info.avatar"></el-avatar>
                        <div class="flex1 overflow-hidden">
                            <div>
                                <div class="text-ellipsis">{{ scope.row.user_info.name }}</div>
                            </div>
                            <div>
                                <div class="text-ellipsis em09 opacity8">{{ scope.row.ip_address }}</div>
                            </div>
                        </div>
                    </div>
                    <template #content>
                        <div class="tooltip-link-box">
                            <a @click="goParams({user_id:scope.row.user_id})" v-if="scope.row.user_id>0" href="javascript:void(0)"><?php echo esc_html__('筛选此用户', 'zib_language'); ?></a>
                            <a @click="goParams({search:scope.row.ip_address, search_filter:'ip_address'})" v-else href="javascript:void(0)"><?php echo esc_html__('筛选此IP', 'zib_language'); ?></a>
                            <a target="_blank" v-if="scope.row.user_info.home_url" :href="scope.row.user_info.home_url"><?php echo esc_html__('前台查看', 'zib_language'); ?></a>
                            <a target="_blank" v-if="scope.row.user_info.admin_url" :href="scope.row.user_info.admin_url"><?php echo esc_html__('后台查看', 'zib_language'); ?></a>
                        </div>
                    </template>
                </el-tooltip>
            </template>
        </el-table-column>
        <el-table-column prop="post_id" label="<?php echo esc_attr__('商品', 'zib_language'); ?>" sortable="custom" min-width="200" v-if="shipping_data.table_option.table_rows_show.includes('post_id')">
            <template #default="scope">
                <el-tooltip placement="top" effect="light">
                    <div class="flex ac">
                        <el-avatar :size="40" class="flex0 mr6" shape="square" :src="scope.row.product_info.thumbnail"></el-avatar>
                        <div class="flex1 overflow-hidden">
                            <div>
                                <div class="text-ellipsis">{{ scope.row.product_info.title }}</div>
                            </div>
                            <div>
                                <div class="text-ellipsis em09 opacity8 mt6">{{ scope.row.product_info.opt_name }}</div>
                            </div>
                        </div>
                    </div>
                    <template #content>
                        <div class="tooltip-link-box">
                            <a @click="goParams({post_id:scope.row.post_id})" v-if="scope.row.post_id>0" href="javascript:void(0)"><?php echo esc_html__('筛选此商品', 'zib_language'); ?></a>
                            <a @click="goParams({post_author:scope.row.post_author})" v-if="scope.row.post_author>0" href="javascript:void(0)"><?php echo esc_html__('筛选此商家', 'zib_language'); ?></a>
                            <a target="_blank" v-if="scope.row.product_info.url" :href="scope.row.product_info.url"><?php echo esc_html__('查看商品', 'zib_language'); ?></a>
                            <a target="_blank" v-if="scope.row.product_info.edit_url" :href="scope.row.product_info.edit_url"><?php echo esc_html__('编辑商品', 'zib_language'); ?></a>
                        </div>
                    </template>
                </el-tooltip>
            </template>
        </el-table-column>

        <el-table-column prop="shipping_time" label="<?php echo esc_attr__('订单信息', 'zib_language'); ?>" sortable="custom" min-width="180" v-if="shipping_data.table_option.table_rows_show.includes('shipping_time')">
            <template #default="scope">
                <el-tooltip placement="top" effect="light">
                    <div>
                        <div class="flex ac">
                            {{priceFormat(scope.row.prices.pay_price,scope.row.pay_modo)}}
                            <span class="badg" v-if="scope.row.count > 1"><?php echo esc_html__('共', 'zib_language'); ?>{{scope.row.count}}<?php echo esc_html__('件', 'zib_language'); ?></span>
                        </div>
                        <div v-if="[1,2].includes(~~scope.row.after_sale_status)" class="c-red badg">
                            {{after_sale_type_name[scope.row.after_sale_data.type]}} <?php echo esc_html__('处理中', 'zib_language'); ?>
                        </div>
                        <div class="mt6 opacity8">
                            {{scope.row.shipping_time || scope.row.pay_time}}
                        </div>
                    </div>

                    <template #content>
                        <div class="flex xx">
                            <div class="flex ac jsb mb6">
                                <div class="opacity5 mr6 flex0"><?php echo esc_html__('单价', 'zib_language'); ?></div>
                                <div>{{ priceFormat(scope.row.prices.unit_price,scope.row.pay_modo) }}</div>
                            </div>
                            <div class="flex ac jsb mb6">
                                <div class="opacity5 mr6 flex0"><?php echo esc_html__('数量', 'zib_language'); ?></div>
                                <div>{{ scope.row.count }}</div>
                            </div>
                            <div class="flex ac jsb mb6" v-if="scope.row.prices.shipping_fee">
                                <div class="opacity5 mr6 flex0"><?php echo esc_html__('运费', 'zib_language'); ?></div>
                                <div>{{ priceFormat(scope.row.prices.shipping_fee,scope.row.pay_modo) }}</div>
                            </div>
                            <div class="flex ac jsb mb6" v-if="scope.row.pay_num">
                                <div class="opacity5 mr6 flex0"><?php echo esc_html__('总价', 'zib_language'); ?></div>
                                <div>{{ priceFormat(scope.row.prices.total_price,scope.row.pay_modo) }}</div>
                            </div>
                            <div class="flex ac jsb mb6" v-if="scope.row.prices.total_discount && scope.row.prices.total_discount > 0">
                                <div class="opacity5 mr6 flex0"><?php echo esc_html__('优惠', 'zib_language'); ?></div>
                                <div>-{{ priceFormat(scope.row.prices.total_discount,scope.row.pay_modo) }}</div>
                            </div>
                            <div class="flex ac jsb mb6 c-red" v-if="scope.row.prices.pay_price">
                                <div class="opacity5 mr6 flex0">{{scope.row.status == 0 ? '<?php echo esc_js(__('应付', 'zib_language')); ?>' : '<?php echo esc_js(__('实付', 'zib_language')); ?>'}}</div>
                                <div>{{ priceFormat(scope.row.prices.pay_price,scope.row.pay_modo) }}</div>
                            </div>
                            <div class="flex ac jsb mb6">
                                <div class="opacity5 mr6 flex0"><?php echo esc_html__('订单号', 'zib_language'); ?></div>
                                <div>{{ scope.row.order_num }}</div>
                            </div>
                            <div class="flex ac jsb mb6">
                                <div class="opacity5 mr6 flex0"><?php echo esc_html__('下单时间', 'zib_language'); ?></div>
                                <div>{{ scope.row.create_time }}</div>
                            </div>
                            <div class="flex ac jsb mb6" v-if="scope.row.pay_num">
                                <div class="opacity5 mr6 flex0"><?php echo esc_html__('支付单号', 'zib_language'); ?></div>
                                <div>{{ scope.row.pay_num }}</div>
                            </div>
                            <div class="flex ac jsb mb6" v-if="scope.row.pay_num">
                                <div class="opacity5 mr6 flex0"><?php echo esc_html__('付款时间', 'zib_language'); ?></div>
                                <div>{{ scope.row.pay_time }}</div>
                            </div>
                        </div>

                        <div class="tooltip-link-box flex hh jsa" style="max-width: 200px;">
                            <a @click="copy(scope.row.order_num)" href="javascript:void(0)"><?php echo esc_html__('复制订单号', 'zib_language'); ?></a>
                            <a @click="copy(scope.row.pay_num)" v-if="scope.row.pay_num" href="javascript:void(0)"><?php echo esc_html__('复制支付单号', 'zib_language'); ?></a>
                        </div>
                    </template>
                </el-tooltip>
            </template>
        </el-table-column>

        <el-table-column prop="shipping_info" label="<?php echo esc_attr__('物流信息', 'zib_language'); ?>" min-width="220" v-if="shipping_data.table_option.table_rows_show.includes('shipping_info')">
            <template #default="scope">
                <span v-if="scope.row.shipping_status == '0'">
                    <el-tag type="warning"><?php echo esc_html__('待发货', 'zib_language'); ?></el-tag>
                    <div v-if="scope.row.shipping_type == 'auto'">
                        <div class="c-red px12 mt6"><?php echo esc_html__('自动发货失败，待处理', 'zib_language'); ?></div>
                    </div>
                    <div v-if="scope.row.shipping_type == 'express'">
                        <div class="em09 opacity5"><?php echo esc_html__('运费', 'zib_language'); ?>{{ scope.row.prices.shipping_fee}}</div>
                    </div>
                </span>
                <span v-if="scope.row.shipping_status == '1'">
                    <!-- 已发货，待收货 -->
                    <span class="badg c-blue" v-if="scope.row.express_data.state">{{scope.row.express_data.state}}</span><span class="badg c-purple"><?php echo esc_html__('待收货', 'zib_language'); ?> <count-down class="em09" :end-time="scope.row.shipping_receipt_over_time"></count-down></span>
                    <div v-if="scope.row.shipping_data.delivery_type == 'express'">
                        <div class="em09 opacity5">{{ scope.row.shipping_data.express_company_name }}</div>
                    </div>
                    <div v-if="scope.row.shipping_data.delivery_type == 'no_express'">
                        <div class="em09 opacity5"><?php echo esc_html__('无需物流发货', 'zib_language'); ?></div>
                    </div>
                    <div class="opacity5 em09">
                        {{scope.row.express_data.update_time || scope.row.shipping_data.delivery_time}}
                    </div>
                </span>
                <!-- 已收货 -->
                <span v-if="scope.row.shipping_status == '2'">
                    <el-tag type="success"><?php echo esc_html__('已确认收货', 'zib_language'); ?></el-tag>
                    <div class="opacity5 em09"> {{scope.row.shipping_data.receive_time}}</div>
                </span>
            </template>
        </el-table-column>

        <el-table-column prop="action" label="<?php echo esc_attr__('查看详情', 'zib_language'); ?>" min-width="100" v-if="shipping_data.table_option.table_rows_show.includes('action')">
            <template #default="scope">
                <el-button plain class="em09" @click="shippingDetails(scope.row)"><?php echo esc_html__('查看详情', 'zib_language'); ?></el-button>
            </template>
        </el-table-column>

        <template #empty>
            <el-empty description="<?php echo esc_attr__('暂无数据', 'zib_language'); ?>" />
        </template>
    </el-table>

    <div class="flex je mt10">
        <el-pagination @current-change="dBPagChange('shipping','current')" @size-change="dBPagChange('shipping')"
            v-model:current-page="shipping_data.current_page" v-model:page-size="shipping_data.page_size"
            :total="shipping_data.total" :page-sizes="[10,20,30,50,100]" layout="total,sizes,prev,pager,next"
            :background="true" :pager-count="win.width<960 ? 5 : 7" :small="win.width<768">
        </el-pagination>
    </div>
</div>
