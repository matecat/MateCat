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
        } );

        $.extend( UI, {

            registerReviseTab: function () {
                SegmentActions.registerTab( 'review', true, true );
            },

            trackChanges: function ( editarea ) {
                var $segment = $( editarea ).closest( 'section' );
                var source = $segment.find( '.original-translation' ).html();
                source = UI.clenaupTextFromPleaceholders( source );

                var target = $( editarea ).text();
                var diffHTML = trackChangesHTML( source, htmlEncode( target ) );

                $( '.sub-editor.review .track-changes p', $segment ).html( diffHTML );
            },

            openNextTranslated: function ( sid ) {
                sid = sid || UI.currentSegmentId;
                var el = $( '#segment-' + sid );

                var translatedList = [];
                // find in next segments in the current file
                if ( el.nextAll( '.status-translated' ).length ) {
                    translatedList = el.nextAll( '.status-translated' );
                    if ( translatedList.length ) {
                        translatedList.first().find( UI.targetContainerSelector() ).click();
                    }
                    // find in next segments in the next files
                } else if ( el.parents( 'article' ).nextAll( 'section.status-translated' ).length ) {

                    file = el.parents( 'article' );
                    file.nextAll( 'section.status-translated' ).each( function () {
                        if ( !$( this ).is( UI.currentSegment ) ) {
                            translatedList = $( this );
                            translatedList.first().find( UI.targetContainerSelector() ).click();
                            return false;
                        }
                    } );
                    // else find from the beginning of the currently loaded segments in all files
                } else if ( $( 'section.status-translated' ).length ) {
                    $( 'section.status-translated' ).each( function () {
                        if ( !$( this ).is( UI.currentSegment ) ) {
                            translatedList = $( this );
                            translatedList.first().find( UI.targetContainerSelector() ).click();
                            return false;
                        }
                    } );
                } else { // find in not loaded segments or go to the next approved
                    // Go to the next segment saved before
                    var callback = function () {
                        $( window ).off( 'modalClosed' );
                        //Check if the next is inside the view, if not render the file
                        var next = UI.Segment.findEl( UI.nextUntranslatedSegmentIdByServer );
                        if ( next.length > 0 ) {
                            UI.gotoSegment( UI.nextUntranslatedSegmentIdByServer );
                        } else {
                            UI.renderAfterConfirm( UI.nextUntranslatedSegmentIdByServer );
                        }
                    };
                    // If the modal is open wait the close event
                    if ( $( ".modal[data-type='confirm']" ).length ) {
                        $( window ).on( 'modalClosed', function ( e ) {
                            callback();
                        } );
                    } else {
                        callback();
                    }
                }
            },
            setRevision: function ( data ) {
                APP.doRequest( {
                    data: data,
                    error: function () {
                        UI.failedConnection( data, 'setRevision' );
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
                var xEditarea = $( '#segment-' + id_segment + '-editarea' );
                if ( d.original !== '' ) {
                    setTimeout( function () {
                        SegmentActions.addOriginalTranslation( id_segment, UI.getSegmentFileId( $( '#segment-' + id_segment ) ), d.original );
                    } );
                }
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

            clickOnApprovedButton: function ( e, button ) {
                // the event click: 'A.APPROVED' i need to specify the tag a and not only the class
                // because of the event is triggered even on download button
                e.preventDefault();
                var sid = UI.currentSegmentId;
                var goToNextNotApproved = ($( button ).hasClass( 'approved' )) ? false : true;
                UI.tempDisablingReadonlyAlert = true;
                SegmentActions.removeClassToSegment( sid, 'modified' );
                UI.currentSegment.data( 'modified', false );


                $( '.sub-editor.review .error-type' ).removeClass( 'error' );

                UI.changeStatus( button, 'approved', 0 );  // this does < setTranslation

                var original = UI.currentSegment.find( '.original-translation' ).text();

                var err = $( '.sub-editor.review .error-type' );
                var err_typing = $( err ).find( 'input[name=t1]:checked' ).val();
                var err_translation = $( err ).find( 'input[name=t2]:checked' ).val();
                var err_terminology = $( err ).find( 'input[name=t3]:checked' ).val();
                var err_language = $( err ).find( 'input[name=t4]:checked' ).val();
                var err_style = $( err ).find( 'input[name=t5]:checked' ).val();

                if ( goToNextNotApproved ) {
                    UI.openNextTranslated();
                } else {
                    UI.gotoNextSegment( sid );
                }

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



