if ( ReviewImproved.enabled() && config.isReview ) {
(function($, root, undefined) {

    $.extend(UI, {
        execChangeStatus : function( optStr ) {
            var options = $.parseJSON(optStr);

            console.log( options );
        },

        deleteTranslationIssue : function( context ) {
            console.debug('delete issue', context);

            var parsed = JSON.parse( context );
            var issue_path = sprintf(
                '/api/v2/jobs/%s/%s/segments/%s/translation/issues/%s',
                config.id_job, config.password,
                parsed.id_segment,
                parsed.id_issue
            );

            $.ajax({
                url: issue_path,
                type: 'DELETE'
            }).done( function( data ) {
                var record = MateCat.colls.issues.findOne({id : parsed.id_issue}) ;
                MateCat.colls.issues.remove( record );
            })
        },
        createButtons: function() {
            root.ReviewImproved.renderButtons();

            UI.currentSegment.trigger('buttonsCreation');
        },
        copySuggestionInEditarea : function() {
            return ;
        },
        targetContainerSelector : function() {
            return '.errorTaggingArea';
        },
        getSegmentTarget : function() {
            // read status from DOM? wrong approach, read from
            // database instead
            var segment = db.getCollection('segments').findOne({sid : sid});
            var translation =  segment.translation ;

            return translation ;
        },
        evalCurrentSegmentTranslationAndSourceTags : function() {
            var sourceTags = $('.source', this.currentSegment).html()
                .match(/(&lt;\s*\/*\s*(g|x|bx|ex|bpt|ept|ph|it|mrk)\s*.*?&gt;)/gi);
            this.sourceTags = sourceTags || [];
            this.currentSegmentTranslation = $('.errorTaggingArea').text();
        },
        getSegmentMarkup : function() {
            var segmentData = arguments[0];
            var data = UI.getSegmentTemplateData.apply( this, arguments ) ;

            var section            = $( UI.getSegmentTemplate()( data ) );
            var segment_body       = $( MateCat.Templates[ 'review_improved/segment_body' ](data) );
            var textarea_container = MateCat.Templates[ 'review_improved/text_area_container' ](
                {
                    decoded_translation : data.decoded_translation
                });

            segment_body
                .find('[data-mount="segment_text_area_container"]')
                .html( textarea_container );

            section
                .find('[data-mount="segment_body"]')
                .html( segment_body );

            return section[0].outerHTML ;
        },

        getSegmentTemplate : function() {
            return MateCat.Templates['review_improved/segment'];
        }
    });


  })(jQuery, window, ReviewImproved) ;
}
