/* canvas 气泡上升动态背景（自研，替代缺失的 control.js）
 * 白色半透明气泡从底部缓缓上升，适配 #bubble canvas 容器 */
(function(){
  if (window.zhijiBubbleDone) return;
  window.zhijiBubbleDone = true;
  var c = document.querySelector('#bubble canvas');
  if (!c) return;
  var ctx = c.getContext('2d');
  function resize(){
    c.width = window.innerWidth;
    c.height = window.innerHeight;
  }
  resize();
  window.addEventListener('resize', resize);
  var bubbles = [];
  function spawn(){
    if (bubbles.length < 36) {
      bubbles.push({
        x: Math.random() * c.width,
        y: c.height + 24,
        r: 6 + Math.random() * 20,
        v: 0.5 + Math.random() * 1.6,
        o: 0.12 + Math.random() * 0.3,
        drift: (Math.random() * 0.5 - 0.25)
      });
    }
  }
  function loop(){
    ctx.clearRect(0, 0, c.width, c.height);
    for (var i = bubbles.length - 1; i >= 0; i--) {
      var b = bubbles[i];
      b.y -= b.v;
      b.x += b.drift;
      if (b.y < -40) { bubbles.splice(i, 1); continue; }
      ctx.beginPath();
      ctx.arc(b.x, b.y, b.r, 0, Math.PI * 2);
      ctx.fillStyle = 'rgba(255,255,255,' + b.o + ')';
      ctx.fill();
      ctx.strokeStyle = 'rgba(255,255,255,' + (b.o + 0.12) + ')';
      ctx.lineWidth = 1;
      ctx.stroke();
    }
    requestAnimationFrame(loop);
  }
  setInterval(spawn, 420);
  loop();
})();
