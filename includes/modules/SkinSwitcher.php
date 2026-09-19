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
            'preview' => array('#72825C', '#6291AD', '#F7F8F6', '#343036'),
            'tokens'  => array(
                'light' => array(
                    'bg' => '#F7F8F6',
                    'surface' => '#FCFCFB',
                    'text' => '#343036',
                    'brand' => '#72825C',
                    'brand_dark' => '#586447',
                    'brand_weak' => '#EEF0EB',
                    'accent' => '#6291AD',
                    'muted' => '#757476',
                    'border' => '#DCDCDB',
                ),
                'dark' => array(
                    'bg' => '#1D1F1B',
                    'surface' => '#242522',
                    'text' => '#E3E5E8',
                    'brand' => '#96A580',
                    'brand_dark' => '#72825C',
                    'brand_weak' => '#292D25',
                    'accent' => '#7DA4BB',
                    'muted' => '#989A9A',
                    'border' => '#31362A',
                ),
            ),
        ),
        'agate' => array(
            'label'   => '玛瑙石板',
            'note'    => '石板蓝 + 淡紫，沉静高级（实物配色卡：矿物/AgateBlues510.png）',
            'card'    => '矿物/AgateBlues510.png',
            'palette' => array('#D2CBC3', '#5D6E86', '#C7B5E3', '#C2D9F8', '#F2F1E1'),
            'preview' => array('#5D6E86', '#9D7ECE', '#F6F7F8', '#2C323A'),
            'tokens'  => array(
                'light' => array(
                    'bg' => '#F6F7F8',
                    'surface' => '#FBFCFC',
                    'text' => '#2C323A',
                    'brand' => '#5D6E86',
                    'brand_dark' => '#485568',
                    'brand_weak' => '#ECEEF0',
                    'accent' => '#9D7ECE',
                    'muted' => '#71747A',
                    'border' => '#DADBDD',
                ),
                'dark' => array(
                    'bg' => '#1B1D1F',
                    'surface' => '#222425',
                    'text' => '#EAE8E1',
                    'brand' => '#8292A8',
                    'brand_dark' => '#5D6E86',
                    'brand_weak' => '#25292E',
                    'accent' => '#AB90D5',
                    'muted' => '#9A9A98',
                    'border' => '#2B3037',
                ),
            ),
        ),
        'pear' => array(
            'label'   => '蜜梨暖棕',
            'note'    => '暖棕 + 蜜金，温润亲和（实物配色卡：可食用的/PearTones605.png）',
            'card'    => '可食用的/PearTones605.png',
            'palette' => array('#CCA682', '#945638', '#D9C289', '#E3DDD3', '#6C706D'),
            'preview' => array('#945638', '#A98837', '#F9F6F4', '#432E23'),
            'tokens'  => array(
                'light' => array(
                    'bg' => '#F9F6F4',
                    'surface' => '#FDFBFB',
                    'text' => '#432E23',
                    'brand' => '#945638',
                    'brand_dark' => '#6F402A',
                    'brand_weak' => '#F2EBE7',
                    'accent' => '#A98837',
                    'muted' => '#80726B',
                    'border' => '#E0DAD7',
                ),
                'dark' => array(
                    'bg' => '#201B18',
                    'surface' => '#262220',
                    'text' => '#E8E6E3',
                    'brand' => '#BF7755',
                    'brand_dark' => '#945638',
                    'brand_weak' => '#31231D',
                    'accent' => '#C39F46',
                    'muted' => '#9C9996',
                    'border' => '#3B2820',
                ),
            ),
        ),
        'chirp' => array(
            'label'   => '青碧金羽',
            'note'    => '青碧 + 麦金，清新明快（实物配色卡：家禽鸟类/AutumnChirp600.png）',
            'card'    => '家禽鸟类/AutumnChirp600.png',
            'palette' => array('#E8E7CF', '#659C9A', '#C78917', '#826E5F', '#FAB68E'),
            'preview' => array('#598A88', '#BB8115', '#F6F9F8', '#39322D'),
            'tokens'  => array(
                'light' => array(
                    'bg' => '#F6F9F8',
                    'surface' => '#FBFCFC',
                    'text' => '#39322D',
                    'brand' => '#598A88',
                    'brand_dark' => '#456B69',
                    'brand_weak' => '#EBF1F1',
                    'accent' => '#BB8115',
                    'muted' => '#787573',
                    'border' => '#DCDDDC',
                ),
                'dark' => array(
                    'bg' => '#1B201F',
                    'surface' => '#222625',
                    'text' => '#E9E9E2',
                    'brand' => '#7EACAA',
                    'brand_dark' => '#598A88',
                    'brand_weak' => '#242F2E',
                    'accent' => '#E09A19',
                    'muted' => '#999B97',
                    'border' => '#293838',
                ),
            ),
        ),
        'window' => array(
            'label'   => '朱窗杏橙',
            'note'    => '朱红 + 杏橙，热烈活力（实物配色卡：古董/ColorWindow605.png）',
            'card'    => '古董/ColorWindow605.png',
            'palette' => array('#F4EDF5', '#DBD8ED', '#E1D7A0', '#E06661', '#FFC08C'),
            'preview' => array('#DC5650', '#E16600', '#FDF6F5', '#4B1D1B'),
            'tokens'  => array(
                'light' => array(
                    'bg' => '#FDF6F5',
                    'surface' => '#FEFBFB',
                    'text' => '#4B1D1B',
                    'brand' => '#DC5650',
                    'brand_dark' => '#D03129',
                    'brand_weak' => '#FBEBEA',
                    'accent' => '#E16600',
                    'muted' => '#866765',
                    'border' => '#E4D8D6',
                ),
                'dark' => array(
                    'bg' => '#271B1A',
                    'surface' => '#2B2222',
                    'text' => '#E7E2E9',
                    'brand' => '#E2736F',
                    'brand_dark' => '#DC5650',
                    'brand_weak' => '#412322',
                    'accent' => '#FF7A0B',
                    'muted' => '#9D9599',
                    'border' => '#512827',
                ),
            ),
        ),
        'rose' => array(
            'label'   => '秋暮玫蓝',
            'note'    => '玫红 + 石板蓝，文艺柔和（实物配色卡：自然/FallSpectrum605.png）',
            'card'    => '自然/FallSpectrum605.png',
            'palette' => array('#545F6D', '#CA798B', '#B8C8D2', '#F2B780', '#9E525B'),
            'preview' => array('#C4697E', '#DA7416', '#FCF7F8', '#2F3237'),
            'tokens'  => array(
                'light' => array(
                    'bg' => '#FCF7F8',
                    'surface' => '#FEFCFC',
                    'text' => '#2F3237',
                    'brand' => '#C4697E',
                    'brand_dark' => '#B34760',
                    'brand_weak' => '#F8EDF0',
                    'accent' => '#DA7416',
                    'muted' => '#767477',
                    'border' => '#DFDBDD',
                ),
                'dark' => array(
                    'bg' => '#251D1E',
                    'surface' => '#2A2325',
                    'text' => '#E3E6E8',
                    'brand' => '#CE8394',
                    'brand_dark' => '#C4697E',
                    'brand_weak' => '#3C272C',
                    'accent' => '#EA892F',
                    'muted' => '#999999',
                    'border' => '#4A2E34',
                ),
            ),
        ),
        'indigo' => array(
            'label'   => '靛蓝荧光',
            'note'    => '靛蓝 + 荧光黄绿，现代锐利（实物配色卡：家禽鸟类/FeatheredBrights605.png）',
            'card'    => '家禽鸟类/FeatheredBrights605.png',
            'palette' => array('#E6FF79', '#816665', '#412F47', '#3032AA', '#8ED4CE'),
            'preview' => array('#3032AA', '#85A300', '#F4F4FA', '#362C3A'),
            'tokens'  => array(
                'light' => array(
                    'bg' => '#F4F4FA',
                    'surface' => '#FAFAFD',
                    'text' => '#362C3A',
                    'brand' => '#3032AA',
                    'brand_dark' => '#252682',
                    'brand_weak' => '#E6E6F5',
                    'accent' => '#85A300',
                    'muted' => '#75707B',
                    'border' => '#D9D8DF',
                ),
                'dark' => array(
                    'bg' => '#171822',
                    'surface' => '#1F1F28',
                    'text' => '#ECF0DB',
                    'brand' => '#5F61D1',
                    'brand_dark' => '#3032AA',
                    'brand_weak' => '#1B1B36',
                    'accent' => '#A6CC00',
                    'muted' => '#9B9D95',
                    'border' => '#1D1E42',
                ),
            ),
        ),
        'amethyst' => array(
            'label'   => '紫晶夜色',
            'note'    => '紫罗兰 + 品紫，夜色静谧（实物配色卡：矿物/AmethystRocks615.png）',
            'card'    => '矿物/AmethystRocks615.png',
            'palette' => array('#3A343D', '#E0BCE8', '#A95FC2', '#774282', '#B8B7C8'),
            'preview' => array('#774282', '#A95FC2', '#F8F5F8', '#343036'),
            'tokens'  => array(
                'light' => array(
                    'bg' => '#F8F5F8',
                    'surface' => '#FCFBFC',
                    'text' => '#343036',
                    'brand' => '#774282',
                    'brand_dark' => '#583160',
                    'brand_weak' => '#EFE8F0',
                    'accent' => '#A95FC2',
                    'muted' => '#777278',
                    'border' => '#DDD9DD',
                ),
                'dark' => array(
                    'bg' => '#1E191F',
                    'surface' => '#242125',
                    'text' => '#E9E0EB',
                    'brand' => '#A05EAD',
                    'brand_dark' => '#774282',
                    'brand_weak' => '#2B1F2D',
                    'accent' => '#B97DCD',
                    'muted' => '#9C959D',
                    'border' => '#322236',
                ),
            ),
        ),
        'lavender' => array(
            'label'   => '薰衣草',
            'note'    => '蓝紫 + 藕粉，柔和清雅（实物配色卡：春天/SpringFlora500.png）',
            'card'    => '春天/SpringFlora500.png',
            'palette' => array('#F0C6B3', '#674F86', '#8960A3', '#C8B7D4', '#EBA9D0'),
            'preview' => array('#674F86', '#DA62A9', '#F7F5F8', '#322A3C'),
            'tokens'  => array(
                'light' => array(
                    'bg' => '#F7F5F8',
                    'surface' => '#FCFBFC',
                    'text' => '#322A3C',
                    'brand' => '#674F86',
                    'brand_dark' => '#4E3C66',
                    'brand_weak' => '#EDEAF0',
                    'accent' => '#DA62A9',
                    'muted' => '#74707B',
                    'border' => '#DBD9DE',
                ),
                'dark' => array(
                    'bg' => '#1C1A1F',
                    'surface' => '#232125',
                    'text' => '#EDE3DE',
                    'brand' => '#8A71AB',
                    'brand_dark' => '#674F86',
                    'brand_weak' => '#27222E',
                    'accent' => '#E283BB',
                    'muted' => '#9C9796',
                    'border' => '#2E2637',
                ),
            ),
        ),
        'sandgold' => array(
            'label'   => '沙金棕',
            'note'    => '沙棕 + 麦金，大地质感（实物配色卡：矿物/AgateGolds610.png）',
            'card'    => '矿物/AgateGolds610.png',
            'palette' => array('#E0D28F', '#E2DFE7', '#D6A953', '#8F7E53', '#DEDFA4'),
            'preview' => array('#8F7E53', '#B2832A', '#F9F8F6', '#3C372A'),
            'tokens'  => array(
                'light' => array(
                    'bg' => '#F9F8F6',
                    'surface' => '#FDFCFB',
                    'text' => '#3C372A',
                    'brand' => '#8F7E53',
                    'brand_dark' => '#6F6240',
                    'brand_weak' => '#F2F0EA',
                    'accent' => '#B2832A',
                    'muted' => '#7B7870',
                    'border' => '#DFDDD9',
                ),
                'dark' => array(
                    'bg' => '#201E1B',
                    'surface' => '#262522',
                    'text' => '#E6E4E7',
                    'brand' => '#B1A178',
                    'brand_dark' => '#8F7E53',
                    'brand_weak' => '#302C23',
                    'accent' => '#CF9A35',
                    'muted' => '#9A9898',
                    'border' => '#3A3428',
                ),
            ),
        ),
        'sage' => array(
            'label'   => '灰绿陶粉',
            'note'    => '灰绿 + 陶粉，素雅耐看（实物配色卡：夏天/SunRoom620.png）',
            'card'    => '夏天/SunRoom620.png',
            'palette' => array('#DADBD2', '#6A776A', '#E6A293', '#FEFE0D', '#BEBDB1'),
            'preview' => array('#6A776A', '#D76D55', '#F7F8F7', '#313531'),
            'tokens'  => array(
                'light' => array(
                    'bg' => '#F7F8F7',
                    'surface' => '#FCFCFC',
                    'text' => '#313531',
                    'brand' => '#6A776A',
                    'brand_dark' => '#525C52',
                    'brand_weak' => '#EDEFED',
                    'accent' => '#D76D55',
                    'muted' => '#747674',
                    'border' => '#DBDDDB',
                ),
                'dark' => array(
                    'bg' => '#1D1E1D',
                    'surface' => '#232423',
                    'text' => '#F0F0DB',
                    'brand' => '#8E9A8E',
                    'brand_dark' => '#6A776A',
                    'brand_weak' => '#282B28',
                    'accent' => '#DF8976',
                    'muted' => '#9E9F93',
                    'border' => '#2E322E',
                ),
            ),
        ),
        'peony' => array(
            'label'   => '粉杏薄荷',
            'note'    => '玫粉 + 薄荷，清爽通透（实物配色卡：夏天/ColorBlown605.png）',
            'card'    => '夏天/ColorBlown605.png',
            'palette' => array('#FAFAE6', '#C8E6DD', '#EB778D', '#EBCE4C', '#DEBBE4'),
            'preview' => array('#E65671', '#469A82', '#FEF6F7', '#4E1823'),
            'tokens'  => array(
                'light' => array(
                    'bg' => '#FEF6F7',
                    'surface' => '#FEFBFC',
                    'text' => '#4E1823',
                    'brand' => '#E65671',
                    'brand_dark' => '#DF2A4C',
                    'brand_weak' => '#FCEBEE',
                    'accent' => '#469A82',
                    'muted' => '#87636A',
                    'border' => '#E5D7D9',
                ),
                'dark' => array(
                    'bg' => '#281B1D',
                    'surface' => '#2C2224',
                    'text' => '#ECECDF',
                    'brand' => '#E96880',
                    'brand_dark' => '#E65671',
                    'brand_weak' => '#432329',
                    'accent' => '#56B298',
                    'muted' => '#9F9993',
                    'border' => '#542831',
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

    /**
     * 变量映射。
     * ⚠️ 关键：父主题 zibll 把 `--theme-color` 等**挂载在 `body{}` 上**（zib-head.php:240），
     * 而 CSS 变量是"元素自身声明优先于继承值"——因此我们这些必须**同样作用在 body 上**，
     * 否则写 `html:root` 会被 body 自身的声明压掉，全站配色根本不变（只剩我们自己的组件变色）。
     *
     * ⚠️ 背景（--body-bg-color / --main-bg-color）**不覆盖**：用户明确要求背景保持父主题默认
     * （尊重 zibll 后台的背景图/背景色配置，也避免"整页被染色"）。
     * 皮肤只接管"主色系统"——强调色、文字、边框与语义色类。
     * 背景令牌仍生成到 `--zhiji-bg`/`--zhiji-surface`，供我们自己的组件与预览页使用。
     */
    $map = array(
        'brand'      => array('zhiji-brand', 'theme-color', 'focus-color'),
        'bg'         => array('zhiji-bg'),
        'surface'    => array('zhiji-surface'),
        'text'       => array('zhiji-text', 'main-color'),
        'muted'      => array('zhiji-muted', 'muted-color'),
        'border'     => array('zhiji-border', 'main-border-color'),
        'brand_weak' => array('zhiji-brand-weak'),
        'accent'     => array('zhiji-accent'),
        'brand_dark' => array('zhiji-brand-dark'),
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
        // 父主题还用这 5 个半透明变量做 hover 背景/阴影/选中态，必须一并换色，
        // 否则换肤后这些地方仍残留父主题的强调色
        if (!empty($set['brand'])) {
            $out[] = '--focus-shadow-color:' . zhiji_hex_rgba($set['brand'], 0.4);
            $out[] = '--focus-color-opacity1:' . zhiji_hex_rgba($set['brand'], 0.1);
            $out[] = '--focus-color-opacity05:' . zhiji_hex_rgba($set['brand'], 0.05);
            $out[] = '--focus-color-opacity3:' . zhiji_hex_rgba($set['brand'], 0.3);
            $out[] = '--focus-color-opacity6:' . zhiji_hex_rgba($set['brand'], 0.6);
        }
        return implode(';', $out);
    };

    // 选择器策略：
    //   html:root            —— 兜底（覆盖不在 body 子树内的元素）
    //   html body            —— 主覆盖，(0,0,2) 特异性高于父主题的 body (0,0,1)，必胜
    //   html body.white-theme —— 亮色（zibll 的 body 类），与父主题 body.dark-theme 同级，防患未然
    //   html body.dark-theme  —— 暗色，(0,1,2) 高于父主题 body.dark-theme (0,1,1)
    $css = 'html:root{' . $build($light) . '}';
    $css .= 'html body{' . $build($light) . '}';
    $css .= 'html body.white-theme{' . $build($light) . '}';
    if ($dark) {
        $css .= 'html body.dark-theme{' . $build($dark) . '}';
    }

    // 语义色类映射（可关）：让「主 CTA / 链接」跟随皮肤
    if (zhiji_is_enabled('skin_switcher_strong', true)) {
        $css .= zhiji_skin_semantic_css($light, $dark);
    }
    return $css;
}

/**
 * 把父主题的「硬编码色类」映射到皮肤令牌。
 *
 * 为什么需要：CSS 变量只影响「用变量的地方」。zibll 里大量按钮/链接带的是
 * 硬编码色类（`.jb-blue` 是写死的蓝色渐变 #59c3fb→#268df7），不读 `--theme-color`，
 * 所以只改变量时它们纹丝不动 —— 这正是"换了皮肤但网站没变"的观感来源。
 *
 * 行业做法（Radix / Material / Tailwind 的 token 体系一致）：
 *   - **品牌/主操作色** 跟随主题：主 CTA（.jb-blue）、链接（.c-blue）、主色按钮（.b-blue）
 *   - **语义色** 保留不动：红=危险/删除、绿=成功/免费、黄=警告、紫=VIP、粉=会员
 *     把它们也刷成主色会破坏信息传达（用户分不清"删除"和"发布"）。
 *
 * 覆盖手段：提高特异性到 `html body .xxx`（等价于 CSS Cascade Layers 的效果，
 * 因为不能改父主题、无法把父主题 CSS 放进 @layer）。
 *
 * @param array $light 浅色令牌
 * @param array $dark  暗色令牌
 * @return string
 */
function zhiji_skin_semantic_css($light, $dark)
{
    // ⚠️ 每个选择器都必须独自带 `html body ` 前缀：
    // 逗号分隔的选择器是独立的，写成 `html body .b-blue,.b-blue-2` 会让后者退化成
    // 全局 `.b-blue-2`，特异性反而低于父主题；且 `html body.x` 是"body 自身有 x 类"，
    // 必须是 `html body .x`（后代）。
    $cta = function ($prefix, $t) {
        if (empty($t['brand'])) {
            return '';
        }
        $from = $t['brand'];
        $to   = !empty($t['brand_dark']) ? $t['brand_dark'] : $from;
        $out  = array();
        // 主 CTA 渐变按钮（发布、开通会员等）：改渐变两端 + zibll 预留的 --this-bg-b
        $out[] = $prefix . '.jb-blue{--this-bg-b:' . $from . ';--this-bg:linear-gradient(135deg,' . $from . ' 10%,' . $to . ' 100%)}';
        // 主色实心按钮（逐个写全前缀）
        $out[] = $prefix . '.b-blue{--this-bg:' . $from . '}';
        $out[] = $prefix . '.b-blue-2{--this-bg:' . $from . '}';
        // 链接 / 文字主色
        if (!empty($t['brand_weak'])) {
            $out[] = $prefix . '.c-blue{--this-color:' . $from . ';--this-bg:' . $t['brand_weak'] . '}';
            $out[] = $prefix . '.c-blue-2{--this-color:' . $from . ';--this-bg:' . $t['brand_weak'] . '}';
        }
        return implode('', $out);
    };

    $css = $cta('html body ', $light);
    if ($dark) {
        $css .= $cta('html body.dark-theme ', $dark);
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
 * 资源入队：必须挂在 wp_enqueue_scripts
 * ⚠️ 不能在 wp_footer 回调里 enqueue —— wp_print_footer_scripts 在 wp_footer:20 已执行完，
 *    晚于它再入队，资源标签根本不会输出（本项目坑清单里的经典坑）。
 */
add_action('wp_enqueue_scripts', function () {
    if (!zhiji_is_enabled('skin_switcher_enabled')) {
        return;
    }
    if (!zhiji_is_enabled('skin_switcher_allow_guest', true) && !is_user_logged_in()) {
        return;
    }
    if (!zhiji_is_enabled('skin_switcher_show_ui', true)) {
        return;
    }
    wp_enqueue_style('zhiji-skin-switcher', zhiji_asset_url('css/skin-switcher.css'), array(), null);
    wp_enqueue_script('zhiji-skin-switcher', zhiji_asset_url('js/skin-switcher.js'), array(), null, true);
}, 20);

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
        array(
            'id'         => 'skin_switcher_strong',
            'type'       => 'switcher',
            'title'      => '主按钮/链接跟随皮肤',
            'desc'       => '把父主题硬编码的主 CTA 渐变（.jb-blue）、主色按钮（.b-blue）与链接色（.c-blue）映射到当前皮肤。语义色（红=删除、绿=成功、黄=警告、紫=会员）始终保留，不会被刷成主色。',
            'default'    => true,
            'dependency' => array('skin_switcher_enabled', '==', 'true'),
        ),
    ));
});
