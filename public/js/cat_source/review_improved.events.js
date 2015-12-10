
// TODO: move this into a specific file
//
// in memory database for MateCat
//

(function($, root, undefined) {

    window.rangy_backup = window.rangy ;
    window.rangy = {
        init: function() { },
        saveSelection: function() {}
    };

    var db = new loki('loki.json');
    var segments = db.addCollection('segments', { indices: ['sid']} ) ;
    segments.ensureUniqueIndex('sid');

    $(document).on('segments:load', function(e, data) {
        $.each(data.files, function() {
            $.each( this.segments, function() {
                var seg = segments.findOne( {sid : this.sid} );
                if ( seg ) {
                    var update = segments.update( this );
                }
                else {
                    var insert = segments.insert( this );
                }
            });
        });
    });

    root.MateCat = root.MateCat || {};
    root.MateCat.DB = db ;
    root.MateCat.DB.upsert = function(collection, record) {
        var c = this.getCollection(collection);
        console.debug('upsert', collection, record);
        if ( !c.insert( record ) ) {
            console.debug('upsert, updating');
            c.update( record );
        }
    }
    root.MateCat.Segments = segments ;

})(jQuery, window);

//
if ( Review.enabled() && Review.type == 'improved' ) {
(function($, UI, undefined) {
    var last_selection;

    var db = window.MateCat.DB;

    var versions = db.addCollection('segment_versions', {indices: [ 'id_segment']});
    versions.ensureUniqueIndex('id_segment');

    var issues = db.addCollection('segment_review_issues', {indices: ['id', 'id_segment']});
    issues.ensureUniqueIndex('id');

    var segments = db.getCollection('segments');
    var modal ;

    function showModalWindow(selection) {
        var data             = {};

        var selection = document.getSelection();
        var offsets = [selection.anchorOffset, selection.focusOffset];
        offsets.sort();

        data.start_offset  = offsets[0];
        data.end_offset    = offsets[1];

        data.selected_string = selection.toString() ;
        last_selection       = data.selected_string ;
        data.lqa_model       = JSON.parse( config.lqa_model ) ;

        var template = $( MateCat.Templates['review_improved/error_selection']( data ) );
        modal = template.remodal({});

        template.on('keydown', function(e)  {
            var esc = 27 ;
            e.stopPropagation();
            if ( e.which == esc ) {
                modal.close();
            }
        });

        modal.open();
    }

    function overrideButtons() {
        var div = $('<ul>' + UI.segmentButtons + '</ul>');
        div.find('.translated').text('APPROVED')
            .removeClass('translated').addClass('approved');
        div.find('.next-untranslated').parent().remove();
        UI.segmentButtons = div.html();
    }

    $(document).on('mouseup', 'section.opened .errorTaggingArea', function(e) {
        var selection = document.getSelection();

        if (
            selection.focusNode.parentNode.closest('.errorTaggingArea') &&
            selection.anchorNode.parentNode.closest('.errorTaggingArea') &&
            selection.toString().length > 0
        ) {
            showModalWindow( selection );
        }
    });

    $('html').on('buttonsCreation', 'section', function() {
        overrideButtons();
    });

    $(document).on('click', '.errorTaggingArea', function(e) {
        var section = $(e.target).closest('section') ;
        var segment = new UI.Segment( section );

        if ( UI.currentSegmentId != segment.id ) {
            UI.openSegment( segment );
            UI.scrollSegment( segment.el );
        }
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
            'target_text'         : last_selection,
            'start_position'      : form.find('input[name=start_offset]').val(),
            'stop_position'       : form.find('input[name=end_offset]').val(),
            'comment'             : comment
        };

        $.post( path, modelToSave )
            .success(function( data ) {
                // push the new data to the store
                console.log('success creation of entry', data.issue );
                MateCat.DB.upsert('segment_review_issues', data.issue );
                modal.close();
                //
            });

        return false;
    });

    $(document).on('click', '.reviewImproved .tabs-menu a', function(event) {
        event.preventDefault();

        $(this).parent().addClass("current");
        $(this).parent().siblings().removeClass("current");

        var section = $(event.target).closest('section');
        var tab = $(this).data("ref");

        console.log(tab);

        section.find('.tab-content').not(tab).css("display", "none");
        section.find(tab).show();

    });

    $( window ).on( 'segmentOpened', function ( e ) {
        var segment = new UI.Segment( $( e.segment ) );
        var versions_path  = sprintf('/api/v2/jobs/%s/%s/segments/%s/versions',
                  config.id_job, config.password, segment.id);

        var issues_path = sprintf('/api/v2/jobs/%s/%s/segments/%s/issues',
                   config.id_job, config.password, segment.id);

        $.getJSON( versions_path )
        .done(function( data ) {
            if ( data.versions.length ) {
                $.each( data.versions, function() {
                    MateCat.DB.upsert('segment_versions', this);
                });
            }
        })
        .complete(function() {
            updateVersionViews();
        });

        $.getJSON( issues_path )
        .done(function( data ) {
            if ( data.issues.length ) {
                $.each( data.issues, function() {
                    MateCat.DB.upsert('segment_review_issues', this);
                });
            }
        })
        .complete(function() {
            updateIssueViews();
        });

    });

    function renderViews(segment) {

    }

    function updateIssueViews() {
        var sid = UI.currentSegmentId ;
        // get all issues for the current segment
        var current_issues = issues.findObjects({ id_segment : sid });
        console.debug('current_issues',  current_issues );

        var data = {
            issues : current_issues
        };

        var template = $(MateCat.Templates['review_improved/translation_issues']( data ));
        UI.Segment.findEl( sid ).find('[data-mount=translation-issues]').html( template );
    }

    function updateVersionViews( ) {
        var original_target ;
        var sid     = UI.currentSegmentId ;
        var segment = segments.findOne({sid : sid});
        var data    = { segment : segment };

        if ( parseInt(segment.original_target_provied) ) {
            var first_version = db.getCollection('segment_versions').findObject( {
                id_segment : sid,
                version_number : '0'
            });

            if ( !first_version ) {
                original_target = segment.translation ;
            } else {
                original_target = first_version.translation ;
            }

            data.original_target = UI.decodePlaceholdersToText(original_target) ;
        }

        var template = $(MateCat.Templates['review_improved/original_target']( data ));
        UI.Segment.findEl( segment.sid ).find('[data-mount=original-target]').html( template );
    }

    issues.on('update', updateIssueViews);
    issues.on('insert', updateIssueViews);

    versions.on('update', updateVersionViews);
    versions.on('insert', updateVersionViews);

    // Event to trigger on approval
    // TODO: this is to be redone, this should only set the state on the
    // segment.
    $(document).on('click', 'a.approved', function(e) {
        e.preventDefault();

        UI.tempDisablingReadonlyAlert = true;
        UI.hideEditToolbar();

        UI.currentSegment.removeClass('modified');

        original = UI.currentSegment.find('.original-translation').text();

        $('.sub-editor.review .error-type').removeClass('error');

        UI.changeStatus(this, 'approved', 0);

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

})($, UI);
}
