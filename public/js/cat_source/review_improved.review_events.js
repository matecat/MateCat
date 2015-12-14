
if ( ReviewImproved.enabled() ) {

    $(document).on('segments:load', function(e, data) {
        $.each(data.files, function() {
            $.each( this.segments, function() {
                MateCat.db.upsert('segments', _.clone( this ) );
            });
        });
    });

    $(document).on('change', '.version-picker', function(e) {
        var segment = new UI.Segment( $(e.target).closest('section'));
        var target = $(e.target);
        var value = target.val();
        if ( value == '' ) {
            segment.el.removeClass('reverted');
            segment.el.data('revertingVersion', null);
        }
        else {
            segment.el.addClass('reverted');
            segment.el.data('revertingVersion', value);
            ReviewImproved.loadIssuesForVersion( segment );
        }

        $(document).trigger('segmentVersionChanged', segment);
    });

    // Globally reusable functions
    $.extend(ReviewImproved, {

        // Global vars: TODO: find a way to remove the need for these
        currentHiglight : null,
        modal : null,
        lastSelection : null,

        loadIssuesForVersion : function( segment ) {
            var issues_path = sprintf(
                '/api/v2/jobs/%s/%s/segments/%s/issues?version_number=%s',
                config.id_job, config.password,
                segment.id, segment.el.data('revertingVersion')
            );
            $.getJSON( issues_path )
            .done(function( data ) {
                if ( data.issues.length ) {
                    $.each( data.issues, function() {
                        MateCat.db.upsert('segment_translation_issues', _.clone(this) );
                    });
                }
            });
        },

        getSegmentRecord : function( segment ) {
            return MateCat.db.getCollection('segments')
                .findObject({sid : segment.id });
        },

        getTranslationText : function( segment ) {
            var record = ReviewImproved.getSegmentRecord( segment );
            var version;
            var revertingVersion = segment.el.data('revertingVersion');

            if ( revertingVersion ) {
                version = MateCat.db.getCollection('segment_versions').findObject({
                    id_segment : record.sid,
                    version_number : revertingVersion + ''
                });
                return version.translation ;
            }
            else {
                return record.translation ;
            }
        },

        updateIssueViews : function( segment ) {
            var targetVersion = segment.el.data('revertingVersion');
            var record = MateCat.db.getCollection('segments').findObject({sid : segment.id });
            var version = (targetVersion == null ? record.version_number : targetVersion) ;
            var issues = MateCat.db.getCollection('segment_translation_issues');
            var current_issues = issues.findObjects({
                id_segment : record.sid, translation_version : version
            });

            var data = {
                issues : current_issues
            };

            var tpl = template('review_improved/translation_issues', data );

            tpl.find('.issue-container').on('mouseover', ReviewImproved.highlightIssue);
            tpl.find('.issue-container').on('mouseout', ReviewImproved.resetHighlight);

            UI.Segment.findEl( record.sid ).find('[data-mount=translation-issues]').html( tpl );
        },

        highlightIssue : function(e) {
            var container = $(e.target).closest('.issue-container');
            var issue = MateCat.db.getCollection('segment_translation_issues').findObject({
                id : container.data('issue-id') + ''
            });
            var segment = MateCat.db.getCollection('segments').findObject({sid : issue.id_segment});

            // TODO: check for this to be really needed
            if ( container.data('current-issue-id') == issue.id ) {
                return ;
            }

            // TODO: check for this to be really needed
            container.data('current-issue-id', issue.id);
            var selection = document.getSelection();
            selection.removeAllRanges();

            var area = container.closest('section').find('.issuesHighlightArea') ;

            // TODO: fix this to take into account cases when monads are in plac
            // var tt    = UI.decodePlaceholdersToText( segment.translation );
            var tt             = area.html() ;
            var contents       = area.contents() ;
            var range = document.createRange();

            range.setStart( contents[ issue.start_node ], issue.start_offset );
            range.setEnd( contents[ issue.end_node ], issue.end_offset );

            selection.addRange( range );
        },

        resetHighlight : function(e) {
            var container = $(e.target).closest('.issue-container');
            var selection = document.getSelection();
            selection.removeAllRanges();

            container.data('current-issue-id', null) ; // TODO: check for this to be really needed
            var container = $(e.target).closest('.issue-container');
            var area = container.closest('section').find('.issuesHighlightArea') ;
            var issue = MateCat.db.getCollection('segment_translation_issues').findObject({
                id : container.data('issue-id') + ''
            });
            var segment = MateCat.db.getCollection('segments').findObject({sid : issue.id_segment});
            area.html( UI.decodePlaceholdersToText( segment.translation ) );
        },

        versionsAndIssuesPromise : function( segment ) {
            var versions_path  = sprintf(
                '/api/v2/jobs/%s/%s/segments/%s/versions',
                config.id_job, config.password, segment.id
            );

            var issues_path = sprintf(
                '/api/v2/jobs/%s/%s/segments/%s/issues',
                config.id_job, config.password, segment.id
            );

            return $.when(
                $.getJSON( versions_path )
                .done(function( data ) {
                    if ( data.versions.length ) {
                        $.each( data.versions, function() {
                            MateCat.db.upsert('segment_versions', _.clone(this) );
                        });
                    }
                })
                ,
            $.getJSON( issues_path )
            .done(function( data ) {
                if ( data.issues.length ) {
                    $.each( data.issues, function() {
                        this.formattedDate = moment(this.created_at).format('lll');
                        MateCat.db.upsert('segment_translation_issues', _.clone(this) );
                    });
                }
            })
            );
        }
    });
}

