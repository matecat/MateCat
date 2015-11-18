(function($, UI) {

    var Segment = function(el) {
        this.el = $(el) ;
        this.raw = el ;
        var that = this;

        this.id = UI.getSegmentId(el);

        this.absoluteId = this.id.split('-')[0];
        this.chunkId = this.id.split('-')[1] || null ;

        this.isSplit = function() {
            return (this.id.indexOf('-') != -1);
        }
        this.isFirstOfSplit = function() {
            return Number(this.chunkId) == 1 ;
        }

        this.isFooterCreated = function() {
            return $('.footer', this.el).text() === '';
        }
    }

    Segment.findEl = function( number ) {
        var el = UI.getSegmentById( number );
        if (el.length == 0) {
            el = UI.getSegmentById( '' + number + '-1' );
        }
        return el;
    }

    Segment.find = function( number ) {
        return new Segment( Segment.findEl( number ) );
    }

    $.extend(UI, {
        Segment : Segment
    });

})(jQuery, UI);
