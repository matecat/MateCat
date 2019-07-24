/*
 Component: ui.contribution
 */

if (config.translation_matches_enabled) {

    $('html').on('copySourceToTarget', 'section', function () {
        UI.setChosenSuggestion(0);
    });
    $( document ).on( 'sse:contribution', function ( ev, message ) {
        var $segment = UI.getSegmentById(message.data.id_segment);
        var $segmentSplitted = UI.getSegmentById(message.data.id_segment + "-1");
        if ( $segment.length > 0 ) {
            UI.getContribution_success(message.data, $segment);
        } else if ($segmentSplitted.length > 0 ) {
            $('section[id^="segment-' + message.data.id_segment + '"]').each(function (  ) {
                UI.getContribution_success(message.data, $(this));
            });
        }
    } );

    $( document ).on( 'sse:cross_language_matches', function ( ev, message ) {
        var $segment = UI.getSegmentById(message.data.id_segment);
        var $segmentSplitted = UI.getSegmentById(message.data.id_segment + "-1");
        if ( $segment.length > 0 ) {
            SegmentActions.setSegmentCrossLanguageContributions(message.data.id_segment, UI.getSegmentFileId($segment), message.data.matches, []);
        } else if ($segmentSplitted.length > 0 ) {
            $('section[id^="segment-' + message.data.id_segment + '"]').each(function (  ) {
                SegmentActions.setSegmentCrossLanguageContributions(UI.getSegmentId($(this)), UI.getSegmentFileId($(this)), message.data.matches, []);
            });
        }
    } );

$.extend(UI, {
	copySuggestionInEditarea: function(segment, translation, editarea, match, which, createdBy) {

		var percentageClass = this.getPercentuageClass(match);
		if ($.trim(translation) !== '') {

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
                SegmentActions.modifiedTranslation(UI.getSegmentId( segment ),UI.getSegmentFileId(segment),true);
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
	getContribution: function(segmentSid, next) {
        var txt;
        var currentSegment = (next === 0) ? SegmentStore.getSegmentByIdToJS(segmentSid) : (next == 1) ? SegmentStore.getNextSegment(segmentSid) : SegmentStore.getNextSegment(segmentSid, 8);

        if ( !currentSegment) return;

        if (currentSegment.ice_locked === "1" && !currentSegment.unlocked) {
            SegmentActions.addClassToSegment(currentSegment.sid, 'loaded');
            var deferred = new jQuery.Deferred() ;
            return deferred.resolve();
            return;
        }

        /* If the segment just translated is equal or similar (Levenshtein distance) to the
         * current segment force to reload the matches
        **/
        var s1 = $('#segment-' + this.lastTranslatedSegmentId + ' .source').text();
        var s2 = currentSegment.segment;
        var areSimilar = lev(s1,s2)/Math.max(s1.length,s2.length)*100 < 50;
        var isEqual = (s1 == s2) && s1 !== '';

        var callNewContributions = areSimilar || isEqual;

        if (currentSegment.contributions && currentSegment.contributions.matches.length > 0 && !callNewContributions) {
            return $.Deferred().resolve();
		}
		if ((!currentSegment) && (next)) {
			return $.Deferred().resolve();
		}

        var id = currentSegment.original_sid;
        var id_segment_original = id.split('-')[0];

        txt = UI.prepareTextToSend(currentSegment.segment);

		txt = view2rawxliff(txt);
		// Attention: As for copysource, what is the correct file format in attributes? I am assuming html encoded and "=>&quot;

        // `next` and `untranslated next` are the same
        if ((next === 2) && (this.nextSegmentId === this.nextUntranslatedSegmentId)) {
            return $.Deferred().resolve();
        }

        var contextBefore = UI.getContextBefore(id);
        var idBefore = UI.getIdBefore(id);
        var contextAfter = UI.getContextAfter(id);
        var idAfter = UI.getIdAfter(id);

        if ( _.isUndefined(config.id_client) ) {
            setTimeout(() => {
                UI.getContribution(segmentSid, next);
            }, 3000);
            console.log("SSE: ID_CLIENT not found");
            return $.Deferred().resolve();
        }

        //Cross language matches
        if ( UI.crossLanguageSettings ) {
            var crossLangsArray = [UI.crossLanguageSettings.primary, UI.crossLanguageSettings.secondary];
        }

		return APP.doRequest({
			data: {
				action: 'getContribution',
				password: config.password,
				is_concordance: 0,
				id_segment: id_segment_original,
				text: txt,
				id_job: config.id_job,
				num_results: this.numContributionMatchesResults,
				id_translator: config.id_translator,
                context_before: contextBefore,
                id_before: idBefore,
                context_after: contextAfter,
                id_after: idAfter,
                id_client: config.id_client,
                cross_language: crossLangsArray
			},
			context: $('#segment-' + id),
			error: function() {
				UI.failedConnection(0, 'getContribution');
				UI.showContributionError(this);
			},
			success: function(d) {
				if (d.errors.length) {
                    UI.processErrors(d.errors, 'getContribution');
                    UI.renderContributionErrors(d.errors, this);
                }
			}
		});
	},
    getContribution_success: function(data, segment) {
        this.addInStorage('contribution-' + config.id_job + '-' + UI.getSegmentId(segment), JSON.stringify(data), 'contribution');
        this.processContributions(data, segment);
        // if (UI.currentSegmentId === UI.getSegmentId(segment))  {
        //     this.segmentQA(segment);
        // }
    },
  	processContributions: function(data, segment) {
		if(!data) return true;
		this.renderContributions(data, segment);
		this.saveInUndoStack();
    },

    renderContributions: function(data, segment) {
        if(!data) return true;

        var editarea = $('.editarea', segment);
        SegmentActions.setSegmentContributions(UI.getSegmentId(segment), UI.getSegmentFileId(segment), data.matches, data.errors);
        var segmentObj = SegmentStore.getSegmentByIdToJS(UI.getSegmentId(segment));

        if ( data.matches && data.matches.length > 0 && _.isUndefined(data.matches[0].error)) {
            var editareaLength = segmentObj.translation.length;
            var translation = data.matches[0].translation;

            var match = data.matches[0].match;

            var segment_id = segmentObj.sid;

            if (editareaLength === 0) {

                UI.setChosenSuggestion(1, segmentObj);


                // translation = $('#' + segment_id + ' .matches ul.graysmall').first().find('.translation').html();

                /*If Tag Projection is enable and the current contribution is 100% match I leave the tags and replace
                 * the source with the text with tags, the segment is tagged
                 */
                if (UI.checkCurrentSegmentTPEnabled(segment)) {
                    var currentContribution = this.getCurrentSegmentContribution(segmentObj);
                    if (parseInt(currentContribution.match) !== 100) {
                        translation = currentContribution.translation;
                        translation = UI.removeAllTags(translation);
                    } else {
                        UI.disableTPOnSegment(segment);
                    }
                }

                var copySuggestion = function() {
                    UI.copySuggestionInEditarea(segment, translation, editarea, match, 1);
                };
                if ( UI.autoCopySuggestionEnabled() &&
                    ((Speech2Text.enabled() && Speech2Text.isContributionToBeAllowed( match )) || !Speech2Text.enabled() )
                ) {
				    copySuggestion();
                }

            }

            $('.translated', segment).removeAttr('disabled');
            $('.draft', segment).removeAttr('disabled');
        }

        SegmentActions.addClassToSegment(segment_id, 'loaded');
    },
    showContributionError: function(segment) {
        SegmentActions.setSegmentContributions(UI.getSegmentId(segment), UI.getSegmentFileId(segment), [], [{}]);

    },
    autoCopySuggestionEnabled: function () {
        return true;
    },
    renderContributionErrors: function(errors, segment) {
        SegmentActions.setSegmentContributions(UI.getSegmentId(segment), UI.getSegmentFileId(segment), [], errors);
    },
	setDeleteSuggestion: function(source, target, id, sid) {

        return APP.doRequest({
            data: {
                action: 'deleteContribution',
                source_lang: config.source_rfc,
                target_lang: config.target_rfc,
                id_job: config.id_job,
                password: config.password,
                seg: source,
                tra: target,
                id_translator: config.id_translator,
                id_match: id
            },
            error: function() {
                UI.failedConnection(0, 'deleteContribution');
            },
            success: function(d) {
                UI.setDeleteSuggestion_success(d);
            }
        });
	},
	setDeleteSuggestion_success: function(d, idMatch, sid) {
		if (d.errors.length)
			this.processErrors(d.errors, 'setDeleteSuggestion');
	},
	setChosenSuggestion: function(index, segmentObj) {
        var currentSegmentId = (segmentObj)? segmentObj.sid : UI.currentSegmentId;
        SegmentActions.setChoosenSuggestion(currentSegmentId, index);
	}

    });
}
