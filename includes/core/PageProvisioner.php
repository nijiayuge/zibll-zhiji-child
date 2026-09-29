<?php
/**
 * @module  PageProvisioner
 * @desc    模块启用时自动创建前台页面（统一建页基建）
 *
 *          设计目标：把"模块需要前台页面"这件事从各模块散落逻辑收敛为一处。
 *          模块在 register_module() 的 'pages' 键声明所需页面即可，本类负责：
 *            - 幂等创建：按 slug + 已存 option id 双判重，绝不重复建页
 *            - 回收站恢复：页面被误删进回收站时，自动恢复而非新建重复页
 *            - slug 冲突后缀：目标 slug 被其它页面占用时追加 -2 / -3
 *            - 禁用不删页：模块关闭只跳过创建，绝不删除已建页面
 *            - 手动同步：admin-post 端点（nonce + manage_options 能力校验）
 *
 *          声明示例（模块文件内）：
 *          Zhiji_Registry::register_module('points_mall', array(
 *              'title' => '积分商城', 'parent' => 'zhiji_user',
 *              'pages' => array(
 *                  array('slug' => 'points-mall', 'title' => '积分商城', 'content' => '[zhiji_points_mall]'),
 *              ),
 *          ));
 *
 * @since   2.0.0（批次C 批2，2026-09-29）
 */

defined('ABSPATH') || exit;

class Zhiji_PageProvisioner
{
    /** 页面 id 在 zhiji_options 中的键前缀 */
    const OPT_KEY_PREFIX = 'zhiji_prov_page_';

    /**
     * 为单个已注册模块建页（幂等；禁用模块直接跳过，不删页）
     *
     * @param string $key 模块 key
     * @return array
     */
    public static function provision_module($key)
    {
        $mods = Zhiji_Registry::modules();
        if (!isset($mods[$key])) {
            return array();
        }
        $pages = isset($mods[$key]['pages']) ? (array) $mods[$key]['pages'] : array();
        if (empty($pages)) {
            return array();
        }
        // 禁用不删页：仅启用模块才建页；关闭时直接返回，不动已有页
        if (!Zhiji_Registry::module_enabled($key)) {
            return array();
        }
        $stat = array('created' => 0, 'skipped' => 0, 'adopted' => 0, 'restored' => 0, 'errors' => array());
        foreach ($pages as $spec) {
            $r = self::ensure_one($spec);
            if ('created' === $r) {
                $stat['created']++;
            } elseif ('skipped' === $r) {
                $stat['skipped']++;
            } elseif ('adopted' === $r) {
                $stat['adopted']++;
            } elseif ('restored' === $r) {
                $stat['restored']++;
            } elseif (is_string($r)) {
                $stat['errors'][] = $r;
            }
        }
        return $stat;
    }

