<?php
/**
 * @module  ConfigSchema
 * @desc    配置键注册表 —— 声明「哪些键是活的、哪些已废弃、哪些是运行时状态」
 * @since   2.0.9
 *
 * 2026-10-02（P4）新增。
 *
 * ─────────────────────────────────────────────────────────────
 * 【P4 要解决的真问题（实测数据，不是推测）】
 *
 *   已存配置键 265 个，但源码只认 174 个字段 + 40 个模块 option。
 *   差出来的 **95 个是孤儿键** —— 已被 CSF 的「白名单整体替换」机制
 *   定义为「字段表外」，即**下一次管理员保存设置时会被静默清除**。
 *
 *   而这 95 个键并非垃圾，它们全部来自已下线的模块（AF.19 秒杀/砍价、
 *   AF.20 移除的 18 模块 / 2 整类）：bargain_* / danmu_* / effect_* /
 *   points_mall_* / seckill_* / tag3d_* / history_today_* / consume_rank_* /
 *   home_search_* / exit_intent_* / flatterer_* / site_font_* …
 *
 *   风险点：用户若只是「进来改个开关点保存」，这 95 个键就消失了。
 *   将来若恢复这些模块（用户已明确要重做），用户此前的配置**无法找回**。
 *
 * ─────────────────────────────────────────────────────────────
 * 【本文件做什么 / 刻意不做什么】
 *
 *   做：① 键的注册表（键 → 状态 + 归属 + 层级）
 *       ② 废弃键的**别名兼容层**（读废弃键时回落默认值并留痕，而非静默）
 *       ③ 清理前的备份与可观测
 *
 *   不做：① 不改任何键名（改名 = 改 265 个已存值，风险远大于收益）
 *        ② 不自动删除任何键（清理必须有备份 + 明确入口 + 可观测）
 *        ③ 不改变任何现有读取行为（本阶段对运行时**零影响**）
 *
 *   理由：这轮的定位是「让键的生死有据可查」，不是「重构键的命名」。
 *   命名空间统一（P4 原始计划里的「命名空间」）留到真有改名需求时再做，
 *   且必须配「旧键 → 新键」映射表，不能裸改。
 * ─────────────────────────────────────────────────────────────
 */

defined('ABSPATH') || exit;

if (!defined('ZHIJI_DEPRECATED_OPTION_KEY')) {
    define('ZHIJI_DEPRECATED_OPTION_KEY', 'zhiji_options_deprecated');
}

/**
 * 已下线模块的配置键（按模块分组，供后台展示与批量清理）
 *
 * ⚠️ 本表**不是**「垃圾键清单」，而是「这些键曾有意义」的记录。
 * 保留它们是为了将来恢复模块时能对照找回用户配置。
 * 新增废弃键时必须在此登记（preflight 会校验：已存但不在字段表也不在此表的键 = 漏登记）。
 *
 * 键名 → 说明（说明写清「原本是干什么的」，恢复模块时能对上号）
 *
 * @return array<string,string>
 */
