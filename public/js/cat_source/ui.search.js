/*
	Component: ui.search
 */
$.extend(UI, {
	applySearch: function(segment) {
		if (this.body.hasClass('searchActive'))
			this.markSearchResults({
				singleSegment: segment,
				where: 'no'
			});
	},
	resetSearch: function() {console.log('reset search');
		this.body.removeClass('searchActive');
		this.clearSearchMarkers();
		this.setFindFunction('find');
		$('#exec-find').removeAttr('disabled');
		this.enableTagMark();
        this.markGlossaryItemsInSource(UI.cachedGlossaryData);
	},
    checkReplaceAvailability: function () {
        if(($('#search-target').val() == '') && ($('#replace-target').val() != '') ) {
            $('#search-target').addClass('warn');
        } else {
            $('#search-target').removeClass('warn');
        }
    },
    execFind: function() {
        this.removeGlossaryMarksFormAllSources();

		this.searchResultsSegments = false;
		$('.search-display').removeClass('displaying');
		$('section.currSearchSegment').removeClass('currSearchSegment');

		if ($('#search-source').val() !== '') {
			this.searchParams.source = $('#search-source').val();
		} else {
			delete this.searchParams.source;
		}

		if ($('#search-target').val() !== '') {
			this.searchParams.target = $('#search-target').val();
		} else {
			delete this.searchParams.target;
		}

		if ($('#select-status').val() !== '') {
			this.searchParams.status = $('#select-status').val() ;
			this.searchParams.status = this.searchParams.status.toLowerCase();

			this.body.attr('data-filter-status', this.searchParams.status);
		} else {
			delete this.searchParams.status;
		}

		if ($('#replace-target').val() !== '') {
			this.searchParams.replace = $('#replace-target').val();
		} else {
			delete this.searchParams.replace;
		}
		this.searchParams['match-case'] = $('#match-case').is(':checked');
		this.searchParams['exact-match'] = $('#exact-match').is(':checked');
		this.searchParams.search = 1;
		if ((typeof this.searchParams.source == 'undefined') && (typeof this.searchParams.target == 'undefined') && (this.searchParams.status == 'all')) {
			APP.alert({msg: 'Enter text in source or target input boxes<br /> or select a status.'});
			return false;
		}
		this.disableTagMark();

		var p = this.searchParams;

		this.searchMode = ((typeof p.source == 'undefined') && (typeof p.target == 'undefined')) ? 'onlyStatus' :
				((typeof p.source != 'undefined') && (typeof p.target != 'undefined')) ? 'source&target' : 'normal';

		var source = (p.source) ? p.source : '';
		var target = (p.target) ? p.target : '';
		var replace = (p.replace) ? p.replace : '';
		this.markSearchResults();
		this.gotoSearchResultAfter({
			el: 'segment-' + this.currentSegmentId
		});
		this.setFindFunction('next');
		this.body.addClass('searchActive');
		//Save the current segment to not lose the translation
		UI.saveSegment(UI.currentSegment);
		var dd = new Date();
		APP.doRequest({
			data: {
				action: 'getSearch',
				function: 'find',
				job: config.job_id,
				token: dd.getTime(),
				password: config.password,
				source: source,
				target: target,
				status: this.searchParams.status,
				matchcase: this.searchParams['match-case'],
				exactmatch: this.searchParams['exact-match'],
				replace: replace
			},
			success: function(d) {
                UI.execFind_success(d);
			}
		});

	},
	execFind_success: function(d) {
        console.log('execFind_success');
		this.numSearchResultsItem = d.total;
		this.searchResultsSegments = d.segments;
		this.numSearchResultsSegments = (d.segments) ? d.segments.length : 0;
		this.updateSearchDisplay();
		if (this.pendingRender) {
			if (this.pendingRender.detectSegmentToScroll) {
				this.pendingRender.segmentToScroll = this.nextUnloadedResultSegment();
			}
			$('#outer').empty();

			this.render(this.pendingRender);
			this.pendingRender = false;
		}
	},
	execReplaceAll: function() {
		$('.search-display .numbers').text('No segments found');
		$('.targetarea mark.searchMarker').remove();
		this.applySearch();

        if ( $('#search-target').val() !== '' ) {
            this.searchParams.target = $('#search-target').val();
        } else {
            APP.alert({msg: 'You must specify the Target value to replace.'});
            delete this.searchParams.target;
            return false;
        }

        if ($('#replace-target').val() !== '') {
            this.searchParams.replace = $('#replace-target').val();
        } else {
            APP.alert({msg: 'You must specify the replacement value.'});
            delete this.searchParams.replace;
            return false;
        }

        if ($('#select-status').val() !== '') {
            this.searchParams.status = $('#select-status').val();
            this.body.attr('data-filter-status', $('#select-status').val());
        } else {
            delete this.searchParams.status;
        }

        this.searchParams['match-case'] = $('#match-case').is(':checked');
        this.searchParams['exact-match'] = $('#exact-match').is(':checked');

        var p = this.searchParams;
		var source = (p.source) ? p.source : '';
		var target = (p.target) ? p.target : '';
		var replace = (p.replace) ? p.replace : '';
		var dd = new Date();

		APP.doRequest({
			data: {
				action: 'getSearch',
				function: 'replaceAll',
				job: config.job_id,
				token: dd.getTime(),
				password: config.password,
				source: source,
				target: target,
				status: p.status,
				matchcase: p['match-case'],
				exactmatch: p['exact-match'],
				replace: replace
			},
			success: function(d) {				
				if(d.errors.length) {
					APP.alert({msg: d.errors[0].message});
					return false;
				}
				$('#outer').empty();
				UI.render({
					firstLoad: false
				});
			}
		});
	},
	checkSearchStrings: function() {
		s = this.searchParams.source;
		if (s.match(/[<\>]/gi)) { // there is a tag in source
			this.disableTagMark();
		} else {
			this.enableTagMark();
		}
	},
	updateSearchDisplay: function() {
		if ((this.searchMode == 'onlyStatus')) {
			res = (this.numSearchResultsSegments) ? this.numSearchResultsSegments : 0;
			resNumString = (res == 1) ? '' : 's';
			numbers = (res) ? 'Found <span class="segments">...</span> segment' + resNumString : 'No segments found';
			$('.search-display .numbers').html(numbers);
		} else if ((this.searchMode == 'source&target')) {
			res = (this.numSearchResultsSegments) ? this.numSearchResultsSegments : 0;
			resNumString = (res == 1) ? '' : 's';
			numbers = (res) ? 'Found <span class="segments">...</span> segment' + resNumString : 'No segments found';
			$('.search-display .numbers').html(numbers);
		} else {
			res = (this.numSearchResultsItem) ? this.numSearchResultsItem : 0;
			resNumString = (res == 1) ? '' : 's';
			numbers = (res) ? 'Found <span class="results">...</span> result' + resNumString + ' in <span class="segments">...</span> segment' + resNumString : 'No segments found';
			$('.search-display .numbers').html(numbers);
			$('.search-display .results').text(res);
		}
		$('.search-display .segments').text(this.numSearchResultsSegments);

		var query = '';
		if (this.searchParams['exact-match'])
			query += ' exactly';
		if (this.searchParams.source)
			query += ' <span class="param">' + htmlEncode(this.searchParams.source) + '</span> in source';
		if (this.searchParams.target)
			query += ' <span class="param">' + htmlEncode(this.searchParams.target) + '</span> in target';

		if (this.searchParams.status)
			query += (((this.searchParams.source) || (this.searchParams.target)) ? ' and' : '') + ' status <span class="param">' + this.searchParams.status + '</span>';
		query += ' (' + ((this.searchParams['match-case']) ? 'case sensitive' : 'case insensitive') + ')';
		$('.search-display .query').html(query);
		$('.search-display').addClass('displaying');
		if ((this.searchMode == 'normal') && (this.numSearchResultsItem < 2)) {
			$('#exec-find[data-func=next]').attr('disabled', 'disabled');
		}
		if ((this.searchMode == 'source&target') && (this.numSearchResultsSegments < 2)) {
			$('#exec-find[data-func=next]').attr('disabled', 'disabled');
		}
		this.updateSearchItemsCount();
		if (this.someSegmentToSave()) {
			this.addWarningToSearchDisplay();
		} else {
			this.removeWarningFromSearchDisplay();
		}
	},
	addWarningToSearchDisplay: function() {
		if (!$('.search-display .found .warning').length)
			$('.search-display .found').append('<span class="warning"></span>');
		$('.search-display .found .warning').text(' (maybe some results in segments modified but not saved)');
	},
	removeWarningFromSearchDisplay: function() {
		$('.search-display .found .warning').remove();
	},
	updateSearchDisplayCount: function(segment) {
		numRes = $('.search-display .numbers .results');
		currRes = parseInt(numRes.text());
		newRes = (currRes == 0)? 0 : currRes - 1;
		numRes.text(newRes);
		if (($('.targetarea mark.searchMarker', segment).length - 1) === 0) {
			numSeg = $('.search-display .numbers .segments');
			currSeg = parseInt(numSeg.text());
			newSeg = (currSeg == 0)? 0 : currSeg - 1;
			numSeg.text(newSeg);
		}
		this.updateSearchItemsCount();
	},
	updateSearchItemsCount: function() {
		c = parseInt($('.search-display .numbers .results').text());
		if (c > 0) {
			$('#filterSwitch .numbererror').text(c).attr('title', $('.search-display .found').text());
		} else {
		}
	},
	execNext: function() {
		this.gotoNextResultItem(false);
	},
	markSearchResults: function(options) { // if where is specified mark only the range of segment before or after seg (no previous clear)
        options = options || {};
		var where = options.where;
		var seg = options.seg;
		var singleSegment = options.singleSegment || false;
		var status, what, q, hasTags;
		if (typeof where == 'undefined') {
			this.clearSearchMarkers();
		}
		var p = this.searchParams;

		var containsFunc = (p['match-case']) ? 'contains' : 'containsNC';
		var ignoreCase = (p['match-case']) ? '' : 'i';

		window.openTagReg = new RegExp(UI.openTagPlaceholder, "g");
		window.closeTagReg = new RegExp(UI.closeTagPlaceholder, "g");

		if (this.searchMode == 'onlyStatus') { // search mode: onlyStatus

		} else if (this.searchMode == 'source&target') { // search mode: source&target
			console.log('source & target');
			status = (p.status == 'all') ? '' : '.status-' + p.status;
			q = (singleSegment) ? '#' + $(singleSegment).attr('id') : "section" + status + ':not(.status-new)';
            var psource = p.source.replace(/(\W)/gi, "\\$1");
            var ptarget = p.target.replace(/(\W)/gi, "\\$1");

			var regSource = new RegExp('(' + htmlEncode(psource).replace(/\(/g, '\\(').replace(/\)/g, '\\)') + ')', "g" + ignoreCase);
			var regTarget = new RegExp('(' + htmlEncode(ptarget).replace(/\(/g, '\\(').replace(/\)/g, '\\)') + ')', "g" + ignoreCase);
			var txtSrc = p.source;
			var txtTrg = p.target;
			var srcHasTags = (txtSrc.match(/<.*?\>/gi) !== null) ? true : false;
			var trgHasTags = (txtTrg.match(/<.*?\>/gi) !== null) ? true : false;

			if (typeof where == 'undefined') {
				UI.doMarkSearchResults(srcHasTags, $(q + " .source:" + containsFunc + "('" + txtSrc + "')"), regSource, q, txtSrc, ignoreCase);
				UI.doMarkSearchResults(trgHasTags, $(q + " .targetarea:" + containsFunc + "('" + txtTrg + "')"), regTarget, q, txtTrg, ignoreCase);

				$('section').has('.source mark.searchPreMarker').has('.targetarea mark.searchPreMarker').find('mark.searchPreMarker').addClass('searchMarker').removeClass('searchPreMarker');

				$('mark.searchPreMarker:not(.searchMarker)').each(function() {
					var a = $(this).html();
					$(this).replaceWith(a);
				});
			} else {
				sid = $(seg).attr('id');
				if (where == 'before') {
					$('section').each(function() {
						if ($(this).attr('id') < sid) {
							$(this).addClass('justAdded');
						}
					});
				} else {
					$('section').each(function() {
						if ($(this).attr('id') > sid) {
							$(this).addClass('justAdded');
						}
					});
				}
				UI.execSearchResultsMarking(UI.filterExactMatch($(q + ".justAdded:not(.status-new) .source:" + containsFunc + "('" + txtSrc + "')"), txtSrc), regSource, false);
				UI.execSearchResultsMarking(UI.filterExactMatch($(q + ".justAdded:not(.status-new) .targetarea:" + containsFunc + "('" + txtTrg + "')"), txtTrg), regTarget, false);

				$('section').has('.source mark.searchPreMarker').has('.targetarea mark.searchPreMarker').find('mark.searchPreMarker').addClass('searchMarker');
				$('mark.searchPreMarker').removeClass('searchPreMarker');
				$('section.justAdded').removeClass('justAdded');
			}
		} else { // search mode: normal
			status = (p.status == 'all') ? '' : '.status-' + p.status;
			var txt = (typeof p.source != 'undefined') ? p.source : (typeof p.target != 'undefined') ? p.target : '';
			if (singleSegment) {
				what = (typeof p.source != 'undefined') ? ' .source' : (typeof p.target != 'undefined') ? ' .targetarea' : '';
				q = '#' + $(singleSegment).attr('id') + what;
			} else {
				what = (typeof p.source != 'undefined') ? ' .source' : (typeof p.target != 'undefined') ? ':not(.status-new) .targetarea' : '';
				q = "section" + status + what;
			}
			hasTags = (txt.match(/<.*?\>/gi) !== null) ? true : false;
            // var regTxt = txt.replace(/</g, UI.openTagPlaceholder).replace(/>/g, UI.closeTagPlaceholder);
            var regTxt = txt.replace(/(\W)/gi, "\\$1");
            regTxt = regTxt.replace(/\(/gi, "\\(").replace(/\)/gi, "\\)");

            var reg = new RegExp('(' + htmlEncode(regTxt).replace(/\(/g, '\\(').replace(/\)/g, '\\)') + ')', "g" + ignoreCase);
            var reg1 = new RegExp('(' + htmlEncode(regTxt).replace(/\(/g, '\\(').replace(/\)/g, '\\)').replace(/\\\\\(/gi, "\(").replace(/\\\\\)/gi, "\)") + ')', "g" + ignoreCase);


			if ((typeof where == 'undefined') || (where == 'no')) {
				UI.doMarkSearchResults(hasTags, $(q + ":" + containsFunc + "('" + txt + "')"), reg1, q, txt, ignoreCase);
			} else {
				sid = $(seg).attr('id');
				if (where == 'before') {
					$('section').each(function() {
						if ($(this).attr('id') < sid) {
							$(this).addClass('justAdded');
						}
					});
				} else {
					$('section').each(function() {
						if ($(this).attr('id') > sid) {
							$(this).addClass('justAdded');
						}
					});
				}
				UI.doMarkSearchResults(hasTags, $("section" + status + ".justAdded" + what + ":" + containsFunc + "('" + txt + "')"), reg, q, txt, ignoreCase);
				$('section.justAdded').removeClass('justAdded');
			}
		}
		if (!singleSegment) {
			UI.unmarkNumItemsInSegments();
			UI.markNumItemsInSegments();
		}
	},
	doMarkSearchResults: function(hasTags, items, regex, q, txt, ignoreCase) {
		if (!hasTags) {
			this.execSearchResultsMarking(UI.filterExactMatch(items, txt), regex, false);
		} else {
			var inputReg = new RegExp(txt, "g" + ignoreCase);
			this.execSearchResultsMarking(items, regex, inputReg);
		}
	},
	execSearchResultsMarking: function(areas, regex, testRegex) {
        var searchMarker = (UI.searchMode == 'source&target')? 'searchPreMarker' : 'searchMarker';
		$(areas).each(function() {

			if (!testRegex || ($(this).text().match(testRegex) !== null)) {
				var tt = $(this).html();
				if (LXQ.cleanUpHighLighting) {
					tt = LXQ.cleanUpHighLighting(tt);
				}
				tt = tt.replace(regex, '<mark class="' + searchMarker + '">$1</mark>');
                 //    .replace(/&lt;/g, UI.openTagPlaceholder)
 				// 	.split(UI.openTagPlaceholder);
                //
				// $.each(tt, function(i, elem){
				// 	elem = elem.replace(/&gt;/g, UI.closeTagPlaceholder)
				// 			.split(UI.closeTagPlaceholder);
				// 	$.each(elem, function(j, text){
				// 		elem[j] = text.replace(regex, '<mark class="' + searchMarker + '">$1</mark>')
				// 	});
				// 	tt[i] = elem.join(UI.closeTagPlaceholder);
				// });
				// tt = tt.join(UI.openTagPlaceholder)
				// 		.replace(window.openTagReg, '&lt;')
				// 		.replace(window.closeTagReg, '&gt;')
                	// 	.replace(/(<span[^>]+>)[^<]*<mark[^>]*>(.*?)<\/mark>[^<]*(<\/span>?)/gi, "$1$3$4");
                $(this).html(tt);
			}
		});
	},
	filterExactMatch: function(items, txt) {
		return (this.searchParams['exact-match']) ? items.filter(function() {
			if (UI.searchParams['match-case']) {
				return $(this).text() == txt;
			} else {
				return $(this).text().toUpperCase() == txt.toUpperCase();
			}
		}) : items;
	},
	clearSearchFields: function() {
		$('.searchbox form')[0].reset();
	},
	clearSearchMarkers: function() {
		$('mark.searchMarker').each(function() {
			$(this).replaceWith($(this).html());
		});
		$('section.currSearchResultSegment').removeClass('currSearchResultSegment');
	},
	gotoNextResultItem: function(unmark) {
		var p = this.searchParams;

		if (this.searchMode == 'onlyStatus') {

			var status = (p.status == 'all') ? '' : '.status-' + p.status;
			var el = $('section.currSearchSegment');

			if (p.status == 'all') {
				// TODO: this case should never heppen since onlyStatus and
				// all combination is denied by UI. Consider removing this block.
				this.scrollSegment( $(el).next() );
			} else {
				if (el.nextAll(status).length) {
					nextToGo = el.nextAll(status).first();
					$(el).removeClass('currSearchSegment');
					nextToGo.addClass('currSearchSegment');
					this.scrollSegment(nextToGo);
				} else {
					// We fit this case if the next batch of segments is to load
					// from the server.
					this.gotoSearchResultAfter({
						el: el.attr('id'),
					});
				}

			}
		} else if (this.searchMode == 'source&target') {
			var m = $(".targetarea mark.currSearchItem");

			if ($(m).nextAll('mark.searchMarker').length) { // there are other subsequent results in the segment
				console.log('altri item nel segmento');
				$(m).removeClass('currSearchItem');
				$(m).nextAll('mark.searchMarker').first().addClass('currSearchItem');
				if (unmark)
					$(m).replaceWith($(m).text());
				UI.goingToNext = false;
			} else { // jump to results in subsequents segments
				console.log('m.length: ' + m.length);
				var seg = (m.length) ? $(m).parents('section') : $('mark.searchMarker').first().parents('section');
				if (seg.length) {
					skipCurrent = $(seg).has("mark.currSearchItem").length;
					this.gotoSearchResultAfter({
						el: 'segment-' + $(seg).attr('id').split('-')[1],
						skipCurrent: skipCurrent,
						unmark: unmark
					});
				} else {
					setTimeout(function() {
						UI.gotoNextResultItem(false);
					}, 500);
				}
			}

		} else {
			var m = $("mark.currSearchItem");

			if ($(m).nextAll('mark.searchMarker').length) { // there are other subsequent results in the segment
				console.log('altri item nel segmento');
				$(m).removeClass('currSearchItem');
				$(m).nextAll('mark.searchMarker').first().addClass('currSearchItem');
				if (unmark)
					$(m).replaceWith($(m).text());
				UI.goingToNext = false;
			} else { // jump to results in subsequents segments
				seg = (m.length) ? $(m).parents('section') : $('mark.searchMarker').first().parents('section');
				if (seg.length) {
					skipCurrent = $(seg).has("mark.currSearchItem").length;
					this.gotoSearchResultAfter({
						el: 'segment-' + $(seg).attr('id').split('-')[1],
						skipCurrent: skipCurrent,
						unmark: unmark
					});
				} else {
					setTimeout(function() {
						UI.gotoNextResultItem(false);
					}, 500);
				}
			}
		}
	},
	gotoSearchResultAfter: function(options) {
		console.log('options: ', options);
		var el = options.el;
		var skipCurrent = (options.skipCurrent || false);
		var unmark = (options.unmark || false);

		var p = this.searchParams;

		if (this.searchMode == 'onlyStatus') { // searchMode: onlyStatus
			var status = (p.status == 'all') ? '' : '.status-' + p.status;

			if (p.status == 'all') {
				this.scrollSegment($('#' + el).next());
			} else {
				if ($('#' + el).nextAll(status).length) { // there is at least one next result loaded after the currently selected
					var nextToGo = $('#' + el).nextAll(status).first();
					nextToGo.addClass('currSearchSegment');
					this.scrollSegment(nextToGo);
				} else {
					// load new segments
					if (!this.searchResultsSegments) {
						this.pendingRender = {
							firstLoad: false,
							applySearch: true,
							detectSegmentToScroll: true
						};
					} else {
						var seg2scroll = this.nextUnloadedResultSegment();
						$('#outer').empty();
						this.render({
							firstLoad: false,
							applySearch: true,
							segmentToScroll: seg2scroll
						});
					}
				}
			}
		} else { // searchMode: source&target or normal
			var wh = (this.searchMode == 'source&target')? ' .targetarea' : '';
			var seg = $('section' + wh).has("mark.searchMarker");
			var ss = (this.searchMode == 'source&target')? el + '-editarea' : el;
			var found = false;

			$.each(seg, function() {
				if ($(this).attr('id') >= ss) {
					if (($(this).attr('id') == ss) && (skipCurrent)) {
					} else {
						found = true;
						$("html,body").animate({
							scrollTop: $(this).offset().top - 200
						}, 500);
						setTimeout(function() {
							UI.goingToNext = false;
						}, 500);
						var m = $("mark.currSearchItem");
						$(m).removeClass('currSearchItem');
						$(this).find('mark.searchMarker').first().addClass('currSearchItem');
						if (unmark)
							$(m).replaceWith($(m).text());
						return false;
					}
				}
			});			
			if (!found) {
				// load new segments
				if (!this.searchResultsSegments) {
					this.pendingRender = {
						firstLoad: false,
						applySearch: true,
						detectSegmentToScroll: true
					};
				} else {
					seg2scroll = this.nextUnloadedResultSegment();
					$('#outer').empty();
					this.render({
						firstLoad: false,
						applySearch: true,
						segmentToScroll: seg2scroll
					});
				}
			}
		}
	},
	checkSearchChanges: function() {
		changes = false;
		var p = this.searchParams;
		if (p.source != $('#search-source').val()) {
			if (!((typeof p.source == 'undefined') && ($('#search-source').val() === '')))
				changes = true;
		}
		if (p.target != $('#search-target').val()) {
			if (!((typeof p.target == 'undefined') && ($('#search-target').val() === '')))
				changes = true;
		}
		if (p.status != $('#select-status').val()) {
			if ((typeof p.status != 'undefined'))
				changes = true;
		}
		if (p['match-case'] != $('#match-case').is(':checked')) {
			changes = true;
		}
		if (p['exact-match'] != $('#exact-match').is(':checked')) {
			changes = true;
		}
		return changes;
	},
	setFindFunction: function(func) {
		var b = $('#exec-find');
		if (func == 'next') {
			b.attr('data-func', 'next').attr('value', 'Next');
		} else {
			b.attr('data-func', 'find').attr('value', 'Find');
		}
		b.removeAttr('disabled');
	},
	unmarkNumItemsInSegments: function() {
		$('section[data-searchItems]').removeAttr("data-searchItems");
	},
	markNumItemsInSegments: function() {
		$('section').has("mark.searchMarker").each(function() {
			$(this).attr('data-searchItems', $('mark.searchMarker', this).length);
		});
	},

    searchIsOpen : function() {
        // TODO: change the name of this, filter is not a good name.
        // This panel opens search actually.
        return $('body').hasClass('filterOpen') ;
    },
    closeSearch : function() {
        $('body').removeClass('filterOpen');
    },
    openSearch : function() {
        $(document).trigger('header-tool:open', { name: 'search' });
        $('body').addClass('filterOpen');
        $('#search-source').focus();
    },
	toggleSearch: function(e) {
		if (!this.searchEnabled) return;
		e.preventDefault();
        if (LXQ.enabled()) LXQ.hidePopUp();
		if ( UI.searchIsOpen() )  {
            UI.closeSearch();
		} else {
            UI.openSearch();
		}
        this.fixHeaderHeightChange();
	},
});

$(document).on('header-tool:open', function(e, data) {
    if ( data.name != 'search' ) {
        UI.closeSearch();
    }
});
