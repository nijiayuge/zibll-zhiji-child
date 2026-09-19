<?php
/**
 * @module  SkinSwitcher
 * @desc    皮肤选择器：多套皮肤一键切换并记住选择，配色取自「实物配色卡」自动派生
 *          皮肤变量用 html:root / html.dark-theme:root 覆盖父主题 zibll 的
 *          --theme-color / --body-bg-color / --main-bg-color / --main-color 等，
 *          从而作用于整体界面（不是只改几个按钮）。
 * @option  skin_switcher_enabled   总开关
 * @hook    wp_head(999) 输出皮肤变量；wp_footer 输出切换器 UI 与全部皮肤数据
 * @since   2.0.0
 */

defined('ABSPATH') || exit;

/* ============================================================
 * 皮肤数据
 * 由 tools/gen_skins.py 依据「实物配色卡」生成；请勿手工改动标记区间内的结构。
 * 重新生成：python tools/gen_skins.py
 * ============================================================ */

// === SKINS-DATA-START ===
function zhiji_skins()
{
    return array(
        'palm' => array(
            'label'   => '棕榈清风',
            'note'    => '冷灰蓝底 + 橄榄绿，清爽耐看（实物配色卡：夏天/PalmTones.png）',
            'card'    => '夏天/PalmTones.png',
            'palette' => array('#D8DCE6', '#BBCFDB', '#3C363E', '#72825C', '#877D7E'),
            'preview' => array('#74904E', '#6693AD', '#EFF0F4', '#352F37'),
            'tokens'  => array(
                'light' => array(
                    'bg' => '#EFF0F4',
                    'surface' => '#FAFBFC',
                    'text' => '#352F37',
                    'muted' => '#969399',
                    'border' => '#D5D5DA',
                    'brand' => '#74904E',
                    'brand_dark' => '#596F3C',
                    'brand_weak' => '#EEF2EA',
                    'accent' => '#6693AD',
                ),
                'dark' => array(
                    'bg' => '#363138',
                    'surface' => '#3B353D',
                    'text' => '#E5E8EE',
                    'muted' => '#9C9BA2',
                    'border' => '#59565C',
                    'brand' => '#98B373',
                    'brand_dark' => '#74904E',
                    'brand_weak' => '#44463D',
                    'accent' => '#81A6BB',
                ),
            ),
        ),
        'agate' => array(
            'label'   => '玛瑙石板',
            'note'    => '石板蓝 + 淡紫，沉静高级（实物配色卡：矿物/AgateBlues510.png）',
            'card'    => '矿物/AgateBlues510.png',
            'palette' => array('#D2CBC3', '#5D6E86', '#C7B5E3', '#C2D9F8', '#F2F1E1'),
            'preview' => array('#4F6C94', '#8F6BC7', '#FAF9F2', '#2A313C'),
            'tokens'  => array(
                'light' => array(
                    'bg' => '#FAF9F2',
                    'surface' => '#FDFDFB',
                    'text' => '#2A313C',
                    'muted' => '#96999B',
                    'border' => '#DDDDD9',
                    'brand' => '#4F6C94',
                    'brand_dark' => '#3D5473',
                    'brand_weak' => '#EAEDF2',
                    'accent' => '#8F6BC7',
                ),
                'dark' => array(
                    'bg' => '#546379',
                    'surface' => '#5B6C83',
                    'text' => '#F6F6EB',
                    'muted' => '#B2B8BB',
                    'border' => '#748090',
                    'brand' => '#B1C0D5',
                    'brand_dark' => '#4F6C94',
                    'brand_weak' => '#53657F',
                    'accent' => '#C7B5E3',
                ),
            ),
        ),
        'pear' => array(
            'label'   => '蜜梨暖棕',
            'note'    => '暖棕 + 蜜金，温润亲和（实物配色卡：可食用的/PearTones605.png）',
            'card'    => '可食用的/PearTones605.png',
            'palette' => array('#CCA682', '#945638', '#D9C289', '#E3DDD3', '#6C706D'),
            'preview' => array('#945638', '#AD8B38', '#F3F1ED', '#4A2B1C'),
            'tokens'  => array(
                'light' => array(
                    'bg' => '#F3F1ED',
                    'surface' => '#FCFBFA',
                    'text' => '#4A2B1C',
                    'muted' => '#A29289',
                    'border' => '#DBD5D0',
                    'brand' => '#945638',
                    'brand_dark' => '#6F402A',
                    'brand_weak' => '#F2EBE7',
                    'accent' => '#AD8B38',
                ),
                'dark' => array(
                    'bg' => '#854D32',
                    'surface' => '#915437',
                    'text' => '#EDE9E2',
                    'muted' => '#C1A798',
                    'border' => '#9A6C55',
                    'brand' => '#D8AC98',
                    'brand_dark' => '#945638',
                    'brand_weak' => '#884F33',
                    'accent' => '#CCAD61',
                ),
            ),
        ),
        'chirp' => array(
            'label'   => '青碧金羽',
            'note'    => '青碧 + 麦金，清新明快（实物配色卡：家禽鸟类/AutumnChirp600.png）',
            'card'    => '家禽鸟类/AutumnChirp600.png',
            'palette' => array('#E8E7CF', '#659C9A', '#C78917', '#826E5F', '#FAB68E'),
            'preview' => array('#509391', '#B77E15', '#F5F5EB', '#3B322B'),
            'tokens'  => array(
                'light' => array(
                    'bg' => '#F5F5EB',
                    'surface' => '#FCFCF9',
                    'text' => '#3B322B',
                    'muted' => '#9C978F',
                    'border' => '#DBDAD0',
                    'brand' => '#509391',
                    'brand_dark' => '#3E7270',
                    'brand_weak' => '#EAF2F2',
                    'accent' => '#B77E15',
                ),
                'dark' => array(
                    'bg' => '#756356',
                    'surface' => '#7F6C5D',
                    'text' => '#F0EFDF',
                    'muted' => '#BCB4A5',
                    'border' => '#8E7F71',
                    'brand' => '#A7CFCE',
                    'brand_dark' => '#509391',
                    'brand_weak' => '#6D6E63',
                    'accent' => '#ECB756',
                ),
            ),
        ),
        'window' => array(
            'label'   => '朱窗杏橙',
            'note'    => '朱红 + 杏橙，热烈活力（实物配色卡：古董/ColorWindow605.png）',
            'card'    => '古董/ColorWindow605.png',
            'palette' => array('#F4EDF5', '#DBD8ED', '#E1D7A0', '#E06661', '#FFC08C'),
            'preview' => array('#D73932', '#E56700', '#FAF7FB', '#551311'),
            'tokens'  => array(
                'light' => array(
                    'bg' => '#FAF7FB',
                    'surface' => '#FEFDFE',
                    'text' => '#551311',
                    'muted' => '#AB8A8B',
                    'border' => '#E3D7DA',
                    'brand' => '#D73932',
                    'brand_dark' => '#B32923',
                    'brand_weak' => '#FAE7E6',
                    'accent' => '#E56700',
                ),
                'dark' => array(
                    'bg' => '#CA5C57',
                    'surface' => '#DC645F',
                    'text' => '#F8F3F8',
                    'muted' => '#E5B4B4',
                    'border' => '#D37A77',
                    'brand' => '#F5CDCC',
                    'brand_dark' => '#D73932',
                    'brand_weak' => '#CD544F',
                    'accent' => '#FFD8B7',
                ),
            ),
        ),
        'rose' => array(
            'label'   => '秋暮玫蓝',
            'note'    => '玫红 + 石板蓝，文艺柔和（实物配色卡：自然/FallSpectrum605.png）',
            'card'    => '自然/FallSpectrum605.png',
            'palette' => array('#545F6D', '#CA798B', '#B8C8D2', '#F2B780', '#9E525B'),
            'preview' => array('#BA5067', '#D06F15', '#E1E8EC', '#2C323A'),
            'tokens'  => array(
                'light' => array(
                    'bg' => '#E1E8EC',
                    'surface' => '#F6F8FA',
                    'text' => '#2C323A',
                    'muted' => '#8A9197',
                    'border' => '#C8CFD3',
                    'brand' => '#BA5067',
                    'brand_dark' => '#9A3D51',
                    'brand_weak' => '#F7EAED',
                    'accent' => '#D06F15',
                ),
                'dark' => array(
                    'bg' => '#4C5662',
                    'surface' => '#525D6B',
                    'text' => '#D0DBE1',
                    'muted' => '#99A3AC',
                    'border' => '#66717B',
                    'brand' => '#DBA4B0',
                    'brand_dark' => '#BA5067',
                    'brand_weak' => '#645563',
                    'accent' => '#EC9341',
                ),
            ),
        ),
    );
}
// === SKINS-DATA-END ===

