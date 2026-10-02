<?php
/**
 * @module  FieldCollapse
 * @desc    后台设置页的「可折叠分组」—— 基于 subheading，零数据风险
 * @since   2.1.4
 *
 * 2026-10-03 新增。
 *
 * 【为什么不直接用 CSF 的 accordion 字段】—— 真实踩坑，两个问题都无解：
 *   ① 父主题 csf-framework/fields/accordion/accordion.php:61 直接访问
 *      `$this->field['id']`（**无 isset 保护**）。accordion 容器不给 id → PHP Warning，
 *      累积后触发 WP 致命错误 → **后台设置页整页打不开**；
 *      补了 id 又如何？见 ②。
 *   ② 补 id 后，表单 name 变成 `zhiji_options[zhiji_accordion_1][reward_center_w_points]` ——
 *      **多了一层嵌套**。用户点「保存设置」后值写进嵌套数组，
 *      而代码用 `zhiji_get_option('reward_center_w_points')` 读顶层 → **读不回来**，
 *      表现为「保存后所有配置恢复默认」，属数据损坏。
 *   两条都出自父主题 CSF 的实现，**绝不改父主题** → 本项目放弃 accordion。
 *
 * 【本方案怎么做】
 * subheading 渲染为 `div.csf-field.csf-field-subheading`，其后是同级的
 * `csf-field` 兄弟节点。于是：
 *   点击 subheading 标题 → 折叠「它自己 + 到下一个 subheading 之前」的所有兄弟节点。
 * 字段 HTML 与 name **一个字节都不动**，只加显示/隐藏 —— 零数据风险。
 *
 * 【效果】
 * - 分组默认展开（与原来一致，不改变既有使用习惯）；
 * - 点标题即可收起/展开该组，长页面显著变短；
 * - 状态记在 sessionStorage，刷新后保持；当前打开的分组最多一个，避免全展开等于没折叠。
 */
defined('ABSPATH') || exit;

/**
 * 输出折叠分组所需的 CSS + JS
 *
 * @param string $scope 限定作用域（分节的 data-section-id）。留空 = 全页生效。
 * @return void
 */
function zhiji_admin_collapse_assets($scope = '')
{
    if (!current_user_can('manage_options')) {
        return;
    }
    $sel = $scope ? ' .csf-section[data-section-id="' . esc_attr($scope) . '"]' : '';
    ?>
<style id="zhiji-field-collapse">
<?php echo $sel; ?> .zhiji-fold-h {
    cursor: pointer; user-select: none; position: relative; padding-right: 26px;
    transition: color .15s;
}
<?php echo $sel; ?> .zhiji-fold-h:hover { color: #2271b1; }
<?php echo $sel; ?> .zhiji-fold-h::after {
    content: ''; position: absolute; right: 4px; top: 50%; margin-top: -5px;
    width: 0; height: 0; border-left: 5px solid currentColor;
    border-top: 4px solid transparent; border-bottom: 4px solid transparent;
    transition: transform .18s; transform-origin: 2px 50%;
}
<?php echo $sel; ?> .zhiji-fold.is-closed .zhiji-fold-h::after { transform: rotate(-90deg); }
<?php echo $sel; ?> .zhiji-fold.is-closed > .csf-field { display: none; }
</style>
<script id="zhiji-field-collapse">
(function(){
  var SCOPE = <?php echo $sel ? "'" . esc_js($scope) . "'" : "''"; ?>;
  function root(){
    return SCOPE ? document.querySelector(SCOPE) : document;
  }
  function bind(){
    var r = root();
    if(!r) return;
    var heads = r.querySelectorAll('.csf-field-subheading');
    if(!heads.length) return;
    Array.prototype.forEach.call(heads, function(head, i){
      if(head.dataset.zhijiBound) return;
      head.dataset.zhijiBound = '1';
      var h4 = head.querySelector('.csf-title h4');
      if(!h4) return;
      h4.classList.add('zhiji-fold-h');
      h4.setAttribute('role', 'button');
      h4.setAttribute('tabindex', '0');
      h4.setAttribute('aria-expanded', 'true');
      h4.title = '点击折叠 / 展开本组';
      // 给所属分组加 class：本 subheading 自己 + 到下一个 subheading 之前的兄弟节点
      var group = [];
      var n = head;
      while(n){
        group.push(n);
        n = n.nextElementSibling;
        if(n && n.classList.contains('csf-field-subheading')) break;
      }
      group.forEach(function(el){
        if(el !== head) el.classList.add('zhiji-fold-body');
      });
      function toggle(){
        var closed = head.classList.toggle('is-closed');
        group.forEach(function(el){
          if(el !== head) el.style.display = closed ? 'none' : '';
        });
        h4.setAttribute('aria-expanded', closed ? 'false' : 'true');
      }
      h4.addEventListener('click', toggle);
      h4.addEventListener('keydown', function(e){
        if(e.key === 'Enter' || e.key === ' '){ e.preventDefault(); toggle(); }
      });
    });
  }
  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', bind);
  } else { bind(); }
  // CSF 的 dependency 会在切换时重排/显隐字段，这里补绑一次
  var tries = 0;
  var iv = setInterval(function(){
    bind();
    if(++tries > 20) clearInterval(iv);
  }, 700);
})();
</script>
    <?php
}
