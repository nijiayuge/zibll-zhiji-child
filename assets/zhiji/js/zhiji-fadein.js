(function ($) {
            $(function () {
                var $items = $('.posts-row .ajax-item, .posts .post, .post-list .post, .forum-list .post');
                if (!$items.length) return;
                document.documentElement.classList.add('zhiji-fade-on');
                if (!('IntersectionObserver' in window)) {
                    $items.addClass('zhiji-fade zhiji-in');
                    return;
                }
                var io = new IntersectionObserver(function (entries) {
                    entries.forEach(function (e) {
                        if (e.isIntersecting) {
                            $(e.target).addClass('zhiji-fade zhiji-in');
                            io.unobserve(e.target);
                        }
                    });
                }, { threshold: 0.05 });
                $items.each(function (i, el) { io.observe(el); });
            });
        })(jQuery);
