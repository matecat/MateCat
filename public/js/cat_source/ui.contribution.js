/*
	Component: ui.contribution
 */
$('html').on('copySourceToTarget', 'section', function() {
    UI.setChosenSuggestion(0);
});

$(document).on('afterFooterCreation', function(e, segment) {
    UI.appendAddTMXButton( segment );
});

$.extend(UI, {
	chooseSuggestion: function(w) {
		var ulDataItem = '.editor .tab.matches ul[data-item=';
		this.copySuggestionInEditarea(this.currentSegment, $(ulDataItem + w + '] li.b .translation').html(),
			$('.editor .editarea'), $(ulDataItem + w + '] ul.graysmall-details .percent').text(), false, false, w);
		this.lockTags(this.editarea);
		this.setChosenSuggestion(w);

		this.editarea.focus();
		this.highlightEditarea();
	},
	copySuggestionInEditarea: function(segment, translation, editarea, match, decode, auto, which) {
		if (typeof (decode) == "undefined") {
			decode = false;
		}
		var percentageClass = this.getPercentuageClass(match);
		if ($.trim(translation) !== '') {

			//ANTONIO 20121205 editarea.text(translation).addClass('fromSuggestion');

			if (decode) {
//				console.log('translation 2: ', translation);
				translation = htmlDecode(translation);
			}
			if (this.body.hasClass('searchActive'))
				this.addWarningToSearchDisplay();

			this.saveInUndoStack('copysuggestion');
//			translation = UI.decodePlaceholdersToText(translation, true);
//			translation = UI.decodePlaceholdersToText(htmlEncode(translation), true);
// console.log('translation 3: ', translation);
			if(!which) translation = UI.encodeSpacesAsPlaceholders(translation, true);
//			translation = UI.encodeSpacesAsPlaceholders(translation);
// console.log('translation 4: ', translation);
			$(editarea).html(translation).addClass('fromSuggestion');
			this.saveInUndoStack('copysuggestion');
			$('.percentuage', segment).text(match).removeClass('per-orange per-green per-blue per-yellow').addClass(percentageClass).addClass('visible');
            $('.repetition', segment).hide();
			if (which)
				this.currentSegment.addClass('modified');
		}

		// a value of 0 for 'which' means the choice has been made by the
		// program and not by the user

		$(window).trigger({
			type: "suggestionChosen",
			segment: UI.currentSegment,
			element: UI.editarea,
			which: which,
			translation: translation
		});
	},
	getContribution: function(segment, next) {
		var n = (next === 0) ? $(segment) : (next == 1) ? $('#segment-' + this.nextSegmentId) : $('#segment-' + this.nextUntranslatedSegmentId);

		if ($(n).hasClass('loaded')) {
            if(segment.find('.footer .matches .overflow').text().length) {
                this.spellCheck();
                if (next) {
                    this.nextIsLoaded = true;
                } else {
                    this.currentIsLoaded = true;
                }
                if (this.currentIsLoaded)
                    this.blockButtons = false;
                if (this.currentSegmentId == this.nextUntranslatedSegmentId)
                    this.blockButtons = false;
                if (!next)
                    this.currentSegmentQA();
                return false;
            }
		}

		if ((!n.length) && (next)) {
			return false;
		}
		var id = n.attr('id');
		var id_segment = id.split('-')[1];

        if( config.brPlaceholdEnabled ) {
            txt = this.postProcessEditarea(n, '.source');
        } else {
            txt = $('.source', n).text();
        }

		txt = view2rawxliff(txt);
		// Attention: As for copysource, what is the correct file format in attributes? I am assuming html encoded and "=>&quot;

		if (!next) {
			$(".loader", n).addClass('loader_on');
		}

        // `next` and `untranslated next` are the same
		if((next == 2)&&(this.nextSegmentId == this.nextUntranslatedSegmentId)) {
			return false;
		}

		APP.doRequest({
			data: {
				action: 'getContribution',
				password: config.password,
				is_concordance: 0,
				id_segment: id_segment,
				text: txt,
				id_job: config.id_job,
				num_results: this.numContributionMatchesResults,
				id_translator: config.id_translator
			},
			context: $('#' + id),
			error: function() {
        console.log('getContribution error');
				UI.failedConnection(0, 'getContribution');
			},
			success: function(d) {
//                console.log('getContribution success');
//				console.log('getContribution from ' + this + ': ', d.data.matches);
				if (d.errors.length)
					UI.processErrors(d.errors, 'getContribution');
				UI.getContribution_success(d, this);
			},
			complete: function() {
//                console.log('getContribution complete');
				UI.getContribution_complete(n);
			}
		});
	},
	getContribution_complete: function(n) {
		$(".loader", n).removeClass('loader_on');
	},
    appendAddTMXButton : function( segment ) {
        $('.footer', segment).append('<div class="addtmx-tr white-tx"><a class="open-popup-addtm-tr">Add your personal TM</a></div>');
    },
    getContribution_success: function(d, segment) {
        this.addInStorage('contribution-' + config.id_job + '-' + UI.getSegmentId(segment), JSON.stringify(d), 'contribution');

        this.appendAddTMXButton( segment );

        this.processContributions(d, segment);
        this.currentSegmentQA();
        console.log('getContribution:complete');
        $(document).trigger('getContribution:complete', segment);
    },
  	processContributions: function(d, segment) {
		if(!d) return true;
		this.renderContributions(d, segment);
		this.lockTags(this.editarea);
		this.spellCheck();
		this.saveInUndoStack();
		this.blockButtons = false;
		if (d.data.matches.length > 0) {
			$('.submenu li.matches a span', segment).text('(' + d.data.matches.length + ')');
		} else {
			$(".sbm > .matches", segment).hide();
		}
		this.renderContributionErrors(d.errors, segment);
    },

  renderContributions: function(d, segment) {
    if(!d) return true;

    var isActiveSegment = $(segment).hasClass('editor');
    var editarea = $('.editarea', segment);

    if ( d.data.matches.length) {
      var editareaLength = editarea.text().trim().length;
      if (isActiveSegment) {
        editarea.removeClass("indent");
      } else {
        if (editareaLength === 0)
          editarea.addClass("indent");
      }
      var translation = d.data.matches[0].translation;
      var perc_t = $(".percentuage", segment).attr("title");

      $(".percentuage", segment).attr("title", '' + perc_t + "Created by " + d.data.matches[0].created_by);
      var match = d.data.matches[0].match;

      var copySuggestionDone = false;
      var segment_id = segment.attr('id');
      $(segment).addClass('loaded');
      $('.sub-editor.matches .overflow', segment).empty();

      $.each(d.data.matches, function(index) {
        if ((this.segment === '') || (this.translation === ''))
          return;
        var disabled = (this.id == '0') ? true : false;
        cb = this.created_by;

        if ("sentence_confidence" in this &&
            (
                this.sentence_confidence !== "" &&
                this.sentence_confidence !== 0 &&
                this.sentence_confidence != "0" &&
                this.sentence_confidence !== null &&
                this.sentence_confidence !== false &&
                typeof this.sentence_confidence != 'undefined'
                )
            ) {
                    suggestion_info = "Quality: <b>" + this.sentence_confidence + "</b>";
                } else if (this.match != 'MT') {
          suggestion_info = this.last_update_date;
        } else {
          suggestion_info = '';
        }
//                console.log('typeof fieldTest: ', typeof d.data.fieldTest);
                if (typeof d.data.fieldTest == 'undefined') {
                    percentClass = UI.getPercentuageClass(this.match);
                    percentText = this.match;
                } else {
                    quality = parseInt(this.quality);
//                    console.log('quality: ', quality);
                    percentClass = (quality > 98)? 'per-green' : (quality == 98)? 'per-red' : 'per-gray';
                    percentText = 'MT';
                }
//				cl_suggestion = UI.getPercentuageClass(this.match);

				if (!$('.sub-editor.matches', segment).length) {
					UI.createFooter(segment);
				}
				// Attention Bug: We are mixing the view mode and the raw data mode.
				// before doing a enanched view you will need to add a data-original tag
                //
                decodedHtml = UI.decodePlaceholdersToText(this.segment, true, segment_id, 'contribution source');


                var toAppend = $('<ul class="suggestion-item graysmall" data-item="' + (index + 1) + '" data-id="' + this.id + '"><li class="sugg-source" >' + ((disabled) ? '' : ' <a id="' + segment_id + '-tm-' + this.id + '-delete" href="#" class="trash" title="delete this row"></a>') + '<span id="' + segment_id + '-tm-' + this.id + '-source" class="suggestion_source">' + decodedHtml + '</span></li><li class="b sugg-target"><!-- span class="switch-editing">Edit</span --><span class="graysmall-message">' + UI.suggestionShortcutLabel + (index + 1) + '</span><span id="' + segment_id + '-tm-' + this.id + '-translation" class="translation">' + UI.decodePlaceholdersToText( this.translation, true, segment_id, 'contribution translation' ) + '</span></li><ul class="graysmall-details"><li class="percent ' + percentClass + '">' + percentText + '</li><li>' + suggestion_info + '</li><li class="graydesc">Source: <span class="bold">' + cb + '</span></li></ul></ul>');

                toAppend.find('li:first').data('original', this.segment);

                $('.sub-editor.matches .overflow', segment).append( toAppend );

//				console.log('dopo: ', $('.sub-editor.matches .overflow .suggestion_source', segment).html());
			});
            // start addtmxTmp
//            $('.sub-editor.matches .overflow', segment).append('<div class="addtmx-tr white-tx"><a class="open-popup-addtm-tr">Add your personal TM</a></div>');
            // end addtmxTmp


			UI.setDeleteSuggestion(segment);
			UI.lockTags();
            UI.setContributionSourceDiff(segment);
			UI.markSuggestionTags(segment);

//            UI.setContributionSourceDiff_Old();
			if (editareaLength === 0) {
//				console.log('translation AA: ', translation);
//				translation = UI.decodePlaceholdersToText(translation, true, segment_id, 'translation');
				translation = $('#' + segment_id + ' .matches ul.graysmall').first().find('.translation').html();
//				console.log($('#' + segment_id + ' .matches .graysmall'));
//				console.log('translation BB: ', translation);
				UI.copySuggestionInEditarea(segment, translation, editarea, match, false, true, 1);
				if (UI.body.hasClass('searchActive'))
					UI.addWarningToSearchDisplay();
				UI.setChosenSuggestion(1);
				copySuggestionDone = true;
			}
//			if (copySuggestionDone) {
//				if (isActiveSegment) {
//				}
//			}

			$('.translated', segment).removeAttr('disabled');
			$('.draft', segment).removeAttr('disabled');
		} else {
			if (UI.debug)
				console.log('no matches');

      $(segment).addClass('loaded');

      if((config.mt_enabled)&&(!config.id_translator)) {
                $('.sub-editor.matches .overflow', segment).append('<ul class="graysmall message"><li>No matches could be found for this segment. Please, contact <a href="mailto:support@matecat.com">support@matecat.com</a> if you think this is an error.</li></ul>');
            } else {
                $('.sub-editor.matches .overflow', segment).append('<ul class="graysmall message"><li>No match found for this segment</li></ul>');
            }
    }
    $(window).trigger('renderContribution:complete', segment);
  },
        renderContributionErrors: function(errors, segment) {
            $('.tab.sub-editor.matches .engine-errors', segment).empty();
            $('.tab.sub-editor.matches .engine-errors', segment).hide();
            $.each(errors, function(){
                var percentClass = "";
                var messageClass = "";
                var imgClass = "";
                var  messageTypeText = '';
                if(this.code == '-2001') {
                    console.log('ERROR -2001');
                    percentClass = "per-red";
                    messageClass = 'error';
                    imgClass = 'error-img';
                    messageTypeText = 'Error: ';
                }
                else if (this.code == '-2002') {
                    console.log('WARNING -2002');
                    percentClass = "per-orange";
                    messageClass = 'warning';
                    imgClass = 'warning-img';
                    messageTypeText = 'Warning: ';
                }
                else {
                    return;
                }
                $('.tab.sub-editor.matches .engine-errors', segment).show();
                var percentText = this.created_by_type;
                var suggestion_info = '';
                var cb = this.created_by;

                $('.tab.sub-editor.matches .engine-errors', segment).append('<ul class="engine-error-item graysmall"><li class="engine-error">' +
                        '<div class="' + imgClass + '"></div><span class="engine-error-message ' + messageClass + '">' + messageTypeText + this.message +
                        '</span></li><ul class="graysmall-details"><li class="percent ' + percentClass + '">' + percentText +
                        '</li><li>' + suggestion_info + '</li><li class="graydesc">Source: <span class="bold">' + cb + '</span></li></ul></ul>');
            });
        },
	setDeleteSuggestion: function(segment) {

        $('.sub-editor .overflow a.trash', segment).click(function(e) {
			e.preventDefault();

            var source, target;

			var ul = $(this).parents('.graysmall');

            if( config.brPlaceholdEnabled ){

                source = $('.sugg-source', ul).data('original');
                source = htmlDecode( source );

                target = UI.postProcessEditarea( ul, '.translation' );
                console.log('source 1: ', source);

            } else {

                source = $('.sugg-source', ul).data('original');
                source = htmlDecode( source );

                target = $('.translation', ul).text();
                console.log('source 2: ', source);
            }

            target = view2rawxliff(target);
            source = view2rawxliff(source);

            ul.remove();
			APP.doRequest({
				data: {
					action: 'deleteContribution',
					source_lang: config.source_lang,
					target_lang: config.target_lang,
					id_job: config.id_job,
					password: config.password,
					seg: source,
					tra: target,
					id_translator: config.id_translator
				},
				error: function() {
					UI.failedConnection(0, 'deleteContribution');
				},
				success: function(d) {
					UI.setDeleteSuggestion_success(d);
				}
			});
		});
	},
	setDeleteSuggestion_success: function(d) {
		if (d.errors.length)
			this.processErrors(d.errors, 'setDeleteSuggestion');
		if (this.debug)
			console.log('match deleted');

		$(".editor .matches .graysmall").each(function(index) {
			$(this).find('.graysmall-message').text(UI.suggestionShortcutLabel + (index + 1));
			$(this).attr('data-item', index + 1);
//			UI.reinitMMShortcuts();
		});
	},
	reinitMMShortcuts: function() {//console.log('reinitMMShortcuts');
		var keys = (this.isMac) ? 'alt+meta' : 'alt+ctrl';
		$('body').unbind('keydown.alt1').unbind('keydown.alt2').unbind('keydown.alt3').unbind('keydown.alt4').unbind('keydown.alt5');
		$("body, .editarea").bind('keydown.alt1', keys + '+1', function(e) {
			e.preventDefault();
			e.stopPropagation();
			UI.chooseSuggestion('1');
		}).bind('keydown.alt2', keys + '+2', function(e) {
			e.preventDefault();
			e.stopPropagation();
			UI.chooseSuggestion('2');
		}).bind('keydown.alt3', keys + '+3', function(e) {
			e.preventDefault();
			e.stopPropagation();
			UI.chooseSuggestion('3');
		}).bind('keydown.alt4', keys + '+4', function(e) {
			e.preventDefault();
			e.stopPropagation();
			UI.chooseSuggestion('4');
		}).bind('keydown.alt5', keys + '+5', function(e) {
			e.preventDefault();
			e.stopPropagation();
			UI.chooseSuggestion('5');
		}).bind('keydown.alt6', keys + '+6', function(e) {
			e.preventDefault();
			e.stopPropagation();
			UI.chooseSuggestion('6');
		});
	},
	setChosenSuggestion: function(w) {
		this.editarea.data('lastChosenSuggestion', w);
	},
    setContributionSourceDiff: function (segment) {
        var sourceText = '';
        var suggestionSourceText = '';
        var html = $(segment).find('.source').html();
        var parsed = $.parseHTML( html ) ;

        if ( parsed == null ) return;

        $.each( parsed, function (index) {
            if(this.nodeName == '#text') {
                sourceText += this.data;
            } else {
                sourceText += this.innerText;
            }
        });

        $(segment).find('.sub-editor.matches ul.suggestion-item').each(function () {
            percent = parseInt($(this).find('.graysmall-details .percent').text().split('%')[0]);
            if(percent > 74) {
                var ss = $(this).find('.suggestion_source');

                suggestionSourceText = '';

                $.each($.parseHTML($(ss).html()), function (index) {

                    if(this.nodeName == '#text') {
                        suggestionSourceText += this.data;
                    } else {
                        suggestionSourceText += this.innerText;
                    }
                });

                $(this).find('.suggestion_source').html(
                    UI.dmp.diff_prettyHtml(
                        UI.execDiff(sourceText, suggestionSourceText)
                    )
                );
            }


        });
    },

    setContributionSourceDiff_Old: function (segment) {
        sourceText = '';
        $.each($.parseHTML($('.editor .source').html()), function (index) {
            if(this.nodeName == '#text') {
                sourceText += this.data;
            } else {
                sourceText += this.innerText;
            }
        });
        console.log('sourceText: ', sourceText);
        UI.currentSegment.find('.sub-editor.matches ul.suggestion-item').each(function () {
            ss = $(this).find('.suggestion_source');

            suggestionSourceText = '';
            $.each($.parseHTML($(ss).html()), function (index) {
                if(this.nodeName == '#text') {
                    suggestionSourceText += this.data;
                } else {
                    suggestionSourceText += this.innerText;
                }
            });
            console.log('suggestionSourceText: ', suggestionSourceText);
            console.log('diff: ', UI.execDiff(sourceText, suggestionSourceText));

//            console.log('a: ', $('.editor .source').html());
//            console.log($.parseHTML($('.editor .source').html()));
//            console.log('b: ', $(ss).html());
//            console.log($(this).find('.graysmall-details .percent').text());

            diff = UI.dmp.diff_main(sourceText, suggestionSourceText);
            diffTxt = '';
            $.each(diff, function (index) {
                if(this[0] == -1) {
                    diffTxt += '<del>' + this[1] + '</del>';
                } else if(this[0] == 1) {
                    diffTxt += '<ins>' + this[1] + '</ins>';
                } else {
                    diffTxt += this[1];
                }
            });
            console.log('diffTxt: ', diffTxt);
            $(ss).html(diffTxt);
            UI.lockTags();


        })


    },

});
