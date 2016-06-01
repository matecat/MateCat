(function($, UI) {

    var Segment = function(el) {
        this.el = $(el).closest('section') ;

        this.raw = this.el[0] ;
        var that = this;

        this.id = UI.getSegmentId(this.el);

        this.absoluteId = this.id.split('-')[0];
        
        this.absId = this.absoluteId ; /// alias 
        
        this.chunkId = this.id.split('-')[1] || null ;


        this.isSplit = function() {
            return (this.id.indexOf('-') != -1);
        }

        this.isFirstOfSplit = function() {
            return Number(this.chunkId) == 1 ;
        }

        this.unsplittedOrFirst = function() {
            return !this.isSplit() || this.isFirstOfSplit() ;
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
        if ( el.length == 0 ) {
            return null ; 
        } else {
            return el;
        }
    }

    Segment.find = function( number ) {
        var el = Segment.findEl( number ) ;
        if ( el == null ) {
            return ;
        }
        return new Segment( el );
    }

    /**
     * Finds the original segment of a split.
     * 
     * @param number
     */
    Segment.findAbsolute = function( number ) {
        return Segment.find( number.split('-')[0] ) ;
    }

    $.extend(UI, {
        Segment : Segment
    });

})(jQuery, UI);
