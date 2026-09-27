<?php
/**
 * @module  OpsRender
 * @desc    运维台渲染元件：样式（设计令牌）/ 结果提示 / 统计卡片 / 分区标题 / 操作审计。
 * @since   2.0.0
 */

defined('ABSPATH') || exit;

/* ============================================================
 * 二、页面头部与公共片段
 * ============================================================ */

/**
 * 运维页面通用样式（仅本页面加载）
 *
 * @return void
 */
function zhiji_ops_print_styles()
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    ?>
    <style>
    /* ============================================================
     * 知集运维台 · 设计规范（2026-09-27 重做）
     * ------------------------------------------------------------
     * 令牌：主色 #2271b1（WP 后台主色）｜文本 #1d2327/#50575e/#646970/#8c8f94
     *       边框 #dcdcde/#e2e2e4｜底 #fff/#f6f7f7｜圆角 10/8/999｜栅格 4·8·12·16·20·24
     * 约定：所有规则以 .zhiji-ops 作用域前缀隔离，不污染其它后台页面；
     *       组件类名与既有测试断言保持一致（zhiji-ops-card / -table / -filters …）。
     * ============================================================ */
    .zhiji-ops{--zhiji-ink:#1d2327;--zhiji-body:#50575e;--zhiji-muted:#646970;--zhiji-faint:#8c8f94;
      --zhiji-line:#dcdcde;--zhiji-line-soft:#e2e2e4;--zhiji-bg-soft:#f6f7f7;
      --zhiji-primary:#2271b1;--zhiji-primary-soft:#eef4fa;--zhiji-danger:#b32d2e;--zhiji-ok:#1a7f37;
      --zhiji-radius:10px;--zhiji-shadow:0 1px 2px rgba(16,24,40,.06);--zhiji-shadow-hover:0 6px 18px rgba(16,24,40,.10)}

    /* ---------- 页头 ---------- */
    .zhiji-ops .zhiji-ops-head{margin:10px 0 18px}
    .zhiji-ops .zhiji-ops-head h1{margin:0 0 6px;font-size:20px;line-height:1.35;font-weight:600;color:var(--zhiji-ink)}
    .zhiji-ops .zhiji-ops-desc{color:var(--zhiji-muted);margin:0;max-width:960px;font-size:13px;line-height:1.7}
    .zhiji-ops .zhiji-ops-head .page-title-action{margin-left:8px;vertical-align:middle}

    /* ---------- 分区标题 ---------- */
    .zhiji-ops .zhiji-ops-section{display:flex;align-items:center;gap:10px;margin:22px 0 10px}
    .zhiji-ops .zhiji-ops-section h2{margin:0;font-size:14px;font-weight:600;color:var(--zhiji-ink)}
    .zhiji-ops .zhiji-ops-section .zhiji-ops-section-line{flex:1;height:1px;background:var(--zhiji-line-soft)}
    .zhiji-ops .zhiji-ops-section .zhiji-ops-count{font-size:12px;color:var(--zhiji-faint);background:var(--zhiji-bg-soft);
      border:1px solid var(--zhiji-line-soft);border-radius:999px;padding:1px 9px}

    /* ---------- 统计卡片 ---------- */
    .zhiji-ops-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:12px;margin:0 0 6px}
    .zhiji-ops-card{position:relative;background:#fff;border:1px solid var(--zhiji-line);border-radius:var(--zhiji-radius);
      padding:14px 16px 14px 18px;box-shadow:var(--zhiji-shadow);transition:box-shadow .18s ease,transform .18s ease}
    .zhiji-ops-card:hover{box-shadow:var(--zhiji-shadow-hover);transform:translateY(-1px)}
    .zhiji-ops-card::before{content:"";position:absolute;left:0;top:12px;bottom:12px;width:3px;border-radius:0 3px 3px 0;background:var(--zhiji-primary);opacity:.85}
    .zhiji-ops-card.tone-warn::before{background:var(--zhiji-danger)}
    .zhiji-ops-card.tone-ok::before{background:var(--zhiji-ok)}
    .zhiji-ops-card .zhiji-ops-card-label{font-size:12px;color:var(--zhiji-muted);margin-bottom:6px;display:flex;align-items:center;gap:6px}
    .zhiji-ops-card .zhiji-ops-card-label .dashicons{font-size:15px;width:15px;height:15px;color:var(--zhiji-primary);opacity:.9}
    .zhiji-ops-card.tone-warn .zhiji-ops-card-label .dashicons{color:var(--zhiji-danger)}
    .zhiji-ops-card.tone-ok .zhiji-ops-card-label .dashicons{color:var(--zhiji-ok)}
    .zhiji-ops-card .zhiji-ops-card-value{font-size:26px;font-weight:600;line-height:1.2;color:var(--zhiji-ink);font-variant-numeric:tabular-nums}
    .zhiji-ops-card .zhiji-ops-card-hint{font-size:12px;color:var(--zhiji-faint);margin-top:6px}
    .zhiji-ops-card.tone-warn .zhiji-ops-card-value{color:var(--zhiji-danger)}
    .zhiji-ops-card.tone-ok .zhiji-ops-card-value{color:var(--zhiji-ok)}

    /* ---------- 总览：场景卡片 ---------- */
    .zhiji-ops-scene-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:14px;margin:0 0 6px}
    .zhiji-ops-scene-card{position:relative;background:#fff;border:1px solid var(--zhiji-line);border-radius:var(--zhiji-radius);
      padding:16px 18px;box-shadow:var(--zhiji-shadow);transition:box-shadow .18s ease,transform .18s ease}
    .zhiji-ops-scene-card:hover{box-shadow:var(--zhiji-shadow-hover);transform:translateY(-1px)}
    .zhiji-ops-scene-card h3{margin:0 0 6px;font-size:15px;font-weight:600;color:var(--zhiji-ink);display:flex;align-items:center;gap:8px}
    .zhiji-ops-scene-card h3 .dashicons{color:var(--zhiji-primary)}
    .zhiji-ops-scene-card p{color:var(--zhiji-muted);font-size:13px;line-height:1.7;margin:0 0 12px}
    .zhiji-ops-scene-card .zhiji-ops-chipset{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 12px}
    .zhiji-ops-scene-card .zhiji-ops-chip{font-size:12px;color:var(--zhiji-body);background:var(--zhiji-bg-soft);
      border:1px solid var(--zhiji-line-soft);border-radius:999px;padding:2px 10px;font-variant-numeric:tabular-nums}
    .zhiji-ops-scene-card .zhiji-ops-scene-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}

    /* ---------- 工具条：筛选 / 表单操作 ---------- */
    .zhiji-ops-filters{background:#fff;border:1px solid var(--zhiji-line);border-radius:var(--zhiji-radius);
      padding:14px 16px;margin:0 0 12px;display:flex;flex-wrap:wrap;gap:12px 14px;align-items:flex-end;box-shadow:var(--zhiji-shadow)}
    .zhiji-ops-filters .zhiji-ops-field{display:flex;flex-direction:column;gap:5px}
    .zhiji-ops-filters label{font-size:12px;color:var(--zhiji-muted)}
    .zhiji-ops-filters input[type="text"],.zhiji-ops-filters input[type="date"],.zhiji-ops-filters select{min-width:150px;border-radius:6px}
    .zhiji-ops-filters input[type="text"].zhiji-ops-w-lg{min-width:250px}
    .zhiji-ops-filters .zhiji-ops-field-actions{gap:8px;flex-direction:row;align-items:center}
    .zhiji-ops-preactions{display:flex;flex-direction:column;gap:10px;margin:0 0 12px}
    .zhiji-ops-preactions form{display:flex;flex-wrap:wrap;gap:10px;align-items:center;background:#fff;border:1px solid var(--zhiji-line);
      border-left:3px solid var(--zhiji-primary);border-radius:var(--zhiji-radius);padding:12px 16px;box-shadow:var(--zhiji-shadow)}
    .zhiji-ops-preactions label{font-size:12px;color:var(--zhiji-muted)}
    .zhiji-ops-preactions strong{font-size:13px;color:var(--zhiji-ink);white-space:nowrap}
    .zhiji-ops-preactions input[type="text"],.zhiji-ops-preactions input[type="email"]{min-width:280px;border-radius:6px}
    .zhiji-ops-preactions .description{flex-basis:100%;margin:0;color:var(--zhiji-faint)}

    /* ---------- 数据表 ---------- */
    .zhiji-ops-bulkbar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;background:#fff;border:1px solid var(--zhiji-line);
      border-bottom:none;border-radius:var(--zhiji-radius) var(--zhiji-radius) 0 0;padding:10px 14px}
    .zhiji-ops-bulkbar strong{font-size:13px;color:var(--zhiji-ink)}
    .zhiji-ops .zhiji-ops-table-wrap{background:#fff;border:1px solid var(--zhiji-line);border-radius:var(--zhiji-radius);
      overflow:auto;box-shadow:var(--zhiji-shadow)}
    .zhiji-ops .zhiji-ops-table-wrap.has-bulkbar{border-radius:0 0 var(--zhiji-radius) var(--zhiji-radius)}
    .zhiji-ops table.zhiji-ops-table{margin:0;border:none;box-shadow:none;border-radius:0}
    /* 表头：不做 position:sticky —— 列表容器高度自适应时 sticky 会与首行叠压（实测出现遮挡），
       改为静态表头 + 底色区分，视觉同样清晰且无副作用 */
    .zhiji-ops table.zhiji-ops-table thead th,.zhiji-ops table.zhiji-ops-table thead td{
      background:var(--zhiji-bg-soft);border-bottom:1px solid var(--zhiji-line);font-size:11px;font-weight:600;
      letter-spacing:.04em;color:var(--zhiji-muted);text-transform:uppercase;padding:10px 12px}
    .zhiji-ops table.zhiji-ops-table tbody td{font-size:13px;color:var(--zhiji-body);padding:11px 12px;vertical-align:middle}
    .zhiji-ops table.zhiji-ops-table tbody tr{transition:background .12s ease}
    .zhiji-ops table.zhiji-ops-table tbody tr:hover{background:var(--zhiji-bg-soft)}
    .zhiji-ops table.zhiji-ops-table tbody td strong{color:var(--zhiji-ink)}
    .zhiji-ops table.zhiji-ops-table tbody small{color:var(--zhiji-faint)}
    .zhiji-ops table.zhiji-ops-table .check-column{padding-left:14px}
    @media screen and (max-width:782px){.zhiji-ops table.zhiji-ops-table thead th,.zhiji-ops table.zhiji-ops-table thead td{top:46px}}

    /* ---------- 元件 ---------- */
    .zhiji-ops .zhiji-ops-empty{padding:38px 20px;text-align:center;color:var(--zhiji-faint)}
    .zhiji-ops .zhiji-ops-empty .dashicons{display:block;font-size:28px;width:28px;height:28px;margin:0 auto 8px;opacity:.55}
    .zhiji-ops .zhiji-ops-empty strong{display:block;color:var(--zhiji-body);font-size:14px;margin-bottom:4px}
    .zhiji-ops .zhiji-ops-tag{display:inline-block;padding:2px 10px;border-radius:999px;font-size:12px;line-height:1.6;font-weight:500;white-space:nowrap}
    .zhiji-ops .zhiji-ops-tag.active{background:#fdecea;color:var(--zhiji-danger);border:1px solid #f5c2c0}
    .zhiji-ops .zhiji-ops-tag.cleared{background:#edfaef;color:var(--zhiji-ok);border:1px solid #bfe6c8}
    .zhiji-ops .zhiji-ops-tag.muted{background:var(--zhiji-bg-soft);color:var(--zhiji-muted);border:1px solid var(--zhiji-line-soft)}
    .zhiji-ops .zhiji-ops-code{font-family:Menlo,Consolas,Monaco,monospace;font-size:12px;background:var(--zhiji-bg-soft);
      border:1px solid var(--zhiji-line-soft);border-radius:6px;padding:2px 7px;color:var(--zhiji-body);white-space:nowrap}
    .zhiji-ops .zhiji-ops-rowactions{display:flex;flex-wrap:wrap;gap:6px;align-items:center}
    .zhiji-ops .zhiji-ops-rowactions form{display:inline}
    .zhiji-ops .zhiji-ops-danger{color:var(--zhiji-danger);border-color:#f0c6c4 !important}
    .zhiji-ops .zhiji-ops-danger:hover{background:#fdecea !important;border-color:var(--zhiji-danger) !important;color:var(--zhiji-danger) !important}

    /* ---------- 分页 ---------- */
    .zhiji-ops .zhiji-ops-pagination{margin:12px 0 4px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;
      background:#fff;border:1px solid var(--zhiji-line);border-radius:var(--zhiji-radius);padding:10px 14px;box-shadow:var(--zhiji-shadow)}
    .zhiji-ops .zhiji-ops-pagination .description{margin:0 auto 0 0}

    /* ---------- 审计时间线 ---------- */
    .zhiji-ops .zhiji-ops-activity{margin-top:8px;background:#fff;border:1px solid var(--zhiji-line);border-radius:var(--zhiji-radius);
      padding:14px 18px;box-shadow:var(--zhiji-shadow)}
    .zhiji-ops .zhiji-ops-activity ul{margin:0;padding:0;list-style:none}
    .zhiji-ops .zhiji-ops-activity li{position:relative;padding:8px 0 8px 18px;color:var(--zhiji-body);font-size:13px;line-height:1.7}
    .zhiji-ops .zhiji-ops-activity li::before{content:"";position:absolute;left:2px;top:15px;width:7px;height:7px;border-radius:50%;
      background:#fff;border:2px solid var(--zhiji-primary)}
    .zhiji-ops .zhiji-ops-activity li+li{border-top:1px dashed var(--zhiji-line-soft)}
    .zhiji-ops .zhiji-ops-activity .zhiji-ops-time{color:var(--zhiji-faint);margin-right:8px;font-variant-numeric:tabular-nums}
    .zhiji-ops .zhiji-ops-activity strong{color:var(--zhiji-ink)}

    /* ---------- 接入指引 ---------- */
    .zhiji-ops .zhiji-ops-doc{margin-top:8px;background:#fff;border:1px solid var(--zhiji-line);border-radius:var(--zhiji-radius);
      padding:16px 20px;box-shadow:var(--zhiji-shadow)}
    .zhiji-ops .zhiji-ops-doc h2{margin:0 0 6px;font-size:14px}
    .zhiji-ops .zhiji-ops-doc ol{margin:8px 0 0 18px;color:var(--zhiji-body);font-size:13px;line-height:1.9}
    .zhiji-ops .zhiji-ops-doc code{background:var(--zhiji-bg-soft);border:1px solid var(--zhiji-line-soft);
      border-radius:5px;padding:1px 6px;font-size:12px}
    .zhiji-ops .zhiji-ops-doc .description{margin:6px 0 0}

    /* ---------- 无障碍：键盘焦点可见 ---------- */
    .zhiji-ops a:focus-visible,.zhiji-ops button:focus-visible,.zhiji-ops input:focus-visible,
    .zhiji-ops select:focus-visible{outline:2px solid var(--zhiji-primary);outline-offset:1px;box-shadow:none}

    /* ---------- 弱化占位（2026-09-28：无值时间/优惠内容等） ---------- */
    .zhiji-ops .zhiji-ops-muted{color:var(--zhiji-faint)}

    /* ---------- 表头排序（2026-09-28 新增） ---------- */
    .zhiji-ops table.zhiji-ops-table thead a.zhiji-ops-sort{color:var(--zhiji-muted);text-decoration:none;
      display:inline-flex;align-items:center;gap:3px}
    .zhiji-ops table.zhiji-ops-table thead a.zhiji-ops-sort:hover{color:var(--zhiji-primary)}
    .zhiji-ops table.zhiji-ops-table thead a.zhiji-ops-sort.is-active{color:var(--zhiji-primary);font-weight:700}
    .zhiji-ops table.zhiji-ops-table thead a.zhiji-ops-sort .zhiji-ops-sort-arrow{font-size:11px;line-height:1;opacity:.8}

    /* ---------- 行级详情抽屉（2026-09-28 v2：slide-over，行业惯例见附录 R.9） ---------- */
    .zhiji-ops .zhiji-ops-modal-mask{position:fixed;inset:0;z-index:100000;background:rgba(16,24,40,.32);
      display:flex;justify-content:flex-end;align-items:stretch;padding:0;animation:zhiji-ops-fade .18s ease}
    .zhiji-ops .zhiji-ops-modal-mask[hidden]{display:none}
    .zhiji-ops .zhiji-ops-modal{background:#fff;width:min(480px,92vw);height:100%;max-height:none;
      border-radius:0;box-shadow:-12px 0 40px rgba(16,24,40,.18);display:flex;flex-direction:column;
      animation:zhiji-ops-slide .24s cubic-bezier(.2,.7,.3,1)}
    @media (max-width:640px){.zhiji-ops .zhiji-ops-modal{width:100vw}}
    .zhiji-ops .zhiji-ops-modal-head{display:flex;align-items:center;justify-content:space-between;gap:12px;
      padding:16px 20px;border-bottom:1px solid var(--zhiji-line);flex-shrink:0}
    .zhiji-ops .zhiji-ops-modal-titlewrap{display:flex;align-items:center;gap:10px;min-width:0}
    .zhiji-ops .zhiji-ops-modal-head strong{font-size:15px;color:var(--zhiji-ink);white-space:nowrap}
    .zhiji-ops .zhiji-ops-modal-close{background:none;border:1px solid transparent;border-radius:6px;color:var(--zhiji-muted);
      font-size:20px;line-height:1;width:30px;height:30px;cursor:pointer;padding:0;flex-shrink:0}
    .zhiji-ops .zhiji-ops-modal-close:hover{background:var(--zhiji-bg-soft);color:var(--zhiji-ink)}
    .zhiji-ops .zhiji-ops-modal-body{padding:0 0 20px;overflow:auto;flex:1}

    /* 概览区：2 列大字网格（关键信息一眼扫到） */
    .zhiji-ops .zhiji-ops-dl-primary{display:grid;grid-template-columns:1fr 1fr;gap:1px;
      background:var(--zhiji-line-soft);border-bottom:1px solid var(--zhiji-line-soft)}
    .zhiji-ops .zhiji-ops-dl-pcell{background:#fff;padding:14px 20px 12px;display:flex;flex-direction:column;gap:4px;min-width:0}
    .zhiji-ops .zhiji-ops-dl-k{font-size:12px;color:var(--zhiji-faint)}
    .zhiji-ops .zhiji-ops-dl-v{font-size:14px;font-weight:600;color:var(--zhiji-ink);word-break:break-all;overflow-wrap:anywhere}

    /* 明细区：label 上 / value 下的分段列表 */
    .zhiji-ops .zhiji-ops-dl-fields{padding:4px 20px 0}
    .zhiji-ops .zhiji-ops-dl-item{padding:11px 0;border-bottom:1px dashed var(--zhiji-line-soft);
      display:flex;flex-direction:column;gap:4px}
    .zhiji-ops .zhiji-ops-dl-item:last-child{border-bottom:none}
    .zhiji-ops .zhiji-ops-dl-item .zhiji-ops-dl-k{font-size:12px;color:var(--zhiji-faint)}
    .zhiji-ops .zhiji-ops-dl-item .zhiji-ops-dl-v{font-size:13px;color:var(--zhiji-ink);word-break:break-all;overflow-wrap:anywhere}

    /* meta：原生 <details> 默认折叠（progressive disclosure） */
    .zhiji-ops .zhiji-ops-dl-meta{margin:12px 20px 0;border:1px solid var(--zhiji-line-soft);
      border-radius:8px;background:var(--zhiji-bg-soft)}
    .zhiji-ops .zhiji-ops-dl-meta summary{cursor:pointer;padding:10px 14px;font-size:13px;color:var(--zhiji-muted);user-select:none}
    .zhiji-ops .zhiji-ops-dl-meta summary:hover{color:var(--zhiji-primary)}
    .zhiji-ops .zhiji-ops-dl-meta[open] summary{border-bottom:1px dashed var(--zhiji-line-soft)}
    .zhiji-ops .zhiji-ops-dl-meta pre.zhiji-ops-pre{margin:10px;background:#fff;border:none;max-height:300px}

    @keyframes zhiji-ops-fade{from{opacity:0}to{opacity:1}}
    @keyframes zhiji-ops-slide{from{transform:translateX(56px);opacity:0}to{transform:none;opacity:1}}
    @media (prefers-reduced-motion:reduce){.zhiji-ops .zhiji-ops-modal-mask,.zhiji-ops .zhiji-ops-modal{animation:none}}
    </style>
    <?php
}

/**
 * 操作结果提示（由 admin-post 重定向带回）
 *
 * @return void
 */
function zhiji_ops_print_notice()
{
    if (!isset($_GET['ops_msg'])) {
        return;
    }
    $ok  = isset($_GET['ops_ok']) && '1' === (string) $_GET['ops_ok'];
    $msg = sanitize_text_field(rawurldecode(wp_unslash($_GET['ops_msg'])));
    if ('' === $msg) {
        return;
    }
    printf(
        '<div class="notice %s is-dismissible"><p>%s</p></div>',
        $ok ? 'notice-success' : 'notice-error',
        esc_html($msg)
    );
}

/**
 * 输出统计卡片
 *
 * @param array $stats
 * @return void
 */
function zhiji_ops_render_cards(array $stats)
{
    if (!$stats) {
        return;
    }
    echo '<div class="zhiji-ops-cards">';
    foreach ($stats as $card) {
        $tone = !empty($card['tone']) && in_array($card['tone'], array('warn', 'ok'), true) ? ' tone-' . $card['tone'] : '';
        echo '<div class="zhiji-ops-card' . esc_attr($tone) . '">';
        echo '<div class="zhiji-ops-card-label">';
        if (!empty($card['icon'])) {
            echo '<span class="dashicons ' . esc_attr($card['icon']) . '"></span>';
        }
        echo esc_html($card['label']) . '</div>';
        echo '<div class="zhiji-ops-card-value">' . esc_html($card['value']) . '</div>';
        if (!empty($card['hint'])) {
            echo '<div class="zhiji-ops-card-hint">' . esc_html($card['hint']) . '</div>';
        }
        echo '</div>';
    }
    echo '</div>';
}

/**
 * 分区标题（含右侧分隔线与可选计数徽标）
 *
 * @param string $title
 * @param string $count 计数徽标文案（可选）
 * @return void
 */
function zhiji_ops_section_title($title, $count = '')
{
    echo '<div class="zhiji-ops-section"><h2>' . esc_html($title) . '</h2>';
    if ('' !== (string) $count) {
        echo '<span class="zhiji-ops-count">' . esc_html($count) . '</span>';
    }
    echo '<span class="zhiji-ops-section-line"></span></div>';
}
/**
 * 渲染运维操作审计列表
 *
 * @param string $scene
 * @param int    $limit
 * @return void
 */
function zhiji_ops_render_activity($scene = '', $limit = 10)
{
    $rows = zhiji_ops_activities($limit, $scene);
    echo '<div class="zhiji-ops-activity">';
    if (!$rows) {
        echo '<p class="description">' . esc_html__('暂无操作记录。', 'zhiji') . '</p>';
        echo '</div>';
        return;
    }
    echo '<ul style="margin:0;padding:0;list-style:none">';
    foreach ($rows as $row) {
        printf(
            '<li><span class="zhiji-ops-time">%s</span><strong>%s</strong> · %s · %s%s</li>',
            esc_html($row['time']),
            esc_html($row['user']),
            esc_html(zhiji_ops_action_label($row['action'])),
            esc_html($row['detail']),
            $row['scene'] ? ' <span class="zhiji-ops-code">' . esc_html($row['scene']) . '</span>' : ''
        );
    }
    echo '</ul></div>';
}
