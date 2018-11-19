(function($,LXQ) {

    var tpls = { // TODO: make this local

        historyOuter : '' +
            ' <div class="lxq-history-balloon-outer hide"> ' +
            '     <div class="lxq-triangle lxq-triangle-top"></div> ' +
            ' </div> ',

        // historyHasErrors: '' +
        //     ' <div class="lxq-history-balloon lxq-history-balloon-has-comment"> ' +
        //     '   <div class="lxq-thread-wrap"> ' +
        //     '       <div class="lxq-history-balloon-header lxq-clearfix"> ' +
        //     '           <span class="lxq-history-balloon-header-top"><a href="'+config.lexiqaServer+'/documentation.html" class="lxq-info-link" target="_blank">Info</a> <span class="lxq-highlight-toggle">Highlight <div class="lxq-toggle-switch"><input id="cmn-toggle-1" class="cmn-toggle cmn-toggle-round" type="checkbox" checked><label for="cmn-toggle-1"></label></div></span></span>' +
        //     '           <span class="lxq-history-balloon-header-text">See the full lexiQA report <a href="#" class="lxq-history-balloon-header-link" target="_blank"></a> </span>' +
        //     '       </div> ' +
        //     '       <div class="lxq-history-balloon-body lxq-clearfix"> ' +
        //     '            <div class="lxq-history-balloon-row lxq-clearfix"> ' +
        //     '                <span class="lxq-history-balloon-header-segment">Segment</span> ' +
        //     '                <span class="lxq-history-balloon-header-total">Errors</span>' +
        //     '                <span class="lxq-history-balloon-header-ignored">Ignored</span>' +
        //     '            </div> ' +
        //     '' + //
        //     '       </div> ' +
        //     '   </div> ' +
        //     ' ' +
        //     ' </div> ',

        //   <img src="http://s29.postimg.org/zdbe56c9v/segment_arrow.png" alt="Go to segment" />
        segmentWarningsRow: '' +
            '            <div class="lxq-history-balloon-row lxq-clearfix"> ' +
            '                <span class="lxq-history-balloon-segment"><a href="#" class="lxq-history-balloon-segment-link"><span class="lxq-history-balloon-segment-number">231</span></a></span> ' +
            '                <span class="lxq-history-balloon-total">3</span>' +
            '                <span class="lxq-history-balloon-ignored">2</span>' +
            '           </div> ',

        historyNoComments : '' +
            ' <div class="lxq-history-balloon lxq-history-balloon-has-no-comments" style="display: block;">' +
            '    <div class="lxq-thread-wrap"> ' +
            '       <div class="lxq-show-comment"> ' +
            '           <span class="lxq-comment-label">No errors found</span>'  +
            '       </div> ' +
            '    </div> ' +
            ' </div>',

        lxqTooltipWrap: ''+
            '<div class="tooltip-error-wrapper"> '+
            ''+ //add lxqTooltipBody here...
            '</div>',
        lxqTooltipBody: ''+
            '<div class="tooltip-error-container"> '+
                '<span class="tooltip-error-category">xxxx</span> '+
                '<div class="tooltip-error-ignore">' +
                    '<span class="icon-cancel-circle"></span>' +
                    '<span class="tooltip-error-ignore-text">Ignore</span> </div> ' +
            '</div>',
        lxqTooltipSpellcheckBody: ''+
            '<div class="tooltip-error-container"> '+
                '<a class="tooltip-error-category">xxxx</a> </div>',
        lxqTooltipSuggestionBody: ''+
            '<div class="tooltip-error-container lxq-suggestion"> '+
                '<a class="tooltip-error-category">xxxx</a> </div>'
    };

    $.extend(LXQ.const, {
        get tpls() {
            return tpls ;
        }
    });

})(jQuery,LXQ);
