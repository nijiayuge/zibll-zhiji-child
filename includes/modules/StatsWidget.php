<?php
/**
 * @module  StatsWidget
 * @desc    站点数据统计小工具（WP_Widget）
 * @option  stats_widget_enabled  总开关
 * @hook    widgets_init · 注册小工具
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/StatsWidget.php`
 *          （v2 迁移：CSF 块转 csf_section_for_legacy、常量与命名规范对齐）
 */

defined('ABSPATH') || exit;

Zhiji_Registry::register_module('stats_widget', array(
    'title'    => '站点统计',
    'parent'   => 'zhiji_page',
    'priority' => 100,
    'option'   => 'stats_widget_enabled',
));



if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 内置SVG图标库（统计小工具专用）
 *
 * @return array 图标key => SVG路径
 */
function zhiji_stats_widget_svg_icons() {
    return array(
        'calendar' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
        'user'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
        'eye'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',
        'doc'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>',
        'chat'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
        'clock'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
        'gift'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/></svg>',
        'heart'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>',
        'star'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
        'trophy'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>',
    );
}

/**
 * 输出SVG图标
 *
 * @param string $key   图标key。
 * @param string $class CSS类名。
 * @return string
 */
function zhiji_stats_widget_svg_icon( $key, $class = 'icon' ) {
    $icons = zhiji_stats_widget_svg_icons();
    $svg = isset( $icons[ $key ] ) ? $icons[ $key ] : $icons['doc'];
    return '<span class="' . esc_attr( $class ) . '">' . $svg . '</span>';
}

/**
 * 统计小工具类
 */
class Zhiji_Stats_Widget extends WP_Widget {

    /**
     * 构造函数
     */
    public function __construct() {
        parent::__construct(
            'zhiji_stats_widget',
            '知集 - 站点统计看板',
            array(
                'classname'   => 'zhiji-stats-widget',
                'description' => '底部统计看板：运行天数、用户、浏览、文章、评论，支持虚拟数量叠加',
            )
        );
    }

