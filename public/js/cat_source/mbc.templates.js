if ( MBC.enabled() )
(function($,MBC) {

    var tpls = { // TODO: make this local
        threadCollapsedControl : '' +
            ' <a href="javascript:" class="show-thread-btn">' +
            ' <span class="show-thread-label">Show more</span> ' +
            ' <span class="show-thread-number">(1)</span> ' +
            ' <span class="show-toggle-icon">+</span>' +
            ' </a>' +
            ' <div class="divider"></div>',

        threadCollapsedWrapper : '' +
            '<div class="thread-collapsed hide"></div>',

        insertCommentHeader : '' +
            '<div class="mbc-first-comment-header">' +
            ' <span class="mbc-comment-label mbc-first-comment-label">Insert a comment</span>' +
            ' <div class="divider"></div>' +
            ' </div>',

        resolveButton : ''+
            ' <a href="#" class="mbc-comment-label mbc-comment-btn mbc-comment-resolve-btn pull-right">Resolve</a>',

        historyIcon : '' +
            '  <div id="mbc-history" title="View comments"> ' +
            /*'      <span class="icon-bubble2"></span> ' +*/
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="3 3 36 36">' +
                ' <path fill="#000" fill-rule="evenodd" stroke="none" stroke-width="1" d="M33.125 13.977c-1.25-1.537-2.948-2.75-5.093-3.641C25.886 9.446 23.542 9 21 9c-2.541 0-4.885.445-7.031 1.336-2.146.89-3.844 2.104-5.094 3.64C7.625 15.514 7 17.188 7 19c0 1.562.471 3.026 1.414 4.39.943 1.366 2.232 2.512 3.867 3.439-.114.416-.25.812-.406 1.187-.156.375-.297.683-.422.922-.125.24-.294.505-.508.797a8.15 8.15 0 01-.484.617 249.06 249.06 0 00-1.023 1.133 1.1 1.1 0 00-.126.141l-.109.132-.094.141c-.052.078-.075.127-.07.148a.415.415 0 01-.031.156c-.026.084-.024.146.007.188v.016c.042.177.125.32.25.43a.626.626 0 00.422.163h.079a11.782 11.782 0 001.78-.344c2.73-.697 5.126-1.958 7.189-3.781.78.083 1.536.125 2.265.125 2.542 0 4.886-.445 7.032-1.336 2.145-.891 3.843-2.104 5.093-3.64C34.375 22.486 35 20.811 35 19c0-1.812-.624-3.487-1.875-5.023z"/> ' +
                '</svg>' +
            '  </div>',

        historyOuter : '' +
            ' <div class="mbc-history-balloon-outer hide"> ' +
            '     <div class="mbc-triangle mbc-triangle-top"></div> ' +
            ' </div> ',

        historyViewButton: '' +
            ' <div class="mbc-clearfix mbc-view-comment-wrap"> ' +
            '   <a href="javascript:;" class="mbc-comment-link-btn mbc-view-link mbc-show-comment-btn">View thread</a>' +
            ' </div> ',

        historySegmentLabel: '' +
            ' <span class="mbc-nth-comment mbc-nth-comment-label">Segment <span class="mbc-comment-segment-number"></span></span> ',

        historyHasComments: '' +
            ' <div class="mbc-history-balloon mbc-history-balloon-has-comment"> ' +
            ' ' + // showComment loop here
            ' </div> ',

        historyNoComments : '' +
            ' <div class="mbc-history-balloon mbc-history-balloon-has-no-comments" style="display: block;">' +
            '    <div class="mbc-thread-wrap"> ' +
            '       <div class="mbc-show-comment"> ' +
            '           <span class="mbc-comment-label">No comments</span>'  +
            '       </div> ' +
            '    </div> ' +
            ' </div>',

        activeCommentsNumberInHistory : '' +
            '<span class="mbc-comment-highlight mbc-comment-highlight-balloon-history mbc-comment-notification"></span>',

        divider : '' +
            '<div class="divider"></div>',

        showResolve : '' +
            '<div class="mbc-resolved-comment">' +
            ' <span class="mbc-comment-resolved-label">' +
            '   <span class="mbc-comment-username mbc-comment-resolvedby"></span>' +
            '   <span class="">marked as resolved</span>' +
            ' </span>' +
            '</div>' ,

        threadWrap : '' +
            ' <div class="mbc-thread-wrap mbc-clearfix">'  +
            '' + // comments go here
            ' </div>',

        showComment : '' +
            '<div class="mbc-show-comment mbc-clearfix">' +
            ' <span class="mbc-comment-label mbc-comment-username mbc-comment-username-label mbc-truncate"></span>' +
            ' <span class="mbc-comment-label mbc-comment-email-label mbc-truncate"></span>' +
            ' <div class="mbc-comment-info-wrap mbc-clearfix">' +
            '   <span class="mbc-comment-info mbc-comment-time pull-left"></span>' +
            ' </div>' +
            ' <p class="mbc-comment-body"></p>' +
            ' </div>' ,

        inputForm : '' +
            ' <div class="mbc-thread-wrap mbc-post-comment-wrap mbc-clearfix">' +
            /*'<div class="mbc-triangle mbc-open-view mbc-re-messages"></div>' +*/
            ' <div class="mbc-new-message-notification">' +
            '    <span class="mbc-new-message-icon mbc-new-message-arrowdown">&#8595;</span>' +
            '    <a href="javascript:" class="mbc-new-message-link"></a> ' +
            ' </div>' +
            ' <div class="mbc-post-comment">' +
            ' <span class="mbc-comment-label mbc-comment-username mbc-comment-username-label mbc-truncate mbc-comment-anonymous-label"></span>' +
            ' <a href="javascript:" class="mbc-comment-link-btn mbc-login-link">Login to receive comments</a>' +
            ' <div class="mbc-comment-input mbc-comment-textarea" contenteditable="true" data-placeholder="Write a comment..."></div>' +
            // ' <div>' +
            // ' <a href="javascript:;" class="mbc-tag-link">Tag someone</a>' +
            // ' </div>' +
            // ' <div>' +
            // ' <textarea class="mbc-comment-input mbc-comment-textarea-tagging hide" id="tagging-area" placeholder="Tag someone"></textarea>' +
            // ' </div>' +
            ' <div>' +
            ' <a href="javascript:;" class="ui primary tiny button mbc-comment-btn mbc-comment-send-btn hide">Comment</a>' +
            ' </div>' +
            ' <div class="mbc-ajax-message-wrap hide">' +
            ' <span class="mbc-warnings">Oops, something went wrong. Please try again later.</span>' +
            ' </div>' +
            ' <div>' +
            ' </div>' +
            ' </div>' +
            ' </div>',

        firstCommentWrap : '' +
            ' <div class="mbc-thread-wrap mbc-thread-wrap-active mbc-clearfix">' +
            '' + // insertCommentHeader
            '</div>',

        segmentThread : '' +
            ' <div class="mbc-comment-balloon-outer">' +
            ' <div class="mbc-comment-balloon-inner">' +
            ' <div class="mbc-triangle mbc-open-view mbc-re-messages"></div>' +
            ' <a class="re-close-balloon shadow-1"><i class="icon-cancel3 icon" /></a>' +
            ' <div class="mbc-comments-wrap">' +
            ' ' +
            ' </div>' +
            ' </div>' +
            ' </div>',

        commentLink : '' +
            '<div class="mbc-comment-icon-button txt" title="Add comment">' +
            '   <span class="mbc-comment-icon icon-bubble2"></span>' +
            '</div>',

        commentIconHighlightNumber : '' +
            '<span class="mbc-comment-notification mbc-comment-highlight mbc-comment-highlight-segment"></span>',

        commentIconHighlightInvite : '' +
            '<span class="mbc-comment-notification mbc-comment-highlight-segment mbc-comment-highlight-invite">+</span>'

    };

    $.extend(MBC.const, {
        get tpls() {
            return tpls ;
        }
    });

})(jQuery,MBC);
