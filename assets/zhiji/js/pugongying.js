/* 蒲公英种子飞絮（自研，配合 pugongying.css）
 * 从蒲公英球位置向两侧上方飘散渐隐（pos-left 向左、pos-right 向右）
 * 注：函数中 <script> 先于 <div class="dandelion"> 输出，需轮询等待 DOM */
(function(){
  if (window.zhijiPugongyingDone) return;
  window.zhijiPugongyingDone = true;
  var d = null;
  var timer = setInterval(function(){
    d = document.querySelector('.dandelion');
    if (d) { clearInterval(timer); init(); }
  }, 200);
  function init(){
    var isLeft = d.classList.contains('pos-left');
    function spawn(){
      var r = d.getBoundingClientRect();
      var s = document.createElement('i');
      var size = 3 + Math.random() * 3;
      var dx = (Math.random() * 320 + 80) * (isLeft ? -1 : 1);
      var dy = -(Math.random() * 240 + 50);
      s.style.cssText = 'position:fixed;left:' + (r.left + r.width / 2 + (Math.random() * 24 - 12)) + 'px;top:' + (r.top + 36) + 'px;width:' + size.toFixed(1) + 'px;height:' + size.toFixed(1) + 'px;border-radius:50%;background:rgba(196,161,118,.9);box-shadow:0 0 5px rgba(196,161,118,.5);pointer-events:none;z-index:99999;opacity:.95;transition:transform 6s linear,opacity 6s linear;';
      document.body.appendChild(s);
      requestAnimationFrame(function(){
        s.style.transform = 'translate(' + dx + 'px,' + dy + 'px) rotate(240deg) scale(.25)';
        s.style.opacity = '0';
      });
      setTimeout(function(){ if (s.parentNode) s.parentNode.removeChild(s); }, 6200);
    }
    spawn();
    setInterval(spawn, 2200);
  }
})();
