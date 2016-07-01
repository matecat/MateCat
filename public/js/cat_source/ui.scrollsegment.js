(function(window, $, UI) {

    var segment ;

    var scrollSelector = 'html,body'; 

    var tryToRenderAgain = function( segment, highlight ) {
        
        $('#outer').empty();
        
        var id_segment = segment.selector.split('-')[1];

        UI.render({
            firstLoad: false,
            segmentToScroll: id_segment, 
            highlight : highlight 
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
    
    var doDirectScroll = function( segment, highlight, quick ) {
        var pointSpeed = (quick)? 0 : 500;

        var scrollPromise = animateScroll( segment, pointSpeed ) ;
        scrollPromise.done( function() {
            UI.goingToNext = false;
        });
        
        if ( highlight ) { 
            scrollPromise.done( function() {
                UI.highlightEditarea( segment ) ;
            }); 
        }
        
        return scrollPromise ; 
    }

    var scrollSegment = function(inputSegment, highlight, quick) {
        var segment = $(inputSegment);

        quick = quick || false;
        highlight = highlight || false;
        
        if ( segment.length ) {
            return doDirectScroll( segment, highlight, quick ) ; 
        }
        else {
            return tryToRenderAgain( segment, highlight ) ;
        }

    }

    /**
     * This function takes a segment as argument and the speed to apply to scroll.
     *
     * If a previous segment is found, then we scroll to the previous segment, so to keep
     * a sufficient amount of space to read the previous segment.
     *
     * If a previous segment is not found, then we assume the segment is the first of a file.
     *
     * This function returns a Deferred, so to make the it chainable with other functions to be triggered
     * when scroll animation is completed.
     *
     * @param segment
     * @param speed
     * @returns Deferred
     */
    var animateScroll = function( segment, speed ) {
        var scrollAnimation = $( scrollSelector ).stop().delay( 300 ); 
        var pos ;
        var prev = segment.prev('section') ;

        // XXX: this condition is necessary **only** because in case of first segment of a file,
        // the previous element (<ul>) has display:none style. Such elements are ignored by the
        // the .offset() function.
        if ( prev.length ) {
            pos = prev.offset().top - $('.header-menu').height() ;
        } else {
            pos = segment.offset().top - $('.header-menu').height() ;
        }

        scrollAnimation.animate({
            scrollTop: pos
        }, speed);

        return scrollAnimation.promise() ; 
    }

    $.extend(UI, {
        scrollSegment : scrollSegment,
    });

})(window, $, UI, undefined);
