if ( Review.enabled() && Review.type == 'improved' ) {

(function($, undefined) {

    var selector = $('html');
    selector.on('open', 'section', function() {

        if($(this).hasClass('opened')) {
            $(this).find('.tab-switcher-review').click();
        }
    });

    selector.on('start', function() {
        config.stat_quality = [
            {
                "type":"Typing",
                "allowed":5,
                "found":1,
                "vote":"Excellent"
            },
            {
                "type":"Translation",
                "allowed":5,
                "found":1,
                "vote":"Excellent"
            },
            {
                "type":"Terminology",
                "allowed":5,
                "found":1,
                "vote":"Excellent"
            },
            {
                "type":"Language Quality",
                "allowed":5,
                "found":1,
                "vote":"Excellent"
            },
            {
                "type":"Style",
                "allowed":5,
                "found":1,
                "vote":"Excellent"
            }
        ];
    });


    function changeLabelToTranslateButton() {
        var div = $('<ul>' + UI.segmentButtons + '</ul>');

        div.find('.translated').text('APPROVED')
        .removeClass('translated').addClass('approved');
        div.find('.next-untranslated').parent().remove();

        UI.segmentButtons = div.html();
    }

    selector.on('buttonsCreation', 'section', function() {
        changeLabelToTranslateButton();
      });

    selector.on('footerCreation', 'section', function() {
        var div = $('<div>' + UI.footerHTML + '</div>');

        var tab_li_template = MateCat.Templates['review_improved/review_tab'];
        var review_tab_template = MateCat.Templates['review_improved/review_tab_content'];

        div.find('.submenu').append(tab_li_template({
            id : $(this).attr('id')
        }));

        div.append(review_tab_template({
            segment_id : UI.currentSegmentId
        }));

        UI.footerHTML = div.html();

        UI.currentSegment.find('.tab-switcher-review').click();

      });

      selector.on('click', '.editor .tab-switcher-review', function(e) {
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

    selector.on('input', '.editor .editarea', function() {
      trackChanges(this);
    });

    selector.on('afterFormatSelection', '.editor .editarea', function() {
      trackChanges(this);
    });

    selector.on('click', '.editor .outersource .copy', function(e) {
      trackChanges(UI.editarea);
    });

    selector.on('click', 'a.approved', function(e) {

          e.preventDefault();
          UI.tempDisablingReadonlyAlert = true;
          UI.hideEditToolbar();
          UI.currentSegment.removeClass('modified');

          noneSelected = !((UI.currentSegment.find('.sub-editor.review .error-type input[value=1]').is(':checked'))
          ||(UI.currentSegment.find('.sub-editor.review .error-type input[value=2]').is(':checked')));

          if((noneSelected)&&($('.editor .track-changes p span').length)) {
            $('.editor .tab-switcher-review').click();
            $('.sub-editor.review .error-type').addClass('error');
          } else {
            original = UI.currentSegment.find('.original-translation').text();
            $('.sub-editor.review .error-type').removeClass('error');
            //            console.log('a: ', UI.currentSegmentId);
            UI.changeStatus(this, 'approved', 0);
            sid = UI.currentSegmentId;
            err = $('.sub-editor.review .error-type');
            err_typing = $(err).find('input[name=t1]:checked').val();
            err_translation = $(err).find('input[name=t2]:checked').val();
            err_terminology = $(err).find('input[name=t3]:checked').val();
            err_language = $(err).find('input[name=t4]:checked').val();
            err_style = $(err).find('input[name=t5]:checked').val();
            UI.openNextTranslated();
            // temp fix

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

        selector.on('click', '.sub-editor.review .error-type input[type=radio]', function(e) {
          $('.sub-editor.review .error-type').removeClass('error');
        });

        selector.on('setCurrentSegment_success', function(e, d) {
          if(d.original == '') d.original = UI.editarea.text();
          if(!UI.currentSegment.find('.original-translation').length) UI.editarea.after('<div class="original-translation" style="display: none">' + d.original + '</div>');
          UI.setReviewErrorData(d.error_data);
          trackChanges(UI.editarea);
        });

        function trackChanges(editarea) {
            var diff = UI.dmp.diff_main(UI.currentSegment.find('.original-translation').text()
            .replace( config.lfPlaceholderRegex, "\n" )
            .replace( config.crPlaceholderRegex, "\r" )
            .replace( config.crlfPlaceholderRegex, "\r\n" )
            .replace( config.tabPlaceholderRegex, "\t" )
            //.replace( config.tabPlaceholderRegex, String.fromCharCode( parseInt( 0x21e5, 10 ) ) )
            .replace( config.nbspPlaceholderRegex, String.fromCharCode( parseInt( 0xA0, 10 ) ) ),
            $(editarea).text().replace(/(<\s*\/*\s*(g|x|bx|ex|bpt|ept|ph|it|mrk)\s*.*?>)/gi,""));

            UI.dmp.diff_cleanupSemantic( diff ) ;

            diffTxt = '';

            $.each(diff, function (index) {

                if(this[0] == -1) {
                    var rootElem = $( document.createElement( 'div' ) );
                    var newElem = $.parseHTML( '<span class="deleted"/>' );
                    $( newElem ).text( this[1] );
                    rootElem.append( newElem );
                    diffTxt += $( rootElem ).html();
                } else if(this[0] == 1) {
                    var rootElem = $( document.createElement( 'div' ) );
                    var newElem = $.parseHTML( '<span class="added"/>' );
                    $( newElem ).text( this[1] );
                    rootElem.append( newElem );
                    diffTxt += $( rootElem ).html();
                } else {
                    diffTxt += this[1];
                }

                $('.editor .sub-editor.review .track-changes p').html(diffTxt);
            });
        }

        // END behaviours

        $.extend(UI, {
            setRevision: function( data ){
                APP.doRequest({
                  data: data,
                  error: function() {
                    UI.failedConnection( data, 'setRevision' );
                  },
                  success: function(d) {
                    $('#quality-report').attr('data-vote', d.data.overall_quality_class);
                  }
                });
              },
              setReviewErrorData: function (d) {
                $.each(d, function (index) {
                  if(this.type == "Typing") $('.editor .error-type input[name=t1][value=' + this.value + ']').prop('checked', true);
                  if(this.type == "Translation") $('.editor .error-type input[name=t2][value=' + this.value + ']').prop('checked', true);
                  if(this.type == "Terminology") $('.editor .error-type input[name=t3][value=' + this.value + ']').prop('checked', true);
                  if(this.type == "Language Quality") $('.editor .error-type input[name=t4][value=' + this.value + ']').prop('checked', true);
                  if(this.type == "Style") $('.editor .error-type input[name=t5][value=' + this.value + ']').prop('checked', true);

                });

              },
              openNextTranslated: function (sid) {
                console.log('openNextTranslated');
                sid = sid || UI.currentSegmentId;
                el = $('#segment-' + sid);

                var translatedList = [];
                var approvedList = [];

                // find in current UI
                if(el.nextAll('.status-translated, .status-approved').length) { // find in next segments in the current file
                translatedList = el.nextAll('.status-translated');
                approvedList   = el.nextAll('.status-approved');
                console.log('translatedList: ', translatedList);
                console.log('approvedList: ', approvedList);
                if( translatedList.length ) {
                  translatedList.first().find('.editarea').click();
                } else {
                  approvedList.first().find('.editarea').click();
                }

                } else {
                  file = el.parents('article');
                  file.nextAll(':has(section.status-translated), :has(section.status-approved)').each(function () { // find in next segments in the next files

                  var translatedList = $(this).find('.status-translated');
                  var approvedList   = $(this).find('.status-approved');

                  if( translatedList.length ) {
                    translatedList.first().find('.editarea').click();
                  } else {
                    UI.reloadWarning();
                  }

                  return false;

                });

                // else
                //
                if($('section.status-translated, section.status-approved').length) {
                  // find from the beginning of the currently loaded segments

                translatedList = $('section.status-translated');
                approvedList   = $('section.status-approved');

                if( translatedList.length ) {
                  if((translatedList.first().is(UI.currentSegment))) {
                    UI.scrollSegment(translatedList.first());
                  } else {
                    translatedList.first().find('.editarea').click();
                  }
                  } else {
                    if((approvedList.first().is(UI.currentSegment))) {
                      UI.scrollSegment(approvedList.first());
                    } else {
                      approvedList.first().find('.editarea').click();
                    }
                  }

                  } else { // find in not loaded segments
                  APP.doRequest({
                    data: {
                      action: 'getNextReviseSegment',
                      id_job: config.job_id,
                      password: config.password,
                      id_segment: sid
                    },
                    error: function() {
                    },
                    success: function(d) {
                      if( d.nextId == null ) return false;
                      UI.render({
                        firstLoad: false,
                        segmentToOpen: d.nextId
                      });
                    }
                  });
                }
              }
            }

          });
  })(jQuery) ;
}