/**
 * 可用皮肤 slug 列表
 *
 * @return array
 */
function zhiji_skin_slugs()
{
    return array_keys(zhiji_skins());
}

/**
 * 后台允许展示的皮肤（选项为空时=全部）
 *
 * @return array
 */
function zhiji_skin_allowed()
{
    $allowed = (array) zhiji_get_option('skin_switcher_allow', array());
    $allowed = array_values(array_intersect($allowed, zhiji_skin_slugs()));
    return $allowed ? $allowed : zhiji_skin_slugs();
}

/**
 * 默认皮肤 slug
 *
 * @return string
 */
function zhiji_skin_default()
{
    $d = (string) zhiji_get_option('skin_switcher_default', '');
    if (in_array($d, zhiji_skin_allowed(), true)) {
        return $d;
    }
    $allowed = zhiji_skin_allowed();
    return $allowed ? $allowed[0] : '';
}

/**
 * 当前生效的皮肤 slug：cookie（游客可用）→ 用户 meta（登录用户）→ 默认值
 *
 * @return string
 */
function zhiji_skin_active()
{
    $allowed = zhiji_skin_allowed();

    $cookie = isset($_COOKIE['zhiji_skin']) ? sanitize_key(wp_unslash($_COOKIE['zhiji_skin'])) : '';
    if ($cookie && in_array($cookie, $allowed, true)) {
        return $cookie;
    }

    if (is_user_logged_in()) {
        $meta = sanitize_key((string) get_user_meta(get_current_user_id(), 'zhiji_skin', true));
        if ($meta && in_array($meta, $allowed, true)) {
            return $meta;
        }
    }

    return zhiji_skin_default();
}

