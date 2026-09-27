<?php
/**
 * @module  CopyToast
 * @desc    统一复制提醒服务：任意可复制元素「点一下即复制 + 明确提示」。
 *
 *          背景（2026-09-27）：站内复制逻辑此前散落在 5 处各自实现
 *          （挽留弹窗优惠码 / 评论福袋券码 / 我的优惠码表格 / 消息中心高亮 / 邀请链接），
 *          反馈方式各异（有的改背景色、有的改按钮文字、有的毫无提示），且都是直接绑事件 ——
 *          AJAX 后插入的内容会漏绑。本模块把这套能力收口为一处。
 *
 *          HTML 契约（新代码统一用这个写法）：
 *            · data-zhiji-copy="要复制的文本"
 *            · data-zhiji-copy-sel="#选择器"      从目标元素取文本（适用于"点按钮复制旁边那段"）
 *            · data-zhiji-copy-msg="自定义成功提示"  可选
 *          兼容接管（渐进迁移，无需改老代码）：
 *            · .zhiji-copy-code[data-code] · .zhiji-cp[data-code] · .zhiji-cf-code
 *
 * @api     zhiji_copy_attrs($text, $args)       生成标准属性串（直接放进标签）
 *          zhiji_copy_attrs_by_selector($sel, …) 从选择器取文本的写法
 *          window.zhijiCopy(text, opts)          JS 侧直接调用（opts.msg 可定制提示）
 * @hook    wp_enqueue_scripts(6) · 登记内联资源（head 输出，自包含，按 id 去重）
 * @since   2.0.0
 *
 * @standard  复制成功提示 microcopy 遵循行业通用规范（VibeHub Toast / Noloco Copy /
 *            W3Tweaks / Stellae 2026 综述一致）：
 *            · 成功：简短确认「已复制到剪贴板」，2 秒自动消失，不弹窗、不长期占位；
 *            · 可带上「复制了什么」：业务侧用 data-zhiji-copy-msg 传入（如「优惠码已复制，结算时粘贴即可抵扣」）；
 *            · 失败：明确原因 + 手动重试路径；成功/失败用 ✓/✕ 图标 + 文字区分，绝不以颜色单独表意；
 *            · 无障碍：toast 带 role="status" + aria-live="polite"，屏幕阅读器可播报。
 */

defined('ABSPATH') || exit;

/**
 * 生成复制元素的属性串（供业务模块拼 HTML 用）
 *
 * @param string $text 要复制的文本
 * @param array  $args msg(string) 自定义成功提示 / class(string) 附加类名（不参与属性）
 * @return string 形如 `data-zhiji-copy="abc" data-zhiji-copy-msg="..."`（已转义）
 */
function zhiji_copy_attrs($text, $args = array())
{
    $args = wp_parse_args($args, array('msg' => ''));
    $out  = 'data-zhiji-copy="' . esc_attr((string) $text) . '"';
    if ('' !== (string) $args['msg']) {
        $out .= ' data-zhiji-copy-msg="' . esc_attr((string) $args['msg']) . '"';
    }
    return $out;
}

/**
 * 生成「从选择器取文本」的属性串
 *
 * @param string $selector CSS 选择器
 * @param array  $args     msg(string)
 * @return string
 */
function zhiji_copy_attrs_by_selector($selector, $args = array())
{
    $args = wp_parse_args($args, array('msg' => ''));
    $out  = 'data-zhiji-copy-sel="' . esc_attr((string) $selector) . '"';
    if ('' !== (string) $args['msg']) {
        $out .= ' data-zhiji-copy-msg="' . esc_attr((string) $args['msg']) . '"';
    }
    return $out;
}

/**
 * 登记复制提醒资源（head 内联，自包含，不依赖外部文件）
 *
 * @return void
 */
