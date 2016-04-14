if ( LXQ.enabled() )
(function($,LXQ) {

    var tpls = { // TODO: make this local

        historyOuter : '' +
            ' <div class="lxq-history-balloon-outer hide"> ' +
            '     <div class="lxq-triangle lxq-triangle-top"></div> ' +
            ' </div> ',

        historyHasErrors: '' +
            ' <div class="lxq-history-balloon lxq-history-balloon-has-comment"> ' +
            '   <div class="lxq-thread-wrap"> ' +
            '       <div class="lxq-history-balloon-header lxq-clearfix"> ' +
            '            <span class="lxq-history-balloon-header-text">See the full lexiQA report</span> <a href="http://www.lexiqa.net" target="_blank" class="lxq-history-balloon-header-link"></a>' +
            '       </div> ' +
            '       <div class="lxq-history-balloon-body lxq-clearfix"> ' +
            '            <div class="lxq-history-balloon-row lxq-clearfix"> ' +
            '                <span class="lxq-history-balloon-header-segment">Segment</span> ' +
            '                <span class="lxq-history-balloon-header-total">Total errors</span>' + 
            '                <span class="lxq-history-balloon-header-ignored">Ignored</span>' + 
            '            </div> ' +   
            '' + //     
            '       </div> ' +
            '   </div> ' +    
            ' ' + 
            ' </div> ',
          
        //   <img src="http://s29.postimg.org/zdbe56c9v/segment_arrow.png" alt="Go to segment" />
        segmentWarningsRow: '' +
            '            <div class="lxq-history-balloon-row lxq-clearfix"> ' +
            '                <span class="lxq-history-balloon-segment"><span class="lxq-history-balloon-segment-number">231</span><a href="#" class="lxq-history-balloon-segment-link"></a></span> ' +
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
                '<a class="tooltip-error-ignore">ignore</a> </div> ' 

    };

    $.extend(LXQ.const, {
        get tpls() {
            return tpls ;
        }
    });

})(jQuery,LXQ);