/**
 * 取某套皮肤的数据
 *
 * @param string $slug
 * @return array
 */
function zhiji_skin_get($slug)
{
    $skins = zhiji_skins();
    return isset($skins[$slug]) ? $skins[$slug] : array();
}

/* ============================================================
 * 输出：皮肤 CSS 变量
 * ============================================================ */

/**
 * 生成一套皮肤的 CSS 变量声明（浅色 + 暗色）
 *
 * @param array $tokens
 * @return string
 */
function zhiji_skin_css_vars($tokens)
{
    $light = isset($tokens['light']) ? (array) $tokens['light'] : array();
    $dark  = isset($tokens['dark']) ? (array) $tokens['dark'] : array();
    if (!$light) {
        return '';
    }

    // 变量名 → 父主题变量映射（皮肤只有改这些才真正"作用于整体界面"）
    $map = array(
        'brand'        => array('zhiji-brand', 'theme-color', 'focus-color'),
        'bg'           => array('zhiji-bg', 'body-bg-color'),
        'surface'      => array('zhiji-surface', 'main-bg-color'),
        'text'         => array('zhiji-text', 'main-color'),
        'muted'        => array('zhiji-muted', 'muted-color'),
        'border'       => array('zhiji-border', 'main-border-color'),
        'brand_weak'   => array('zhiji-brand-weak', 'focus-shadow-color'),
        'accent'       => array('zhiji-accent'),
        'brand_dark'   => array('zhiji-brand-dark'),
    );

    $build = function ($set) use ($map) {
        $out = array();
        foreach ($map as $key => $vars) {
            if (empty($set[$key])) {
                continue;
            }
            foreach ($vars as $v) {
                $out[] = '--' . $v . ':' . $set[$key];
            }
        }
        return implode(';', $out);
    };

    $css  = 'html:root{' . $build($light) . '}';
    if ($dark) {
        $css .= 'html.dark-theme:root{' . $build($dark) . '}';
    }
    return $css;
}

/**
 * wp_head 末位输出：当前皮肤变量（服务端渲染，避免刷新闪烁）
 */
add_action('wp_head', function () {
    if (!zhiji_is_enabled('skin_switcher_enabled')) {
        return;
    }
    $skin = zhiji_skin_get(zhiji_skin_active());
    if (empty($skin['tokens'])) {
        return;
    }
    $css = zhiji_skin_css_vars($skin['tokens']);
    if (!$css) {
        return;
    }
    echo "\n<style id=\"zhiji-skin-vars\">" . $css . "</style>\n";
}, 999);

/**
 * wp_footer：输出全部皮肤数据 + 切换器 UI（供前端零刷新切换）
 */
