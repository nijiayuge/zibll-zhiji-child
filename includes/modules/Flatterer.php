<?php
/**
 * @module  Flatterer
 * @desc    舔狗日记：随机语录卡片短代码，内置 20 条默认文案 + 自定义追加
 * @option  flatterer_enabled  总开关
 *          flatterer_custom   自定义语录（每行一条，追加到默认池）
 * @shortcode [zhiji_flatterer]
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/Flatterer.php`（JS 随短代码注入，static 防重复）
 */

defined('ABSPATH') || exit;

/* ============================================================
 * 模块自注册
 * ============================================================ */
Zhiji_Registry::register_module('flatterer', array(
    'title'    => '舔狗日记',
    'parent'   => 'zhiji_element',
    'priority' => 90,
    'option'   => 'flatterer_enabled',
));

/* ============================================================
 * 语料
 * ============================================================ */

/**
 * 默认语料池
 *
 * @return array
 */
function zhiji_flatterer_defaults()
{
    return array(
        '今天去庙里求签，求了个上上签，签文写着「爱情事业两丰收」，我想了半天，大概是因为今天给你发消息了。',
        '你回我一个字，我能开心一整天；你回我两个字，我能开心一个礼拜。今天你回我了吗？',
        '刚刚不小心摔了一跤，疼得厉害，可是想到你，就觉得一点也不疼了——因为你比药还甜。',
        '天气预报说今天有雨，我特意带了伞。其实我盼着下雨，这样就能名正言顺地送你回家了。',
        '我查了查星座，今天宜聊天、宜分享、宜关心你。所以，我来营业了。',
        '月亮很圆，夜色很美，风也很温柔。可这些都抵不上你回我一句「嗯」。',
        '今天也是元气满满的一天，因为早上梦到你了，醒来心情特别好，特此汇报。',
        '我问朋友怎么追到喜欢的人，他说要真诚。我想了想，那我每天都来真诚地问候你一次好了。',
        '你的头像真好看，我盯着看了十分钟，然后发现自己忘了看时间。',
        '今天煮了粥，第一次下厨，糊了。不过没关系，下次做给你吃的时候一定会成功的。',
        '你发朋友圈的时候我第一时间点赞，不是因为网快，是因为我把你设成了特别关注。',
        '刚才路过花店，看到一束向日葵，觉得它像我——永远朝着太阳，而你就是我的太阳。',
        '我把你的备注改成了「充电器」，因为你总能让我满血复活。',
        '今天健身房打卡了，教练夸我进步快。其实我只是想让自己配得上站在你身边。',
        '今天下雨没带伞，淋了个透，但想到你可能也在看这场雨，就觉得这场雨还挺浪漫的。',
        '听说多喝热水对身体好，所以我今天喝了八杯，每一杯都在想你。',
        '你分享的歌我循环了一整天，不是因为好听，是因为想离你的生活近一点。',
        '今天学会了做你爱吃的菜，虽然火候还差点，但请相信，我会越来越好的。',
        '深夜了，别人都在看剧打游戏，我却在想：你睡了吗？被子盖好了吗？明天见。',
        '如果问候有温度，那我每天发出去的消息，大概能把整个冬天都捂热了。',
    );
}

/**
 * 完整语料池（默认 + 自定义追加）
 *
 * @return array
 */
function zhiji_flatterer_pool()
{
    $pool   = zhiji_flatterer_defaults();
    $custom = trim((string) zhiji_get_option('flatterer_custom', ''));
    if ('' !== $custom) {
        foreach (preg_split('/\r\n|\r|\n/', $custom) as $line) {
            $line = trim($line);
            if ('' !== $line) {
                $pool[] = $line;
            }
        }
    }
    return $pool;
}

/* ============================================================
 * 短代码
 * ============================================================ */
add_shortcode('zhiji_flatterer', 'zhiji_flatterer_shortcode');

function zhiji_flatterer_shortcode()
{
    if (!zhiji_is_enabled('flatterer_enabled')) {
        return '';
    }
    $pool = zhiji_flatterer_pool();
    if (empty($pool)) {
        return '';
    }
    static $rendered = false;
    $json = wp_json_encode(array_values($pool), JSON_UNESCAPED_UNICODE);
    $html  = '<div class="zhiji-flatterer" data-pool="' . esc_attr($json) . '" data-render="' . ($rendered ? '0' : '1') . '">';
    $html .= '<div class="zhiji-flatterer-card"><div class="zhiji-flatterer-text">' . esc_html($pool[0]) . '</div></div>';
    $html .= '<div class="zhiji-flatterer-bar"><span class="zhiji-flatterer-label">舔狗日记</span><button type="button" class="zhiji-flatterer-btn">换一条</button></div>';
    $html .= '</div>';
    if (!$rendered) {
        $rendered = true;
        zhiji_flatterer_script();
    }
    return $html;
}

/**
 * 交互脚本（随首个卡片输出一次）
 *
 * @return void
 */
function zhiji_flatterer_script()
{
    echo '<script id="zhiji-flatterer-js">'
        . '(function(){'
        . 'var boxes=document.querySelectorAll(".zhiji-flatterer");'
        . 'for(var i=0;i<boxes.length;i++){(function(box){'
        . 'var pool=[];try{pool=JSON.parse(box.getAttribute("data-pool"))||[]}catch(err){}'
        . 'if(box.getAttribute("data-render")!=="1")return;'
        . 'var text=box.querySelector(".zhiji-flatterer-text");'
        . 'var btn=box.querySelector(".zhiji-flatterer-btn");'
        . 'if(!btn||!text||!pool.length)return;'
        . 'btn.addEventListener("click",function(){'
        . 'text.textContent=pool[Math.floor(Math.random()*pool.length)];'
        . '});'
        . '})(boxes[i]);}'
        . '})();'
        . '</script>';
}

/* ============================================================
 * 后台字段
 * ============================================================ */
add_action('after_setup_theme', function () {
    Zhiji_Registry::csf_section_for('flatterer', array(
        array(
            'id'      => 'flatterer_enabled',
            'type'    => 'switcher',
            'title'   => '启用舔狗日记',
            'desc'    => '通过短代码 [zhiji_flatterer] 展示随机语录卡片。',
            'default' => false,
        ),
        array(
            'id'         => 'flatterer_custom',
            'type'       => 'textarea',
            'title'      => '自定义语录',
            'desc'       => '每行一条，追加到内置语料池之后。',
            'dependency' => array('flatterer_enabled', '==', '1'),
        ),
    ));
}, 20);
