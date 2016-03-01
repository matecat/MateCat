// ---------------- specific for review page

if ( ReviewImproved.enabled() && config.isReview ) {
(function($, root, RI, UI, undefined) {

    var db = window.MateCat.db;
    var issues = db.segment_translation_issues ;
    var versions = db.segment_versions ;
    var segments = db.segments ;
    var comments = db.segment_translation_issue_comments ;

    // issues.on('update', issueRecordChanged);
    // issues.on('insert', issueRecordChanged);
    // issues.on('delete', issueRecordChanged);

    // versions.on('update', versionRecordChanged);
    // versions.on('insert', versionRecordChanged);
    // versions.on('delete', versionRecordChanged);

    function getSelectionData(selection, container) {
        var data             = {};

        data.start_node = $.inArray( selection.anchorNode, container.contents() );
        data.start_offset = selection.anchorOffset;

        data.end_node = $.inArray( selection.focusNode, container.contents() );
        data.end_offset = selection.focusOffset;

        data.selected_string = selection.toString() ;

        return data ;
    }

    function overrideButtons() {
        var div = $('<ul>' + UI.segmentButtons + '</ul>');

        div.find('.translated')
            .text('APPROVED')
            .removeClass('translated')
            .addClass('approved');

        div.find('.next-untranslated').parent().remove();

        UI.segmentButtons = div.html();
    }

    function restoreFirstTab( segment ) {
        $( segment ).find('.tabs-menu li').removeClass('current');
        $( segment ).find('.tabs-menu li:first').addClass('current');
        $( segment ).find('.tab-content:not(:first)').css("display", "none");
        $( segment ).find('.tab-content:first').show();
    }

    $(document).on('changeData', 'section', function(e, key) {
        var section = $(e.target);
        switch( key ) {
            case 'revertingVersion':
                break;
        }

    });

    $(document).on('segment:deactivate', function(e, data) {
        var lastOpenedSegment = data.deactivated_segment ;
        if ( lastOpenedSegment ) {
            restoreFirstTab( lastOpenedSegment );
        }
        $(lastOpenedSegment)
            .data('revertingVersion', null)
            .trigger('changeData', 'revertingVersion');
        // TODO: restore segment here
    });

    // $(document).on('review:text:selected', function(e, data) {
    //     // show activator button
    //     showIssueSelectionModalWindow( data.selection, data.container );
    // });

    var textSelectedInsideSelectionArea = function( selection, container ) {
        return $.inArray( selection.focusNode, container.contents() ) !==  -1 &&
            $.inArray( selection.anchorNode, container.contents() ) !== -1 &&
            selection.toString().length > 0 ;
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

    $('html').on('buttonsCreation', 'section', function() {
        overrideButtons();
    });

    $(document).on('click', 'section .textarea-container .errorTaggingArea', function(e) {
        var section = $(e.target).closest('section') ;
        var segment = new UI.Segment( section );

        if ( ! segment.el.hasClass('opened') ) {
            UI.openSegment( segment );
            UI.scrollSegment( segment.el );
        }

    });

    $(document).on('click', '.reviewImproved .tab-switcher-review', function(e) {
        e.preventDefault();

        $('.editor .submenu .active').removeClass('active');
        $(this).addClass('active');
        $('.editor .sub-editor.open').removeClass('open');
        if($(this).hasClass('untouched')) {
            $(this).removeClass('untouched');
            if(!UI.body.hasClass('hideMatches')) {
                $('.editor .sub-editor.review').addClass('open');
            }
        } else {
            $('.editor .sub-editor.review').addClass('open');
        }
    });

    $(document).on('submit', '#error-selection-form', function(e) {
        e.preventDefault();

        var form    = $( e.target );
        var segment = new UI.Segment( UI.currentSegment );
        var path  = sprintf('/api/v2/jobs/%s/%s/segments/%s/translation-issues',
                  config.id_job, config.password, segment.id);

        var checked = form.find('input[type=radio]:checked').val() ;

        var id_category = checked.split('-')[0];
        var severity = checked.split('-')[1];
        var comment = form.find('textarea').val();

        var modelToSave = {
            'id_category'         : id_category,
            'severity'            : severity,
            'target_text'         : RI.lastSelection,
            'start_node'          : form.find('input[name=start_node]').val(),
            'start_offset'        : form.find('input[name=start_offset]').val(),
            'end_node'            : form.find('input[name=end_node]').val(),
            'end_offset'          : form.find('input[name=end_offset]').val(),
            'comment'             : comment,
            'formattedDate'       : moment().format('lll') // TODO: check if this is still required
        };

        /**
         * Whenever an error is saved, change status to rejected.
         */

        $.post( path, modelToSave )
            .done(function( data ) {
                // push new data to the store
                // TODO make an helper for this date conversion
                data.issue.formattedDate = moment(data.issue.created_at).format('lll');
                MateCat.db.segment_translation_issues.insert( data.issue ) ;
                RI.modal.close();
                RI.reloadQualityReport();

            }).done(function( data ) {
                UI.changeStatus(segment.el, 'rejected', false, {
                    noPropagation: true,
                });
            });

        return false;
    });

    $(document).on('setTranslation:success', function(e, data) {
        ReviewImproved.reloadQualityReport();
    });

    $(document).on('click', '.reviewImproved .tabs-menu a', function(event) {
        event.preventDefault();

        var section = $(event.target).closest('section');

        // update styles
        $(this).parent().addClass("current");
        $(this).parent().siblings().removeClass("current");
        var tab = $(this).data("ref");
        section.find('.tab-content').not(tab).css("display", "none");
        section.find(tab).show();

        // save current status
        var id = $(this).data('id');
        section.data('activeTab', id);
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

    function updateTrackChangesView( segment ) {
        var current = UI.clenaupTextFromPleaceholders( RI.getTranslationText(segment) );
        var prev_text =  getPreviousTranslationText( segment );
        var el = segment.el ;

        if ( prev_text ) {
            el.find('.trackChanges').html(
              trackChangesHTML( UI.clenaupTextFromPleaceholders( prev_text ), current )
            );
        }
        else {
            el.find('.trackChanges').html( current );
        }
    }

    function updateOriginalTargetView( segment ) {
        var record = segments.by('sid', segment.id);
        var original_target ;
        var data    = { record : record };

        if ( parseInt(record.original_target_provied) ) {
            var first_version = db.segment_versions.findObject({
                id_segment : record.sid,
                version_number : '0'
            });

            if ( !first_version ) {
                original_target = record.translation ;
            } else {
                original_target = first_version.translation ;
            }

            data.original_target = UI.decodePlaceholdersToText(original_target) ;
        }

        var template = $(MateCat.Templates['review_improved/original_target']( data ));
        segment.el.find('[data-mount=original-target]').html( template );
    }

    function updateVersionDependentViews( segment ) {
        updateTextAreaContainer( segment );
        updateTrackChangesView( segment );
        RI.updateIssueViews( segment );
        renderButtons( segment );
    }

    function updateTextAreaContainer( segment ) {
        var text = RI.getTranslationText( segment );

        var textarea_container = template('review_improved/text_area_container',
            {
                decoded_translation : UI.decodePlaceholdersToText( text ),
            });

        segment.el.find('[data-mount=segment_text_area_container]')
            .html( textarea_container );
    }

    $(document).on('segmentVersionChanged', function(e, segment) {
        updateVersionDependentViews( segment );
    });

    $(document).on('click', '.action-delete-issue', function(e) {
        id = $(e.target).closest('.issue-container').data('issue-id');
        var issue = db.segment_translation_issues.findObject({
            id : id + ''
        });

        var message = sprintf(
            "You are about to delete the issue on string '%s' posted on %s." ,
            issue.target_text,
            moment( issue.created_at ).format('lll')
        );

        APP.confirm({
            name : 'Confirm issue deletion',
            callback : 'deleteTranslationIssue',
            msg: message,
            okTxt: 'Yes delete this issue',
            context: JSON.stringify({
                id_segment : issue.id_segment,
                id_issue : issue.id
            })
        });

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

})($, window, ReviewImproved, UI);
}
