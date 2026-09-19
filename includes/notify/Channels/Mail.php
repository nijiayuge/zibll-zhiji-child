<?php
/**
 * @module  Notify_Channel_Mail
 * @desc    渠道：邮件（自研票据模板；发送时临时摘掉父主题 wp_mail 内容覆盖）
 * @since   2.0.0
 */

defined('ABSPATH') || exit;

class Zhiji_Notify_Mail
{
    /**
     * @param int    $uid
     * @param string $event
     * @param array  $args
     * @param array  $cfg
     * @return bool
     */
    public static function send($uid, $event, array $args, array $cfg)
    {
        $user = get_userdata((int) $uid);
        if (!$user || !is_email($user->user_email)) {
            zhiji_log('邮件渠道跳过：收件人邮箱无效', array('event' => $event, 'user' => $uid));
            return false;
        }

        $tpl     = !empty($cfg['mail']) ? $cfg['mail'] : 'ticket';
        $subject = (string) $args['title'];
        $body    = ('plain' === $tpl)
            ? self::plain($user, $args)
            : self::ticket($user, $args);

        $headers = array('Content-Type: text/html; charset=UTF-8');

        // 关键：父主题会用 zib_get_mail_content 覆盖 wp_mail 内容（优先级 10），发送前摘掉、发完还原
        Zhiji_Adapter::mail_filter_off();
        $ok = wp_mail($user->user_email, $subject, $body, $headers);
        Zhiji_Adapter::mail_filter_on();

        if (!$ok) {
            zhiji_log('邮件发送失败', array('event' => $event, 'user' => $uid));
        }
        return (bool) $ok;
    }

    /**
     * 纯文本正文
     */
    private static function plain($user, array $args)
    {
        $lines = array(
            $args['title'],
            '',
            wp_strip_all_tags((string) $args['content']),
        );
        if (!empty($args['link'])) {
            $lines[] = '';
            $lines[] = $args['link'];
        }
        return nl2br(esc_html(implode("\n", $lines)));
    }

    /**
     * 票据模板（品牌色 + 左右信息行；不依赖任何外部资源）
     */
    private static function ticket($user, array $args)
    {
        $brand = apply_filters('zhiji_notify_brand_color', '#2e7cf6');
        $name  = $user->display_name ? $user->display_name : $user->user_login;
        $rows  = array();

        if (!empty($args['data']) && is_array($args['data'])) {
            foreach ($args['data'] as $k => $v) {
                if (is_array($v)) {
                    $v = implode('、', array_map('strval', $v));
                }
                $rows[] = array('k' => (string) $k, 'v' => (string) $v);
            }
        }

        ob_start();
        ?>
        <div style="max-width:560px;margin:0 auto;font-family:-apple-system,'PingFang SC','Microsoft YaHei',sans-serif;color:#333;background:#fff;border:1px solid #eee;border-radius:12px;overflow:hidden">
            <div style="background:<?php echo esc_attr($brand); ?>;color:#fff;padding:18px 22px;font-size:16px;font-weight:500">
                <?php echo esc_html($args['title']); ?>
            </div>
            <div style="padding:22px">
                <p style="margin:0 0 14px"><?php echo esc_html($name); ?>，你好：</p>
                <div style="line-height:1.8;font-size:14px"><?php echo wp_kses_post(wpautop((string) $args['content'])); ?></div>

                <?php if ($rows) : ?>
                <table style="width:100%;border-collapse:collapse;margin:16px 0;font-size:14px">
                    <?php foreach ($rows as $r) : ?>
                    <tr>
                        <td style="padding:8px 0;color:#888;width:38%"><?php echo esc_html($r['k']); ?></td>
                        <td style="padding:8px 0;text-align:right;font-weight:500"><?php echo esc_html($r['v']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <?php endif; ?>

                <?php if (!empty($args['link'])) : ?>
                <p style="margin:18px 0 0">
                    <a href="<?php echo esc_url($args['link']); ?>"
                       style="display:inline-block;background:<?php echo esc_attr($brand); ?>;color:#fff;text-decoration:none;padding:10px 22px;border-radius:8px;font-size:14px">查看详情</a>
                </p>
                <?php endif; ?>
            </div>
            <div style="padding:14px 22px;background:#fafafa;color:#999;font-size:12px">
                本邮件由 <?php echo esc_html(get_bloginfo('name')); ?> 自动发送，请勿直接回复。
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
