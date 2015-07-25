;
(function ($, document, my_post_key) {
    this.MybbStuff = this.MybbStuff || {};

    this.MybbStuff.LikeSystem = (function LikeSystemModule($) {
        var self;

        var module = function LikeSystem(likeButtonSelector, postKey) {
            this.selector = likeButtonSelector;
            this.postKey = postKey;

            this.VERSION = "2.0.0";

            self = this;
        };

        module.prototype = {
            constructor: module,
            init: function init() {
                $("body").on("click", this.selector, this.togglePostLike);

                return this;
            },
            togglePostLike: function togglePostLike(event) {
                event.preventDefault();

                var likeButton = $(this),
                    postId = likeButton.attr("id").substr(16);

                $.post(
                    "xmlhttp.php?action=like_post",
                    {
                        my_post_key: self.postKey,
                        post_id: postId
                    },
                    self.togglePostLikeSuccess,
                    "json"
                );

                return false;
            },
            togglePostLikeSuccess: function togglePostLikeSuccess(data) {
                if (data.errors) {
                    $.each(data.errors, function (index, error) {
                        if (error) {
                            console.log(error);
                            alert(error);
                        }
                    });
                    alert(data.error);
                } else {
                    var likeBar = $("#post_likes_" + data.postId),
                        likeButton = $("#likeButton_post_" + data.postId + " .postbit_like__text");

                    if (likeBar.length !== 0 && data.likeString.length !== 0) {
                        likeBar.html(data.likeString);
                    } else if (likeBar.length !== 0) {
                        likeBar.fadeOut("slow", function () {
                            $(this).remove();
                        });
                    } else {
                        $("#pid_" + data.postId).after(data.templateString);
                    }

                    if (likeButton.length !== 0) {
                        likeButton.text(data.buttonString);
                        likeButton.attr("title", data.buttonString);
                    }
                }
            }
        };

        return module;
    })($, window);

    $(document).ready(function () {
        var simpleLikes = new MybbStuff.LikeSystem("a[id^='likeButton_post_']", my_post_key);
        simpleLikes.init();
    });
})(jQuery, document, my_post_key);
