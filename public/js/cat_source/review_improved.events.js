if ( Review.enabled() && Review.type == 'improved' ) {
(function($, UI, undefined) {

    var severties = [
        { code: 1, name: 'Low', weight: 0.3 },
        { code: 2, name: 'Medium', weight: 0.4 },
        { code: 3, name: 'None', weight: 0 } ,
        { code: 4, name: 'High', weight: 1 }
    ];

    var quality_model = {
        typologies: [
            { name: 'Mistranslation', severities: severities }

        ]
    }
    // TODO: make this dynamic
    var typologies_map = [
        { name: '1) Mistranslation', code: 1 },
        { name: '2) Addition', code: 2 },
        { name: '3) Omission', code: 3 },
        { name: '4) Untranslated', code: 4 },
        { name: '5) Terminology', code: 5 },
        { name: '5) Spelling', code: 6 },
        { name: '6) Grammar', code: 7 },
        { name: '7) Punctuation', code: 8 },
        { name: '8) Company style', code: 9 },
    ];

    var severities = [1,2,3,4];

    function showModalWindow(selection) {
        var data = {};
        data.selected_string = selection.toString() ;
        data.test_partial = '1234abcd';
        data.typologies = typologies_map ;
        data.severities = severities ;

        var template = $( MateCat.Templates['review_improved/error_selection']( data ) );
        var modal = template.remodal({});

        template.on('keydown', function(e)  {
            var esc = 27 ;
            e.stopPropagation();
            if ( e.which == esc ) {
                modal.close();
            }
        });

        modal.open();
    }

    function overrideButtons() {
        var div = $('<ul>' + UI.segmentButtons + '</ul>');
        div.find('.translated').text('APPROVED')
            .removeClass('translated').addClass('approved');
        div.find('.next-untranslated').parent().remove();
        UI.segmentButtons = div.html();
    }

    $(document).on('mouseup', 'section.opened .errorTaggingArea', function(e) {
        var selection =  rangy.getSelection() ;
        if (
            selection.focusNode.parentNode.closest('.errorTaggingArea') &&
            selection.anchorNode.parentNode.closest('.errorTaggingArea') &&
            selection.toString().length > 0
        ) {
            showModalWindow( selection );
        }
    });

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

    $(document).on('click', '.reviewImproved .tabs-menu a', function(event) {
        event.preventDefault();

        $(this).parent().addClass("current");
        $(this).parent().siblings().removeClass("current");

        var section = $(event.target).closest('section');
        var tab = $(this).data("ref");

        console.log(tab);

        section.find('.tab-content').not(tab).css("display", "none");
        section.find(tab).show();

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
