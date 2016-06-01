(function(window, $, UI) {

    var segment ;

    /**
     * 
     * @param segment
     * @param highlight
     * 
     * @returns Deferred
     */
    var tryToRenderAgain = function( segment ) {
        $('#outer').empty();

        return UI.render({
            firstLoad: false,
            segmentToScroll: segment.selector.split('-')[1]
        });
    }

    var someOpenSegmentOnPage = function() {
        // XXX: This is also true when segment is closed, is it correct?
        return $(UI.currentSegment).length ;
    }

    var getDestinationValues = function() {
        var top_segment = segment.prev('section');
        var destinationTop = 0;
        var spread = 23 ;

        if ( !top_segment.length ) {
            top_segment = segment;
            spread = 103;
        }

        destinationTop =  $(top_segment).offset().top;

        return { destinationTop : destinationTop, spread : spread };
    }

    var scrollingBelowCurrent = function() {
        return segment.offset().top > UI.currentSegment.offset().top ;
    }

    var scrollingRightBelowCurrent = function() {
        return UI.currentSegment.is( segment.prev() );
    }

    var getDestinationTop = function() {
        var values         = getDestinationValues() ;
        var destinationTop = values.destinationTop ;
        var spread         = values.spread ;

        if ( someOpenSegmentOnPage() ) {
            if ( scrollingRightBelowCurrent() ) {
                destinationTop = destinationTop - spread;
            } else if ( scrollingBelowCurrent() ) {
                var diff = ( UI.firstLoad ) ? ( UI.currentSegment.height() - 200 + 120 ) : 20;
               destinationTop = destinationTop - diff;
            } else {
                destinationTop = destinationTop - spread;
            }
        } else {
            destinationTop = destinationTop - spread;
        }

        return destinationTop ;
    }

    var animatedSelector = 'html,body';
    
    /**
     * 
     * @param inputSegment
     * @param highlight
     * @param quick
     * 
     * @returns Deferred
     */
    var scrollSegment = function(inputSegment, highlight, quick) {
        quick = quick || false;
        highlight = highlight || false;
        
        var segment = $(inputSegment);
        var id_segment = inputSegment.selector.split('-')[1]; 
        var animation ; 
        var scrollTime = (quick)? 0 : 500
        
        if ( !segment.length ) {
            animation = tryToRenderAgain( segment ) ;
            animation.pipe(function() {
                return UI.Segment.find( id_segment ).el ;
            }); 
            
            animation.done( function(segment) {
                positionSegmentOnTop( segment, scrollTime ); 
            }); 
            
        }
        else {
            animation = positionSegmentOnTop( segment, scrollTime ) ;
            animation.pipe(function() {
                return segment; 
            }); 
        }

        if ( highlight ) {
            animation.done(function( segment ) {
                UI.highlightEditarea( segment );
            });
        }

        return animation ; 
    }

    var positionSegmentOnTop = function( segment, scrollTime ) {
        var animation = $( animatedSelector ).stop() ; 
        
        if ( segment.prev().length ) {
           animation
               .delay( 300 )
               .animate({
                    scrollTop: segment.prev().offset().top - $('.header-menu').height()
                }, scrollTime, function() {
                   UI.goingToNext = false ; 
               });
        }
        
        return animation.promise() ; 
    }

    $.extend(UI, {
        scrollSegment : scrollSegment,
    });

})(window, $, UI, undefined);
