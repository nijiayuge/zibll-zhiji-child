/* 知集 · 404 小游戏：贪吃蛇（零外部依赖，canvas 实现）
 * 独立文件纪律：node --check 可校验；DOM 安全启动（DOMContentLoaded 兜底）。
 * 操作：方向键 / WASD 移动，空格暂停/继续；撞墙或咬到自己即结束。
 */
(function () {
	'use strict';

	function boot() {
		var canvas = document.getElementById('zhiji-404-snake');
		if (!canvas || !canvas.getContext) { return; }

		var ctx = canvas.getContext('2d');
		var scoreEl = document.getElementById('zhiji-404-score');
		var restartBtn = document.getElementById('zhiji-404-restart');

		var GRID = 20;                       // 格子尺寸(px)
		var COLS = canvas.width / GRID;      // 20 列
		var ROWS = canvas.height / GRID;     // 15 行
		var TICK = 130;                      // 步进间隔(ms)

		var snake, dir, nextDir, food, score, timer, paused, over;

		function reset() {
			snake = [{ x: 8, y: 7 }, { x: 7, y: 7 }, { x: 6, y: 7 }];
			dir = { x: 1, y: 0 };
			nextDir = dir;
			score = 0;
			paused = false;
			over = false;
			placeFood();
			renderScore();
			draw();
			start();
		}

		function start() {
			stopTimer();
			timer = setInterval(step, TICK);
		}

		function stopTimer() {
			if (timer) { clearInterval(timer); timer = null; }
		}

		function placeFood() {
			while (true) {
				var f = { x: Math.floor(Math.random() * COLS), y: Math.floor(Math.random() * ROWS) };
				if (!snake.some(function (s) { return s.x === f.x && s.y === f.y; })) { food = f; return; }
			}
		}

		function step() {
			if (paused || over) { return; }
			dir = nextDir;
			var head = { x: snake[0].x + dir.x, y: snake[0].y + dir.y };

			// 撞墙 / 咬到自己
			if (head.x < 0 || head.y < 0 || head.x >= COLS || head.y >= ROWS ||
				snake.some(function (s) { return s.x === head.x && s.y === head.y; })) {
				over = true;
				stopTimer();
				draw(true);
				return;
			}

			snake.unshift(head);
			if (head.x === food.x && head.y === food.y) {
				score++;
				renderScore();
				placeFood();
			} else {
				snake.pop();
			}
			draw();
		}

		function draw(lose) {
			// 背景
			ctx.fillStyle = '#fafafa';
			ctx.fillRect(0, 0, canvas.width, canvas.height);

			// 食物
			ctx.fillStyle = '#e8533f';
			ctx.fillRect(food.x * GRID + 3, food.y * GRID + 3, GRID - 6, GRID - 6);

			// 蛇身
			ctx.fillStyle = '#22c55e';
			snake.forEach(function (s, i) {
				ctx.fillStyle = i === 0 ? '#15803d' : '#22c55e';
				ctx.fillRect(s.x * GRID + 1, s.y * GRID + 1, GRID - 2, GRID - 2);
			});

			// 状态文字
			if (paused) { banner('已暂停 · 按空格继续'); }
			if (over || lose) { banner('游戏结束 · 得分 ' + score + '，点「重新开始」再来'); }
		}

		function banner(text) {
			ctx.fillStyle = 'rgba(17,24,39,.72)';
			ctx.fillRect(0, canvas.height / 2 - 24, canvas.width, 48);
			ctx.fillStyle = '#fff';
			ctx.font = '14px sans-serif';
			ctx.textAlign = 'center';
			ctx.textBaseline = 'middle';
			ctx.fillText(text, canvas.width / 2, canvas.height / 2);
		}

		function renderScore() {
			if (scoreEl) { scoreEl.textContent = '得分：' + score; }
		}

		var KEYMAP = {
			ArrowUp: { x: 0, y: -1 }, w: { x: 0, y: -1 }, W: { x: 0, y: -1 },
			ArrowDown: { x: 0, y: 1 }, s: { x: 0, y: 1 }, S: { x: 0, y: 1 },
			ArrowLeft: { x: -1, y: 0 }, a: { x: -1, y: 0 }, A: { x: -1, y: 0 },
			ArrowRight: { x: 1, y: 0 }, d: { x: 1, y: 0 }, D: { x: 1, y: 0 }
		};

		document.addEventListener('keydown', function (e) {
			if (over) { return; }
			if (e.code === 'Space' || e.key === ' ') {
				e.preventDefault();
				paused = !paused;
				draw();
				return;
			}
			var nd = KEYMAP[e.key];
			if (!nd) { return; }
			// 禁止 180° 掉头
			if (nd.x === -dir.x && nd.y === -dir.y) { return; }
			e.preventDefault(); // 防方向键滚动页面
			nextDir = nd;
		});

		if (restartBtn) {
			restartBtn.addEventListener('click', reset);
		}

		// 页面不可见时自动暂停，回来自动恢复（节能）
		document.addEventListener('visibilitychange', function () {
			if (over) { return; }
			if (document.hidden) { paused = true; draw(); }
		});

		reset();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
