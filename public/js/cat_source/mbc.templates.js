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

        reopenThread : ''+
            ' <div class="mbc-thread-wrap mbc-thread-wrap-active mbc-clearfix">' +
            ' <a href="#" class="mbc-comment-btn mbc-ask-btn mbc-show-form-btn">Ask new question</a>' +
            ' <div class="mbc-ask-comment-wrap hide">' +
            '' + // insertCommentHeader
            '' + // insert inputForm here
            ' </div>' +
            ' </div>',

        resolveButton : ''+
            ' <a href="#" class="mbc-comment-label mbc-comment-btn mbc-comment-resolve-btn pull-right">Resolve</a>',

        historyIcon : '' +
            '  <li id="mbc-history" title="View comments"> ' +
            '      <span class="icon-bubble2"></span> ' +
            '      <span class="mbc-comment-notification mbc-comment-highlight mbc-comment-highlight-history hide"></span> ' +
            '  </li>',

        historyOuter : '' +
            ' <div class="mbc-history-balloon-outer hide"> ' +
            '     <div class="mbc-triangle mbc-triangle-top"></div> ' +
            ' </div> ',

        historyViewButton: '' +
            ' <div class="mbc-clearfix mbc-view-comment-wrap"> ' +
            '   <span class="mbc-comment-label mbc-comment-segment-number"></span> ' +
            '   <a href="javascript:;" class="mbc-comment-btn mbc-comment-default-btn mbc-show-comment-btn pull-right">View</a>' +
            ' </div> ',

        historyHasComments: '' +
            ' <div class="mbc-history-balloon mbc-history-balloon-has-comment"> ' +
            ' ' + // showComment loop here
            ' </div> ',

        historyNoComments : '' +
            ' <div class="mbc-comment-balloon-header"> ' +
            '   <a href="#" class="mbc-close-btn mbc-close-icon mbc-close-comment-icon"></a> ' +
            ' </div> ' +
            ' <div class="mbc-history-balloon mbc-history-balloon-has-no-comments" style="display: block;">' +
            '    <div class="mbc-thread-wrap"> ' +
            '        <span class="mbc-comment-label">No comments</span>'  +
            '    </div> ' +
            ' </div>',

        activeCommentsNumberInHistory : '' +
            '<span class="mbc-comment-highlight mbc-comment-highlight-balloon-history mbc-comment-notification"></span>',

        divider : '' +
            '<div class="divider"></div>',

        showResolve : '' +
            '<div class="mbc-resolved-comment">' +
            ' <span class="mbc-comment-label mbc-comment-username-label mbc-comment-resolvedby mbc-truncate"></span>' +
            ' <span class="mbc-comment-resolved-label">resolved</span>' +
            ' </div>' ,

        threadWrap : '' +
            ' <div class="mbc-thread-wrap mbc-clearfix">'  +
            '' + // comments go here
            ' </div>',

        showComment : '' +
            '<div class="mbc-show-comment mbc-clearfix">' +
            ' <span class="mbc-comment-label mbc-comment-username-label"></span>' +
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
            ' <span class="mbc-comment-label mbc-comment-username-label mbc-comment-anonymous-label"></span>' +
            ' <a href="javascript:" class="mbc-login-link">Login to receive notification</a>' +
            ' <textarea class="mbc-comment-input mbc-comment-textarea" placeholder="TODO: Write a comment..."></textarea>' +
            ' <div>' +
            ' <a href="#" class="mbc-comment-btn mbc-comment-send-btn pull-right hide">Comment</a>' +
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
            ' <div class="mbc-triangle mbc-triangle-topleft"></div>' +
            ' <div class="mbc-comment-balloon-header"> ' +
            '   <a href="#" class="mbc-close-btn mbc-close-icon mbc-close-comment-icon"></a>' +
            ' </div>' +
            ' <div class="mbc-comments-wrap">' +
            ' ' +
            ' </div>' +
            ' </div>',

        commentLink : '' +
            '<div class="mbc-comment-link">' +
            '   <div class="txt">' +
            '       <span class="mbc-comment-icon icon-bubble2"></span>' +
            '   </div>' +
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
