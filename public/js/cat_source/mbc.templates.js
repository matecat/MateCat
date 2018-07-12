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
            '  <li id="mbc-history" title="View comments"> ' +
            '      <span class="icon-bubble2"></span> ' +
            '  </li>',

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
            ' <div class="mbc-triangle mbc-triangle-topleft"></div>' +
            ' <div class="mbc-comments-wrap">' +
            ' ' +
            ' </div>' +
            ' </div>' +
            ' </div>',

        commentLink : '' +
            '<div class="mbc-comment-icon-button txt">' +
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
