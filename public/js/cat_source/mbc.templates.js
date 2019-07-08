if ( MBC.enabled() )
(function($,MBC) {

    var tpls = { // TODO: make this local

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

        // showResolve : '' +
        //     '<div class="mbc-resolved-comment">' +
        //     ' <span class="mbc-comment-resolved-label">' +
        //     '   <span class="mbc-comment-username mbc-comment-resolvedby"></span>' +
        //     '   <span class="">marked as resolved</span>' +
        //     ' </span>' +
        //     '</div>' ,

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

        firstCommentWrap : '' +
            ' <div class="mbc-thread-wrap mbc-thread-wrap-active mbc-clearfix">' +
            '' + // insertCommentHeader
            '</div>',
    };

    $.extend(MBC.const, {
        get tpls() {
            return tpls ;
        }
    });

})(jQuery,MBC);
