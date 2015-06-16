/*
 Component: ui.comment
 */

(function($,config,window,undefined) {
    if ( config.commentEnabled && !!window.EventSource ) {
        SSE.init();

        var tpls = {
            divider : '' +
                '<div class="divider"></div>',

            showComment : '' +
                '<div class="mbc-show-comment mbc-show-first-comment">' +
                ' <span class="mbc-comment-label mbc-comment-username-label"></span>' +
                ' <div class="mbc-comment-info-wrap mbc-clearfix">' +
                ' <span class="mbc-comment-info mbc-comment-time"></span>' +
                ' <span class="mbc-comment-info mbc-comment-role"></span>' +
                ' </div>' +
                ' <p class="mbc-comment-body"></p>' +
                ' </div>' ,

            postComment : '' +
                '<div class="mbc-post-comment">' +
                ' <span class="mbc-comment-label mbc-comment-username-label">Anonymous</span>' +
                ' <textarea class="mbc-comment-textarea"></textarea>' +
                ' <div><a href="#" class="mbc-comment-btn">Send</a></div>' +
                ' </div>',

            commentHeader : '' +
                '<div class="mbc-first-comment-header">' +
                '<span class="mbc-comment-label mbc-first-comment-label">Insert a comment</span>' +
                '<div class="divider"></div>' +
                '</div>',

            segmentThread : '' +
                ' <div class="mbc-comment-balloon-outer mbc-thread-active mbc-thread-active-first-comment">' +
                ' <div class="triangle triangle-topleft"></div>' +
                '<a href="#" class="mbc-close-btn">&#10005;</a>' +
                ' <div class="mbc-thread-wrap mbc-thread-wrap-active mbc-thread-number">' +
                ' ' +
                ' ' +
                ' </div>' +
                ' </div>',

            commentLink : '' +
                '<div class="mbc-comment-link">' +
                ' <div class="txt">' +
                ' <span class="mbc-comment-total"></span>' +
                ' <span class="mbc-comment-icon icon-bubbles4"></span>' +
                ' <span class="mbc-comment-highlight mbc-comment-total-none"></span>' +
                '</div>' +
                '</div>',
        }

        var db = {
            segments: [ ],
            };

        var source = SSE.getSource('comments');

        var getRole = function() {
            return 'translator';
        }

        // SSE event listeners
        source.addEventListener('message', function(e) {
            console.log('message', JSON.parse(e.data));
        }, false);

        source.addEventListener('open', function(e) {
        }, false);

        source.addEventListener('error', function(e) {
            if (e.readyState == EventSource.CLOSED) {
                console.log('connection closed');
            }
        }, false);


        // behaviour and rendering functions
        var openSegmentComment = function(el) {
            var id_segment = el.attr('id').split('-')[1];
            // open comments
            $('article').addClass('mbc-commenting-opened');

            $('.mbc-comment-balloon-outer').remove();

            el.find('.editarea').click();

            // TODO: read into database and populate this template
            //       according to current data
            //
            var root = $(tpls.segmentThread);
            var header = $(tpls.commentHeader);
            var postComment = $(tpls.postComment);

            root.find('.mbc-thread-wrap')
                .append(header)
                .append(postComment);

            el.append( root.show() );


        }

        var closeSegment = function(el) {
            $('.mbc-comment-balloon-outer').remove();
            $('article').removeClass('mbc-commenting-opened');
        }

        // DOM bindings
        $(document).on('click', '.mbc-comment-link', function(e) {
            e.preventDefault();
            openSegmentComment( $(e.target).closest('section') );
        });

        $(document).on('click', '.mbc-close-btn', function(e) {
            e.preventDefault();
            closeSegment( $(e.target).closest('section') );
        });

        $(document).on('ready', function() {
            // TODO: init links when first call to get remote comments is done
        });
        // TODO: on page load get comments struct
        // TODO: on new segments loaded get new struct for new segments
        // TODO: click on icon to post a comment
        //

        // temp global functions
        // AJAX functions
        window.mbc_postComment = function() {
            var data = {
                action : 'comment',
                id_client: 123,
                id_job: config.job_id,
                id_segment: 12345,
                username: 'John Doe',
                password: config.password,
                role: getRole(),
                message: 'this is my message'
            }

            APP.doRequest({
                data: data,
                success: function() {
                    console.log('success');
                },
                failure: function() {

                }
            });
        }

    }

    // Dev front-end
    $(document).ready(function(){

        return false;

        // Activate Commento visibile state
        $('.dev-commento-visibile').on('click', function(){
            $('article').toggleClass('mbc-commenting-opened');
            event.preventDefault();
        });
        // Show active thread Show add first comment balloon
        $('.dev-first-comment').on('click', function(){
            $('article').addClass('mbc-commenting-opened').removeClass('mbc-commenting-closed');
            $('.mbc-thread-active-first-comment').toggleClass('visible');
            $('.mbc-thread-active-reply-comment, .mbc-thread-justresolved, .mbc-thread-resolved-ask').removeClass('visible');
            event.preventDefault();
        });
        // Show active thread Show reply to comment balloon
        $('.dev-reply-comment').on('click', function(){
            $('article').addClass('mbc-commenting-opened').removeClass('mbc-commenting-closed');
            $('.mbc-thread-active-reply-comment').toggleClass('visible');
            $('.mbc-thread-active-first-comment, .mbc-thread-justresolved, .mbc-thread-resolved-ask').removeClass('visible');
            event.preventDefault();
            $('.mbc-show-form-btn').on('click', function(){
                $(this).addClass('hide').closest('div').next('.mbc-post-comment').removeClass('hide');
                event.preventDefault();
            });
        });

        // Show thread just resolved
        $('.dev-thread-justresolved').on('click', function(){
            $('article').addClass('mbc-commenting-opened').removeClass('mbc-commenting-closed');
            $('.mbc-thread-justresolved').toggleClass('visible');
            $('.mbc-thread-active-first-comment, .mbc-thread-active-reply-comment').removeClass('visible');
            $('.mbc-thread-resolved-ask').removeClass('visible');
            event.preventDefault();
            $('.mbc-show-form-btn').on('click', function(){
                $(this).addClass('hide').next('.mbc-ask-comment-wrap').removeClass('hide');
                event.preventDefault();
            });
        });
        // Show thread resolved Ask new question
        $('.dev-thread-resolved-ask').on('click', function(){
            $('article').addClass('mbc-commenting-opened').removeClass('mbc-commenting-closed');
            $('.mbc-thread-resolved-ask').toggleClass('visible');
            $('.mbc-thread-ask').removeClass('hide')
            $('.mbc-thread-active-first-comment, .mbc-thread-active-reply-comment').removeClass('visible');
            $('.mbc-thread-justresolved').removeClass('visible');
            event.preventDefault();
            $('.show-thread-btn').on('click', function(){
                $('.thread-collapsed').stop().slideToggle();
                var showlabel = $(this).find('.show-thread-label');
                var showIconlabel = $(this).find('.show-toggle-icon');
                $(showlabel).text( $(showlabel).text() == 'Show more' ? "Show less" : "Show more");
                $(showIconlabel).text( $(showIconlabel).text() == '+' ? "−" : "+");
                event.preventDefault();
            });
            $('.mbc-show-form-btn').on('click', function(){
                $(this).addClass('hide').next('.mbc-ask-comment-wrap').removeClass('hide');
                event.preventDefault();
            });
        });
        $('#mbc-history').on('click', function(){
            $('.mbc-history-balloon-outer').toggleClass('visible');
        });
        // Perfect scroll
        $(document).ready(function(){
            // Perfect scroll
            // $('.mbc-history-balloon').perfectScrollbar();
        });
    });
})(jQuery, config, window, undefined);
