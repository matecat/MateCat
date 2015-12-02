if ( Review.enabled() && Review.type == 'improved' ) {

(function($, undefined) {

    var segments = {};

    $.extend(UI, {
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
            var textarea_container = $( MateCat.Templates[ 'review_improved/text_area_container' ](data) )

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

  })(jQuery) ;
}
