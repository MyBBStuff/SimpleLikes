(function (e, t, n) {
    this.MybbStuff = this.MybbStuff || {};
    this.MybbStuff.LikeSystem = function (t) {
        var n;
        var r = function (t, r) {
            this.selector = t;
            this.postKey = r;
            this.VERSION = "1.3.1";
            n = this
        };
        r.prototype = {
            constructor: r, init: function () {
                t("body").on("click", this.selector, this.togglePostLike);
                return this
            }, togglePostLike: function (r) {
                r.preventDefault();
                var i = t(this), s = i.attr("id").substr(16);
                t.post("xmlhttp.php?action=like_post", {
                    my_post_key: n.postKey,
                    post_id: s
                }, n.togglePostLikeSuccess, "json");
                return false
            }, togglePostLikeSuccess: function (n) {
                if (n.errors) {
                    t.each(n.errors, function (e, t) {
                        if (t) {
                            console.log(t);
                            alert(t)
                        }
                    });
                    alert(n.error)
                } else {
                    var r = t("#post_likes_" + n.postId), i = t("#likeButton_post_" + n.postId + " .postbit_like__text");
                    if (r.length !== 0 && n.likeString.length !== 0) {
                        r.html(n.likeString)
                    } else if (r.length !== 0) {
                        r.fadeOut("slow", function () {
                            t(this).remove()
                        })
                    } else {
                        t("#pid_" + n.postId).after(n.templateString)
                    }
                    if (i.length !== 0) {
                        i.text(n.buttonString);
                        i.attr("title", n.buttonString)
                    }
                }
            }
        };
        return r
    }(e, window);
    e(t).ready(function () {
        var e = new MybbStuff.LikeSystem("a[id^='likeButton_post_']", n);
        e.init()
    })
})(jQuery, document, my_post_key)
