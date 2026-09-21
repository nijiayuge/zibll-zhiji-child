/* 2D 雪花飘落特效（自研，替代缺失的 xuehua.js）
 * 生成 60 片白色雪花缓慢飘落，移动端自动跳过 */
(function(){
  if (window.zhijiXuehuaDone) return;
  window.zhijiXuehuaDone = true;
  var isMobile = /Android|iPhone|SymbianOS|Windows Phone|iPad|iPod/i.test(navigator.userAgent);
  if (isMobile) return;
  var count = 60;
  for (var i = 0; i < count; i++) {
    var s = document.createElement('i');
    var size = 3 + Math.random() * 5;
    var drift = (Math.random() * 80 - 40).toFixed(0);
    var fall = 7 + Math.random() * 8;
    s.style.cssText = 'position:fixed;top:-24px;left:' + (Math.random() * 100).toFixed(1) + 'vw;width:' + size.toFixed(1) + 'px;height:' + size.toFixed(1) + 'px;background:#fff;border-radius:50%;opacity:' + (0.35 + Math.random() * 0.5).toFixed(2) + ';pointer-events:none;z-index:99999;animation:zhijiSnowFall ' + fall.toFixed(1) + 's linear infinite;animation-delay:-' + (Math.random() * 12).toFixed(1) + 's;';
    document.body.appendChild(s);
  }
  var st = document.createElement('style');
  st.textContent = '@keyframes zhijiSnowFall{0%{transform:translateY(0) translateX(0)}100%{transform:translateY(112vh) translateX(' + (Math.random() * 80 - 40).toFixed(0) + 'px)}}';
  document.head.appendChild(st);
})();
