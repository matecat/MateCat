/*
 Component: ui.contribution
 */

if (config.translation_matches_enabled) {

    $('html').on('copySourceToTarget', 'section', function () {
        UI.setChosenSuggestion(0);
    });

$.extend(UI, {
	copySuggestionInEditarea: function(segment, translation, editarea, match, decode, auto, which, createdBy) {
		if (typeof (decode) == "undefined") {
			decode = false;
		}
		var percentageClass = this.getPercentuageClass(match);
		if ($.trim(translation) !== '') {

			//ANTONIO 20121205 editarea.text(translation).addClass('fromSuggestion');

			if (decode) {
				translation = htmlDecode(translation);
			}
			if (this.body.hasClass('searchActive'))
				SearchUtils.addWarningToSearchDisplay();

			this.saveInUndoStack('copysuggestion');

			if(!which) translation = UI.encodeSpacesAsPlaceholders(translation, true);

            // XXX we are modifing the APP state so that MateCat will know that the object is changed
            // in particular this is needed for the Speech2Text to know that the newly added text is coming
            // from a 100% match.
            var segmentObj = MateCat.db.segments.by('sid', UI.getSegmentId( segment ) );
            if ( segmentObj ) {
                segmentObj.suggestion_match = match.replace('%', '');
                MateCat.db.segments.update( segmentObj );
            }

            SegmentActions.replaceEditAreaTextContent(UI.getSegmentId(segment), UI.getSegmentFileId(segment), translation);
            SegmentActions.addClassToEditArea(UI.getSegmentId(segment), UI.getSegmentFileId(segment), 'fromSuggestion');
            SegmentActions.setHeaderPercentage(UI.getSegmentId( segment ), UI.getSegmentFileId(segment), match ,percentageClass, createdBy);

            $(document).trigger('contribution:copied', { translation: translation, segment: segment });

            if (which) {
                SegmentActions.addClassToSegment(UI.getSegmentId( segment ), 'modified');
                segment.data('modified', true);
                segment.trigger('modified');
            }
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
        var txt;
        var current = (next === 0) ? $(segment) : (next == 1) ? $('#segment-' + this.nextSegmentId) : $('#segment-' + this.nextUntranslatedSegmentId);
        try {
            var currentSegment  = new UI.Segment( current );
        } catch (e) {
            console.error("Error, get Contribution");
            return;
        }
        var segmentUnlocked = !!(UI.getFromStorage('unlocked-' + currentSegment.absId));
        if (currentSegment.isReadonly() && !segmentUnlocked) {
            UI.blockButtons = false ;
            SegmentActions.addClassToSegment(UI.getSegmentId(current), 'loaded');
            var deferred = new jQuery.Deferred() ;
            return deferred.resolve();
            return;
        }

        /* If the segment just translated is equal or similar (Levenshtein distance) to the
         * current segment force to reload the matches
        **/
        var s1 = $('#segment-' + this.lastTranslatedSegmentId + ' .source').text();
        var s2 = $('.source', current).text();
        var areSimilar = lev(s1,s2)/Math.max(s1.length,s2.length)*100 < 50;
        var isEqual = (s1 == s2) && s1 !== '';

        var callNewContributions = areSimilar || isEqual;

        if ($(current).hasClass('loaded') && current.find('.footer .matches .overflow').text().length && !callNewContributions) {
            SegmentActions.addClassToSegment(UI.getSegmentId(current), 'loaded');
            $(".loader", current).removeClass('loader_on');
            if (!next) {
                this.blockButtons = false;
                this.segmentQA(segment);
            }
            if (this.currentSegmentId == this.nextUntranslatedSegmentId)
                this.blockButtons = false;
            return $.Deferred().resolve();
		}
        $(".loader", current).addClass('loader_on');
		if ((!current.length) && (next)) {
			return $.Deferred().resolve();
		}

        var id = current.attr('id');
        var id_segment = id.split('-')[1];

        if( config.brPlaceholdEnabled ) {
            txt = this.postProcessEditarea(current, '.source');
        } else {
            txt = $('.source', current).text();
        }

        //If tag projection enabled in the source there are not tags, so I take the data-original value
        if (UI.checkCurrentSegmentTPEnabled(current)) {
            txt = current.find('.source').data('original');
            txt = htmlDecode(txt).replace(/&quot;/g, '\"');
            txt = htmlDecode(txt);
        }

		txt = view2rawxliff(txt);
		// Attention: As for copysource, what is the correct file format in attributes? I am assuming html encoded and "=>&quot;

            if (!next) {
                $(".loader", current).addClass('loader_on');
            }

            // `next` and `untranslated next` are the same
            if ((next == 2) && (this.nextSegmentId == this.nextUntranslatedSegmentId)) {
                return $.Deferred().resolve();
            }

            var contextBefore = UI.getContextBefore(id_segment);
            var contextAfter = UI.getContextAfter(id_segment);

		return APP.doRequest({
			data: {
				action: 'getContribution',
				password: config.password,
				is_concordance: 0,
				id_segment: id_segment,
				text: txt,
				id_job: config.id_job,
				num_results: this.numContributionMatchesResults,
				id_translator: config.id_translator,
                context_before: contextBefore,
                context_after: contextAfter
			},
			context: $('#' + id),
			error: function() {
				UI.failedConnection(0, 'getContribution');
			},
			success: function(d) {
				if (d.errors.length)
					UI.processErrors(d.errors, 'getContribution');
				UI.getContribution_success(d, this);
			},
			complete: function() {
				UI.getContribution_complete(current);
			}
		});
	},
	getContribution_complete: function(n) {
		$(".loader", n).removeClass('loader_on');
	},
    getContribution_success: function(d, segment) {
        this.addInStorage('contribution-' + config.id_job + '-' + UI.getSegmentId(segment), JSON.stringify(d), 'contribution');
        this.processContributions(d, segment);
        this.segmentQA(segment);
    },
  	processContributions: function(d, segment) {
		if(!d) return true;
		this.renderContributions(d, segment);
		this.saveInUndoStack();
		this.blockButtons = false;  //Used for offline mode

        // TODO Move to SegmentFooter Component
		if (d.data.matches && d.data.matches.length > 0) {
			$('.submenu li.tab-switcher-tm a span', segment).text(' (' + d.data.matches.length + ')');
		}
		this.renderContributionErrors(d.errors, segment);
    },

    renderContributions: function(d, segment) {
        if(!d) return true;

        var editarea = $('.editarea', segment);
        if (!$('.sub-editor.matches', segment).length) {
            SegmentActions.createFooter(UI.getSegmentId(segment));
        }
        if ( d.data.hasOwnProperty('matches') && d.data.matches.length) {
            var editareaLength = editarea.text().trim().length;
            var translation = d.data.matches[0].translation;

            var match = d.data.matches[0].match;

            var segment_id = segment.attr('id');
            $('.sub-editor.matches .overflow .graysmall .message', segment).remove();
            $('.tab-switcher-tm .number', segment).text('');
            SegmentActions.setSegmentContributions(UI.getSegmentId(segment), d.data.matches, d.data.fieldTest);

            // UI.setDeleteSuggestion(segment);
            // UI.setContributionSourceDiff(segment);
            //If Tag Projection is enable I take out the tags from the contributions
            // if (!UI.enableTagProjection) {
            //     UI.markSuggestionTags(segment);
            // }
            if (editareaLength === 0) {

                UI.setChosenSuggestion(1, segment);

                translation = $('#' + segment_id + ' .matches ul.graysmall').first().find('.translation').html();
                /*If Tag Projection is enable and the current contribution is 100% match I leave the tags and replace
                 * the source with the text with tags, the segment is tagged
                 */
                if (UI.checkCurrentSegmentTPEnabled(segment)) {
                    var currentContribution = this.getCurrentSegmentContribution(segment);
                    if (parseInt(currentContribution.match) !== 100) {
                        translation = currentContribution.translation;
                        translation = UI.removeAllTags(translation);
                    } else {
                        UI.disableTPOnSegment(segment);
                    }
                }

                var copySuggestion = function() {
                    UI.copySuggestionInEditarea(segment, translation, editarea, match, false, true, 1);
                };
                if ( UI.autoCopySuggestionEnabled() &&
                    ((Speech2Text.enabled() && Speech2Text.isContributionToBeAllowed( match )) || !Speech2Text.enabled() )
                ) {
				    copySuggestion();
                }

                if (UI.body.hasClass('searchActive')) {
                    SearchUtils.addWarningToSearchDisplay();
                }

            }

            $('.translated', segment).removeAttr('disabled');
            $('.draft', segment).removeAttr('disabled');
        } else {
            // TODO Move to SegmentFooter Component
            $('.tab-switcher-tm .number', segment).text('');
            if((config.mt_enabled)&&(!config.id_translator)) {
                $('.sub-editor.matches .overflow', segment).html('<ul class="graysmall message"><li>No matches could be found for this segment. Please, contact <a' +
                    ' href="mailto:support@matecat.com">support@matecat.com</a> if you think this is an error.</li></ul>');
            } else {
                $('.sub-editor.matches .overflow', segment).html('<ul class="graysmall message"><li>No match found for this segment</li></ul>');
            }
        }
        SegmentActions.addClassToSegment(UI.getSegmentId(segment), 'loaded');
    },
    autoCopySuggestionEnabled: function () {
        return true;
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
                '</span></li></ul>');
        });
    },
	setDeleteSuggestion: function(source, target) {

        return APP.doRequest({
            data: {
                action: 'deleteContribution',
                source_lang: config.source_rfc,
                target_lang: config.target_rfc,
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
	},
	setDeleteSuggestion_success: function(d) {
		if (d.errors.length)
			this.processErrors(d.errors, 'setDeleteSuggestion');

		// $(".editor .matches .graysmall").each(function(index) {
		// 	$(this).find('.graysmall-message').text(UI.suggestionShortcutLabel + (index + 1));
		// 	$(this).attr('data-item', index + 1);
		// });
	},
	setChosenSuggestion: function(w, segment) {
        var currentSegment = (segment)? segment : UI.currentSegment;
        currentSegment.find('.editarea').data('lastChosenSuggestion', w);
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
            var percent = parseInt($(this).find('.graysmall-details .percent').text().split('%')[0]);
            if(percent > 74) {
                var ss = $(this).find('.suggestion_source');

                suggestionSourceText = '';

                $.each($.parseHTML($(ss).html()), function (index) {

                        if (this.nodeName == '#text') {
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


    });
}
;