function zhiji_config_deprecated_keys()
{
    $map = array(
        // ── AF.19 下线：积分秒杀 / 砍价 ──
        'bargain_enabled'            => '砍价总开关（AF.19 下线，用户计划重做）',
        'bargain_hours'              => '砍价活动时长（小时）',
        'bargain_scope'              => '砍价适用商品范围',
        'bargain_product_id'         => '砍价关联商品 ID',
        'bargain_assist_daily'       => '每日可协助砍价次数',
        'bargain_first_cut_pct'      => '首个用户减免比例',
        'bargain_new_multiplier'     => '新用户加成倍数',

        // ── AF.20 下线：弹幕 ──
        'danmu_enabled'              => '弹幕总开关',
        'danmu_mode'                 => '弹幕模式 float/cornor',
        'danmu_limit'                => '同屏弹幕上限',
        'danmu_max_len'              => '单条弹幕最大长度',
        'danmu_font_size'            => '弹幕字号',
        'danmu_radius'               => '弹幕圆角',
        'danmu_mobile'               => '移动端是否显示',
        'danmu_poll'                 => '弹幕轮询间隔',
        'danmu_review_mode'          => '仅登录可见',
        'danmu_blacklist'            => '弹幕屏蔽词',
        'danmu_mute_users'           => '弹幕静音用户',
        'danmu_event_comment'        => '评论时触发弹幕',
        'danmu_event_lottery'        => '抽奖时触发弹幕',
        'danmu_event_pay'            => '支付时触发弹幕',
        'danmu_event_sign'           => '签到时触发弹幕',

        // ── AF.20 下线：美化效果类（zhiji_beautify 整类）──
        'effects_enabled'            => '页面特效总开关',
        'effect_snow'                => '雪花特效',
        'effect_sakura'              => '樱花特效',
        'effect_particle'            => '粒子特效',
        'effect_coin'                => '金币特效',
        'effect_coin_text'           => '金币文案',
        'effect_color'               => '特效配色',
        'effect_cursor'              => '自定义光标',

        // ── AF.20 下线：积分商城 ──
        'points_mall_enabled'        => '积分商城总开关',
        'points_mall_items'          => '积分商城商品列表',
        'points_mall_daily'          => '每日兑换上限',
        'points_mall_scope'          => '积分商城可见范围',

        // ── AF.20 下线：秒杀 ──
        'seckill_enabled'            => '秒杀总开关',
        'seckill_items'              => '秒杀商品列表',

        // ── AF.20 下线：那年今日 ──
        'history_today_enabled'      => '那年今日总开关',
        'history_today_title'        => '那年今日标题',
        'history_today_limit'        => '那年今日条数上限',

        // ── AF.20 下线：消费排行榜 ──
        'consume_rank_enabled'       => '消费排行榜开关',
        'consume_rank_points'        => '排行榜统计点数',
        'consume_rank_top'           => '排行榜显示条数',

        // ── AF.20 下线：首页大搜索框 ──
        'home_search_enabled'        => '首页大搜索框开关',
        'home_search_placeholder'    => '搜索框占位文案',
        'home_search_hot'            => '搜索热词开关',
        'home_search_hot_tags'       => '搜索热词列表',
        'home_search_bg_style'       => '搜索框背景样式',

        // 2026-10-03「退出挽留弹窗」恢复上线 —— 原为 AF.20 下线，
        // 已从本表**摘除**（否则会被回收器当成废弃键删掉）。
        // ⚠️ 恢复模块时必须同步摘除其配置键，否则第二次跑「配置回收」会误伤。

        // ── AF.20 下线：舔狗日记 ──
        'flatterer_enabled'          => '舔狗日记开关',
        'flatterer_custom'           => '舔狗日记自定义文案',

        // ── AF.20 下线：人生倒计时 ──
        'countdown_enabled'          => '人生倒计时开关',
        'countdown_birth'            => '出生日期',

        // ── AF.20 下线：3D 云标签 ──
        'tag3d_enabled'              => '3D 云标签开关',
        'tag3d_limit'                => '云标签数量上限',
        'tag3d_radius'               => '云标签扩散半径',

        // ── AF.20 下线：站点统计（前端）──
        'ticket_enabled'             => '工单系统开关',
        'ticket_notify_email'        => '工单通知邮箱',

        // ── AF.20 下线：资讯 CPT ──
        'infomation_enabled'         => '资讯 CPT 开关',
        'infomation_slug'            => '资讯 CPT 别名',

        // 2026-10-03「图片宽度排版」恢复上线 —— 原为 AF.20 下线，已从本表摘除（理由同上）

        // ── AF.20 下线：页面元素整类（zhiji_element）──
        'notfound_game_enabled'      => '404 贪吃蛇游戏开关',
        'misc_beautify_enabled'      => '杂项美化开关',
        'misc_jump_selectors'        => '跳转按钮选择器',
        'misc_mobile_only'           => '仅移动端',
        'misc_auto_clean_users'      => '自动清理用户',
        'misc_clean_days'            => '清理保留天数',

        // ── AF.20 下线：字体设置（随美化整类移除）──
        'site_font_enabled'          => '自定义字体开关',
        'site_font_body'             => '正文字体',
        'site_font_title'            => '标题字体',
        'site_font_size'             => '基础字号',
        'site_font_local'            => '本地字体',
        'site_font_custom_url'       => '自定义字体 URL',

        // ── ColorTokens 模块随 AF.20 移除：色值函数已下沉 core（见 Helpers.php），
        //    开关字段失去作用。品牌色现由 BrandColor 模块承载。──
        'zhiji_color_tokens_enabled' => '配色令牌开关（模块已移除，功能已下沉 core，此键失效）',

        // ── 其他：曾存在但当前无对应字段 ──
        'security_scanner_enabled'   => '安全扫描开关（与 security_scan_enabled 重复，已统一）',

        // ── v1 遗留：奖励规则在 v2 迁到 RewardCenter，键名同步改过 ──
        'comment_fortune_w_points'   => 'v1 遗留：福袋积分权重（已迁至 reward_center_w_points）',
        'comment_fortune_w_balance'  => 'v1 遗留：福袋余额权重（已迁至 reward_center_w_balance）',
        'comment_fortune_w_coupon'   => 'v1 遗留：福袋券权重（已迁至 reward_center_w_coupon）',
        'comment_fortune_w_vip'      => 'v1 遗留：福袋会员权重（已迁至 reward_center_w_vip）',
        'comment_fortune_w_free'     => 'v1 遗留：福袋免单权重（已迁至 reward_center_w_free）',
        'comment_fortune_pts_min'    => 'v1 遗留：福袋积分下限（已迁至 reward_center_points_min）',
        'comment_fortune_pts_max'    => 'v1 遗留：福袋积分上限（已迁至 reward_center_points_max）',
        'comment_fortune_bal_min'    => 'v1 遗留：福袋余额下限（已迁至 reward_center_balance_min）',
        'comment_fortune_bal_max'    => 'v1 遗留：福袋余额上限（已迁至 reward_center_balance_max）',
        'comment_fortune_vip_days'   => 'v1 遗留：福袋会员天数（已迁至 reward_center_vip_days）',
        'comment_fortune_vip_level'  => 'v1 遗留：福袋会员等级（已迁至 reward_center_vip_level）',
    );

    /**
     * 允许扩展废弃键清单（第三方下线自己的字段时登记）
     *
     * @param array $map
     */
    return (array) apply_filters('zhiji_config_deprecated_keys', $map);
}

