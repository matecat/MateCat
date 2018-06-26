(function(window, $, UI) {

    var segment ;

    UI.scrollSelector = "#outer";

    var tryToRenderAgain = function( idSegment, highlight, open ) {
        UI.unmountSegments();
        if (open) {
            UI.render({
                firstLoad: false,
                segmentToOpen: idSegment,
                highlight : highlight
            });
        } else {
            UI.render({
                firstLoad: false,
                segmentToScroll: idSegment,
                highlight : highlight
            });
        }

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

        var scrollPromise = UI.animateScroll( segment, pointSpeed ) ;
        scrollPromise.done( function() {
            UI.goingToNext = false;
        });
        
        if ( highlight ) { 
            scrollPromise.done( function() {
                SegmentActions.highlightEditarea(segment.find(".editarea").data("sid"));
            }); 
        }
        
        return scrollPromise ; 
    }

    var scrollSegment = function(inputSegment, idSegment, highlight, quick) {
        var segment = (inputSegment instanceof jQuery) ? inputSegment : $(inputSegment);

        quick = quick || false;
        highlight = highlight || false;
        
        if ( segment.length ) {
            return doDirectScroll( segment, highlight, quick ) ; 
        } else if( $(segment.selector + '-1').length ) {
            return doDirectScroll( $(segment.selector + '-1'), highlight, quick ) ;
        }
        else if ( idSegment ){
            return tryToRenderAgain( idSegment, highlight, true ) ;
        } else {
            console.error("Segment not found in the UI");
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
    var animateScroll = function( element, speed ) {
        var scrollAnimation = $( UI.scrollSelector ).stop().delay( 300 );
        var segment = element.closest('section');
        var pos = 0;
        var prev = segment.prev('section') ;
        var segmentOpen = $('section.editor');
        var article = segment.closest('article');

        if ( prev.length ) {
            pos = prev.offset().top ; // to show also the segment before
        } else {
            pos = segment.offset().top ;
        }
        pos = pos - segment.offsetParent('#outer').offset().top;

        if (article.prevAll('article').length > 0) {
            _.forEach(article.prevAll('article'), function ( item ) {
                pos = pos + $(item).outerHeight() + 140;
            });
        }

        scrollAnimation.animate({
            scrollTop: pos
        }, speed);


        return scrollAnimation.promise() ;
    };

    $.extend(UI, {
        scrollSegment : scrollSegment,
        animateScroll: animateScroll
    });

})(window, $, UI, undefined);
