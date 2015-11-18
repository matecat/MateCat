/*
 Component: ui.navigator
 */

$(document).on('click', '.experiments-compass', function(e) {
    e.preventDefault();
    UI.body.toggleClass('viewNavigator');
}).on('click', '.docNavigator .currentSegmentPosition', function(e) {
    e.preventDefault();
    UI.pointToOpenSegment();
    UI.setCurrentlyLoadedWindow();
}).on('click', '.docNavigator .firstSegmentPosition', function(e) {
    e.preventDefault();
    UI.scrollSegment($('#segment-' + config.first_job_segment));
    UI.setCurrentlyLoadedWindow();
}).on('click', '.docNavigator .lastSegmentPosition', function(e) {
    e.preventDefault();
    UI.scrollSegment($('#segment-' + config.last_job_segment));
    UI.setCurrentlyLoadedWindow();
})

$(window).scroll(function() {
    UI.updateNavigator();
}).on('segmentsAdded', function() {
    console.log('segmenti aggiunti');
    UI.setCurrentlyLoadedWindow();
});
$.extend(UI, {
    createNavigator: function () {
        this.body.find('footer').prepend('<a href="#" class="experiments-compass icon-eye"></a><div id="navContainer"><div class="docNavigator"><div class="docZoom"><div class="firstSegmentPosition"></div><div class="currentSegmentPosition"></div><div class="currentViewPosition draggable ui-widget-content"></div><div class="currentlyLoaded"></div></div><div class="lastSegmentPosition"></div></div></div>');
        this.setNavigator();
        this.updateNavigator();
    },
    setNavigator: function () {
        fSeg = parseInt(config.first_job_segment);
        lSeg = parseInt(config.last_job_segment);
        lSegNew = lSeg - fSeg;

        this.navFirstVal = fSeg;
//        this.navFactorVal = lSegNew / 200;
        this.navFactorVal = 1.8 * 100 / lSegNew; // 180 px is the disposable height

        $('.docNavigator .firstSegmentPosition').attr('data-sid', fSeg);
        $('.docNavigator .lastSegmentPosition').attr('data-val', lSegNew).attr('data-sid', lSeg);
        this.setCurrentlyLoadedWindow();
        $('.docNavigator .currentViewPosition').draggable({
            axis: "y" ,
            containment: "parent",
            stop: function( event, ui ) {
                UI.getSidFromNavigatorPosition($(this).css('top').replace('px', ''));
            }
        });
        $.each(config.firstSegmentOfFiles, function() {
            console.log(UI.getNavigatorPositionFromSid(this.first_segment));
            $('.docZoom .currentlyLoaded').after('<div class="fileStart" title="' + this.file_name + '" style="top: 10px"></div>')
//            $('.docZoom').append('<div class="fileStart" title="' + this.file_name + '" style="top: 10px"></div>')
        });
    },
    setCurrentlyLoadedWindow: function () {
        if(!$('section').length) {
            setTimeout(function() {
                UI.setCurrentlyLoadedWindow();
            }, 1000);
            return false;
        }
        console.log("$('section').length: ", $('section').length);
        wFirst = this.getNavigatorPositionFromSid(this.getSegmentId($('section').first()));
        wLast = this.getNavigatorPositionFromSid(this.getSegmentId($('section').last()));
        $('.docNavigator .currentlyLoaded').css('top', (parseInt(wFirst) + 10) + 'px').css('height', (wLast - wFirst) + 'px');
    },


    updateNavigator: function () {
        firstSegmentInViewportId = this.firstSegmentInViewport();
//        console.log(UI.getNavigatorPositionFromSid(firstSegmentInViewportId));
        $('.docNavigator .currentViewPosition').attr('data-sid', firstSegmentInViewportId).css('top', UI.getNavigatorPositionFromSid(firstSegmentInViewportId) + 'px');
        $('.docNavigator .currentSegmentPosition').attr('data-sid', UI.currentSegmentId).css('top', UI.getNavigatorPositionFromSid(UI.currentSegmentId) + 'px');
    },
    getNavigatorPositionFromSid: function (sid) {
        coso = parseInt(sid) - this.navFirstVal;
        val = parseFloat(coso * this.navFactorVal);
        valDef = val.toFixed(2);
        return valDef;
    },
    getSidFromNavigatorPosition: function (pos) {
        coso = Math.round(parseFloat(pos) / this.navFactorVal) + this.navFirstVal;
//        this.scrollSegment($('#segment-48746332'));
        fSeg = parseInt(config.first_job_segment);
        lSeg = parseInt(config.last_job_segment);
        console.log(fSeg + ' - ' + coso + ' - ' + lSeg);
        if(coso < fSeg) coso = fSeg;
        if(coso > lSeg) coso = lSeg;
        this.scrollSegment($('#segment-' + coso));
        this.setCurrentlyLoadedWindow();
    },


    firstSegmentInViewport: function () {
        target = '';
        $('section').each(function() {
            if($(this)[0].offsetTop > window.pageYOffset) {
                target = $(this).attr('id');
                return false;
            }
        })
        return target.split('-')[1];
    },

})