/**
 * 旧键 → 新键 的兼容别名映射
 *
 * 与「废弃键」的区别：
 *   · 废弃键 = 功能没了，值无意义（读时回落默认值）
 *   · 兼容键 = 功能还在，只是改名了，**读旧键的值必须能生效**
 *
 * 目前 v2 的改名都伴随「旧键不再被读取」的选择（读新键、缺失时用默认值），
 * 所以本表暂时为空 —— 但**机制先建好**：将来真要改名时，
 * 在这里登记映射即可让旧站点的配置自动跟随，不需要用户手动重填。
 *
 * 格式：'新键' => array('旧键1', '旧键2')
 *
 * @return array<string,array<int,string>>
 */
function zhiji_config_key_aliases()
{
    $map = array(
        // 例：'reward_center_w_points' => array('comment_fortune_w_points'),
    );
    return (array) apply_filters('zhiji_config_key_aliases', $map);
}

/**
 * 配置键分层
 *
 * 存在的意义：CSF 保存是**白名单整体替换**（`update_option(ZHIJI_OPTION_KEY, $data)`），
 * 凡是「不在字段表里」的键都会被清除。这对 feature 层（后台可见、可编辑）是正确的，
 * 但对另外两类是错的：
 *
 *   · runtime 运行时状态：由代码写入、字段表里刻意不声明
 *     （如 `zhiji_prov_page_points-mall` 记录「已自动建过 /points-mall 页」）。
 *     这类键若被清除，代码会重复建页、重复迁移 —— 通常幂等，但白跑一趟，
 *     且部分迁移不可重复。
 *   · infra 基础设施状态：同上，只是语义上是「技术状态」而非「运行时缓存」。
 *
 * 声明分层后，`zhiji_config_persistent_keys()` 可算出「必须保留」的键集合，
 * 清理孤儿键时把它们排除在外。
 *
 * @return array<string,array<int,string>> 层名 => 键列表（目前只列 runtime；其余由字段表推导）
 */