function zhiji_copy_assets()
{
    if (is_admin()) {
        return;
    }

    // 文案遵循行业通用 microcopy 规范（见本文件 @standard）：
    //  · 成功：简短确认「已复制到剪贴板」，2 秒自动消失，不弹窗、不长期占位
    //  · 可带「复制了什么」：业务侧通过 data-zhiji-copy-msg 传入（如「优惠码已复制，结算时粘贴即可抵扣」）
    //  · 失败：明确原因 + 手动重试路径；绝不以颜色单独表意（已配 ✓/✕ 图标）
    $cfg = array(
        'ok'      => __('已复制到剪贴板', 'zhiji'),
        'fail'    => __('复制失败，请长按文本后手动复制', 'zhiji'),
        'manual'  => __('已帮你选中，长按即可复制', 'zhiji'),
        'selectors' => array('.zhiji-copy-code[data-code]', '.zhiji-cp[data-code]', '.zhiji-cf-code'),
    );

    $css = '.zhiji-copy-toast{position:fixed;left:50%;bottom:40px;transform:translate(-50%,24px) scale(.92);z-index:999999;'
        . 'display:flex;align-items:center;gap:10px;max-width:86vw;padding:13px 24px;border-radius:999px;'
        . 'background:rgba(24,28,32,.97);color:#fff;font-size:15px;font-weight:500;line-height:1.5;'
        . 'box-shadow:0 12px 40px rgba(0,0,0,.35);pointer-events:auto;opacity:0;'
        . 'transition:opacity .22s cubic-bezier(.2,.8,.3,1),transform .22s cubic-bezier(.2,.8,.3,1)}'
        . '.zhiji-copy-toast.is-show{opacity:1;transform:translate(-50%,0) scale(1)}'
        . '.zhiji-copy-toast .zhiji-copy-toast__icon{display:inline-flex;align-items:center;justify-content:center;'
        . 'width:22px;height:22px;border-radius:50%;font-size:13px;font-weight:700;background:#1a7f37;flex:0 0 auto;box-shadow:0 0 0 4px rgba(26,127,55,.18)}'
        . '.zhiji-copy-toast.is-error .zhiji-copy-toast__icon{background:#d63638;box-shadow:0 0 0 4px rgba(214,54,56,.18)}'
        . 'body.dark-theme .zhiji-copy-toast{background:rgba(244,246,248,.98);color:#1d2327}'
        . '@media (prefers-reduced-motion:reduce){.zhiji-copy-toast{transition:opacity .15s linear;transform:translate(-50%,0)}}';

    $js = '(function(){'
        . 'var CFG=' . wp_json_encode($cfg, JSON_UNESCAPED_UNICODE) . ';'
        . 'if(window.zhijiCopy)return;'
        . 'var box=null,timer=null,remain=0,startAt=0;'
        . 'function hide(){if(box)box.classList.remove("is-show");}'
        . 'function toast(msg,isErr){'
        . 'if(!box){box=document.createElement("div");box.className="zhiji-copy-toast";'
        . 'box.setAttribute("role","status");box.setAttribute("aria-live","polite");'
        . 'box.innerHTML=\'<span class="zhiji-copy-toast__icon"></span><span class="zhiji-copy-toast__text"></span>\';'
        . 'document.body.appendChild(box);'
        // hover 暂停倒计时（行业惯例：hover 期间不清除，移开后按剩余时间恢复）
        . 'box.addEventListener("mouseenter",function(){if(timer){clearTimeout(timer);timer=null;remain=Math.max(remain-(Date.now()-startAt),800);}});'
        . 'box.addEventListener("mouseleave",function(){if(!box.classList.contains("is-show"))return;startAt=Date.now();if(timer)clearTimeout(timer);timer=setTimeout(hide,remain);});}'
        . 'box.className="zhiji-copy-toast"+(isErr?" is-error":"");'
        . 'box.querySelector(".zhiji-copy-toast__icon").textContent=isErr?"\\u2715":"\\u2713";'
        . 'box.querySelector(".zhiji-copy-toast__text").textContent=msg||(isErr?CFG.fail:CFG.ok);'
        . 'void box.offsetWidth;box.classList.add("is-show");'
        // 行业时长基线：成功 3s（Helix Snackbar 2.5-3s / ORON 成功 3s），失败 6s（5-8s，要留够读原因的时间）
        . 'remain=isErr?6000:3000;startAt=Date.now();'
        . 'if(timer)clearTimeout(timer);'
        . 'timer=setTimeout(hide,remain);}'
        . 'function legacyCopy(text){'
        . 'var ta=document.createElement("textarea");ta.value=text;'
        . 'ta.setAttribute("readonly","");ta.style.cssText="position:fixed;top:-1000px;opacity:0";'
        . 'document.body.appendChild(ta);ta.select();ta.setSelectionRange(0,ta.value.length);'
        . 'var ok=false;try{ok=document.execCommand("copy");}catch(e){ok=false;}'
        . 'document.body.removeChild(ta);return ok;}'
        . 'function selectNode(el){if(!el)return;try{var r=document.createRange();r.selectNodeContents(el);'
        . 'var s=window.getSelection();s.removeAllRanges();s.addRange(r);}catch(e){}}'
        . 'function doCopy(text,el,msg){'
        . 'text=(text||"").trim();if(!text){toast(CFG.fail,true);return;}'
        . 'function ok(){toast(msg||CFG.ok,false);try{document.dispatchEvent(new CustomEvent("zhiji:copied",{detail:{text:text}}));}catch(e){}}'
        . 'function fail(){var done=legacyCopy(text);'
        . 'if(done){ok();return;}'
        . 'selectNode(el);toast(CFG.manual,true);}'
        . 'if(navigator.clipboard&&navigator.clipboard.writeText){'
        . 'navigator.clipboard.writeText(text).then(ok,function(){fail();});'
        . '}else{fail();}}'
        . 'window.zhijiCopy=function(text,opts){opts=opts||{};doCopy(text,null,opts.msg);};'
        . 'function resolve(el){'
        . 'var txt=el.getAttribute("data-zhiji-copy");'
        . 'if(txt===null){var sel=el.getAttribute("data-zhiji-copy-sel");'
        . 'if(sel){var t=document.querySelector(sel);txt=t?(t.value!==undefined&&t.value!==""?t.value:t.textContent):"";}}'
        . 'if(txt===null||txt===""){txt=el.getAttribute("data-code");}'
        . 'if(txt===null||txt===""){txt=el.textContent;}'
        . 'return {text:txt,msg:el.getAttribute("data-zhiji-copy-msg")};}'
        . 'var SEL="[data-zhiji-copy],[data-zhiji-copy-sel],[data-zhiji-copy-text],"+CFG.selectors.join(",");'
        . 'document.addEventListener("click",function(e){'
        . 'var el=e.target&&e.target.closest?e.target.closest(SEL):null;if(!el)return;'
        . 'e.preventDefault();var r=resolve(el);doCopy(r.text,el,r.msg);},false);'
        . '})();';

    zhiji_asset_add_css('copy-toast', $css);
    zhiji_asset_add_js('copy-toast', $js);
}
add_action('wp_enqueue_scripts', 'zhiji_copy_assets', 6);
