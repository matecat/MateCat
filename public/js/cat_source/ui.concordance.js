/*
	Component: ui.concordance
 */
$.extend(UI, {
	getConcordance: function(txt, in_target) {
		$('.cc-search', UI.currentSegment).addClass('loading');
		$('.sub-editor.concordances .overflow .results', this.currentSegment).empty();
		txt = view2rawxliff(txt);
		APP.doRequest({
			data: {
				action: 'getContribution',
				is_concordance: 1,
				from_target: in_target,
				id_segment: UI.currentSegmentId,
				text: txt,
				id_job: config.job_id,
				num_results: UI.numMatchesResults,
				id_translator: config.id_translator,
				password: config.password
			},
			error: function() {
				UI.failedConnection(this, 'getConcordance');
			},
			success: function(d) {
				UI.renderConcordances(d, in_target);
			}
		});
	},
	openConcordance: function() {
		SegmentActions.activateTab(UI.getSegmentId(UI.currentSegment), 'cc');
		$('.editor .cc-search .input').text('');
		$('.editor .concordances .results').empty();
		var searchField = (this.currentSearchInTarget) ? $('.editor .cc-search .search-target') : $('.editor .cc-search .search-source');
		$(searchField).text(this.currentSelectedText);

		this.getConcordance(this.currentSelectedText, this.currentSearchInTarget);
	},
	preOpenConcordance: function() {
		var selection = window.getSelection();
		if (selection.type == 'Range') { // something is selected
			var isSource = $(selection.baseNode.parentElement).hasClass('source');
			var str = selection.toString().trim();
			if (str.length) { // the trimmed string is not empty
				this.currentSelectedText = str;
				this.currentSearchInTarget = (isSource) ? 0 : 1;
//                this.currentSearchInTarget = ($(this).hasClass('source'))? 0 : 1;
				this.openConcordance();
			}
		}
	},
	setExtendedConcordances: function(extended) {
		if(!extended) {
			$('.sub-editor.concordances').removeClass('extended');
			$('.sub-editor.concordances .overflow').removeAttr('style');	
			if($('.sub-editor.concordances .more').length) {
				$('.sub-editor.concordances .more').text('More');
			} else {
				$('.sub-editor.concordances', UI.currentSegment).append('<br class="clear"><a href="#" class="more">More</a>');
			}
			this.custom.extended_concordance = false;
			this.saveCustomization();
		} else {
			$('.sub-editor.concordances .overflow').css('height', $('.sub-editor.concordances').height() + 'px');
			$('.sub-editor.concordances').addClass('extended');
			if($('.sub-editor.concordances .more').length) {
				$('.sub-editor.concordances .more').text('Fewer');
			} else {
				$('.sub-editor.concordances', UI.currentSegment).append('<a href="#" class="more">Fewer</a>');
			}
			this.custom.extended_concordance = true;
			this.saveCustomization();
		}
	},
	renderConcordances: function(d, in_target) {
		var segment = this.currentSegment;
		var segment_id = this.currentSegmentId;
		$('.sub-editor.concordances .overflow .results', segment).empty();
		$('.sub-editor.concordances .overflow .message', segment).remove();
		if (d.data.matches.length) {
            $.each(d.data.matches, function(index) {
                if ((this.segment === '') || (this.translation === ''))
                    return;
                var prime = (index < UI.numDisplayContributionMatches)? ' prime' : '';

                var disabled = (this.id == '0') ? true : false;
                var cb = this.created_by;
                var cl_suggestion = UI.getPercentuageClass(this.match);

                var leftTxt = (in_target) ? this.translation : this.segment;
                leftTxt = UI.decodePlaceholdersToText( leftTxt );
                leftTxt = leftTxt.replace(/\#\{/gi, "<mark>");
                leftTxt = leftTxt.replace(/\}\#/gi, "</mark>");

                var rightTxt = (in_target) ? this.segment : this.translation;
                rightTxt = UI.decodePlaceholdersToText( rightTxt );
                rightTxt = rightTxt.replace(/\#\{/gi, "<mark>");
                rightTxt = rightTxt.replace(/\}\#/gi, "</mark>");

                $('.sub-editor.concordances .overflow .results', segment).append('<ul class="graysmall' + prime + '" data-item="' + (index + 1) + '" data-id="' + this.id + '"><li class="sugg-source">' + ((disabled) ? '' : ' <a id="' + segment_id + '-tm-' + this.id + '-delete" href="#" class="trash" title="delete this row"></a>') + '<span id="' + segment_id + '-tm-' + this.id + '-source" class="suggestion_source">' + leftTxt + '</span></li><li class="b sugg-target"><!-- span class="switch-editing">Edit</span --><span id="' + segment_id + '-tm-' + this.id + '-translation" class="translation">' + rightTxt + '</span></li><ul class="graysmall-details"><!-- li class="percent ' + cl_suggestion + '">' + (this.match) + '</li --><li>' + this.last_update_date + '</li><li class="graydesc">Source: <span class="bold">' + cb + '</span></li></ul></ul>');
            });
			if(UI.custom.extended_concordance) {
				UI.setExtendedConcordances(true);
			} else {
				UI.setExtendedConcordances(false);
			}
		} else {
			console.log('no matches');
			$('.sub-editor.concordances .overflow', segment).append('<ul class="graysmall message"><li>Can\'t find any matches. Check the language combination.</li></ul>');
		}

		$('.cc-search', this.currentSegment).removeClass('loading');
		// TODO: why this call?
		// this.setDeleteSuggestion(segment);
	}
});