    /**
     * 全量自检（遍历所有已注册模块；当前仅在 admin 上下文触发）
     *
     * @return array 聚合统计
     */
    public static function provision_all()
    {
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return array();
        }
        $total = array('created' => 0, 'skipped' => 0, 'adopted' => 0, 'restored' => 0, 'errors' => array());
        foreach (array_keys(Zhiji_Registry::modules()) as $key) {
            $s = self::provision_module($key);
            if (empty($s)) {
                continue;
            }
            foreach (array('created', 'skipped', 'adopted', 'restored') as $k) {
                $total[$k] += (int) $s[$k];
            }
            if (!empty($s['errors'])) {
                $total['errors'] = array_merge($total['errors'], $s['errors']);
            }
        }
        return $total;
    }

    /**
     * 确保单个页面存在（幂等核心）
     *
     * @param array $spec slug / title / content / template / status
     * @return string 'created' | 'skipped' | 'adopted' | 'restored' | 错误描述
     */
    protected static function ensure_one(array $spec)
    {
        $slug = isset($spec['slug']) ? sanitize_title($spec['slug']) : '';
        if ('' === $slug) {
            return 'slug 为空，跳过';
        }
        $opt_key = self::OPT_KEY_PREFIX . $slug;
        $content = isset($spec['content']) ? (string) $spec['content'] : '';
        $title   = isset($spec['title']) ? sanitize_text_field($spec['title']) : $slug;
        $status  = isset($spec['status']) ? $spec['status'] : 'publish';
        $template = isset($spec['template']) ? sanitize_text_field($spec['template']) : '';

        // 1) 已存 id 且仍有效（非回收站）→ 跳过（轻量补内容可选，此处不重建）
        $pid = (int) zhiji_get_option($opt_key, 0);
        if ($pid) {
            $post = get_post($pid);
            if ($post && 'trash' !== $post->post_status) {
                return 'skipped';
            }
            // 存的是回收站里的页 → 走恢复分支
        }

        // 2) 回收站里有同名 slug → 恢复（而非新建重复页）
        $trashed = self::find_by_slug($slug, 'trash');
        if ($trashed) {
            wp_update_post(array('ID' => $trashed->ID, 'post_status' => $status));
            if ($content && $trashed->post_content !== $content) {
                wp_update_post(array('ID' => $trashed->ID, 'post_content' => wp_kses_post($content)));
            }
            if ($template) {
                update_post_meta($trashed->ID, '_wp_page_template', $template);
            }
            zhiji_update_option($opt_key, (int) $trashed->ID);
            return 'restored';
        }

        // 3) 已有发布页同 slug → 采用（记录 id，不重建）
        $exist = self::find_by_slug($slug, 'publish');
        if ($exist) {
            zhiji_update_option($opt_key, (int) $exist->ID);
            return 'adopted';
        }

        // 4) slug 被其它页面占用 → 追加后缀
        $final_slug = $slug;
        if (self::slug_taken_by_other($slug)) {
            $i = 2;
            while (self::slug_taken_by_other($slug . '-' . $i)) {
                $i++;
            }
            $final_slug = $slug . '-' . $i;
        }

        // 5) 创建
        $new = wp_insert_post(array(
            'post_title'     => $title,
            'post_name'      => $final_slug,
            'post_status'    => $status,
            'post_type'      => 'page',
            'post_content'   => wp_kses_post($content),
            'comment_status' => 'closed',
        ), true);
        if (is_wp_error($new)) {
            return '创建失败：' . $new->get_error_message();
        }
        if ($template) {
            update_post_meta((int) $new, '_wp_page_template', $template);
        }
        zhiji_update_option($opt_key, (int) $new);
        return 'created';
    }

    /**
     * 按 slug + 状态查页面（get_page_by_path 对回收站不可靠，故直接查询）
     *
     * @param string $slug
     * @param string $status
     * @return WP_Post|null
     */
    protected static function find_by_slug($slug, $status)
    {
        $posts = get_posts(array(
            'name'        => $slug,
            'post_type'   => 'page',
            'post_status' => $status,
            'numberposts' => 1,
        ));
        return !empty($posts) ? $posts[0] : null;
    }

    /**
     * slug 是否被（发布态）其它页面占用
     *
     * @param string $slug
     * @return bool
     */
    protected static function slug_taken_by_other($slug)
    {
        $ids = get_posts(array(
            'name'        => $slug,
            'post_type'   => 'page',
            'post_status' => 'publish',
            'numberposts' => 1,
            'fields'      => 'ids',
        ));
        return !empty($ids);
    }

    /**
     * 手动同步入口（admin-post 端点）
     */
    public static function admin_sync()
    {
        if (!current_user_can('manage_options')) {
            wp_die('无权限');
        }
        check_admin_referer('zhiji_provision_pages', '_zhiji_prov_nonce');
        $stat = self::provision_all();
        $ref  = isset($_POST['_wp_http_referer'])
            ? esc_url_raw(wp_unslash($_POST['_wp_http_referer']))
            : admin_url();
        wp_redirect(add_query_arg('zhiji_prov_msg', urlencode(self::format_stat($stat)), $ref));
        exit;
    }

    /**
     * 统计转人话
     *
     * @param array $stat
     * @return string
     */
    public static function format_stat(array $stat)
    {
        if (empty($stat)) {
            return '无模块声明页面，未变更';
        }
        $parts = array();
        foreach (array('created' => '新建', 'restored' => '回收站恢复', 'adopted' => '采用已有', 'skipped' => '已存在跳过') as $k => $label) {
            if (!empty($stat[$k])) {
                $parts[] = $label . ' ' . $stat[$k];
            }
        }
        if (!empty($stat['errors'])) {
            $parts[] = '错误：' . implode('；', $stat['errors']);
        }
        return $parts ? implode('，', $parts) : '无需变更';
    }
}

/* ============================================================
 * 挂载：admin_init 自动自检（仅 admin、非 AJAX）；手动同步端点
 * ============================================================ */
add_action('admin_init', array('Zhiji_PageProvisioner', 'provision_all'));
add_action('admin_post_zhiji_provision_pages', array('Zhiji_PageProvisioner', 'admin_sync'));

/**
 * 取已建页面 id（模块用来拼前台链接）
 *
 * @param string $slug
 * @return int
 */
function zhiji_provisioner_page_id($slug)
{
    return (int) zhiji_get_option(Zhiji_PageProvisioner::OPT_KEY_PREFIX . sanitize_title($slug), 0);
}

/**
 * 取已建页面链接
 *
 * @param string $slug
 * @return string
 */
function zhiji_provisioner_page_url($slug)
{
    $id = zhiji_provisioner_page_id($slug);
    return $id ? (string) get_permalink($id) : '';
}

/**
 * 手动同步按钮 HTML（供后台合适位置调用）
 *
 * @return string
 */
function zhiji_page_provisioner_sync_button()
{
    $ref = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';
    ob_start();
    ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block">
        <?php wp_nonce_field('zhiji_provision_pages', '_zhiji_prov_nonce'); ?>
        <input type="hidden" name="action" value="zhiji_provision_pages" />
        <input type="hidden" name="_wp_http_referer" value="<?php echo esc_attr($ref); ?>" />
        <?php submit_button('同步模块页面', 'secondary', '', false); ?>
    </form>
    <?php
    return ob_get_clean();
}

/**
 * 仅在「本主题设置页」且确有模块声明页面时，提示手动同步入口
 */
add_action('admin_notices', function () {
    if (!is_admin()) {
        return;
    }
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    if ($page !== ZHIJI_OPTION_KEY) {
        return;
    }
    $has = false;
    foreach (Zhiji_Registry::modules() as $m) {
        if (!empty($m['pages'])) {
            $has = true;
            break;
        }
    }
    if (!$has) {
        return;
    }
    $msg = isset($_GET['zhiji_prov_msg']) ? wp_kses_post(wp_unslash($_GET['zhiji_prov_msg'])) : '';
    ?>
    <div class="notice notice-info is-dismissible">
        <p>有模块声明了前台页面，系统会在访问后台时自动创建；如需立即同步可点：
            <?php echo zhiji_page_provisioner_sync_button(); ?>
            <?php if ($msg) : ?>
                <span style="margin-left:8px;color:#1a7f43">✓ <?php echo esc_html($msg); ?></span>
            <?php endif; ?>
        </p>
    </div>
    <?php
});
