(function(window, $, UI) {

    var segment ;

    UI.scrollSelector = "#outer";

    var scrollSegment = function(idSegment) {
        SegmentActions.scrollToSegment(idSegment);
    };


    $.extend(UI, {
        scrollSegment : scrollSegment,
    });

})(window, $, UI, undefined);
