if ( Review.enabled() && Review.type == 'improved' ) {
(function($, UI, undefined) {

    function overrideButtons() {
        var div = $('<ul>' + UI.segmentButtons + '</ul>');
        div.find('.translated').text('APPROVED')
            .removeClass('translated').addClass('approved');
        div.find('.next-untranslated').parent().remove();
        UI.segmentButtons = div.html();
    }

    $('html').on('buttonsCreation', 'section', function() {
        overrideButtons();
    });

    $(document).on('click', '.textarea-container .tabs-menu a', function(e) {
        e.preventDefault();
    });

    $(document).on('click', '.errorTaggingArea', function(e) {
        var section = $(e.target).closest('section') ;
        var segment = new UI.Segment( section );

        UI.openSegment( segment );
        UI.scrollSegment( segment.el );
    });

    $('html').on('footerCreation', 'section', function() {
        var div = $('<div>' + UI.footerHTML + '</div>');

        var data = { id: $(this).attr('id') };

        div.find('.submenu').append(
            $(MateCat.Templates['review_improved/review_tab']( data ))
        );

        div.append(
            $(MateCat.Templates['review_improved/review_tab_content'](data))
        );

        UI.footerHTML = div.html();
    });

    $(document).on('click', '.reviewImproved .tab-switcher-review', function(e) {
        e.preventDefault();

        $('.editor .submenu .active').removeClass('active');
        $(this).addClass('active');
        $('.editor .sub-editor.open').removeClass('open');
        if($(this).hasClass('untouched')) {
            $(this).removeClass('untouched');
            if(!UI.body.hasClass('hideMatches')) {
                $('.editor .sub-editor.review').addClass('open');
            }
        } else {
            $('.editor .sub-editor.review').addClass('open');
        }
    });

    $(document).on('click', 'a.approved', function(e) {
        console.log('clicked');

        e.preventDefault();

        UI.tempDisablingReadonlyAlert = true;
        UI.hideEditToolbar();

        UI.currentSegment.removeClass('modified');

        noneSelected = !(
            (UI.currentSegment.find('.sub-editor.review .error-type input[value=1]').is(':checked'))||
            (UI.currentSegment.find('.sub-editor.review .error-type input[value=2]').is(':checked'))
        );

        if ( (noneSelected)&&($('.editor .track-changes p span').length) ) {

            $('.editor .tab-switcher-review').click();
            $('.sub-editor.review .error-type').addClass('error');

        } else {

            original = UI.currentSegment.find('.original-translation').text();
            $('.sub-editor.review .error-type').removeClass('error');
            UI.changeStatus(this, 'approved', 0);
            sid = UI.currentSegmentId;
            err = $('.sub-editor.review .error-type');
            err_typing = $(err).find('input[name=t1]:checked').val();
            err_translation = $(err).find('input[name=t2]:checked').val();
            err_terminology = $(err).find('input[name=t3]:checked').val();
            err_language = $(err).find('input[name=t4]:checked').val();
            err_style = $(err).find('input[name=t5]:checked').val();
            UI.openNextTranslated();

            var data = {
                action: 'setRevision',
                job: config.job_id,
                jpassword: config.password,
                segment: sid,
                original: original,
                err_typing: err_typing,
                err_translation: err_translation,
                err_terminology: err_terminology,
                err_language: err_language,
                err_style: err_style
            };

            UI.setRevision( data );

        }
    });

})($, UI);
}
