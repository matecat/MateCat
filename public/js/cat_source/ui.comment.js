/*
 Component: ui.comment
 */
if ( config.commentEnabled && !!window.EventSource ) {

    SSE.init();
    var source = SSE.getSource('comments');

    var getRole = function() {
        return 'revisor';
    }

    source.addEventListener('message', function(e) {
        console.log('message', JSON.parse(e.data));
    }, false);

    source.addEventListener('open', function(e) {
        // Connection was opened.
        console.log('connection open');
    }, false);

    source.addEventListener('error', function(e) {
        if (e.readyState == EventSource.CLOSED) {
            // Connection was closed.
            console.log('connection closed');
        }
    }, false);

    // temp global
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
        $('.mbc-history-balloon').perfectScrollbar();
    });
});