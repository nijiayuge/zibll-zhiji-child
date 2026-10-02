<?php
/**
 * @module  FieldCollapse
 * @desc    后台设置页的「可折叠分组」—— 基于 subheading，零数据风险，视觉统一
 * @since   2.1.5
 *
 * 2026-10-03 新增（P4 返工）。
 *
 * 【为什么不直接用 CSF 的 accordion 字段】—— 真实踩坑，两个问题都无解：
 *   ① 父主题 csf-framework/fields/accordion/accordion.php:61 直接访问
 *      `$this->field['id']`（**无 isset 保护**）。accordion 容器不给 id → PHP Warning，
 *      累积后触发 WP 致命错误 → **后台设置页整页打不开**；
 *   ② 补了 id 之后，表单 name 变成 `zhiji_options[zhiji_accordion_1][reward_center_w_points]`
 *      —— **多了一层嵌套**。用户点「保存设置」后值写进嵌套数组，而代码用
 *      `zhiji_get_option('reward_center_w_points')` 读顶层 → **配置全部读不回来**，
 *      表现为「保存后所有配置恢复默认」，属数据损坏。
 *   两条都出自父主题实现，**绝不改父主题** → 本项目放弃 accordion 字段。
 *
 * 【本方案】
 * subheading（渲染为 `div.csf-field.csf-field-subheading`，其后是同级 `csf-field`
 * 兄弟节点）+ 自己的 CSS/JS：
 *   · 点击标题折叠「它自己 + 到下一个 subheading 之前」的所有兄弟节点；
 *   · 字段 HTML 与 name **一个字节都不动** → 零数据风险。
 *
 * 【为什么自带一套样式，而不是用父主题的】—— 实测发现
 *   ⚠️ 父主题 CSS 里**根本没有 `.csf-field-subheading` 的规则**（grep 零命中），
 *   subheading 只是个裸 `<h4>`，完全继承普通字段的
 *   `.csf-field { padding: 20px 30px; }`。后果正是用户反馈的：
 *     · **没有图标** —— 父主题没给任何图标机制
 *     · **间距不统一、标题与内容「贴紧」** —— 标题和它下面的字段用同一套 padding，
 *       视觉上没有任何「分组」感
 *   故本文件自带一套：**左侧竖条 + 浅底 + 圆角 + 统一内边距 + 折叠箭头**，
 *   并对「组内第一个字段」去掉上边距，让分组边界清晰。
 */
defined('ABSPATH') || exit;

/**
 * 输出折叠分组的 CSS + JS
 *
 * @return void
 */
function zhiji_admin_collapse_assets()
{
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
<style id="zhiji-field-collapse">
/* ---------- 折叠分组标题：左侧竖条 + 浅底，形成清晰的分组边界 ---------- */
.csf-section .csf-field-subheading {
    padding: 14px 30px 14px 34px;
    margin: 4px 0 0;
    background: #f6f8fb;
    border-left: 4px solid #2271b1;
    border-radius: 0 6px 6px 0;
    position: relative;
    cursor: pointer;
    user-select: none;
    transition: background .15s, border-color .15s;
}
.csf-section .csf-field-subheading:hover { background: #eef3f9; }
.csf-section .csf-field-subheading.is-open { background: #f0f6fc; }

/* 标题文字：小一号、加粗、深色 —— 与普通字段标题明显区分 */
.csf-section .csf-field-subheading .csf-title { width: auto; }
.csf-section .csf-field-subheading .csf-title h4 {
    display: inline-block;
    margin: 0;
    padding-right: 24px;
    font-size: 13px;
    font-weight: 600;
    color: #1d2327;
    line-height: 1.6;
    letter-spacing: .2px;
}
.csf-section .csf-field-subheading .csf-fieldset,
.csf-section .csf-field-subheading .csf-desc-text { display: none; }

/* 折叠箭头：纯 CSS 三角，旋转表示展开/收起（所有分组一致） */
.csf-section .csf-field-subheading::after {
    content: '';
    position: absolute;
    right: 14px;
    top: 50%;
    margin-top: -4px;
    width: 0; height: 0;
    border-left: 6px solid #2271b1;
    border-top: 5px solid transparent;
    border-bottom: 5px solid transparent;
    transform: rotate(90deg);
    transform-origin: 1px 50%;
    transition: transform .18s;
}
.csf-section .csf-field-subheading.is-closed::after { transform: rotate(0deg); }
.csf-section .csf-field-subheading.is-closed { background: #fbfcfd; border-left-color: #c3c4c7; }
.csf-section .csf-field-subheading.is-closed .csf-title h4 { color: #646970; }

/* ---------- 组内字段：与标题的距离收一下，避免"贴紧" ---------- */
.csf-section .csf-field-subheading + .csf-field { margin-top: 0; }
.csf-section .csf-fold-body { padding-top: 16px; }

/* 只读型分组（标题带「（只读」的）用灰色竖条，与可配置分组区分 */
.csf-section .csf-field-subheading.is-readonly { border-left-color: #8c8f94; background: #f8f9f9; }
.csf-section .csf-field-subheading.is-readonly.is-closed { border-left-color: #dcdcde; }
</style>
<script id="zhiji-field-collapse">
(function(){
  var READONLY_RE = /只读|无需配置|在用户中心查看/;

  function bind(){
    var heads = document.querySelectorAll('.csf-section .csf-field-subheading');
    if(!heads.length) return;

    Array.prototype.forEach.call(heads, function(head){
      if(head.dataset.zhijiBound) return;
      head.dataset.zhijiBound = '1';

      var h4 = head.querySelector('.csf-title h4');
      if(!h4) return;
      var title = h4.textContent || '';

      // 本组范围：本 subheading 起，到下一个 subheading 之前的全部兄弟节点
      var group = [];
      var n = head;
      while(n){
        group.push(n);
        n = n.nextElementSibling;
        if(n && n.classList.contains('csf-field-subheading')) break;
      }
      // 标出组内字段（不含标题自己）
      group.forEach(function(el){
        if(el !== head && el.classList.contains('csf-field')) el.classList.add('zhiji-fold-body');
      });

      // 只读分组：淡化竖条，视觉上与「要配置」的分组区分
      if(READONLY_RE.test(title)) head.classList.add('is-readonly');

      function set(closed){
        head.classList.toggle('is-closed', closed);
        head.classList.toggle('is-open', !closed);
        head.setAttribute('aria-expanded', closed ? 'false' : 'true');
        group.forEach(function(el){
          if(el !== head) el.style.display = closed ? 'none' : '';
        });
      }

      // 点标题或整条竖条区域都可切换
      head.addEventListener('click', function(){ set(!head.classList.contains('is-closed')); });
      head.setAttribute('role', 'button');
      head.setAttribute('tabindex', '0');
      head.setAttribute('aria-expanded', 'true');
      head.addEventListener('keydown', function(e){
        if(e.key === 'Enter' || e.key === ' '){
          e.preventDefault();
          set(!head.classList.contains('is-closed'));
        }
      });
    });
  }

  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', bind);
  } else { bind(); }

  // CSF 的 dependency 会在字段显隐时重排 DOM，这里补绑几次
  var tries = 0;
  var iv = setInterval(function(){
    bind();
    if(++tries > 20) clearInterval(iv);
  }, 700);
})();
</script>
    <?php
}
