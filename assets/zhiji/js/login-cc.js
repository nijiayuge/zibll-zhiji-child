/* CareerCompass 登录弹窗复刻 —— 运行时注入（子主题，不改父主题文件）
 * 在 #u_sign 弹窗内注入左侧品牌面板（含四个动画角色）与 Welcome 标题，幂等执行。
 * 四个动画角色移植自 careercompass 的 <AnimatedCharacters/> 组件：
 *   - 眼睛跟随鼠标
 *   - 紫/黑角色随机眨眼
 *   - 身体随鼠标轻微倾斜
 *   - 输入时紫/黑对视；密码可见时紫色偷看、其余三角色仅瞳孔向左看（身体回正避免错位）
 */
(function ($) {
    'use strict';

    // ---------- 构建四个角色 DOM ----------
    function buildChars($stage) {
        if ($stage.find('.cc-char').length) return;

        function eyes(hasWhite, pupil) {
            var p = '<span class="cc-pupil" style="width:' + pupil + 'px;height:' + pupil + 'px;"></span>';
            if (hasWhite) {
                return '<div class="cc-eyes">' +
                    '<span class="cc-eye" style="width:' + (pupil + 5) + 'px;height:' + (pupil + 5) + 'px;">' + p + '</span>' +
                    '<span class="cc-eye" style="width:' + (pupil + 5) + 'px;height:' + (pupil + 5) + 'px;">' + p + '</span>' +
                    '</div>';
            }
            return '<div class="cc-eyes">' + p + p + '</div>';
        }

        var html =
            // 紫（后排）
            '<div class="cc-char cc-char--purple">' + eyes(true, 5) + '</div>' +
            // 黑（中排）
            '<div class="cc-char cc-char--black">' + eyes(true, 4) + '</div>' +
            // 橙（前排左，半圆）
            '<div class="cc-char cc-char--orange">' + eyes(false, 7) + '</div>' +
            // 黄（前排右，圆顶 + 嘴）
            '<div class="cc-char cc-char--yellow">' + eyes(false, 7) +
                '<span class="cc-mouth"></span>' +
            '</div>';

        $stage.append(html);
    }

    // ---------- 角色动画（眼睛跟随 / 眨眼 / 倾斜 / 彩蛋） ----------
    function animateChars($stage) {
        var mouse = { x: window.innerWidth / 2, y: window.innerHeight / 2 };
    var state = { typing: false, peek: false };
    var $modal = $stage.closest('#u_sign');
    // 偷看彩蛋：直接响应眼睛图标（.passw）点击。父主题 main.js 用单一全局 _win.passw 标志切换，
    // 多密码框场景下会把“显示/隐藏”反转，导致仅靠 input.type 判定不可靠；故此处独立维护切换状态。
    var peekRevealed = false;
    $modal.on('click.ccpeek', '.passw', function () {
        peekRevealed = !peekRevealed;
    });

        $(document).on('mousemove.ccchars', function (e) {
            mouse.x = e.clientX;
            mouse.y = e.clientY;
        });

        // 角色配置：pupilMax=瞳孔最大位移；white=是否有白色眼球
        var chars = [
            { el: $stage.find('.cc-char--purple')[0], pupils: $stage.find('.cc-char--purple .cc-pupil'), white: true, max: 5, force: null },
            { el: $stage.find('.cc-char--black')[0], pupils: $stage.find('.cc-char--black .cc-pupil'), white: true, max: 4, force: null },
            { el: $stage.find('.cc-char--orange')[0], pupils: $stage.find('.cc-char--orange .cc-pupil'), white: false, max: 5, force: null },
            { el: $stage.find('.cc-char--yellow')[0], pupils: $stage.find('.cc-char--yellow .cc-pupil'), white: false, max: 5, force: null }
        ];

        // 每帧同步彩蛋状态：输入中=对视；眼睛被点亮（显示密码）=偷看
        function syncState() {
            var active = document.activeElement;
            state.typing = !!(active && active.classList && active.classList.contains('line-form-input'));
            state.peek = peekRevealed;                            // 由眼睛图标点击直接驱动，不受父主题全局标志干扰
        }

        function frame() {
            syncState();
        // 彩蛋状态：
        //  - 输入时紫/黑对视；
        //  - 点击眼睛（显示密码）时：紫色角色前倾偷看；其余三角色仅瞳孔向左看，
        //    身体回正（skewX(0deg)），避免 rotate 导致眼睛/嘴与身体错位
        var peekForce = { x: -4, y: 3 };   // 其余三角色转头向左的瞳孔位移
        $stage.parent().toggleClass('cc-peek', state.peek);

            for (var i = 0; i < chars.length; i++) {
                var c = chars[i];
                if (!c.el) continue;

                // 彩蛋力方向：偷看优先于输入对视
                if (state.peek) {
                    // 紫色白眼 10px + 瞳孔 5px 且 overflow:hidden，位移过大会被裁掉导致"瞳孔消失"，故限制在 2px 内保持可见
                    c.force = (i === 0) ? { x: 2, y: 2 } : peekForce;   // 紫保持偷看（瞳孔留白圆内）；其余仅瞳孔向左看
                } else if (state.typing) {
                    c.force = (i === 0) ? { x: 3, y: 4 } : (i === 1 ? { x: 0, y: -4 } : null);
                } else {
                    c.force = null;
                }

                var rect = c.el.getBoundingClientRect();
                var cx = rect.left + rect.width / 2;
                var cy = rect.top + rect.height / 3;
                var dx = mouse.x - cx;
                var dy = mouse.y - cy;

            // 身体倾斜；偷看时紫色前倾放大，其余三角色身体回正，仅瞳孔移动制造"转头"效果
            var skew = Math.max(-6, Math.min(6, -dx / 120));
            var extra = '';
            if (state.peek) {
                if (i === 0) {
                    extra = ' rotate(7deg) scale(1.12)';
                } else {
                    skew = 0;  // 身体回正，避免 rotate 导致眼睛/嘴错位
                }
            }
            c.el.style.transform = 'skewX(' + skew + 'deg)' + extra;

                // 瞳孔位移
                var px, py;
                if (c.force) {
                    px = c.force.x; py = c.force.y;
                } else {
                    var dist = Math.min(Math.sqrt(dx * dx + dy * dy), c.max);
                    var ang = Math.atan2(dy, dx);
                    px = Math.cos(ang) * dist;
                    py = Math.sin(ang) * dist;
                }
                c.pupils.each(function () {
                    this.style.transform = 'translate(' + px + 'px,' + py + 'px)';
                });
            }
            requestAnimationFrame(frame);
        }
        requestAnimationFrame(frame);

        // 紫/黑随机眨眼
        function scheduleBlink($eyes) {
            var t = setTimeout(function () {
                $eyes.addClass('is-blink');
                setTimeout(function () {
                    $eyes.removeClass('is-blink');
                    scheduleBlink($eyes);
                }, 150);
            }, Math.random() * 4000 + 3000);
            return t;
        }
        scheduleBlink($stage.find('.cc-char--purple .cc-eye'));
        scheduleBlink($stage.find('.cc-char--black .cc-eye'));

        // 偷看彩蛋改为直接绑定眼睛图标(.passw)点击切换 peekRevealed，规避父主题全局 _win.passw 标志在
        // 多密码框场景下的反转问题；输入对视彩蛋仍基于 document.activeElement 实时判定。
    }

    // ---------- 注入品牌面板 ----------
    function initCcLogin() {
        var $modal = $('#u_sign');
        if (!$modal.length || $modal.hasClass('cc-login-active')) {
            return; // 幂等
        }
        $modal.addClass('cc-login-active');

        var assets = window.ccLoginAssets || {};
        var logoLight = assets.logo_light || '';
        var logoDark = assets.logo_dark || '';
        var siteName = assets.site_name || 'CareerCompass';
        // 按当前站点昼夜模式选初始 logo：暗色主题用亮色 logo，白色主题用暗色 logo
        var isDark = $('body').hasClass('dark-theme');
        var logoSrc = isDark ? logoDark : logoLight;

        // 左侧品牌面板（对应 careercompass 左栏：站点 logo + 中部四角色 + 底部隐私/条款）
        // 带 white-src / dark-src 属性，站点切换昼夜主题时由 zibll 主脚本自动换图
        var $brand = $(
            '<div class="cc-brand">' +
                '<div class="cc-brand-top">' +
                    '<span class="cc-brand-name">' + siteName + '</span>' +
                '</div>' +
                '<div class="cc-brand-center">' +
                    '<div class="cc-chars"></div>' +
                    '<div class="cc-peek-bubble">偷看中…🫣</div>' +
                    '<div class="cc-tag">您的AI职业副驾驶<br>自信地发现、申请与成长</div>' +
                '</div>' +
                '<div class="cc-brand-foot">' +
                    '<a href="/privacy-policy">隐私政策</a>' +
                    '<a href="/terms">服务条款</a>' +
                '</div>' +
            '</div>'
        );
        $modal.find('.sign-content').prepend($brand);

        // 将站点 Logo 移到右侧表单面板顶部（原项目 logo 在左栏；现按需求移至右侧弹窗内）。
        // 同样带 white-src / dark-src，站点昼夜切换时由 zibll 主脚本自动换图。
        var $formLogo = $(
            '<div class="cc-form-logo-wrap">' +
                '<img class="cc-form-logo" src="' + logoSrc + '" alt="' + siteName + '"' +
                    ' white-src="' + logoLight + '" dark-src="' + logoDark + '">' +
            '</div>'
        );
        $modal.find('.sign').prepend($formLogo);

        buildChars($brand.find('.cc-chars'));
        animateChars($brand.find('.cc-chars'));

        // 右侧欢迎标题 + 占位符 + 按钮文案
        var $signin = $modal.find('#tab-sign-in');
        if ($signin.length) {
            if (!$signin.find('.cc-welcome').length) {
                var $titleBox = $signin.find('.box-body').first();
                if ($titleBox.length) {
                    $titleBox.after(
                        '<div class="cc-welcome">' +
                            '<h2>Welcome back!</h2>' +
                            '<p>Please enter your details</p>' +
                        '</div>'
                    );
                }
            }
            $signin.find('.line-form').each(function () {
                var $lf = $(this);
                var label = $.trim($lf.find('.scale-placeholder').text());
                var $input = $lf.find('.line-form-input');
                if ($input.length && label) {
                    $input.attr('placeholder', label);
                }
                $lf.find('.scale-placeholder').hide();
            });
            var $btn = $signin.find('.signsubmit-loader');
            if ($btn.length) {
                $btn.html('<svg class="icon mr10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"></path></svg>Log in');
            }

            // “没有账号？立即注册” 移到 “找回密码” 左侧：把两者合并为一行，注册在左、找回在右
            function moveLoginLinks() {
                if (!$signin.length || $signin.find('.cc-login-links').length) return;
                var $reg = $signin.find('a[href="#tab-sign-up"]').first();
                // 弹窗内“找回密码”的 href 通常是 /user-sign?tab=resetpassword...，不是 #tab-resetpassword
                var $forgot = $signin.find('a[href="#tab-resetpassword"]').first();
                if (!$forgot.length) {
                    $forgot = $signin.find('.pull-right.muted-2-color a').filter(function () {
                        return $(this).text().indexOf('找回密码') !== -1;
                    }).first();
                }
                if (!$forgot.length) {
                    $forgot = $signin.find('a:contains("找回密码")').first();
                }
                if ($reg.length && $forgot.length) {
                    var $links = $('<div class="cc-login-links"></div>');
                    $links.append($reg.detach());
                    var $forgotParent = $forgot.closest('.pull-right.muted-2-color');
                    $links.append($forgot.detach());
                    if ($forgotParent.length && !$forgotParent.children().length) {
                        $forgotParent.remove();
                    }
                    var $pwRow = $signin.find('.line-form').filter(function () {
                        return $(this).find('input[type="password"]').length > 0;
                    }).first();
                    if ($pwRow.length) {
                        $pwRow.after($links);
                    } else {
                        $signin.find('.signsubmit-loader').first().closest('.box-body').before($links);
                    }
                }
            }
            // 弹窗内容可能由 AJAX 动态加载，延迟执行一次确保 DOM 就绪
            moveLoginLinks();
            setTimeout(moveLoginLinks, 250);
        }
    }

    $(function () {
        initCcLogin();
    });

    $(document).on('shown.bs.modal', '#u_sign', function () {
        initCcLogin();
    });
})(jQuery);
