<?php
/*
 * @Author       : Qinver
 * @Url          : zibll.com
 * @Date         : 2025-05-09 12:17:08
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
    'order_data' => array(
        'statistics_data'      => array(),
        'lits_data'            => array(),
        'total'                => 0,
        'current_page'         => 1,
        'page_size'            => 20,
        'order'                => '',
        'orderby'              => '',
        'search'               => '',
        'search_filter'        => '',
        'timefilter'           => [
            'pay_time'    => [],
            'create_time' => [],
        ],
        'filter'               => array(
            'post_id'           => '',
            'user_id'           => '',
            'status'            => [],
            'shipping_status'   => [],
            'after_sale_status' => [],
            'rebate_status'     => [],
            'income_status'     => [],
            'id'                => '',
        ),
        'search_filter_option' => [
            'user'       => __('用户', 'zib_language'),
            'post'       => __('商品', 'zib_language'),
            'ip_address' => __('IP地址', 'zib_language'),
            'order_num'  => __('订单号', 'zib_language'),
            'pay_num'    => __('支付单号', 'zib_language'),
            'order_data' => __('订单数据', 'zib_language'),
        ],
        //查询通用结束
        'table_option'         => [
            'table_size'      => 'default',
            'table_rows_show' => [
                'post_id',
                'user_id',
                'order_num',
                'order_price',
                'pay_time',
                'shipping_time',
                'after_sale_time',
                'rebate_price',
                'income_price',
            ],
        ],
        'table_rows'           => [
            'post_id'         => __('商品', 'zib_language'),
            'user_id'         => __('用户', 'zib_language'),
            'order_num'       => __('订单号', 'zib_language'), //下单时间
            'order_price'     => __('订单金额', 'zib_language'),
            'pay_time'        => __('付款信息', 'zib_language'), //支付明细，付款状态，支付方式
            'shipping_time'   => __('物流信息', 'zib_language'),
            'after_sale_time' => __('售后信息', 'zib_language'),
            'rebate_price'    => __('佣金信息', 'zib_language'),
            'income_price'    => __('分成信息', 'zib_language'),
        ],
    ),
);
zibpay_admin_page_vue_data_filter($vue_data);
?>
<div class="card-box mb10">
    <div class="flex mb6 hh">
        <el-input placeholder="<?php echo esc_attr__('输入关键词搜索', 'zib_language'); ?>" style="width: auto" class="mb6 mr6" clearable v-model="order_data.search">
            <template #prepend>
                <el-select style="max-width: 160px" v-model="order_data.search_filter" class="mr6 mb6" clearable multiple collapse-tags placeholder="<?php echo esc_attr__('搜索项目', 'zib_language'); ?>">
                    <el-option v-for="(item,index) in order_data.search_filter_option" :key="item" :label="item" :value="index">
                        <span class="em09">{{ item }}</span>
                        <span class="px12 float-right opacity5 ml10">{{ index }}</span>
                    </el-option>
                </el-select>
            </template>
        </el-input>
        <el-button class="mb6 mr6" type="primary" @click="dBSearch('order')"><?php echo esc_html__('搜索', 'zib_language'); ?></el-button>
    </div>
    <div class="flex mb6 hh">

        <el-select v-model="order_data.filter.status" class="mr6 mb6" clearable multiple collapse-tags placeholder="<?php echo esc_attr__('订单状态', 'zib_language'); ?>">
            <el-option v-for="(item,index) in status_name" :key="item" :label="item" :value="~~index">
                <span class="em09">{{ item }}</span>
                <span class="px12 float-right opacity5 ml10">{{ index }}</span>
            </el-option>
        </el-select>
        <el-select v-model="order_data.filter.order_type" class="mr6 mb6" clearable multiple collapse-tags placeholder="<?php echo esc_attr__('商品类型', 'zib_language'); ?>">
            <el-option v-for="(item,index) in order_type_name" :key="item" :label="item" :value="~~index">
                <span class="em09">{{ item }}</span>
                <span class="px12 float-right opacity5 ml10">{{ index }}</span>
            </el-option>
        </el-select>

        <el-select v-model="order_data.filter.shipping_status" class="mr6 mb6" clearable multiple collapse-tags placeholder="<?php echo esc_attr__('发货状态', 'zib_language'); ?>">
            <el-option v-for="(item,index) in shipping_status_name" :key="item" :label="item" :value="~~index">
                <span class="em09">{{ item }}</span>
                <span class="px12 float-right opacity5 ml10">{{ index }}</span>
            </el-option>
        </el-select>

        <el-select v-model="order_data.filter.after_sale_status" class="mr6 mb6" clearable multiple collapse-tags placeholder="<?php echo esc_attr__('售后状态', 'zib_language'); ?>">
            <el-option v-for="(item,index) in after_sale_status_name" :key="item" :label="item" :value="~~index">
                <span class="em09">{{ item }}</span>
                <span class="px12 float-right opacity5 ml10">{{ index }}</span>
            </el-option>
        </el-select>

        <el-select v-model="order_data.filter.rebate_status" class="mr6 mb6" clearable multiple collapse-tags placeholder="<?php echo esc_attr__('佣金状态', 'zib_language'); ?>">
            <el-option v-for="(item,index) in withdraw_status_name" :key="item" :label="item" :value="~~index">
                <span class="em09">{{ item }}</span>
                <span class="px12 float-right opacity5 ml10">{{ index }}</span>
            </el-option>
        </el-select>
        <el-select v-model="order_data.filter.income_status" class="mr6 mb6" clearable multiple collapse-tags placeholder="<?php echo esc_attr__('分成状态', 'zib_language'); ?>">
            <el-option v-for="(item,index) in withdraw_status_name" :key="item" :label="item" :value="~~index">
                <span class="em09">{{ item }}</span>
                <span class="px12 float-right opacity5 ml10">{{ index }}</span>
            </el-option>
        </el-select>

        <el-input clearable class="mr6 mb6" style="width: 98px" v-model="order_data.filter.id" placeholder="<?php echo esc_attr__('订单ID', 'zib_language'); ?>" :formatter="(value) => ~~value || ''"></el-input>
        <el-input clearable class="mr6 mb6" style="width: 98px" v-model="order_data.filter.post_id" placeholder="<?php echo esc_attr__('商品ID', 'zib_language'); ?>" :formatter="(value) => ~~value || ''"></el-input>
        <el-input clearable class="mr6 mb6" style="width: 98px" v-model="order_data.filter.user_id" placeholder="<?php echo esc_attr__('用户ID', 'zib_language'); ?>" :formatter="(value) => ~~value || ''"></el-input>
        <el-input clearable class="mr6 mb6" style="width: 98px" v-model="order_data.filter.post_author" placeholder="<?php echo esc_attr__('商家ID', 'zib_language'); ?>" :formatter="(value) => ~~value || ''"></el-input>

        <div style="width: 203px" class="mr6 mb6 flex">
            <el-date-picker v-model="order_data.timefilter.create_time" format="YY-MM-DD" :shortcuts="date_shortcuts" type="daterange" range-separator="-" start-placeholder="<?php echo esc_attr__('下单时间', 'zib_language'); ?>" end-placeholder="" unlink-panels></el-date-picker>
        </div>

        <div style="width: 203px" class="mr20 mb6 flex">
            <el-date-picker v-model="order_data.timefilter.pay_time" format="YY-MM-DD" :shortcuts="date_shortcuts" type="daterange" range-separator="-" start-placeholder="<?php echo esc_attr__('付款时间', 'zib_language'); ?>" end-placeholder="" unlink-panels></el-date-picker>
        </div>

        <div class="shrink0 table-right-but mb6 flex">
            <el-button @click="dbFilter('order')" type="primary"><?php echo esc_html__('查询', 'zib_language'); ?></el-button>
            <el-button @click="dBRefresh('order')"><?php echo esc_html__('重置', 'zib_language'); ?></el-button>
        </div>
    </div>
</div>

<el-row type="flex" class="mini-card-box" :gutter="10">
    <el-col :xs="12" :sm="12" :md="6" :lg="6"  v-for="(card_item,index) in order_data.statistics_data" :key="index + 'order_data_statistics'" >
        <el-row class="card-box mini-card-box mb10" type="flex" v-loading="loading.order_table_list">
            <el-col :span="12" v-for="(item,item_index) in card_item" :key="index + 'statistics_item' + item_index">
                <div class="val">{{ priceCut(item) }}</div>
                <div class="opacity5 mt6">{{ item_index }}</div>
            </el-col>
        </el-row>
    </el-col>
</el-row>

<div class="card-box">
    <div class="flex jsb table-operation hh">
        <el-button @click="clearOrder"><?php echo esc_html__('清理订单', 'zib_language'); ?></el-button>
        <div class="shrink0 table-right-but mb20">
            <el-button-group>
                <el-button @click="dBRefresh('order')" v-html="svg.refresh"></el-button>
                <el-popover placement="bottom-end" :width="160" trigger="hover">
                    <template #reference>
                        <el-button class="em12 c-main-3" v-html="svg.table_option"></el-button>
                    </template>
                    <div class="text-center mb10">
                        <el-radio-group v-model="order_data.table_option.table_size" size="small" class="text-center">
                            <el-radio-button label="large"><?php echo esc_html__('大', 'zib_language'); ?></el-radio-button>
                            <el-radio-button label="default"><?php echo esc_html__('中', 'zib_language'); ?></el-radio-button>
                            <el-radio-button label="small"><?php echo esc_html__('小', 'zib_language'); ?></el-radio-button>
                        </el-radio-group>
                    </div>
                    <el-checkbox-group v-model="order_data.table_option.table_rows_show">
                        <el-checkbox v-for="(item,index) in order_data.table_rows" :label="index" :key="index">{{item}}</el-checkbox>
                    </el-checkbox-group>
                </el-popover>
            </el-button-group>
        </div>
    </div>

    <el-table :data="order_data.lits_data" style="width: 100%" @sort-change="orderDbSort" v-loading="loading.order_table_list" :size="order_data.table_option.table_size" border>
        <el-table-column prop="post_id" label="<?php echo esc_attr__('商品', 'zib_language'); ?>" sortable="custom" min-width="200" v-if="order_data.table_option.table_rows_show.includes('post_id')">
            <template #default="scope">
                <el-tooltip placement="top" effect="light">
                    <div class="flex ac">
                        <div class="flex0 mr6" v-html="scope.row.product_info.thumb"></div>
                        <div class="flex1 overflow-hidden flex xx jsb">
                            <div>
                                <div class="text-ellipsis">{{ scope.row.product_info.title }}</div>
                            </div>
                            <div class="opacity5 em09 text-ellipsis" v-if="scope.row.product_info.opt_name">{{ scope.row.product_info.opt_name }}</div>
                            <div class="flex ac" v-if="scope.row.post_author>0">
                                <el-avatar :size="14" class="flex0 mr6" :src="scope.row.author_info.avatar"></el-avatar>
                                <div class="opacity8 em09 text-ellipsis">{{ scope.row.author_info.name}}</div>
                            </div>
                        </div>
                        <div class="small-badge" :style="{color:colors[scope.row.order_type]}" v-if="order_type_name[scope.row.order_type]">{{order_type_name[scope.row.order_type]}}</div>
                    </div>
                    <template #content>
                        <div class="tooltip-link-box">
                            <a @click="goParams({post_id:scope.row.post_id})" v-if="scope.row.post_id>0" href="javascript:void(0)"><?php echo esc_html__('筛选此商品', 'zib_language'); ?></a>
                            <a @click="goParams({post_author:scope.row.post_author})" v-if="scope.row.post_author>0" href="javascript:void(0)"><?php echo esc_html__('筛选此商家', 'zib_language'); ?></a>
                            <a @click="goParams({order_type:scope.row.order_type})" v-else href="javascript:void(0)"><?php echo esc_html__('筛选', 'zib_language'); ?>{{order_type_name[scope.row.order_type]}}</a>
                            <a :data-action="'admin_paydown_log&type=post&id='+scope.row.post_id" data-toggle="RefreshModal" href="javascript:void(0)" v-if="scope.row.order_type==2"><?php echo esc_html__('查看下载记录', 'zib_language'); ?></a>
                            <a target="_blank" v-if="scope.row.product_info.url" :href="scope.row.product_info.url"><?php echo esc_html__('查看商品', 'zib_language'); ?></a>
                            <a target="_blank" v-if="scope.row.product_info.edit_url" :href="scope.row.product_info.edit_url"><?php echo esc_html__('编辑商品', 'zib_language'); ?></a>
                        </div>
                    </template>
                </el-tooltip>
            </template>
        </el-table-column>

        <el-table-column prop="user_id" label="<?php echo esc_attr__('用户', 'zib_language'); ?>" sortable="custom" min-width="170" v-if="order_data.table_option.table_rows_show.includes('user_id')">
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

        <el-table-column prop="order_num" label="<?php echo esc_attr__('订单号', 'zib_language'); ?>" sortable="custom" min-width="180" v-if="order_data.table_option.table_rows_show.includes('order_num')">
            <template #default="scope">
                <el-tooltip placement="top" effect="light">
                    <div class="em09">
                        <div>
                            {{scope.row.order_num}}
                        </div>
                        <div class="opacity5">
                            {{scope.row.create_time}}
                        </div>
                    </div>
                    <template #content>
                        <div class="flex xx">
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

        <el-table-column prop="order_price" label="<?php echo esc_attr__('金额', 'zib_language'); ?>" sortable="custom" min-width="150" v-if="order_data.table_option.table_rows_show.includes('order_price')">
            <template #default="scope">
                <el-tooltip placement="top" effect="light">
                    <div>
                        <div class="flex ac">
                            {{ priceFormat(scope.row.order_price,scope.row.pay_modo) }}
                            <span class="badg" v-if="scope.row.count > 1"><?php echo esc_html__('共', 'zib_language'); ?>{{scope.row.count}}<?php echo esc_html__('件', 'zib_language'); ?></span>
                        </div>
                        <span class="opacity5 px12" v-if="scope.row.prices.pay_price<scope.row.prices.total_price"><?php echo esc_html__('原价', 'zib_language'); ?>{{ priceFormat(scope.row.prices.total_price,scope.row.pay_modo) }}</span>
                    </div>
                    <template #content>
                        <div class="flex xx">
                            <template v-if="scope.row.count > 1">
                                <div class="flex ac jsb mb6">
                                    <div class="opacity5 mr6 flex0"><?php echo esc_html__('单价', 'zib_language'); ?></div>
                                    <div>{{ priceFormat(scope.row.prices.unit_price,scope.row.pay_modo) }}</div>
                                </div>
                                <div class="flex ac jsb mb6">
                                    <div class="opacity5 mr6 flex0"><?php echo esc_html__('数量', 'zib_language'); ?></div>
                                    <div>{{ scope.row.count }}</div>
                                </div>
                            </template>
                            <div class="flex ac jsb mb6" v-if="scope.row.pay_num">
                                <div class="opacity5 mr6 flex0"><?php echo esc_html__('总价', 'zib_language'); ?></div>
                                <div>{{ priceFormat(scope.row.prices.total_price,scope.row.pay_modo) }}</div>
                            </div>
                            <div class="flex ac jsb mb6" v-if="scope.row.prices.total_discount && scope.row.prices.total_discount > 0">
                                <div class="opacity5 mr6 flex0">{{ (scope.row.prices.rebate_discount && scope.row.prices.rebate_discount == scope.row.prices.total_discount ? '<?php echo esc_js(__('推广', 'zib_language')); ?>':'') + '<?php echo esc_js(__('优惠', 'zib_language')); ?>'}}</div>
                                <div>-{{ priceFormat(scope.row.prices.total_discount,scope.row.pay_modo) }}</div>
                            </div>
                            <div class="flex ac jsb mb6" v-if="scope.row.prices.shipping_fee">
                                <div class="opacity5 mr6 flex0"><?php echo esc_html__('运费', 'zib_language'); ?></div>
                                <div>{{ priceFormat(scope.row.prices.shipping_fee,scope.row.pay_modo) }}</div>
                            </div>

                             <div class="flex ac jsb mb6 c-red" v-if="scope.row.status != -1">
                                <div class="mr6 flex0">{{scope.row.status == 0 ? '<?php echo esc_js(__('应付', 'zib_language')); ?>' : '<?php echo esc_js(__('实付', 'zib_language')); ?>'}}</div>
                                <div>{{ priceFormat(scope.row.prices.pay_price,scope.row.pay_modo) }}</div>
                            </div>
                            <div class="flex ac jsb mb6 c-red" v-else>
                                <div class="mr6 flex0"><?php echo esc_html__('已关闭', 'zib_language'); ?></div>
                                <div>{{ priceFormat(scope.row.order_price,scope.row.pay_modo) }}</div>
                            </div>
                        </div>
                        <div class="tooltip-link-box">
                            <a :data-action="'order_gift_modal&order_id='+scope.row.id" data-toggle="RefreshModal" href="javascript:void(0)" v-if="isExist(scope.row.order_data.gift_data)">{{scope.row.order_data.gift_data.length}}<?php echo esc_html__('件赠品', 'zib_language'); ?> <?php echo esc_html__('查看', 'zib_language'); ?></a>
                            <a :data-action="'order_discount_modal&order_id='+scope.row.id" data-toggle="RefreshModal" href="javascript:void(0)" v-if="isExist(scope.row.order_data.discount_hit)"><?php echo esc_html__('查看优惠明细', 'zib_language'); ?></a>
                        </div>
                    </template>
                </el-tooltip>
            </template>
        </el-table-column>

        <el-table-column prop="pay_time" label="<?php echo esc_attr__('付款', 'zib_language'); ?>" sortable="custom" min-width="160" v-if="order_data.table_option.table_rows_show.includes('pay_time')">
            <template #default="scope">
                <el-tooltip placement="top" effect="light">
                    <div>
                        <!-- 付款信息：分三种：待付款，已付款，已退款，已关闭 -->
                        <div v-if="scope.row.status == -1">
                            <el-tag type="danger"><?php echo esc_html__('交易已关闭', 'zib_language'); ?></el-tag>
                        </div>
                        <div v-if="scope.row.status == 0">
                            <el-tag type="warning"><?php echo esc_html__('待支付', 'zib_language'); ?> <count-down :end-time="scope.row.close_time" @timeup="closeOrder(scope.row)" /> </el-tag>
                            <div class="em09 opacity5">
                                <?php echo esc_html__('应付', 'zib_language'); ?>：{{priceFormat(scope.row.prices.pay_price,scope.row.pay_modo)}}

                            </div>
                        </div>
                        <div v-if="scope.row.status == 1">
                            <el-tag type="success"><?php echo esc_html__('已支付', 'zib_language'); ?>：{{priceFormat(scope.row.prices.pay_price,scope.row.pay_modo)}}</el-tag>
                            <div class="em09 opacity5">
                                {{scope.row.pay_time}}
                            </div>
                        </div>
                        <div v-if="scope.row.status == -2">
                            <el-tag type="danger"><?php echo esc_html__('已退款', 'zib_language'); ?>：{{priceFormat(scope.row.prices.refund,scope.row.pay_modo)}}</el-tag>
                            <div class="em09 opacity5">
                                {{scope.row.after_sale_data.end_time}}
                            </div>
                        </div>
                    </div>
                    <template #content>
                        <div v-if="scope.row.status == -1">
                            <div class="opacity5" v-if="scope.row.order_data.close_reason">
                                {{scope.row.order_data.close_reason || '<?php echo esc_js(__('交易已关闭', 'zib_language')); ?>'}}
                            </div>
                            <div class="tooltip-link-box">
                                <a @click="paymentOrderDialog(scope.row)" href="javascript:void(0)"><?php echo esc_html__('手动补单', 'zib_language'); ?></a>
                            </div>
                        </div>
                        <div v-if="scope.row.status == 0">
                            <div class="flex xx">
                                <div class="flex ac jsb mb6">
                                    <div class="opacity5 mr6 flex0"><?php echo esc_html__('应付', 'zib_language'); ?></div>
                                    <div>{{priceFormat(scope.row.prices.pay_price,scope.row.pay_modo)}}</div>
                                </div>
                                <div class="flex ac jsb mb6">
                                    <div class="opacity5 mr6 flex0"><?php echo esc_html__('超时时间', 'zib_language'); ?></div>
                                    <div>{{scope.row.close_time}}</div>
                                </div>
                                <div class="tooltip-link-box">
                                    <a @click="paymentOrderDialog(scope.row)" href="javascript:void(0)"><?php echo esc_html__('手动支付', 'zib_language'); ?></a>
                                </div>
                            </div>
                        </div>
                        <div v-if="scope.row.status == 1">
                            <div class="flex xx">
                                <div class="flex ac jsb mb6">
                                    <div class="opacity5 mr6 flex0"><?php echo esc_html__('付款明细', 'zib_language'); ?></div>
                                    <div v-html="scope.row.pay_detail_lists"></div>
                                </div>
                                <div class="flex ac jsb mb6">
                                    <div class="opacity5 mr6 flex0"><?php echo esc_html__('支付接口', 'zib_language'); ?></div>
                                    <div>{{ scope.row.pay_type }}</div>
                                </div>
                            </div>
                        </div>
                        <div v-if="scope.row.status == -2">
                            <div class="tooltip-link-box">
                                <a @click="go('after-sale',{post_id:scope.row.post_id,after_sale_status:scope.row.after_sale_status})" href="javascript:void(0)"><?php echo esc_html__('查看售后详情', 'zib_language'); ?></a>
                            </div>
                        </div>
                    </template>
                </el-tooltip>
            </template>
        </el-table-column>

        <el-table-column prop="shipping_time" label="<?php echo esc_attr__('发货', 'zib_language'); ?>" sortable="custom" min-width="160" v-if="order_data.table_option.table_rows_show.includes('shipping_time') && config.shop_s">
            <template #default="scope">
                <el-tooltip placement="top" effect="light" v-if="scope.row.order_type == 10">
                    <div>
                        <span v-if="scope.row.shipping_status == '0'">
                            <el-button type="primary" plain size="small" @click="showShippingDialog(scope.row)" v-if="scope.row.status == 1"><?php echo esc_html__('立即发货', 'zib_language'); ?></el-button>
                            <div class="mt3 em09 opacity8 c-yellow">
                                {{scope.row.status != 1 ? '<?php echo esc_js(__('订单', 'zib_language')); ?>'+status_name[scope.row.status] : '<?php echo esc_js(__('待发货', 'zib_language')); ?>'}}
                                <span class="ml6" v-if="scope.row.shipping_type == 'express'"><?php echo esc_html__('运费', 'zib_language'); ?>{{ scope.row.prices.shipping_fee }}</span>
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
                            <div v-else class="opacity5 em09">
                                {{scope.row.express_data.update_time || scope.row.shipping_data.delivery_time}}
                            </div>
                        </span>
                        <!-- 已收货 -->
                        <span v-if="scope.row.shipping_status == '2'">
                            <el-tag type="success"><?php echo esc_html__('已确认收货', 'zib_language'); ?></el-tag>
                            <div class="opacity5 em09"> {{scope.row.shipping_data.receive_time}}</div>
                        </span>
                    </div>
                    <template #content>
                        <div>
                            <div class="tooltip-link-box">
                                <a @click="shippingDetails(scope.row)" href="javascript:void(0)"><?php echo esc_html__('查看发货详情', 'zib_language'); ?></a>
                            </div>
                        </div>
                    </template>
                </el-tooltip>
                <div class="opacity5 em09" v-else>{{order_type_name[scope.row.order_type]}}<?php echo esc_html__('无需发货', 'zib_language'); ?></div>
            </template>
        </el-table-column>

        <el-table-column prop="after_sale_time" label="<?php echo esc_attr__('售后', 'zib_language'); ?>" sortable="custom" min-width="160" v-if="order_data.table_option.table_rows_show.includes('after_sale_time') && config.shop_s">
            <template #default="scope">
                <el-tooltip placement="top" effect="light" v-if="scope.row.order_type == 10 && scope.row.status != -1 && scope.row.status != 0">
                    <div class="flex ac">
                        <div v-if="scope.row.after_sale_status == 1">
                            <el-button type="primary" size="small" plain @click="afterSaleHandle(scope.row)"><?php echo esc_html__('立即处理', 'zib_language'); ?></el-button>
                            <el-tag type="warning">{{after_sale_type_name[scope.row.after_sale_data.type]}} <?php echo esc_html__('待处理', 'zib_language'); ?></el-tag>
                        </div>
                        <div v-else-if="scope.row.after_sale_status == 2">
                            <el-button type="primary" size="small" plain @click="afterSaleHandle(scope.row)" v-if="scope.row.after_sale_data.progress == 2"><?php echo esc_html__('立即处理', 'zib_language'); ?></el-button>
                            <div class="em09 mt3">
                                <span>{{after_sale_type_name[scope.row.after_sale_data.type]}}</span>
                                <span class="ml6 c-yellow" v-if="scope.row.after_sale_data.progress == 1"><?php echo esc_html__('待用户发货', 'zib_language'); ?></span>
                                <span class="ml6 c-yellow" v-else-if="scope.row.after_sale_data.progress == 2"><?php echo esc_html__('待商家收货', 'zib_language'); ?></span>
                                <span class="ml6 c-green" v-else-if="scope.row.after_sale_data.progress == 3"><?php echo esc_html__('待用户收货', 'zib_language'); ?></span>
                                <div class="badg c-yellow" v-if="scope.row.after_sale_data.progress == 1"><count-down :end-time="scope.row.after_sale_return_express_over_time"></count-down><?php echo esc_html__('后自动取消', 'zib_language'); ?></div>
                            </div>
                        </div>
                        <div v-else-if="scope.row.after_sale_status == 3">
                            <el-tag type="success">{{after_sale_type_name[scope.row.after_sale_data.type]}} <?php echo esc_html__('处理完成', 'zib_language'); ?></el-tag>
                            <div class="c-red mt6 em09" v-if="scope.row.after_sale_data.price"><?php echo esc_html__('退款', 'zib_language'); ?> {{priceFormat(scope.row.after_sale_data.price,scope.row.pay_modo)}}</div>
                        </div>
                        <div v-else-if="scope.row.after_sale_record_count > 0">
                            <el-tag type="info">{{scope.row.after_sale_record_count}}<?php echo esc_html__('条售后记录', 'zib_language'); ?></el-tag>
                        </div>
                        <div v-else></div>
                    </div>
                    <template #content>
                        <div>
                            <div class="tooltip-link-box">
                                <a @click="afterSaleDetails(scope.row)" href="javascript:void(0)"><?php echo esc_html__('查看售后详情', 'zib_language'); ?></a>
                            </div>
                        </div>
                    </template>
                </el-tooltip>
                <div v-else></div>
            </template>
        </el-table-column>
        <el-table-column prop="rebate_price" label="<?php echo esc_attr__('佣金', 'zib_language'); ?>" sortable="custom" min-width="160" v-if="order_data.table_option.table_rows_show.includes('rebate_price')">
            <template #default="scope">
                <el-tooltip placement="top" effect="light" v-if="scope.row.rebate_info.price">
                    <div>
                        <el-tag type="warning" v-if="scope.row.status == -1"><?php echo esc_html__('交易已关闭', 'zib_language'); ?></el-tag>
                        <el-tag type="warning" v-else-if="scope.row.status == 0"><?php echo esc_html__('待支付', 'zib_language'); ?> {{priceFormat(scope.row.rebate_info.price,scope.row.pay_modo)}}</el-tag>
                        <div v-else>
                            <el-tag type="success" v-if="scope.row.rebate_info.status == 1"><?php echo esc_html__('已提现', 'zib_language'); ?> {{priceFormat(scope.row.rebate_info.price,scope.row.pay_modo)}}</el-tag>
                            <el-tag type="info" v-if="scope.row.rebate_info.status == 0"><?php echo esc_html__('未提现', 'zib_language'); ?> {{priceFormat(scope.row.rebate_info.price,scope.row.pay_modo)}}</el-tag>
                            <div v-if="scope.row.rebate_info.status == 3">
                                <a class="el-button el-button--primary el-button--small is-plain" :href="config.admin_url + 'admin.php?page=zibpay_withdraw&status=0'" target="_blank"><?php echo esc_html__('立即处理', 'zib_language'); ?></a>
                                <div class="em09 c-yellow"><?php echo esc_html__('提现待处理', 'zib_language'); ?> {{priceFormat(scope.row.rebate_info.price,scope.row.pay_modo)}}</div>
                            </div>
                        </div>
                        <div class="flex ac mt6">
                            <el-avatar :size="14" class="flex0 mr6" :src="scope.row.rebate_info.referrer_info.avatar"></el-avatar>
                            <div class="em09">{{ scope.row.rebate_info.referrer_info.name}}</div>
                        </div>
                    </div>
                    <template #content>
                        <div>
                            <div class="tooltip-link-box">
                                <a :href="config.admin_url + 'admin.php?page=zibpay_rebate_page&referrer_id='+scope.row.rebate_info.referrer_id" target="_blank"><?php echo esc_html__('查看ta的明细', 'zib_language'); ?></a>
                            </div>
                        </div>
                    </template>
                </el-tooltip>
                <div v-else></div>
            </template>
        </el-table-column>
        <el-table-column prop="income_price" label="<?php echo esc_attr__('分成', 'zib_language'); ?>" sortable="custom" min-width="160" v-if="order_data.table_option.table_rows_show.includes('income_price')">
            <template #default="scope">
                <el-tooltip placement="top" effect="light" v-if="scope.row.income_info.price">
                    <div>
                        <el-tag type="warning" v-if="scope.row.status == -1"><?php echo esc_html__('交易已关闭', 'zib_language'); ?></el-tag>
                        <el-tag type="warning" v-else-if="scope.row.status == 0"><?php echo esc_html__('交易待支付', 'zib_language'); ?> {{priceFormat(scope.row.income_info.price,scope.row.pay_modo)}}</el-tag>
                        <div v-else>
                            <el-tag type="success" v-if="scope.row.income_info.status == 1">{{(scope.row.pay_modo ==='points' ? '<?php echo esc_js(__('已转入', 'zib_language')); ?>':'<?php echo esc_js(__('已提现', 'zib_language')); ?>') + priceFormat(scope.row.income_info.price,scope.row.pay_modo)}}</el-tag>
                            <el-tag type="info" v-if="scope.row.income_info.status == 0"><?php echo esc_html__('未提现', 'zib_language'); ?> {{priceFormat(scope.row.income_info.price,scope.row.pay_modo)}}</el-tag>
                            <div v-if="scope.row.income_info.status == 3">
                                <a class="el-button el-button--primary el-button--small is-plain" :href="config.admin_url + 'admin.php?page=zibpay_withdraw&status=0'" target="_blank"><?php echo esc_html__('立即处理', 'zib_language'); ?></a>
                                <div class="em09 c-yellow"><?php echo esc_html__('提现待处理', 'zib_language'); ?> {{priceFormat(scope.row.income_info.price,scope.row.pay_modo)}}</div>
                            </div>
                        </div>
                        <div class="flex ac mt6">
                            <el-avatar :size="14" class="flex0 mr6" :src="scope.row.income_info.author_info.avatar"></el-avatar>
                            <div class="em09">{{ scope.row.income_info.author_info.name}}</div>
                        </div>
                    </div>
                    <template #content>
                        <div>
                            <div class="tooltip-link-box">
                                <a :href="config.admin_url + 'admin.php?page=zibpay_income_page&post_author='+scope.row.post_author" target="_blank"><?php echo esc_html__('查看ta的明细', 'zib_language'); ?></a>
                            </div>
                        </div>
                    </template>
                </el-tooltip>
                <div v-else></div>
            </template>
        </el-table-column>

        <template #empty>
            <el-empty description="<?php echo esc_attr__('暂无数据', 'zib_language'); ?>" />
        </template>
    </el-table>


    <div class="flex je mt10">
        <el-pagination @current-change="dBPagChange('order','current')" @size-change="dBPagChange('order')"
            v-model:current-page="order_data.current_page" v-model:page-size="order_data.page_size"
            :total="order_data.total" :page-sizes="[10,20,30,50,100]" layout="total,sizes,prev,pager,next"
            :background="true" :pager-count="win.width<960 ? 5 : 7" :small="win.width<768">
        </el-pagination>
    </div>
</div>
