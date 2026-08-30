<?php
/*
 * @Author       : Qinver
 * @Url          : zibll.com
 * @Date         : 2025-05-09 12:17:08
 * @LastEditTime: 2025-09-10 13:10:01
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

$vue_data = array(
    'author_data'     => array(
        'new_address_dialog_data' => [],
        'new_address_dialog_show' => false,
    ),

    'after_sale_data' => array(
        'lits_data'                  => array(),
        'total'                      => 0,
        'current_page'               => 1,
        'page_size'                  => 20,
        'order'                      => '',
        'orderby'                    => 'after_sale_time',
        'search'                     => '',
        'search_filter'              => '',
        'timefilter'                 => [
            'pay_time' => [],
        ],
        'filter'                     => array(
            'after_sale_status' => [],
            'after_sale_type'   => [],
            'status'            => [],
            'id'                => '',
            'post_id'           => '',
            'user_id'           => '',
            'post_author'       => '',
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
                'after_sale_type',
                'user_id',
                'post_id',
                'after_sale_time',
                'shipping_time',
                'action',
            ],
        ],
        'table_rows'                 => [
            'after_sale_type' => __('售后类型', 'zib_language'),
            'user_id'         => __('购买用户', 'zib_language'),
            'post_id'         => __('商品', 'zib_language'),
            'after_sale_time' => __('售后时间', 'zib_language'),
            'shipping_time'   => __('发货信息', 'zib_language'),
            'action'          => __('查看详情', 'zib_language'),
        ],
        //表格参数通用结束
        'status_name'                => [
            1 => __('待处理', 'zib_language'),
            2 => __('处理中', 'zib_language'),
            3 => __('处理完成', 'zib_language'),
            4 => __('用户取消', 'zib_language'),
            5 => __('商家驳回', 'zib_language'),
        ],
        'type_name'                  => [
            'refund'        => __('仅退款', 'zib_language'),
            'refund_return' => __('退货退款', 'zib_language'),
            'replacement'   => __('换货', 'zib_language'),
            'warranty'      => __('保修', 'zib_language'),
            'insured_price' => __('保价', 'zib_language'),
        ],
        'progress_name'              => [
            1 => __('待用户发货', 'zib_language'),
            2 => __('待商家收货', 'zib_language'),
            3 => __('待用户收货', 'zib_language'),
            4 => __('处理完成', 'zib_language'),
        ],
        'type_color'                 => [
            'refund'        => 'c-purple',
            'refund_return' => 'c-blue',
            'replacement'   => 'c-green',
            'warranty'      => 'c-yellow',
            'insured_price' => 'c-red',
        ],
        'shipping_status_name'       => [
            '0' => __('未发货', 'zib_language'),
            '1' => __('已发货', 'zib_language'),
            '2' => __('已收货', 'zib_language'),
        ],
        'status_count'               => [
            '0' => 0,
            '1' => 0,
            '2' => 0,
            '3' => 0,
            '4' => 0,
        ],
        'handle_dialog_show'         => false,
        'handle_dialog_data'         => [],
        'handle_type'                => [
            'agree'  => __('同意', 'zib_language'),
            'refuse' => __('拒绝', 'zib_language'),
        ],
        'refund_channel_type'        => [
            'balance' => __('余额', 'zib_language'),
            'wechat'  => __('微信', 'zib_language'),
            'alipay'  => __('支付宝', 'zib_language'),
        ],
        'details_drawer_show'        => false,
        'details_drawer_data'        => [],
        'return_address_dialog_show' => false,
        'return_address_dialog_data' => [],
    ),
);
zibpay_admin_page_vue_data_filter($vue_data);
?>
<div class="card-box mb10">
    <div class="flex mb6 hh">
        <el-input placeholder="<?php echo esc_attr__('输入关键词搜索', 'zib_language'); ?>" style="width: auto" class="mb6 mr6" clearable v-model="after_sale_data.search">
            <template #prepend>
                <el-select style="max-width: 160px" v-model="after_sale_data.search_filter" class="mr6 mb6" clearable multiple collapse-tags placeholder="<?php echo esc_attr__('搜索项目', 'zib_language'); ?>">
                    <el-option v-for="(item,index) in after_sale_data.search_filter_option" :key="item" :label="item" :value="index">
                        <span class="em09">{{ item }}</span>
                        <span class="px12 float-right opacity5 ml10">{{ index }}</span>
                    </el-option>
                </el-select>
            </template>
        </el-input>
        <el-button class="mb6 mr6" type="primary" @click="dBSearch('after_sale')"><?php echo esc_html__('搜索', 'zib_language'); ?></el-button>
    </div>
    <div class="flex mb6 hh">
        <el-select v-model="after_sale_data.filter.after_sale_status" class="mr6 mb6" clearable multiple collapse-tags placeholder="<?php echo esc_attr__('售后状态', 'zib_language'); ?>">
            <el-option v-for="(item,index) in after_sale_status_name" :key="item" :label="item" :value="~~index">
                <span class="em09">{{ item }}</span>
                <span class="px12 float-right opacity5 ml10">{{ index }}</span>
            </el-option>
        </el-select>
        <el-select v-model="after_sale_data.filter.after_sale_type" class="mr6 mb6" clearable multiple collapse-tags placeholder="<?php echo esc_attr__('售后类型', 'zib_language'); ?>">
            <el-option v-for="(item,index) in after_sale_data.type_name" :key="item" :label="item" :value="index">
                <span class="em09">{{ item }}</span>
                <span class="px12 float-right opacity5 ml10">{{ index }}</span>
            </el-option>
        </el-select>

        <el-select v-model="after_sale_data.filter.status" class="mr6 mb6" clearable multiple collapse-tags placeholder="<?php echo esc_attr__('订单状态', 'zib_language'); ?>">
            <el-option v-for="(item,index) in status_name" :key="item" :label="item" :value="~~index">
                <span class="em09">{{ item }}</span>
                <span class="px12 float-right opacity5 ml10">{{ index }}</span>
            </el-option>
        </el-select>

        <el-input clearable class="mr6 mb6" style="width: 98px" v-model="after_sale_data.filter.id" placeholder="<?php echo esc_attr__('订单ID', 'zib_language'); ?>" :formatter="(value) => ~~value || ''"></el-input>
        <el-input clearable class="mr6 mb6" style="width: 98px" v-model="after_sale_data.filter.post_id" placeholder="<?php echo esc_attr__('商品ID', 'zib_language'); ?>" :formatter="(value) => ~~value || ''"></el-input>
        <el-input clearable class="mr6 mb6" style="width: 98px" v-model="after_sale_data.filter.user_id" placeholder="<?php echo esc_attr__('用户ID', 'zib_language'); ?>" :formatter="(value) => ~~value || ''"></el-input>
        <el-input clearable class="mr6 mb6" style="width: 98px" v-model="after_sale_data.filter.post_author" placeholder="<?php echo esc_attr__('商家ID', 'zib_language'); ?>" :formatter="(value) => ~~value || ''"></el-input>

        <div style="width: 203px" class="mr6 mb6 flex">
            <el-date-picker v-model="after_sale_data.timefilter.create_time" format="YY-MM-DD" :shortcuts="date_shortcuts" type="daterange" range-separator="-" start-placeholder="<?php echo esc_attr__('下单时间', 'zib_language'); ?>" end-placeholder="" unlink-panels></el-date-picker>
        </div>

        <div style="width: 203px" class="mr20 mb6 flex">
            <el-date-picker v-model="after_sale_data.timefilter.after_sale_time" format="YY-MM-DD" :shortcuts="date_shortcuts" type="daterange" range-separator="-" start-placeholder="<?php echo esc_attr__('售后时间', 'zib_language'); ?>" end-placeholder="" unlink-panels></el-date-picker>
        </div>

        <div class="shrink0 table-right-but mb6 flex">
            <el-button @click="dbFilter('after_sale')" type="primary"><?php echo esc_html__('查询', 'zib_language'); ?></el-button>
            <el-button @click="dBRefresh('after_sale')"><?php echo esc_html__('重置', 'zib_language'); ?></el-button>
        </div>
    </div>
</div>

<div class="card-box">
    <div class="flex jsb table-operation hh">
        <el-button-group class="table-right-but mb20">
            <el-button @click="afterSaleStatusChange()" :type="!isExist(after_sale_data.filter.after_sale_status) ? 'primary' : ''"><?php echo esc_html__('全部', 'zib_language'); ?></el-button>
            <el-button v-for="(item,index) in after_sale_data.status_name" :type="after_sale_data.filter.after_sale_status && after_sale_data.filter.after_sale_status.includes(~~index) ? 'primary' : ''" :key="index" @click="afterSaleStatusChange(index)">
                {{item}}
                <badge class="ml3" v-if="after_sale_data.status_count[index] > 0">{{after_sale_data.status_count[index]}}</badge>
            </el-button>
        </el-button-group>

        <div class="shrink0 table-right-but mb20">
            <el-button-group>
                <el-button @click="dBRefresh('after_sale')" v-html="svg.refresh"></el-button>
                <el-popover placement="bottom-end" :width="160" trigger="hover">
                    <template #reference>
                        <el-button class="em12 c-main-3" v-html="svg.table_option"></el-button>
                    </template>
                    <div class="text-center mb10">
                        <el-radio-group v-model="after_sale_data.table_option.table_size" size="small" class="text-center">
                            <el-radio-button label="large"><?php echo esc_html__('大', 'zib_language'); ?></el-radio-button>
                            <el-radio-button label="default"><?php echo esc_html__('中', 'zib_language'); ?></el-radio-button>
                            <el-radio-button label="small"><?php echo esc_html__('小', 'zib_language'); ?></el-radio-button>
                        </el-radio-group>
                    </div>
                    <el-checkbox-group v-model="after_sale_data.table_option.table_rows_show">
                        <el-checkbox v-for="(item,index) in after_sale_data.table_rows" :label="index" :key="index">{{item}}</el-checkbox>
                    </el-checkbox-group>
                </el-popover>
            </el-button-group>
        </div>
    </div>

    <el-table :data="after_sale_data.lits_data" style="width: 100%" @sort-change="afterSaleDbSort" v-loading="loading.after_sale_table_list" :size="after_sale_data.table_option.table_size" border>
        <el-table-column prop="after_sale_status" label="<?php echo esc_attr__('状态', 'zib_language'); ?>" min-width="115" sortable="custom">
            <template #default="scope">
                <el-button type="primary" plain @click="afterSaleHandle(scope.row)" v-if="scope.row.after_sale_status == 1"><?php echo esc_html__('立即处理', 'zib_language'); ?></el-button>
                <div class="c-yellow em09" v-if="scope.row.after_sale_status == 1"><?php echo esc_html__('待商家审核', 'zib_language'); ?></div>
                <el-tag v-if="scope.row.after_sale_status > 2" :type="[ 'warning','warning','success','success','danger','danger'][scope.row.after_sale_status] || 'warning'">
                    {{ after_sale_data.status_name[scope.row.after_sale_status] || '<?php echo esc_js(__('未知', 'zib_language')); ?>' }}
                </el-tag>
                <el-button type="primary" plain @click="afterSaleHandle(scope.row)" v-if="scope.row.after_sale_status == 2 && scope.row.after_sale_data.progress == 2"><?php echo esc_html__('立即处理', 'zib_language'); ?></el-button>
                <div v-if="scope.row.after_sale_status == 2" class="em09 badg" :class="[ 'c-yellow','c-yellow','c-yellow','c-green','c-red','c-red'][scope.row.after_sale_data.progress] || 'c-yellow'">
                    {{ after_sale_data.progress_name[scope.row.after_sale_data.progress] || '<?php echo esc_js(__('未知', 'zib_language')); ?>' }}
                </div>
                <div class="badg c-yellow em09" v-if="scope.row.after_sale_data.progress == 1"><?php echo esc_html__('剩余', 'zib_language'); ?><count-down :end-time="scope.row.after_sale_return_express_over_time"></count-down></div>
            </template>
        </el-table-column>

        <el-table-column prop="after_sale_type" label="<?php echo esc_attr__('类型', 'zib_language'); ?>" min-width="125" sortable="custom" v-if="after_sale_data.table_option.table_rows_show.includes('after_sale_type')">
            <template #default="scope">
                <div class="badg" :class="after_sale_data.type_color[scope.row.after_sale_type] || 'c-yellow'">{{after_sale_data.type_name[scope.row.after_sale_type] || '<?php echo esc_js(__('未知', 'zib_language')); ?>'}}</div>
                <div class="badg c-yellow" v-if="scope.row.after_sale_data.price"><?php echo esc_html__('退款', 'zib_language'); ?> {{priceFormat(scope.row.after_sale_data.price,scope.row.pay_modo)}}</div>

            </template>
        </el-table-column>

        <el-table-column prop="user_id" label="<?php echo esc_attr__('购买用户', 'zib_language'); ?>" sortable="custom" min-width="160" v-if="after_sale_data.table_option.table_rows_show.includes('user_id')">
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
        <el-table-column prop="post_id" label="<?php echo esc_attr__('商品', 'zib_language'); ?>" sortable="custom" min-width="200" v-if="after_sale_data.table_option.table_rows_show.includes('post_id')">
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

        <el-table-column prop="after_sale_time" label="<?php echo esc_attr__('售后时间', 'zib_language'); ?>" sortable="custom" min-width="180" v-if="after_sale_data.table_option.table_rows_show.includes('after_sale_time')">
        <template #default="scope">
                <el-tooltip placement="top" effect="light">
                    <div>
                        <div class="flex ac">
                            {{priceFormat(scope.row.prices.pay_price,scope.row.pay_modo)}}
                            <span class="badg" v-if="scope.row.count > 1"><?php echo esc_html__('共', 'zib_language'); ?>{{scope.row.count}}<?php echo esc_html__('件', 'zib_language'); ?></span>
                        </div>
                        <div class="mt6 opacity8">
                            {{scope.row.after_sale_time || scope.row.pay_time}}
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

        <el-table-column prop="shipping_time" label="<?php echo esc_attr__('发货信息', 'zib_language'); ?>" sortable="custom" min-width="180" v-if="after_sale_data.table_option.table_rows_show.includes('shipping_time')">
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
                        {{scope.row.express_data.update_time || scope.row.shipping_data.delivery_time || scope.row.shipping_time}}
                    </div>
                </span>
                <!-- 已收货 -->
                <span v-if="scope.row.shipping_status == '2'">
                    <el-tag type="success"><?php echo esc_html__('已确认收货', 'zib_language'); ?></el-tag>
                    <div class="opacity5 em09"> {{scope.row.shipping_data.receive_time}}</div>
                </span>
            </template>
        </el-table-column>

        <el-table-column prop="action" label="<?php echo esc_attr__('查看详情', 'zib_language'); ?>" min-width="110" v-if="after_sale_data.table_option.table_rows_show.includes('action')">
            <template #default="scope">
                <el-button plain @click="afterSaleDetails(scope.row)"><?php echo esc_html__('查看详情', 'zib_language'); ?></el-button>
            </template>
        </el-table-column>

        <template #empty>
            <el-empty description="<?php echo esc_attr__('暂无数据', 'zib_language'); ?>" />
        </template>
    </el-table>

    <div class="flex je mt10">
        <el-pagination @current-change="dBPagChange('after_sale','current')" @size-change="dBPagChange('after_sale')"
            v-model:current-page="after_sale_data.current_page" v-model:page-size="after_sale_data.page_size"
            :total="after_sale_data.total" :page-sizes="[10,20,30,50,100]" layout="total,sizes,prev,pager,next"
            :background="true" :pager-count="win.width<960 ? 5 : 7" :small="win.width<768">
        </el-pagination>
    </div>
</div>