    /**
     * 前端输出
     *
     * @param array $args     小工具参数。
     * @param array $instance 保存的实例数据。
     */
    public function widget( $args, $instance ) {
        global $wpdb;

        // 默认值
        $defaults = array(
            'title'            => '站点统计',
            'widget_kzsj'      => '2025/01/01 00:00:00',
            'virtual_posts'    => 0,
            'virtual_comments' => 0,
            'virtual_days'     => 0,
            'virtual_users'    => 0,
            'virtual_views'    => 0,
            'icon_days'        => 'calendar',
            'icon_users'       => 'user',
            'icon_views'       => 'eye',
            'icon_posts'       => 'doc',
            'icon_comments'    => 'chat',
        );
        $instance = wp_parse_args( (array) $instance, $defaults );

        // 实际数据 + 虚拟数量 = 显示数据
        $published_posts = wp_count_posts()->publish + intval( $instance['virtual_posts'] );
        $post_comments   = intval( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(comment_ID) FROM {$wpdb->comments} WHERE comment_approved = %s", '1' ) ) ) + intval( $instance['virtual_comments'] );
        $run_days        = max( 0, floor( ( time() - strtotime( $instance['widget_kzsj'] ) ) / 86400 ) + intval( $instance['virtual_days'] ) );
        $users           = intval( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(ID) FROM {$wpdb->users}" ) ) ) + intval( $instance['virtual_users'] );
        $views           = intval( $wpdb->get_var( $wpdb->prepare( "SELECT SUM(meta_value+0) FROM {$wpdb->postmeta} WHERE meta_key = %s", 'views' ) ) ) + intval( $instance['virtual_views'] );

        echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

        if ( ! empty( $instance['title'] ) ) {
            echo $args['before_title'] . esc_html( $instance['title'] ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        // 输出样式
        $this->output_style();

        // 统计项
        $items = array(
            array( 'icon' => $instance['icon_days'], 'num' => $run_days, 'label' => '运行天数' ),
            array( 'icon' => $instance['icon_users'], 'num' => $users, 'label' => '用户数量' ),
            array( 'icon' => $instance['icon_views'], 'num' => $views, 'label' => '浏览总量' ),
            array( 'icon' => $instance['icon_posts'], 'num' => $published_posts, 'label' => '文章数量' ),
            array( 'icon' => $instance['icon_comments'], 'num' => $post_comments, 'label' => '评论数量' ),
        );
        ?>
        <div class="zhiji-stats-wrap">
            <div class="zhiji-stats-list">
                <?php foreach ( $items as $item ) : ?>
                <div class="zhiji-stats-item">
                    <?php echo zhiji_stats_widget_svg_icon( $item['icon'], 'zhiji-stats-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <div class="zhiji-stats-meta">
                        <strong><?php echo esc_html( number_format_i18n( intval( $item['num'] ) ) ); ?></strong>
                        <span><?php echo esc_html( $item['label'] ); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php

        echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * 输出内联样式
     */
    private function output_style() {
        static $style_output = false;
        if ( $style_output ) {
            return;
        }
        $style_output = true;
        ?>
        <style>
        .zhiji-stats-wrap {
            background: var(--main-bg-color, #fff);
            border-radius: var(--main-radius, 8px);
            padding: 16px;
            box-shadow: 0 2px 8px var(--main-shadow, rgba(0,0,0,0.08));
        }
        .zhiji-stats-list {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }
        .zhiji-stats-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px;
            background: rgba(128, 128, 128, 0.06);
            border-radius: var(--main-radius, 8px);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .zhiji-stats-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .zhiji-stats-icon {
            width: 24px;
            height: 24px;
            flex-shrink: 0;
            color: var(--zhiji-brand, #2e7cf6);
        }
        .zhiji-stats-icon svg {
            width: 100%;
            height: 100%;
        }
        .zhiji-stats-meta {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }
        .zhiji-stats-meta strong {
            font-size: 15px;
            line-height: 1.2;
            color: var(--main-text-color, #333);
        }
        .zhiji-stats-meta span {
            font-size: 11px;
            color: var(--muted-color, #999);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        @media (max-width: 768px) {
            .zhiji-stats-list {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 480px) {
            .zhiji-stats-list {
                grid-template-columns: 1fr;
            }
        }
        </style>
        <?php
    }

    /**
     * 后台表单
     *
     * @param array $instance 保存的实例数据。
     */
    public function form( $instance ) {
        $defaults = array(
            'title'            => '站点统计',
            'widget_kzsj'      => '2025/01/01 00:00:00',
            'virtual_posts'    => 0,
            'virtual_comments' => 0,
            'virtual_days'     => 0,
            'virtual_users'    => 0,
            'virtual_views'    => 0,
            'icon_days'        => 'calendar',
            'icon_users'       => 'user',
            'icon_views'       => 'eye',
            'icon_posts'       => 'doc',
            'icon_comments'    => 'chat',
        );
        $instance = wp_parse_args( (array) $instance, $defaults );
        $icons = zhiji_stats_widget_svg_icons();
        ?>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">标题：</label>
            <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $instance['title'] ); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'widget_kzsj' ) ); ?>">建站时间：</label>
            <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'widget_kzsj' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'widget_kzsj' ) ); ?>" type="text" value="<?php echo esc_attr( $instance['widget_kzsj'] ); ?>">
            <small>格式：Y/m/d H:i:s，例如 2025/01/01 00:00:00</small>
        </p>

        <fieldset style="border:1px solid #ddd;padding:10px;margin-bottom:10px;">
            <legend><strong>虚拟数量（实际数据 + 虚拟数量 = 显示数据）</strong></legend>
            <p>
                <label for="<?php echo esc_attr( $this->get_field_id( 'virtual_days' ) ); ?>">虚拟运行天数：</label>
                <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'virtual_days' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'virtual_days' ) ); ?>" type="number" value="<?php echo esc_attr( $instance['virtual_days'] ); ?>">
            </p>
            <p>
                <label for="<?php echo esc_attr( $this->get_field_id( 'virtual_users' ) ); ?>">虚拟用户数：</label>
                <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'virtual_users' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'virtual_users' ) ); ?>" type="number" value="<?php echo esc_attr( $instance['virtual_users'] ); ?>">
            </p>
            <p>
                <label for="<?php echo esc_attr( $this->get_field_id( 'virtual_views' ) ); ?>">虚拟浏览量：</label>
                <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'virtual_views' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'virtual_views' ) ); ?>" type="number" value="<?php echo esc_attr( $instance['virtual_views'] ); ?>">
            </p>
            <p>
                <label for="<?php echo esc_attr( $this->get_field_id( 'virtual_posts' ) ); ?>">虚拟文章数：</label>
                <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'virtual_posts' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'virtual_posts' ) ); ?>" type="number" value="<?php echo esc_attr( $instance['virtual_posts'] ); ?>">
            </p>
            <p>
                <label for="<?php echo esc_attr( $this->get_field_id( 'virtual_comments' ) ); ?>">虚拟评论数：</label>
                <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'virtual_comments' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'virtual_comments' ) ); ?>" type="number" value="<?php echo esc_attr( $instance['virtual_comments'] ); ?>">
            </p>
        </fieldset>

        <fieldset style="border:1px solid #ddd;padding:10px;">
            <legend><strong>SVG图标</strong></legend>
            <p>
                <label for="<?php echo esc_attr( $this->get_field_id( 'icon_days' ) ); ?>">运行天数图标：</label>
                <select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'icon_days' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'icon_days' ) ); ?>">
                    <?php foreach ( $icons as $key => $svg ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $instance['icon_days'], $key ); ?>><?php echo esc_html( $key ); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p>
                <label for="<?php echo esc_attr( $this->get_field_id( 'icon_users' ) ); ?>">用户图标：</label>
                <select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'icon_users' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'icon_users' ) ); ?>">
                    <?php foreach ( $icons as $key => $svg ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $instance['icon_users'], $key ); ?>><?php echo esc_html( $key ); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p>
                <label for="<?php echo esc_attr( $this->get_field_id( 'icon_views' ) ); ?>">浏览图标：</label>
                <select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'icon_views' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'icon_views' ) ); ?>">
                    <?php foreach ( $icons as $key => $svg ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $instance['icon_views'], $key ); ?>><?php echo esc_html( $key ); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p>
                <label for="<?php echo esc_attr( $this->get_field_id( 'icon_posts' ) ); ?>">文章图标：</label>
                <select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'icon_posts' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'icon_posts' ) ); ?>">
                    <?php foreach ( $icons as $key => $svg ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $instance['icon_posts'], $key ); ?>><?php echo esc_html( $key ); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p>
                <label for="<?php echo esc_attr( $this->get_field_id( 'icon_comments' ) ); ?>">评论图标：</label>
                <select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'icon_comments' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'icon_comments' ) ); ?>">
                    <?php foreach ( $icons as $key => $svg ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $instance['icon_comments'], $key ); ?>><?php echo esc_html( $key ); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>
        </fieldset>
        <?php
    }

    /**
     * 保存设置
     *
     * @param array $new_instance 新实例数据。
     * @param array $old_instance 旧实例数据。
     * @return array
     */
    public function update( $new_instance, $old_instance ) {
        $instance = array();
        $instance['title']            = sanitize_text_field( $new_instance['title'] );
        $instance['widget_kzsj']      = sanitize_text_field( $new_instance['widget_kzsj'] );
        $instance['virtual_posts']    = intval( $new_instance['virtual_posts'] );
        $instance['virtual_comments'] = intval( $new_instance['virtual_comments'] );
        $instance['virtual_days']     = intval( $new_instance['virtual_days'] );
        $instance['virtual_users']    = intval( $new_instance['virtual_users'] );
        $instance['virtual_views']    = intval( $new_instance['virtual_views'] );
        $instance['icon_days']        = sanitize_text_field( $new_instance['icon_days'] );
        $instance['icon_users']       = sanitize_text_field( $new_instance['icon_users'] );
        $instance['icon_views']       = sanitize_text_field( $new_instance['icon_views'] );
        $instance['icon_posts']       = sanitize_text_field( $new_instance['icon_posts'] );
        $instance['icon_comments']    = sanitize_text_field( $new_instance['icon_comments'] );
        return $instance;
    }
}

/**
 * 注册统计小工具
 */
add_action( 'widgets_init', function () {
    if ( ! zhiji_is_enabled( 'stats_widget_enabled' ) ) {
        return;
    }
    register_widget( 'Zhiji_Stats_Widget' );
} );
