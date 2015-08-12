(function(window, $, UI) {

    var segment ;

    var tryToRenderAgain = function() {
        $('#outer').empty();

        UI.render({
            firstLoad: false,
            segmentToScroll: segment.selector.split('-')[1],
            highlight: highlight
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

    var scrollSegment = function(inputSegment, highlight, quick) {
        segment = $(inputSegment);

        if ( !segment.length ) {
            // TODO: check for this condition to be actually needed
            // to limit responsiblity of this function we must enforce
            // the segment to be present, raise otherwise.
            tryToRenderAgain() ;
            return ;
        }

        quick = quick || false;
        highlight = highlight || false;

        var pointSpeed = (quick)? 0 : 500;

        $("html,body").stop();

        // if ( config.isReview ) {
        if ( true ) {
            // FIXME: experimentally keep the `review` behaviour the default
            // for translate page too. We are not sure what the other block
            // of code actually does, so we need to keep this code around for
            // a while and do some user testing to be sure it is safe to
            // remove it.
            setTimeoutForReview() ;
        } else {
            scrollToDestination( getDestinationTop(), pointSpeed ) ;
        }

        // TODO check if this timeout can be avoided in some way
        setTimeout(function() { UI.goingToNext = false; }, pointSpeed);
    }

    var setTimeoutForReview = function() {
        setTimeout(function() {
            $("html,body").animate({
                scrollTop: segment.prev().offset().top - $('.header-menu').height()
            }, 500);
        }, 300);
    }

    var scrollToDestination = function( destinationTop, pointSpeed ) {
        $("html,body").animate({
            scrollTop: destinationTop - 20
        }, pointSpeed);
    }

    $.extend(UI, {
        scrollSegment : scrollSegment,
    });

})(window, $, UI, undefined);
