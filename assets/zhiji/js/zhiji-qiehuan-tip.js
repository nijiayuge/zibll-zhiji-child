$(".toggle-theme").click(function() {var toggleThemeText = "当前为日间模式";if (!$("body").hasClass('dark-theme')) {toggleThemeText ="当前为夜间模式";}layer.msg(toggleThemeText, {time: 2000,anim: 1});});
