// ---------------- specific for review page

if ( ReviewImproved.enabled() && config.isReview ) {
(function($, root, RI, UI, undefined) {

    var versions = MateCat.db.segment_versions;

    function overrideButtons() {
        var div = $('<ul>' + UI.segmentButtons + '</ul>');

        div.find('.translated')
            .text('APPROVED')
            .removeClass('translated')
            .addClass('approved');

        div.find('.next-untranslated').parent().remove();

        UI.segmentButtons = div.html();
    }

    $('html').on('buttonsCreation', 'section', function() {
        overrideButtons();
    });

    $(document).on('click', 'section .textarea-container .errorTaggingArea', function(e) {
        var section = $(e.target).closest('section') ;

        if ( ! section.hasClass('opened') ) {
            UI.scrollSegment( section );
            UI.openSegment( section );
        }

    });

    $(document).on('setTranslation:success', function(e, data) {
        // ReviewImproved.reloadQualityReport();
    });

    function getPreviousTranslationText( segment ) {
        var record = RI.getSegmentRecord(segment);
        var version ;
        var revertingVersion = segment.el.data('revertingVersion');
        var prevBase =  revertingVersion ? revertingVersion : record.version_number ;
        version = db.segment_versions.findObject({
            id_segment : record.sid,
            version_number : (prevBase -1) + ''
        });
        if ( version ) {
            return version.translation;
        } else {
            return false;
        }
    }

    $(document).on('translation:change', function() {
        ReviewImproved.reloadQualityReport();
    });

    $(document).on('segment:status:change', function(e, segment, options) {
        if ( options.status != 'rejected' ) {
            // save to database!!
            UI.openNextTranslated( segment.id );
        }
    });

    $(document).on('click', 'a.approved', function(e) {
        UI.changeStatus( this , 'approved', 0);
    });


    var textSelectedInsideSelectionArea = function( selection, container ) {
        return $.inArray( selection.focusNode, container.contents() ) !==  -1 &&
            $.inArray( selection.anchorNode, container.contents() ) !== -1 &&
            selection.toString().length > 0 ;
    }

    function getSelectionData(selection, container) {
        var data = {};

        data.start_node = $.inArray( selection.anchorNode, container.contents() );
        data.start_offset = selection.anchorOffset;

        data.end_node = $.inArray( selection.focusNode, container.contents() );
        data.end_offset = selection.focusOffset;

        data.selected_string = selection.toString() ;

        return data ;
    }

    $(document).on('mouseup', 'section.opened .errorTaggingArea', function(e) {
        var segment = new UI.Segment( $(e.target).closest('section'));
        var selection = document.getSelection();
        var container = $(e.target);

        if ( textSelectedInsideSelectionArea(selection, container ) )  {
            var selection = getSelectionData( selection, container ) ;
            RI.openPanel( { sid: segment.id,  selection : selection });
        }
    });

    function renderButtons(segment) {
        if (segment === undefined) {
            segment = UI.Segment.find( UI.currentSegmentId );
        }

        var container = segment.el.find('.buttons') ;
        var revertingVersion = segment.el.data('revertingVersion');

        var buttonData = {
            disabled : !container.hasClass('loaded'),
            id_segment : segment.id,
            ctrl : ( (UI.isMac) ? 'CMD' : 'CTRL'),
        };

        var buttonHTML = MateCat.Templates['review_improved/approve_button']( buttonData ) ;

        var data = {
            versions : versions.findObjects({ id_segment : segment.id }),
            revertingVersion : revertingVersion
        };

        container.append(buttonHTML);
    }

    $.extend( ReviewImproved, {
        renderButtons : renderButtons,
    });

    $(document).on('ready', function() {
        // first step in the direction to not rely on HTML rendering.
        // we fetch quality-report data on page load to get the score
        // to show in quality-report button.
        ReviewImproved.reloadQualityReport();
    });

})($, window, ReviewImproved, UI);
}
