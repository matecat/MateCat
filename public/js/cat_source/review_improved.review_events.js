if ( ReviewImproved.enabled() ) {
    $(document).ready(function() {
        // first step in the direction to not rely on HTML rendering.
        // we fetch quality-report data on page load to get the score
        // to show in quality-report button.
        ReviewImproved.reloadQualityReport();
    });
}

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

        if ( section.hasClass('muted') || section.hasClass('readonly') ) {
            return ;
        }

        if ( ! section.hasClass('opened') ) {
            UI.openSegment( section );
            UI.scrollSegment( section );
        }
    });

    function getPreviousTranslationText( segment ) {
        var record = RI.getSegmentRecord(segment);
        var version ;
        var prevBase = record.version_number ;
        version = db.segment_versions.findObject({
            id_segment : parseInt(record.sid),
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

    $(document).on('click', 'a.approved', function(e) {
        UI.changeStatus( this , 'approved', 0);
        UI.openNextTranslated() ;
    });

    $(document).on('click', '.button-reject', function(e) {
        UI.rejectAndGoToNext();
    });

    var textSelectedInsideSelectionArea = function( selection, container ) {
        // return $.inArray( selection.focusNode, container.contents() ) !==  -1 &&
        //     $.inArray( selection.anchorNode, container.contents() ) !== -1 &&
        return container.contents().text().indexOf(selection.focusNode.textContent)>=0 &&
            container.contents().text().indexOf(selection.anchorNode.textContent)>=0 &&
            selection.toString().length > 0 ;
    };

    function getSelectionData(selection, container) {
        var data = {};
        data.start_node = $.inArray( selection.anchorNode, container.contents() );
        if (data.start_node<0) {
          //this means that the selection is probably ending inside a lexiqa tag,
          //or matecat tag/marking
          data.start_node = $.inArray( $(selection.anchorNode).parent()[0], container.contents() );
        }
        var nodes = container.contents();//array of nodes
        if (data.start_node ===0) {
            data.start_offset = selection.anchorOffset;
        } else {
          data.start_offset = 0;
          for (var i=0;i<data.start_node;i++) {
            data.start_offset += nodes[i].textContent.length;
          }
          data.start_offset += selection.anchorOffset;
          data.start_node = 0;
        }

        data.end_node = $.inArray( selection.focusNode, container.contents() );
        if (data.end_node<0) {
          //this means that the selection is probably ending inside a lexiqa tag,
          //or matecat tag/marking
          data.end_node = $.inArray( $(selection.focusNode).parent()[0], container.contents() );
        }
        if (data.end_node ===0)
          data.end_offset =  selection.focusOffset;
        else {
          data.end_offset = 0;
          for (var i=0;i<data.end_node;i++) {
            data.end_offset += nodes[i].textContent.length;
          }
          data.end_offset += selection.focusOffset;
          data.end_node = 0;
        }
        data.selected_string = selection.toString() ;
        return data ;
    }

    $(document).on('click', 'section .goToNextToReview', function(e) {
        e.preventDefault();
        UI.gotoNextSegment();
    });


    $(document).on('mouseup', 'section.opened .errorTaggingArea', function (e) {
        var segment = new UI.Segment( $(e.target).closest('section'));
        var selection = document.getSelection();
        var container = $(e.target).closest('.errorTaggingArea') ;

        if ( textSelectedInsideSelectionArea(selection, container ) )  {
            var selection = getSelectionData( selection, container ) ;
            SegmentActions.openIssuesPanel({ sid: segment.id,  selection : selection })
        }
    });
    function renderButtons(segment) {
        if (segment === undefined) {
            segment = UI.Segment.find( UI.currentSegmentId );
        }

        var container = segment.el.find('.buttons') ;
        container.empty();

        var currentScore = getLatestScoreForSegment( segment ) ;

        var buttonData = {
            disabled : !container.hasClass('loaded'),
            id_segment : segment.id,
            ctrl : ( (UI.isMac) ? 'CMD' : 'CTRL'),
            show_approve : currentScore == 0,
            show_reject : currentScore > 0
        };

        var buttonsHTML = MateCat.Templates['review_improved/segment_buttons']( buttonData ) ;

        var data = {
            versions : versions.findObjects({ id_segment : segment.absId })
        };

        container.append(buttonsHTML);
    }

    $.extend( ReviewImproved, {
        renderButtons : renderButtons,
    });

    getLatestScoreForSegment = function( segment ) {
        if (! segment) {
            return ;
        }
        var db_segment = MateCat.db.segments.findObject({ sid : '' + segment.absId });
        var latest_issues = MateCat.db.segment_translation_issues.findObjects({
            id_segment : parseInt(segment.absId) ,
            translation_version : '' + db_segment.version_number
        });

        var total_penalty = _.reduce(latest_issues, function(sum, record) {
            return sum + parseInt(record.penalty_points) ;
        }, 0) ;

        return total_penalty ;
    }

    var issuesChanged = function( record ) {
        var segment = UI.Segment.find(record.id_segment);
        if ( segment ) renderButtons( segment ) ;
    }

    MateCat.db.addListener('segment_translation_issues', ['insert', 'delete', 'update'], issuesChanged );

})($, window, ReviewImproved, UI);
}
