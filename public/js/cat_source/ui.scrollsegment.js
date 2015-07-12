(function($, UI, config) {

    var scrollSegment = function(segment, highlight, quick) {
        quick = quick || false;
        highlight = highlight || false;

        if ( !segment.length ) {
            $('#outer').empty();

            this.render({
                firstLoad: false,
                segmentToScroll: segment.selector.split('-')[1],
                highlight: highlight
            });
        }

        var spread = 23;
        var current = this.currentSegment;
        var previousSegment = $(segment).prev('section');

        if ( !previousSegment.length ) {
          previousSegment = $(segment);
          spread = 103;
        }

        if ( !previousSegment.length ) return false;

        var destination = "#" + previousSegment.attr('id');
        var destinationTop = $(destination).offset().top;

        if (this.firstScroll) {
          destinationTop = destinationTop + 100;
          this.firstScroll = false;
        }

        // if there is an open segment
        if ( $(current).length ) {

            // if segment to open is below the current segment
            //
            if ( $(segment).offset().top > $(current).offset().top ) {

                // if segment to open is not the immediate follower of the current segment
                //
                if ( !current.is($(segment).prev()) ) {

                    var diff = (this.firstLoad) ? ($(current).height() - 200 + 120) : 20;

                    destinationTop = destinationTop - diff;

                } else { // if segment to open is the immediate follower of the current segment

                    destinationTop = destinationTop - spread;
                }

            } else { // if segment to open is above the current segment
                // if((typeof UI.provaCoso != 'undefined')&&(config.isReview)) spread = -17;
                destinationTop = destinationTop - spread;
                UI.provaCoso = true;
            }

        } else { // if no segment is opened
            destinationTop = destinationTop - spread;
        }

        $("html,body").stop();

        pointSpeed = (quick)? 0 : 500;

        if ( config.isReview ) {
            setTimeout(function() {
                $("html,body").animate({
                    scrollTop: segment.prev().offset().top - $('.header-menu').height()
                }, 500);
            }, 300);

        } else {

            $("html,body").animate({
                scrollTop: destinationTop - 20
                }, pointSpeed);
        }

        setTimeout(function() { UI.goingToNext = false; }, pointSpeed);
    }

    $.extend(UI, {
        scrollSegment : scrollSegment
    });

})($, UI, config);
