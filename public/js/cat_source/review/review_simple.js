ReviewSimple = {
    enabled : function() {
        return config.enableReview && config.isReview && Review.type === 'simple' ;
    },
    type : config.reviewType
};

if ( ReviewSimple.enabled() ) {

    (function ( Review, $, undefined ) {

        /**
         * Events
         *
         * Only bind events for specific review type
         */
        $( 'html' ).on( 'open', 'section', function () {
            if ( $( this ).hasClass( 'opened' ) && !$( this ).find( '.tab-switcher-review' ).hasClass('active') ) {
                $( this ).find( '.tab-switcher-review' ).click();
            }
        } ).on( 'click', '.sub-editor.review .error-type input[type=radio]', function ( e ) {
            $( '.sub-editor.review .error-type' ).removeClass( 'error' );
        } ).on('afterFormatSelection', '.editor .editarea', function() {
            UI.trackChanges();
        }).on('click', '.editor .outersource .copy', function(e) {
            UI.trackChanges();
        }).on('setCurrentSegment_success', function(e, d, id_segment) {
            UI.addOriginalTranslation(d, id_segment);
        });

        $.extend( UI, {

            registerReviseTab: function () {
                SegmentActions.registerTab( 'review', true, true );
            },

            trackChanges: function () {
                var currentSegmentId = SegmentStore.getCurrentSegment();
                var segment = SegmentStore.getSegmentByIdToJS(currentSegmentId);
                var $segment = UI.getSegmentById(currentSegmentId).closest('section');
                var source = segment.original_translation;
                //Fix for &amp in original-translation
                source = source.replace(/&amp;/g, "&");

                var target = segment.translation;
                var diffHTML = TextUtils.trackChangesHTML( TextUtils.htmlEncode(source), TextUtils.htmlEncode(target) );
                diffHTML = TagUtils.decodeHtmlInTag(diffHTML);
                $('.sub-editor.review .track-changes p', $segment).html( diffHTML );
            },
            setRevision: function ( data ) {
                APP.doRequest( {
                    data: data,
                    error: function () {
                        OfflineUtils.failedConnection( data, 'setRevision' );
                    },
                    success: function ( d ) {

                        window.quality_report_btn_component.setState( {
                            vote: d.data.overall_quality_class
                        } );
                    }
                } );
            },
            /**
             *
             * @param d Data response from the SetCurrentSegment request
             * @param id_segment
             */
            addOriginalTranslation: function ( d, id_segment ) {
                var segment = SegmentStore.getSegmentByIdToJS(id_segment);
                var originalTrans = (d.original) ? d.original : segment.translation;
                // if ( originalTrans && originalTrans !== '' ) {
                //     setTimeout( function () {
                //         SegmentActions.addOriginalTranslation( id_segment, null, originalTrans );
                //     } );
                // }
                UI.setReviewErrorData( d.error_data, $( '#segment-' + id_segment ) );
                setTimeout( function () {
                    UI.trackChanges( xEditarea );
                }, 100 );
            },

            setReviewErrorData: function ( d, $segment ) {
                $.each( d, function ( index ) {
                    if ( this.type == "Typing" ) $segment.find( '.error-type input[name=t1][value=' + this.value + ']' ).prop( 'checked', true );
                    if ( this.type == "Translation" ) $segment.find( '.error-type input[name=t2][value=' + this.value + ']' ).prop( 'checked', true );
                    if ( this.type == "Terminology" ) $segment.find( '.error-type input[name=t3][value=' + this.value + ']' ).prop( 'checked', true );
                    if ( this.type == "Language Quality" ) $segment.find( '.error-type input[name=t4][value=' + this.value + ']' ).prop( 'checked', true );
                    if ( this.type == "Style" ) $segment.find( '.error-type input[name=t5][value=' + this.value + ']' ).prop( 'checked', true );
                } );
            },

            clickOnApprovedButton: function (segment, goToNextNotApproved ) {
                // the event click: 'A.APPROVED' i need to specify the tag a and not only the class
                // because of the event is triggered even on download button
                var sid = segment.sid;
                SegmentActions.removeClassToSegment( sid, 'modified' );
                UI.currentSegment.data( 'modified', false );


                $( '.sub-editor.review .error-type' ).removeClass( 'error' );

                var afterApproveFn = function (  ) {
                    if ( goToNextNotApproved ) {
                        UI.openNextTranslated();
                    } else {
                        UI.gotoNextSegment( sid );
                    }
                };

                UI.changeStatus( SegmentStore.getCurrentSegment(), 'approved', afterApproveFn );  // this does < setTranslation

                var original = UI.currentSegment.find( '.original-translation' ).text();

                var err = $( '.sub-editor.review .error-type' );
                var err_typing = $( err ).find( 'input[name=t1]:checked' ).val();
                var err_translation = $( err ).find( 'input[name=t2]:checked' ).val();
                var err_terminology = $( err ).find( 'input[name=t3]:checked' ).val();
                var err_language = $( err ).find( 'input[name=t4]:checked' ).val();
                var err_style = $( err ).find( 'input[name=t5]:checked' ).val();




                var data = {
                    action: 'setRevision',
                    job: config.id_job,
                    jpassword: config.password,
                    revision_number: config.revisionNumber,
                    segment: sid,
                    original: original,
                    err_typing: err_typing,
                    err_translation: err_translation,
                    err_terminology: err_terminology,
                    err_language: err_language,
                    err_style: err_style
                };

                UI.setRevision( data );
            }
        } );
    })( Review, jQuery );
}



