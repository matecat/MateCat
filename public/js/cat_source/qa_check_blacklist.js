QaCheckBlacklist = {} ;

QaCheckBlacklist.enabled = function() {
    return config.qa_check_blacklist_enabled ;
}

// COMMON EVENTS
if (QaCheckBlacklist.enabled() )
(function($, UI, undefined) {

    var globalReceived = false ;
    var globalWarnings ;

    function blacklistItemClick(e) {
        // TODO: investigate the need for this. Click on .blacklistItem clears up the #outer
        // this function forwards the click to the containing editarea.
        e.preventDefault();
        e.stopPropagation();
        $(e.target).closest(UI.targetContainerSelector()).click();
        console.log('blacklist item clicked');
    }

    function addTip( editarea ) {
        $('.blacklistItem', editarea).powerTip({
            placement : 's'
        });
        $('.blacklistItem', editarea).data({ 'powertipjq' : $('<div class="blacklistTooltip">Blacklisted term</div>') });
    }

    /**
     *
     * @param editarea
     * @param matched_words
     */
    function updateBlacklistItemsInSegment( editarea, matched_words ) {
        editarea.find('.blacklistItem').each(function(index)  {
            $(this).replaceWith( this.childNodes );
        });

        editarea[0].normalize() ;

        var newHTML = editarea.html() ;

        $(matched_words).each(function(index, value) {
            value = escapeRegExp( value );
            var re = new RegExp('\\b(' + value + ')\\b',"g");
            newHTML = newHTML.replace(
                re , '<span class="blacklistItem">$1</span>'
            );
        });

        editarea.html( newHTML );

        if ( editarea.find('.undoCursorPlaceholder').length ) {
            setCursorPosition( editarea.find('.undoCursorPlaceholder')[0] );
        }

        UI.lockTags( editarea );

        $('.blacklistItem', editarea).on('click', blacklistItemClick);
        addTip( editarea ) ;
    }


    function renderGlobalWarnings() {
        if ( !globalWarnings ) return ;

        var mapped = {} ;

        // group by segment id
        var segments_to_refresh = _.each( globalWarnings.matches, function ( item ) {
            mapped[ item.id_segment ] ? null : mapped[ item.id_segment ] = []  ;
            mapped[ item.id_segment ].push( { severity: item.severity, match: item.data.match } );
        });

        _.each(Object.keys( mapped ) , function(item, index) {
            var segment = UI.Segment.find( item );
            if ( !segment || segment.isReadonly() ) return ;

            var matched_words = _.chain( mapped[item]).map( function( match ) {
                return match.match ;
            }).uniq().value() ;

            var editarea = segment.el.find(  UI.targetContainerSelector() ) ;
            updateBlacklistItemsInSegment( editarea, matched_words ) ;
        });

        globalReceived = true ;
    }

    $( window ).on( 'segmentsAdded', function ( e ) {
        globalReceived = false ;
        renderGlobalWarnings() ;
    });

    $(document).on('getWarning:global:success', function(e, data) {
        if ( globalReceived ) {
            return ;
        }

        globalWarnings = data.resp.data.blacklist ;

        renderGlobalWarnings() ;
    });

    $(document).on('getWarning:local:success', function(e, data) {
        if ( !data.resp.data.blacklist || data.segment.isReadonly() ) {
            // No blacklist data contained in response, skip it
            // or segment is readonly, skip
            return ;
        }

        var matched_words = Object.keys( data.resp.data.blacklist.matches )
        var editarea = data.segment.el.find( UI.targetContainerSelector() ) ;

        updateBlacklistItemsInSegment( editarea, matched_words );
    });

})(jQuery, UI );




