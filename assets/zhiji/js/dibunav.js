/* 底部导航交互（自研，替代缺失的 dibunav.js）
 * 波浪微动画 + 按钮 tooltip 提示 */
(function(){
  if (window.zhijiDibunavDone) return;
  window.zhijiDibunavDone = true;
  // 为底部导航链接补 data-toggle 简单悬浮提示（bootstrap tooltip 已由父主题提供）
  var nav = document.querySelector('.joe_header__below-logon');
  if (!nav) return;
  // 波浪（.footwavewave）循环移动，模拟水波
  var wave = document.querySelector('.footwavewave');
  if (wave) {
    var pos = 0;
    setInterval(function(){
      pos -= 6;
      wave.style.backgroundPosition = pos + 'px 0';
    }, 60);
  }
})();
