/*
	Component: ui.contribution
 */
$.extend(UI, {
	chooseSuggestion: function(w) {console.log('chooseSuggestion');
//		console.log($('.editor ul[data-item=' + w + '] li.b .translation'));
		this.copySuggestionInEditarea(this.currentSegment, $('.editor .tab.matches ul[data-item=' + w + '] li.b .translation').text(), $('.editor .editarea'), $('.editor .tab.matches ul[data-item=' + w + '] ul.graysmall-details .percent').text(), false, false, w);
		this.lockTags(this.editarea);
		this.setChosenSuggestion(w);

		this.editarea.focus();
		this.highlightEditarea();
	},
	copySuggestionInEditarea: function(segment, translation, editarea, match, decode, auto, which) {

		if (typeof (decode) == "undefined") {
			decode = false;
		}
		percentageClass = this.getPercentuageClass(match);

		if ($.trim(translation) !== '') {

			//ANTONIO 20121205 editarea.text(translation).addClass('fromSuggestion');

			if (decode) {
				translation = htmlDecode(translation);
			}
			if (this.body.hasClass('searchActive'))
				this.addWarningToSearchDisplay();

			this.saveInUndoStack('copysuggestion');
			$(editarea).text(translation).addClass('fromSuggestion');
			this.saveInUndoStack('copysuggestion');
			$('.percentuage', segment).text(match).removeClass('per-orange per-green per-blue per-yellow').addClass(percentageClass).addClass('visible');
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
	getContribution: function(segment, next) {//console.log('getContribution');
//		console.log('next: ', next);
//		console.log('next: ', next);
//		console.log('getContribution di ', segment);
		var n = (next === 0) ? $(segment) : (next == 1) ? $('#segment-' + this.nextSegmentId) : $('#segment-' + this.nextUntranslatedSegmentId);
//		console.log('n: ', n);
//		console.log('and this is where class loaded is evaluated');
		if ($(n).hasClass('loaded')) {
//			console.log('hasclass loaded');
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

		if ((!n.length) && (next)) {
			return false;
		}
		var id = n.attr('id'); 
		var id_segment = id.split('-')[1];

        if( config.brPlaceholdEnabled ) {
            var txt = this.postProcessEditarea(n, '.source');
        } else {
            var txt = $('.source', n).text();
        }

//		var txt = $('.source', n).text();
		txt = view2rawxliff(txt);
		// Attention: As for copysource, what is the correct file format in attributes? I am assuming html encoded and "=>&quot;
		//txt = txt.replace(/&quot;/g,'"');
		if (!next) {
//				console.log('spinner by getcontribution');
			$(".loader", n).addClass('loader_on');
		}
		if((next == 2)&&(this.nextSegmentId == this.nextUntranslatedSegmentId)) {
//			console.log('il successivo e il successivo non tradotto sono lo stesso');
			return false;
		}
//		console.log('this.nextSegmentId: ', this.nextSegmentId);
//		console.log('this.nextUntranslatedSegmentId: ', this.nextUntranslatedSegmentId);
		APP.doRequest({
			data: {
				action: 'getContribution',
				password: config.password,
				is_concordance: 0,
				id_segment: id_segment,
				text: txt,
				id_job: config.job_id,
				num_results: this.numContributionMatchesResults,
				id_translator: config.id_translator
			},
			context: $('#' + id),
			error: function() {
				UI.failedConnection(0, 'getContribution');
			},
			success: function(d) {
//				console.log(d);
				if (d.error.length)
					UI.processErrors(d.error, 'getContribution');
				UI.getContribution_success(d, this);
			},
			complete: function() {
				UI.getContribution_complete(n);
			}
		});
	},
	getContribution_complete: function(n) {
		$(".loader", n).removeClass('loader_on');
	},
	getContribution_success: function(d, segment) {
//		console.log(d.data.matches);
		localStorage.setItem('contribution-' + config.job_id + '-' + $(segment).attr('id').split('-')[1], JSON.stringify(d));
//		console.log(localStorage.getItem($(segment).attr('id').split('-')[1]));
//		console.log(localStorage.getItem('4679214'));
//		console.log(!localStorage.getItem('4679214'));
//		console.log(localStorage.getItem('4679215'));
		this.processContributions(d, segment);
	},
	processContributions: function(d, segment) {
		this.renderContributions(d, segment);
		if ($(segment).attr('id').split('-')[1] == UI.currentSegmentId)
			this.currentSegmentQA();
		this.lockTags(this.editarea);
		this.spellCheck();

		this.saveInUndoStack();

		this.blockButtons = false;
		if (d.data.matches.length > 0) {
			$('.submenu li.matches a span', segment).text('(' + d.data.matches.length + ')');
		} else {
			$(".sbm > .matches", segment).hide();
		}		
	},
	renderContributions: function(d, segment) {
		var isActiveSegment = $(segment).hasClass('editor');
		var editarea = $('.editarea', segment);



//        console.log(d.data.matches.length);


		if (d.data.matches.length) {
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
			if (editareaLength === 0) {
				UI.copySuggestionInEditarea(segment, translation, editarea, match, true, true, 0);
				if (UI.body.hasClass('searchActive'))
					UI.addWarningToSearchDisplay();
				UI.setChosenSuggestion(1);
				copySuggestionDone = true;
			} else {
			}
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

				cl_suggestion = UI.getPercentuageClass(this.match);

				if (!$('.sub-editor.matches', segment).length) {
					UI.createFooter(segment);
				}
				// Attention Bug: We are mixing the view mode and the raw data mode.
				// before doing a enanched view you will need to add a data-original tag
                escapedSegment = UI.decodePlaceholdersToText(this.segment);
				$('.sub-editor.matches .overflow', segment).append('<ul class="graysmall" data-item="' + (index + 1) + '" data-id="' + this.id + '"><li class="sugg-source">' + ((disabled) ? '' : ' <a id="' + segment_id + '-tm-' + this.id + '-delete" href="#" class="trash" title="delete this row"></a>') + '<span id="' + segment_id + '-tm-' + this.id + '-source" class="suggestion_source">' + escapedSegment + '</span></li><li class="b sugg-target"><!-- span class="switch-editing">Edit</span --><span class="graysmall-message">' + UI.suggestionShortcutLabel + (index + 1) + '</span><span id="' + segment_id + '-tm-' + this.id + '-translation" class="translation">' + UI.decodePlaceholdersToText( this.translation ) + '</span></li><ul class="graysmall-details"><li class="percent ' + cl_suggestion + '">' + (this.match) + '</li><li>' + suggestion_info + '</li><li class="graydesc">Source: <span class="bold">' + cb + '</span></li></ul></ul>');
			});
			UI.markSuggestionTags(segment);
			UI.setDeleteSuggestion(segment);
			UI.lockTags();
//			if (copySuggestionDone) {
//				if (isActiveSegment) {
//				}
//			}

			$('.translated', segment).removeAttr('disabled');
			$('.draft', segment).removeAttr('disabled');
		} else {
			if (UI.debug)
				console.log('no matches');
console.log('add class loaded for segment ' + segment_id+ ' in renderContribution 2')
			$(segment).addClass('loaded');
			$('.sub-editor.matches .overflow', segment).append('<ul class="graysmall message"><li>Sorry. Can\'t help you this time. Check the language pair if you feel this is weird.</li></ul>');
		}
	},
	setContribution: function(segment_id, status, byStatus) {
		segment = $('#segment-' + segment_id);
		if ((status == 'draft') || (status == 'rejected'))
			return false;

        if( config.brPlaceholdEnabled ) {
            source = this.postProcessEditarea(segment, '.source');
            target = this.postProcessEditarea(segment);
        } else {
            source = $('.source', segment).text();
            // Attention: to be modified when we will be able to lock tags.
            target = $('.editarea', segment).text();
        }

		if ((target === '') && (byStatus)) {
			APP.alert({msg: 'Cannot change status on an empty segment. Add a translation first!'});
		}
		if (target === '') {
			return false;
		}
		this.updateContribution(source, target);
	},
	updateContribution: function(source, target) {
		reqArguments = arguments;
		source = view2rawxliff(source);
		target = view2rawxliff(target);
		APP.doRequest({
			data: {
				action: 'setContribution',
				id_job: config.job_id,
				source: source,
				target: target,
				source_lang: config.source_rfc,
				target_lang: config.target_rfc,
				password: config.password,
				id_translator: config.id_translator,
				private_translator: config.private_translator,
				id_customer: config.id_customer,
				private_customer: config.private_customer
			},
			context: reqArguments,
			error: function() {
				UI.failedConnection(this, 'updateContribution');
			},
			success: function(d) {
				if (d.error.length)
					UI.processErrors(d.error, 'setContribution');
			}
		});
	},
	setContributionMT: function(segment_id, status, byStatus) {
		segment = $('#segment-' + segment_id);
		reqArguments = arguments;
		if ((status == 'draft') || (status == 'rejected'))
			return false;
		var source = $('.source', segment).text();
		source = view2rawxliff(source);
		// Attention: to be modified when we will be able to lock tags.
		var target = $('.editarea', segment).text();
		if ((target === '') && (byStatus)) {
			APP.alert({msg: 'Cannot change status on an empty segment. Add a translation first!'});
		}
		if (target === '') {
			return false;
		}
		target = view2rawxliff(target);
//		var languages = $(segment).parents('article').find('.languages');
//		var source_lang = $('.source-lang', languages).text();
//		var target_lang = $('.target-lang', languages).text();
//		var id_translator = config.id_translator;
//		var private_translator = config.private_translator;
//		var id_customer = config.id_customer;
//		var private_customer = config.private_customer;

		var info = $(segment).attr('id').split('-');
		var id_segment = info[1];
		var time_to_edit = UI.editTime;
		var chosen_suggestion = $('.editarea', segment).data('lastChosenSuggestion');

		APP.doRequest({
			data: {
				action: 'setContributionMT',
				id_segment: id_segment,
				source: source,
				target: target,
				source_lang: config.source_lang,
				target_lang: config.target_lang,
				password: config.password,
				time_to_edit: time_to_edit,
				id_job: config.job_id,
				chosen_suggestion_index: chosen_suggestion
			},
			context: reqArguments,
			error: function() {
				UI.failedConnection(this, 'setContributionMT');
			},
			success: function(d) {
				if (d.error.length)
					UI.processErrors(d.error, 'setContributionMT');
			}
		});
	},
	setDeleteSuggestion: function(segment) {
		$('.sub-editor .overflow a.trash', segment).click(function(e) {
			e.preventDefault();
			var ul = $(this).parents('.graysmall');

            if( config.brPlaceholdEnabled ){
                source = UI.postProcessEditarea( ul, '.suggestion_source' );
                target = UI.postProcessEditarea( ul, '.translation' );
            } else {
                source = $('.suggestion_source', ul).text();
                target = $('.translation', ul).text();
            }

            target = view2rawxliff(target);
            source = view2rawxliff(source);
			ul.remove();

			APP.doRequest({
				data: {
					action: 'deleteContribution',
					source_lang: config.source_lang,
					target_lang: config.target_lang,
					id_job: config.job_id,
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
		if (d.error.length)
			this.processErrors(d.error, 'setDeleteSuggestion');
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
});
