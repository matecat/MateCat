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
            var message = new SSE.Message( JSON.parse(e.data) );

            console.log(message);

            if (message.isValid()) {
                $( document ).trigger( message.eventIdentifier, message );
            }

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

        var submitComment = function(el) {
            var id_segment = el.attr('id').split('-')[1];

            var data = {
                action     : 'comment',
                id_client  : config.id_client,
                id_job     : config.job_id,
                id_segment : id_segment,
                username   : 'John Doe', // TODO
                password   : config.password,
                role       : getRole(),
                message    : el.find('.mbc-comment-textarea').val(),
                post_time  : new Date(),
            }

            APP.doRequest({
                data: data,
                success: function() {
                    console.log('success');
                },
                failure: function() {
                    console.log('failure');
                }
            });
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

        $(document).on('click', '.mbc-comment-btn', function(e) {
            e.preventDefault();
            submitComment( $(e.target).closest('section') );
            // var id_segment = el.attr('id').split('-')[1];
        });

        $(document).on('ready', function() {
            // TODO: init links when first call to get remote comments is done
        });

        $(document).on('sse:ack', function(ev, message) {
            // TODO: set client id
            config.id_client = message.data.clientId;
        });

        $(document).on('sse:comment', function(ev, message) {
            bumpJobCallout();
        });

        $(document).on('sse:resolve', function(ev, message) {

        });
    }

})(jQuery, config, window, undefined);