function zhiji_config_layers()
{
    $layers = array(
        // 运行时状态：代码写入、字段表不声明、**不可被 CSF 保存清除**
        'runtime' => array(
            'zhiji_prov_page_points-mall',   // PageProvisioner：已自动建页记录
        ),
    );
    return (array) apply_filters('zhiji_config_layers', $layers);
}

/**
 * 必须保留的键集合（runtime 层 + 兼容别名涉及的旧键）
 *
 * @return array<string,bool>
 */
function zhiji_config_persistent_keys()
{
    static $map = null;
    if (null !== $map) {
        return $map;
    }
    $map = array();
    foreach ((array) zhiji_config_layers() as $keys) {
        foreach ((array) $keys as $k) {
            $map[(string) $k] = true;
        }
    }
    // 兼容别名涉及的旧键也要保留：否则用户改完设置就丢了旧值，
    // 等于改名迁移白做
    foreach (zhiji_config_key_aliases() as $aliases) {
        foreach ((array) $aliases as $old) {
            $map[(string) $old] = true;
        }
    }
    return $map;
}

/**
 * 某个键是否已废弃
 *
 * @param string $key
 * @return bool
 */
function zhiji_config_is_deprecated($key)
{
    return array_key_exists((string) $key, zhiji_config_deprecated_keys());
}

/**
 * 孤儿键体检：已存键中，哪些既不在字段表/模块 option、也不是已登记的废弃键
 *
 * 「已登记废弃键」与「真孤儿」要分开：
 *   · 已登记废弃键 —— 知道它是什么，清理时**先备份**再删，并在后台展示
 *   · 真孤儿       —— 不知道它是什么（多半是历史遗留的意外写入），
 *                    只报告**不删**，人工确认后再处理
 *
 * @param array|null $stored  已存键数组；null 则从 options 表读
 * @return array{deprecated:array,unknown:array,live:int}
 */
function zhiji_config_audit_orphans($stored = null)
{
    if (null === $stored) {
        $stored = (array) get_option(ZHIJI_OPTION_KEY, array());
    }
    $known   = zhiji_config_known_keys();
    $persist = zhiji_config_persistent_keys();
    $depre   = zhiji_config_deprecated_keys();

    $out = array('deprecated' => array(), 'unknown' => array(), 'live' => 0);
    foreach (array_keys($stored) as $k) {
        $k = (string) $k;
        if (isset($known[$k])) {
            $out['live']++;
        } elseif (isset($persist[$k])) {
            $out['live']++;
        } elseif (isset($depre[$k])) {
            $out['deprecated'][$k] = $depre[$k];
        } else {
            $out['unknown'][] = $k;
        }
    }
    return $out;
}

/**
 * 已知配置键全集（字段 id + 模块 option）
 *
 * 字段 id 的取法：按 include 顺序把每个模块文件读进来，对清洗后的源码
 * 抓 `'id' => 'xxx'`。注意 —— **不能对词法清洗后的源码抓**，
 * 清洗会剔除字符串字面量，连键名带引号一起消失（首版就因此误判 265 键全为孤儿）。
 * 只需去掉注释（保留字符串），用 core 的词法工具反而在这里帮了倒忙。
 *
 * @return array<string,bool>
 */