add_action('wp_footer', function () {
    if (!zhiji_is_enabled('skin_switcher_enabled')) {
        return;
    }
    if (!zhiji_is_enabled('skin_switcher_allow_guest', true) && !is_user_logged_in()) {
        return;
    }
    if (!zhiji_is_enabled('skin_switcher_show_ui', true)) {
        return;
    }

    $allowed = zhiji_skin_allowed();
    $skins   = zhiji_skins();
    $payload = array();
    foreach ($allowed as $slug) {
        if (empty($skins[$slug])) {
            continue;
        }
        $payload[$slug] = array(
            'label'     => $skins[$slug]['label'],
            'note'      => isset($skins[$slug]['note']) ? $skins[$slug]['note'] : '',
            'preview'   => isset($skins[$slug]['preview']) ? $skins[$slug]['preview'] : array(),
            'css'       => zhiji_skin_css_vars($skins[$slug]['tokens']),
        );
    }
    if (!$payload) {
        return;
    }

    wp_enqueue_style('zhiji-skin-switcher', zhiji_asset_url('css/skin-switcher.css'), array(), null);
    wp_enqueue_script('zhiji-skin-switcher', zhiji_asset_url('js/skin-switcher.js'), array(), null, true);

    printf(
        "<script id='zhiji-skin-data'>window.zhijiSkins=%s;window.zhijiSkinActive=%s;</script>\n",
        wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        wp_json_encode(zhiji_skin_active())
    );

    $pos = (string) zhiji_get_option('skin_switcher_position', 'right-bottom');
    ?>
    <div id="zhiji-skin-switcher" class="zhiji-skin-switcher zhiji-skin-switcher--<?php echo esc_attr($pos); ?>" hidden>
        <button type="button" class="zhiji-skin-switcher__toggle" aria-label="<?php esc_attr_e('切换皮肤', 'zhiji'); ?>">
            <span class="zhiji-skin-switcher__toggle-icon" aria-hidden="true"></span>
        </button>
        <div class="zhiji-skin-switcher__panel" role="dialog" aria-label="<?php esc_attr_e('皮肤选择', 'zhiji'); ?>">
            <div class="zhiji-skin-switcher__head">
                <span class="zhiji-skin-switcher__title"><?php esc_html_e('皮肤', 'zhiji'); ?></span>
                <button type="button" class="zhiji-skin-switcher__close" aria-label="<?php esc_attr_e('收起', 'zhiji'); ?>">&times;</button>
            </div>
            <div class="zhiji-skin-switcher__list">
                <?php foreach ($payload as $slug => $s) : ?>
                    <button type="button" class="zhiji-skin-item" data-skin="<?php echo esc_attr($slug); ?>"
                            title="<?php echo esc_attr($s['note']); ?>">
                        <span class="zhiji-skin-item__dots" aria-hidden="true">
                            <?php foreach ((array) $s['preview'] as $hex) : ?>
                                <i style="background:<?php echo esc_attr($hex); ?>"></i>
                            <?php endforeach; ?>
                        </span>
                        <span class="zhiji-skin-item__label"><?php echo esc_html($s['label']); ?></span>
                        <span class="zhiji-skin-item__check" aria-hidden="true">✓</span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
}, 97);

/* ============================================================
 * 后台设置
 * ============================================================ */

add_action('after_setup_theme', function () {
    if (!class_exists('CSF')) {
        return;
    }
    Zhiji_Registry::register_module('skin_switcher', array(
        'title'    => '皮肤选择器',
        'parent'   => 'zhiji_beautify',
        'priority' => 5,
        'option'   => 'skin_switcher_enabled',
    ));
}, 20);

add_action('zhiji_loaded', function () {
    Zhiji_Registry::csf_section_for('skin_switcher', array(
        array(
            'id'      => 'skin_switcher_enabled',
            'type'    => 'switcher',
            'title'   => '启用皮肤选择器',
            'default' => false,
        ),
        array(
            'id'         => 'skin_switcher_default',
            'type'       => 'select',
            'title'      => '默认皮肤',
            'options'    => wp_list_pluck(zhiji_skins(), 'label'),
            'default'    => 'palm',
            'dependency' => array('skin_switcher_enabled', '==', 'true'),
        ),
        array(
            'id'         => 'skin_switcher_allow',
            'type'       => 'checkbox',
            'title'      => '允许切换的皮肤',
            'desc'       => '不选=全部可用',
            'options'    => wp_list_pluck(zhiji_skins(), 'label'),
            'default'    => array(),
            'dependency' => array('skin_switcher_enabled', '==', 'true'),
        ),
        array(
            'id'         => 'skin_switcher_show_ui',
            'type'       => 'switcher',
            'title'      => '前台显示切换器',
            'desc'       => '关闭后仅按「默认皮肤」渲染，前台不出现切换入口',
            'default'    => true,
            'dependency' => array('skin_switcher_enabled', '==', 'true'),
        ),
        array(
            'id'         => 'skin_switcher_allow_guest',
            'type'       => 'switcher',
            'title'      => '允许游客切换',
            'desc'       => '关闭则仅登录用户可切换',
            'default'    => true,
            'dependency' => array('skin_switcher_enabled', '==', 'true'),
        ),
        array(
            'id'         => 'skin_switcher_position',
            'type'       => 'radio',
            'title'      => '切换器位置',
            'options'    => array(
                'right-bottom' => '右下角',
                'right-top'    => '右上角',
                'left-bottom'  => '左下角',
            ),
            'default'    => 'right-bottom',
            'inline'     => true,
            'dependency' => array('skin_switcher_enabled', '==', 'true'),
        ),
    ));
});
