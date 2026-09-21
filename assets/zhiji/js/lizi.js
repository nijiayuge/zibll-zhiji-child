/* 鼠标点击粒子特效（自研，替代缺失的 lizi.js）
 * 点击任意位置，从点击点向四周迸发 8 颗彩色粒子，渐隐消失 */
(function(){
  if (window.zhijiLiziDone) return;
  window.zhijiLiziDone = true;
  var colors = ['#ff9a9e', '#fecfef', '#a1c4fd', '#c2e9fb', '#f6d365', '#fda085', '#84fab0', '#8fd3f4'];
  document.addEventListener('click', function(e){
    var n = 8;
    for (var i = 0; i < n; i++) {
      (function(idx){
        var p = document.createElement('span');
        p.style.cssText = 'position:fixed;left:' + e.clientX + 'px;top:' + e.clientY + 'px;width:8px;height:8px;border-radius:50%;background:' + colors[idx % colors.length] + ';pointer-events:none;z-index:999999;opacity:1;transition:transform .65s ease-out,opacity .65s ease-out;';
        document.body.appendChild(p);
        var ang = (Math.PI * 2 / n) * idx + Math.random() * 0.4;
        var dist = 28 + Math.random() * 42;
        var dx = Math.cos(ang) * dist;
        var dy = Math.sin(ang) * dist;
        requestAnimationFrame(function(){
          p.style.transform = 'translate(' + dx + 'px,' + dy + 'px) scale(0.25)';
          p.style.opacity = '0';
        });
        setTimeout(function(){ if (p.parentNode) p.parentNode.removeChild(p); }, 700);
      })(i);
    }
  }, true);
})();