// ---------------- specific for review page

if ( ReviewImproved.enabled() && config.isReview ) {
(function($, root, RI, UI, undefined) {

    var db = window.MateCat.db;
    var issues = db.getCollection('segment_translation_issues');
    var versions = db.getCollection('segment_versions');
    var segments = db.getCollection('segments');

    function showIssueSelectionModalWindow(selection, container) {
        var data             = {};

        var selection = document.getSelection();

        data.start_node = $.inArray( selection.anchorNode, container.contents() );
        data.start_offset = selection.anchorOffset;

        data.end_node = $.inArray( selection.focusNode, container.contents() );
        data.end_offset = selection.focusOffset;

        data.selected_string = selection.toString() ;
        RI.lastSelection       = data.selected_string ;
        data.lqa_model       = JSON.parse( config.lqa_model ) ;

        var tpl = root.template('review_improved/error_selection', data);
        RI.modal = tpl.remodal({});

        tpl.on('keydown', function(e)  {
            var esc = 27 ;
            e.stopPropagation();
            if ( e.which == esc ) {
                RI.modal.close();
            }
        });

        RI.modal.open();
    }

    function overrideButtons() {
        var div = $('<ul>' + UI.segmentButtons + '</ul>');
        div.find('.translated').text('APPROVED')
            .removeClass('translated').addClass('approved');
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

    $(document).on('segment:deactivate', function(e, lastOpenedSegment, currentSegment) {
        if ( lastOpenedSegment ) {
            restoreFirstTab( lastOpenedSegment );
        }
        $(lastOpenedSegment)
            .data('revertingVersion', null)
            .trigger('changeData', 'revertingVersion');
        // TODO: restore segment here
    });

    $(document).on('mouseup', 'section.opened .errorTaggingArea', function(e) {
        var segment = new UI.Segment( $(e.target).closest('section'));

        if ( segment.el.data('revertingVersion') ) return ;

        var selection = document.getSelection();
        var leftMouseButton = 3 ;
        var container = $(e.target);

        if (
            // e.which == leftMouseButton &&
            $.inArray( selection.focusNode, container.contents() ) !==  -1 &&
            $.inArray( selection.anchorNode, container.contents() ) !== -1 &&
            selection.toString().length > 0
        ) {
            showIssueSelectionModalWindow( selection, container );
        }
    });

    $('html').on('buttonsCreation', 'section', function() {
        overrideButtons();
    });

    $(document).on('click', 'section .textarea-container .tab-content', function(e) {
        var section = $(e.target).closest('section') ;
        var segment = new UI.Segment( section );

        if ( ! segment.el.hasClass('opened') ) {
            UI.openSegment( segment );
            UI.scrollSegment( segment.el );
        }

        // if ( UI.currentSegmentId != segment.id ) {
        //     UI.openSegment( segment );
        //     UI.scrollSegment( segment.el );
        // }
    });

    $('html').on('footerCreation', 'section', function() {
        var div = $('<div>' + UI.footerHTML + '</div>');

        var data = { id: $(this).attr('id') };

        div.find('.submenu').append(
            $(MateCat.Templates['review_improved/review_tab']( data ))
        );

        div.append(
            $(MateCat.Templates['review_improved/review_tab_content'](data))
        );

        UI.footerHTML = div.html();
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
        var path  = sprintf('/api/v2/jobs/%s/%s/segments/%s/issues',
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
            'formattedDate'       : moment().format('lll')
        };

        $.post( path, modelToSave )
            .success(function( data ) {
                // push the new data to the store
                // TODO make an helper for this date conversion
                data.issue.formattedDate = moment(data.issue.created_at).format('lll');
                MateCat.db.upsert('segment_translation_issues', data.issue );
                RI.modal.close();
                //
            });

        return false;
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


    $( window ).on( 'segmentOpened', function ( e ) {
        var segment = e.segment ;
        ReviewImproved.versionsAndIssuesPromise( segment ).done(function() {
            updateOriginalTargetView( segment );
            updateVersionDependentViews( segment );
        });

    });

    function getPreviousTranslationText( segment ) {
        var record = RI.getSegmentRecord(segment);
        var version ;
        var revertingVersion = segment.el.data('revertingVersion');
        var prevBase =  revertingVersion ? revertingVersion : record.version_number ;
        version = db.getCollection('segment_versions').findObject({
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
        var record = segments.findOne({sid : segment.id });
        var original_target ;
        var data    = { record : record };

        if ( parseInt(record.original_target_provied) ) {
            var first_version = db.getCollection('segment_versions').findObject( {
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

    function versionRecordChanged( record ) {
        var segment = UI.Segment.find( record.id_segment );
        updateVersionDependentViews( segment );
    }

    function issueRecordChanged( record ) {
        var segment = UI.Segment.find( record.id_segment );
        RI.updateIssueViews( segment );
    }


    issues.on('update', issueRecordChanged);
    issues.on('insert', issueRecordChanged);

    versions.on('update', versionRecordChanged);
    versions.on('insert', versionRecordChanged);

    // Event to trigger on approval
    // TODO: this is to be redone, this should only set the state on the
    // segment.


    $(document).on('segmentVersionChanged', function(e, segment) {
        updateVersionDependentViews( segment );
    });

    function deleteIssue() {
        console.debug('delete issue');
        var issue_path = sprintf(
            '/api/v2/jobs/%s/%s/segments/%s/issues/%s',
            config.id_job, config.password, segment.id, issue.id
        );

        $.ajax({
            url: issue_path,
            type: 'DELETE'
        }).done( function( data ) {

            console.log('data', data );
        })
    }

    $(document).on('click', '.action-delete-issue', function(e) {
        id = $(e.target).closest('.issue-container').data('issue-id');
        var issue = db.getCollection('segment_translation_issues').findObject({
            id : id + ''
        });

        var message = sprintf("You are about to delete the issue on string '%s' posted on %s." ,
                         issue.target_text,
                         moment( issue.created_at ).format('lll')
                        );

        if ( confirm( message ) ) {
            deleteIssue( issue );
        }

    });

    $(document).on('click', 'a.approved', function(e) {
        e.preventDefault();

        UI.tempDisablingReadonlyAlert = true;
        UI.hideEditToolbar();

        UI.currentSegment.removeClass('modified');

        original = UI.currentSegment.find('.original-translation').text();

        $('.sub-editor.review .error-type').removeClass('error');

        UI.changeStatus(this, 'approved', 0);

        // TODO: remove references to currentSegmentId

        err = $('.sub-editor.review .error-type');
        err_typing = $(err).find('input[name=t1]:checked').val();
        err_translation = $(err).find('input[name=t2]:checked').val();
        err_terminology = $(err).find('input[name=t3]:checked').val();
        err_language = $(err).find('input[name=t4]:checked').val();
        err_style = $(err).find('input[name=t5]:checked').val();


        var data = {
            action: 'setRevision',
            job: config.id_job,
            jpassword: config.password,
            segment: UI.currentSegmentId,
            original: original,
            err_typing: err_typing,
            err_translation: err_translation,
            err_terminology: err_terminology,
            err_language: err_language,
            err_style: err_style
        };

        UI.openNextTranslated();

        UI.setRevision( data );

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

        var versionSelectHTML = MateCat.Templates['review_improved/version_selection']( data );

        container.html(versionSelectHTML);
        container.append(buttonHTML);
    }

    $.extend( ReviewImproved, {
        renderButtons : renderButtons,
    });

})($, window, ReviewImproved, UI);
}