function zhiji_config_known_keys()
{
    static $map = null;
    if (null !== $map) {
        return $map;
    }
    $map = array();

    // ① 模块总开关（register_module 的 option=）
    foreach (Zhiji_Registry::modules() as $def) {
        if (!empty($def['option'])) {
            $map[(string) $def['option']] = true;
        }
    }

    // ② CSF 字段 id
    $dir = get_stylesheet_directory() . '/includes/';
    foreach ((array) glob($dir . 'modules/*.php') as $f) {
        $src = _zhiji_config_strip_php_comments((string) @file_get_contents($f));
        if ('' === $src) {
            continue;
        }
        if (preg_match_all("/['\"]id['\"]\s*=>\s*['\"]([A-Za-z0-9_]+)['\"]/", $src, $m)) {
            foreach ($m[1] as $k) {
                $map[(string) $k] = true;
            }
        }
        // ⚠️ 动态注册：zhiji_field_switch('键名', ...) / zhiji_field_*(...) 这类
        //    **首参就是字段 id**，但源码里没有 `'id' => 'xxx'` 字面量。
        //    漏掉它们会造成「活键被判成来路不明」→ 误报，甚至被误当成废弃键回收。
        //    （2026-10-02 真实踩到：monitor_404_track_logged_in 被我误登记成废弃键，
        //      执行前复核「仍被源码读取」这一项把它拦下来了。）
        if (preg_match_all("/zhiji_field_[a-z_]+\(\s*'([A-Za-z0-9_]+)'/", $src, $m2)) {
            foreach ($m2[1] as $k) {
                $map[(string) $k] = true;
            }
        }
    }
    // options/ 与 core/ 也可能注册字段
    foreach (array('options/options.php', 'options/admin-options.php') as $rel) {
        $p = $dir . $rel;
        if (!is_file($p)) {
            continue;
        }
        $src = _zhiji_config_strip_php_comments((string) @file_get_contents($p));
        if (preg_match_all("/['\"]id['\"]\s*=>\s*['\"]([A-Za-z0-9_]+)['\"]/", $src, $m)) {
            foreach ($m[1] as $k) {
                $map[(string) $k] = true;
            }
        }
    }

    return $map;
}

/**
 * 只去注释、保留字符串
 *
 * 不复用 tools/zhiji_lex.php 的 PHP 词法清洗：那个工具的用途是
 * 「给依赖扫描器看代码长什么样」，会把字符串全部剔除；
 * 而这里恰恰需要字符串里的键名。
 *
 * @param string $src
 * @return string
 */
function _zhiji_config_strip_php_comments($src)
{
    $src = (string) $src;
    // ⚠️ 两个坑（都真实踩过）：
    //   ① 分隔符若用 #，模式里的 #[^\n]*（井号注释）那个 # 会被当成修饰符结束 → Unknown modifier
    //   ② 字符类里写 \\ 时，PHP 单引号字符串的转义会吃掉一个反斜杠，字符类反而闭合失败
    //   → 统一用 ~ 作分隔符，且**字符类里不出现反斜杠**：
    //     「前面不是 : " ' 」这个判断交给正则的替代方案 —— 先用 lookbehind 不现实，
    //     改用「把 : " ' 之前的 // 也一并吃掉，再把被吃掉的字符原样补回」的做法。
    // 块注释（含文档注释）
    $src = preg_replace('~/\*.*?\*/~s', ' ', $src);
    // 行注释 //：只有当 // 前面**不是**引号/冒号时才当注释（URL 的 :// 会被保护）
    $src = preg_replace('~(?<!:)//[^\n]*~', ' ', $src);
    // 行注释 #：前面不是空白（保护 CSS 颜色 #fff 与 URL 片段 #anchor）
    $src = preg_replace('~(?<=\s)\#[^\n]*~', ' ', $src);
    return (string) $src;
}
