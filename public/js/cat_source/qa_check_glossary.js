QaCheckGlossary = {};

QaCheckGlossary.enabled = function() {
    return config.qa_check_glossary_enabled ;
};

if ( QaCheckGlossary.enabled() )
(function($, QaCheckGlossary, undefined) {
    var matchRegExp = '\\b(%s)\\b' ;

    var globalReceived = false ;
    var globalWarnings ;

    /**
     * We wait for getWarning local event to notify for Glossary warnings,
     * so to provide a consistent feelint go the user.
     */
    $(document).on('getWarning:local:success', function( e, data ) {
        startLocalUnusedGlossaryHighlight( data.segment );
    });

    $( window ).on( 'segmentsAdded', function ( e ) {
        globalReceived = false ;
        renderGlobalWarnings() ;
    });

    $(document).on('getWarning:global:success', function(e, data) {
        if ( globalReceived ) {
            return ;
        }
        globalWarnings = data.resp.data.glossary ;
        renderGlobalWarnings() ;
    });

    /**
     * Ensure update is reissued after glossarySourceMarked.
     */
    $(document).on('glossarySourceMarked', function(e, data) {
        startLocalUnusedGlossaryHighlight( data.segment );
    });

    function renderGlobalWarnings() {
        if ( !globalWarnings ) return ;

        var mapped = {} ;

        // group by segment id
        var segments_to_refresh = _.each( globalWarnings.matches, function ( item ) {
            mapped[ item.id_segment ] ? null : mapped[ item.id_segment ] = []  ;
            mapped[ item.id_segment ].push( item.data );
        });

        _.each(Object.keys( mapped ) , function(item, index) {
            var segment = UI.Segment.find( item );
            if ( !segment ) return ;

            var unusedGlossaryTerms = mapped[item];

            var container = segment.el.find( '.source' ) ;

            updateGlossaryUnusedMatches( segment, unusedGlossaryTerms );
        });

        globalReceived = true ;
    }

    function removeUnusedGlossaryMarks( container ) {
        container.find('.unusedGlossaryTerm').each(function(index)  {
            $(this).replaceWith( this.childNodes );
        });
    };

    function startLocalUnusedGlossaryHighlight( segment ) {
        var record = MateCat.db.segments.by('sid', segment.absId ) ;
        var unusedMatches = findUnusedGlossaryMatches( record ) ;

        updateGlossaryUnusedMatches( segment, unusedMatches ) ;
    }

    function bindEvents( container, unusedMatches ) {

        container.find('.unusedGlossaryTerm').each(function(index, item) {
            var el = $(item);

            var entry = _.chain(unusedMatches).filter(function findMatch(match, index) {
                return match.id == el.data('id');
            }).first().value();

            console.log( entry );

            el.powerTip({ placement : 's' });
            el.data({ 'powertipjq' : $('<div class="unusedGlossaryTip" style="padding: 4px;">Unused glossary term</div>') });
        });
    }

    function updateGlossaryUnusedMatches( segment, unusedMatches ) {
        // read the segment source, find with a regexp and replace with a span
        var container = segment.el.find('.source');

        removeUnusedGlossaryMarks( container ) ;

        var newHTML = container.html();

        $.each(unusedMatches, function( index ) {
            var value = this.raw_segment ;
            value = escapeRegExp( value );
            var re = new RegExp('\\b(' + value + ')\\b',"g");
            newHTML = newHTML.replace(
                re , '<span data-id="' + this.id + '" class="unusedGlossaryTerm">$1</span>'
            );
        });

        container.html( newHTML );

        bindEvents( container, unusedMatches );
    }

    function findUnusedGlossaryMatches( record ) {
        if ( ! ( record.glossary_matches && record.glossary_matches.length ) )  return [] ;

        var segment = UI.Segment.find( record.sid ) ;
        var currentText = segment.el.find( UI.targetContainerSelector() ).text();

        return _.filter( record.glossary_matches, function( item ) {
            var value = escapeRegExp( item.raw_translation );
            var re = new RegExp( sprintf( matchRegExp, value ),"g");
            var match = currentText.match( re ) ;
            return match == null ;
        });
    }

    $.extend(QaCheckGlossary, {
        removeUnusedGlossaryMarks : removeUnusedGlossaryMarks
    });

})(jQuery, QaCheckGlossary);
