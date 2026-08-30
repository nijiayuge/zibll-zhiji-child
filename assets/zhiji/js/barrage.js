$.fn.barrage=function(opt){
    var _self=$(this);
    var opts={
        data:[],
        row:3,
        time:2500,
        gap:15,
        ismoseoverclose:true,
    }
    var settings = $.extend({},opts,opt);
    var M = {},Obj = {};
    Obj.data = settings.data;
    // 事件类型颜色配置（从后端 ZhijiDanmu.types 注入，兜底用默认色）
    M.typeColors = (typeof ZhijiDanmu !== 'undefined' && ZhijiDanmu.types) ? ZhijiDanmu.types : {
        pay:'#fc6976', comment:'#8ed1fc', reward:'#fcb900', sign:'#7bdcb5',
        lottery:'#f78da7', bargain:'#a855f7', groupbuy:'#06b6d4', seckill:'#ef4444',
        kuakua:'#ec4899', weiyu:'#10b981', notice:'#6366f1'
    };
    M.bgColors = ['#fc6976', '#8ed1fc', '#7bdcb5', '#fcb900', '#f78da7'];
    Obj.arrEle = [];
    M.barrageBox = $('<div id="danmu"></div>');
    M.timer = null;

    var triggerKanban = function(type){
        // 看板娘联动：弹幕出现时触发看板娘动作
        if (typeof ZhijiDanmu !== 'undefined' && ZhijiDanmu.kanban) {
            var kanban = document.querySelector('.zhiji-kanban, #zhiji-kanban, .kanban-niang');
            if (kanban) {
                kanban.classList.add('danmu-wave');
                setTimeout(function(){ kanban.classList.remove('danmu-wave'); }, 1500);
            }
        }
        // 触发自定义事件，供看板娘事件响应系统监听
        document.dispatchEvent(new CustomEvent('zhiji_danmu_show', { detail: { type: type } }));
    };

    var createView = function(){
        if (!Obj.data || Obj.data.length === 0) return;
        var item = Obj.data[0];
        var type = item.type || 'pay';
        var typeCfg = M.typeColors[type] || {};
        var bgColor = typeCfg.color || item.type_color || M.bgColors[Math.floor(Math.random() * M.bgColors.length)];
        var typeIcon = typeCfg.icon || item.type_icon || '';
        var typeLabel = typeCfg.label || item.type_label || '';

        // 用户名里含嵌套 <a>（zib_get_user_name 返回），外层已是 <a class="img">，a 嵌套 a 非法会导致浏览器修复错位，替换为 span
        var safeName = (item.name || '').replace(/<a\b[^>]*>/gi, '<span>').replace(/<\/a>/gi, '</span>');
        // 类型标签前缀
        var typeBadge = typeLabel ? '<span class="danmu-type" style="background:' + bgColor + '33;color:' + bgColor + ';">' + typeIcon + typeLabel + '</span>' : '';

        var ele = $('<li class="danmu-type-' + type + '" style="opacity:0;background-color:' + bgColor + '">'
            + '<a href="' + (item.now_user_link || '#') + '" class="img" target="_blank">'
            + (item.avatar || '') + ' ' + safeName + '</a>'
            + typeBadge
            + (item.content || '') + '</li>');

        var str = Obj.data.shift();
        ele.animate({
            'opacity' : 1,
            'margin-bottom' : settings.gap
        },1000)
        M.barrageBox.append(ele);
        Obj.data.push(str);

        triggerKanban(type);

        if(M.barrageBox.children().length > settings.row){
            M.barrageBox.children().eq(0).animate({
                'opacity' : 0,
            },300,function(){
                $(this).css({
                'margin' : 0,
            }).remove();
            })
        }
    }
    M.mouseClose = function(){
    settings.ismoseoverclose && (function(){
        M.barrageBox.mouseover(function(){
            clearInterval(M.timer);
            M.timer = null;
        }).mouseout(function(){
            M.timer = setInterval(function(){ //循环
                createView();
            },settings.time)
        })

    })()
    }
    Obj.close = function(){
        M.barrageBox.remove();
        clearInterval(M.timer);
        M.timer = null;
    }
    Obj.start = function(){
        if(M.timer) return;
        _self.append(M.barrageBox); //把弹幕盒子放到页面中
        createView(); //创建试图并开始动画
        M.timer = setInterval(function(){ //循环
            createView();
        },settings.time)
        M.mouseClose();
    }
    return Obj;
}


$.ajax({
    type: "post",
    url: '/wp-admin/admin-ajax.php',
    data: {action:'danmu'},
    success: function (msg) {
        if (!msg || msg.length === 0) return;
        var Obj = $('body').barrage({
            data: msg,//数据
            row: 3,//显示行数
            time: 2500,//时间
            gap: 15,//间隙
            ismoseoverclose: true, //悬浮是否停止
        })

        if ($('#danmu').length == 0) {
            Obj.start();
        }

    }
});
