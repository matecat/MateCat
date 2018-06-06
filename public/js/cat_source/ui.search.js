/*
	Component: ui.search
 */
$.extend(UI, {
    /**
     * ???
     * @param segment
     */
	applySearch: function(segment) {
		if (this.body.hasClass('searchActive'))
			this.markSearchResults({
				singleSegment: segment,
				where: 'no'
			});
	},
    /**
     * Called by the search component to execute search
     * @returns {boolean}
     */
    execFind: function() {
        this.removeGlossaryMarksFormAllSources();

		this.searchResultsSegments = false;
		$('.search-display').removeClass('displaying').removeClass('no-results');
		$('section.currSearchSegment').removeClass('currSearchSegment');

		var $searchSource = $('#search-source');
		if ($searchSource.val() !== '' && $searchSource.val() !== ' ' && $searchSource.val() !== '\'' && $searchSource.val() !== '"' ) {
			this.searchParams.source = $('#search-source').val();
		} else {
			delete this.searchParams.source;
		}
		var $searchTarget = $('#search-target');
		if ($searchTarget.val() !== '' && $searchTarget.val() !== ' ' && $searchTarget.val() !== '\'' && $searchTarget.val() !== '"')  {
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

        this.clearSearchMarkers();
        this.pendingRender = {
            applySearch: true,
            detectSegmentToScroll: true
        };
		this.body.addClass('searchActive');
		//Save the current segment to not lose the translation
		UI.saveSegment(UI.currentSegment);
		var dd = new Date();
		APP.doRequest({
			data: {
				action: 'getSearch',
				function: 'find',
				job: config.id_job,
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
    /**
     * Call in response to request getSearch
     * @param d
     */
	execFind_success: function(d) {
		this.numSearchResultsItem = d.total;
		this.searchResultsSegments = d.segments;
		this.numSearchResultsSegments = (d.segments) ? d.segments.length : 0;
		this.updateSearchDisplay();
		if (this.pendingRender) {
			if (this.pendingRender.detectSegmentToScroll) {
				this.pendingRender.segmentToScroll = this.nextUnloadedResultSegment();
			}

			UI.unmountSegments();

			this.render(this.pendingRender);
			this.pendingRender = false;
		}
	},
    /**
     * Executes the replace all for segments if all the params are ok
     * @returns {boolean}
     */
	execReplaceAll: function() {
		$('.search-display .numbers').text('No segments found');
		$('.targetarea mark.searchMarker').remove();
		this.applySearch();

        var $searchTarget = $('#search-target');
        if ($searchTarget.val() !== '' && $searchTarget.val() !== ' ' && $searchTarget.val() !== '\'' && $searchTarget.val() !== '"')  {
            this.searchParams.target = $('#search-target').val();
        } else {
            APP.alert({msg: 'You must specify the Target value to replace.'});
            delete this.searchParams.target;
            return false;
        }

        var $replaceTarget = $('#replace-target');
        if ($replaceTarget.val() !== '' && $replaceTarget.val() !== ' ' && $replaceTarget.val() !== '\'' && $replaceTarget.val() !== '"')  {
            this.searchParams.replace = $replaceTarget.val();
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
				job: config.id_job,
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
				UI.unmountSegments();
                UI.render({
                    firstLoad: false
                });
			}
		});
	},
    /**
     * Displays the message under the search container that contains the number of results and segments found
     */
	updateSearchDisplay: function() {
		var res,resNumString,numbers;

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
		if (!this.numSearchResultsSegments) {
            $('.search-display').addClass('no-results');
        }
		this.updateSearchItemsCount();
		if (this.someSegmentToSave()) {
			this.addWarningToSearchDisplay();
		} else {
			this.removeWarningFromSearchDisplay();
		}
	},
    /**
     * Displays the warning message that appears when there is an unsaved segment.
     */
	addWarningToSearchDisplay: function() {
		if (!$('.search-display .found .warning').length)
			$('.search-display .found').append('<span class="warning"></span>');
		$('.search-display .found .warning').text(' (maybe some results in segments modified but not saved)');
	},
    /**
     * Removes the warning message that appears when there is an unsaved segment
     */
	removeWarningFromSearchDisplay: function() {
		$('.search-display .found .warning').remove();
	},
    /**
     * Update the results counter in the search container
     */
	updateSearchDisplayCount: function(segment) {
		var numRes = $('.search-display .numbers .results'),
			currRes = parseInt(numRes.text()),
			newRes = (currRes == 0)? 0 : currRes - 1;
			numRes.text(newRes);
		if (($('.targetarea mark.searchMarker', segment).length - 1) <= 0) {
			var numSeg = $('.search-display .numbers .segments'),
			currSeg = parseInt(numSeg.text()),
			newSeg = (currSeg == 0)? 0 : currSeg - 1;
			numSeg.text(newSeg);
		}
		this.updateSearchItemsCount();
	},
    /**
     * Update the results counter in the header container
     */
	updateSearchItemsCount: function() {
		var c = parseInt($('.search-display .numbers .results').text());
		if (c > 0) {
			$('#filterSwitch .numbererror').text(c).attr('title', $('.search-display .found').text());
		} else {
            $('#filterSwitch .numbererror').text('');
		}
	},
    /**
     * Go to next result
     */
	execNext: function() {
		this.gotoNextResultItem(false);
	},
    execPrev: function() {
		this.gotoNextResultItem(false, "prev");
	},
    /**
     * Mark your search results according to the type of search is done
     * @param options
     */
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

		if (this.searchMode == 'onlyStatus') { // search mode: onlyStatus
            seg = options.segmentToScroll;
            if ( seg ) {
                SegmentActions.addClassToSegment(seg, 'currSearchSegment');
            }
		} else if (this.searchMode == 'source&target') { // search mode: source&target
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
			var matchTags = txt.match(/<.*?\>/gi) ;
            hasTags = ( matchTags ) ? true : false;
            var regTxt = txt.replace(/(\W)/gi, "\\$1");
            regTxt = regTxt.replace(/\(/gi, "\\(").replace(/\)/gi, "\\)");

            var reg = new RegExp('(' + htmlEncode(regTxt).replace(/\(/g, '\\(').replace(/\)/g, '\\)') + ')', "g" + ignoreCase);
            var reg1 = new RegExp('(' + htmlEncode(regTxt).replace(/\(/g, '\\(').replace(/\)/g, '\\)').replace(/\\\\\(/gi, "\(").replace(/\\\\\)/gi, "\)") + ')', "g" + ignoreCase );


            if (p['exact-match']) {
                reg = new RegExp('\\b(' + htmlEncode(regTxt).replace(/\(/g, '\\(').replace(/\)/g, '\\)') + ')\\b', "g" + ignoreCase);
                reg1 = new RegExp('\\b(' + htmlEncode(regTxt).replace(/\(/g, '\\(').replace(/\)/g, '\\)').replace(/\\\\\(/gi, "\(").replace(/\\\\\)/gi, "\)") + ')\\b', "g" + ignoreCase );
            }


			// Finding double spaces
            if (txt == "  ") {
                reg1 = new RegExp(/( &nbsp;)/, 'gi');
                reg = new RegExp(/( &nbsp;)/, 'gi');
            }

			if ((typeof where == 'undefined') || (where == 'no')) {
				var elems;
				if (txt == "  ") {
					elems = $(q).filter(function(index){ return $(this).text().indexOf('  ')  });
                    reg1 = new RegExp(/( &nbsp;)/, 'gi');
				} else {
					elems = $(q + ":" + containsFunc + "('" + txt + "')");
				}
				UI.doMarkSearchResults(hasTags, elems, reg1, q, txt, ignoreCase);
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
				UI.doMarkSearchResults(hasTags, $("section" + status + ".justAdded" + what + ":" + containsFunc + "('" + txt + "')"), reg, q, txt, ignoreCase );
				$('section.justAdded').removeClass('justAdded');
			}
		}
	},
    /**
     * Check if a segment has tags and call the fn to mark the results
     * @param hasTags
     * @param items
     * @param regex
     * @param q
     * @param txt
     * @param ignoreCase
     */
	doMarkSearchResults: function(hasTags, items, regex, q, txt, ignoreCase) {
		if (!hasTags) {
			this.execSearchResultsMarking(UI.filterExactMatch(items, txt), regex, false);
		} else {
			var inputReg = new RegExp(txt, "g" + ignoreCase);
			this.execSearchResultsMarking(items, regex, inputReg);
		}
	},
    /**
     * Put the parker in the search results
     * @param areas
     * @param regex
     * @param testRegex
     */
	execSearchResultsMarking: function(areas, regex, testRegex) {
        var searchMarker = (UI.searchMode == 'source&target')? 'searchPreMarker' : 'searchMarker';
		$(areas).each(function() {

			if (!testRegex || ($(this).text().match(testRegex) !== null)) {
				var tt = $(this).html();
				if (LXQ.cleanUpHighLighting) {
					tt = LXQ.cleanUpHighLighting(tt);
				}
				tt = tt.replace(regex, '<mark class="' + searchMarker + '">$1</mark>');
                $(this).html(tt);
			}
		});
	},
    /**
     * Filter items that has an exact match
     * @param items List of jQuery items
     * @param txt The exact match to look for
     * @returns the list of items
     */
	filterExactMatch: function(items, txt) {
	    var searchTxt = txt.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
	    var searchTxtUppercase = txt.toUpperCase().replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
		return (this.searchParams['exact-match']) ? items.filter(function() {
			if (UI.searchParams['match-case']) {
				return $(this).text().match(new RegExp("\\b"+searchTxt+"\\b", "i")) != null;
			} else {
				return $(this).text().toUpperCase().match(new RegExp("\\b"+searchTxtUppercase+"\\b", "i")) != null;
			}
		}) : items;
	},
    /**
     * Remove all the markers
     */
	clearSearchMarkers: function() {
		$('mark.searchMarker').each(function() {
			$(this).replaceWith($(this).html());
		});
		$('section.currSearchSegment').removeClass('currSearchSegment');
	},
    /**
     * If the text (in the editarea target) inside a marker is modified remove the marker
     * and update the counter
     * @param el the segment modified
     */
	rebuildSearchSegmentMarkers: function(el) {
		var querySearchString = this.searchParams.target,
			segment = $(el),
			markers = $(el).find('mark.searchMarker'),
			self = this;

		//check if there are markers inside the segment
		if(markers){
			//cycle markers
			$(markers).each(function() {
				//if the content of marker is different from querySearch
				//remove tag mark and reposition the cursors

				if($(this).text() != querySearchString){
					pasteHtmlAtCaret('<span class="currentCursorPositionForReplace"></span>'); //set a tag where there is the cursors
					$(this).replaceWith($(this).html()); //remove tag mark
					setCursorPosition($('.currentCursorPositionForReplace')[0]); //reposition of the cursors
					$('.currentCursorPositionForReplace').remove(); //remove tag

					self.updateSearchDisplayCount(segment);
				}
			});
		}
	},

    /**
     * Go to next match inside the segment,
     * @param unmark
     * @param type next or prev
     */
	gotoNextResultItem: function(unmark, type) {
		var p = this.searchParams;

        var jQueryFnForNext = "nextAll";
        if (type === "prev") {
            jQueryFnForNext = "prevAll";
        }
		if (this.searchMode == 'onlyStatus') {

			var status = (p.status == 'all') ? '' : '.status-' + p.status;
			var el = $('section.currSearchSegment');

			if (p.status == 'all') {
				// TODO: this case should never heppen since onlyStatus and
				// all combination is denied by UI. Consider removing this block.
				this.scrollSegment( $(el).next() );
			} else {
				if (el[jQueryFnForNext](status).length) {
					nextToGo = el[jQueryFnForNext](status).first();
                    SegmentActions.removeClassToSegment(UI.getSegmentId(el), 'currSearchSegment');
                    SegmentActions.addClassToSegment(UI.getSegmentId(nextToGo), 'currSearchSegment');
					this.scrollSegment(nextToGo);
				} else {
					// We fit this case if the next batch of segments is to load
					// from the server.
					this.gotoSearchResultAfter({
						el: el.attr('id'),
					}, type);
				}

			}
		} else if (this.searchMode == 'source&target') {
			var m = $(".targetarea mark.currSearchItem");

			if ($(m)[jQueryFnForNext]('mark.searchMarker').length) { // there are other subsequent results in the segment

				$(m).removeClass('currSearchItem');
				$(m)[jQueryFnForNext]('mark.searchMarker').first().addClass('currSearchItem');
				if (unmark)
					$(m).replaceWith($(m).text());
				UI.goingToNext = false;
			} else { // jump to results in subsequents segments

				var seg = (m.length) ? $(m).parents('section') : $('mark.searchMarker').first().parents('section');
				if (seg.length) {
					skipCurrent = $(seg).has("mark.currSearchItem").length;
					this.gotoSearchResultAfter({
						el: 'segment-' + $(seg).attr('id').split('-')[1],
						skipCurrent: skipCurrent,
						unmark: unmark
					}, type);
				} else {
					setTimeout(function() {
						UI.gotoNextResultItem(false, type);
					}, 500);
				}
			}

		} else {
			var m = $("mark.currSearchItem");

			if ($(m)[jQueryFnForNext]('mark.searchMarker').length) { // there are other subsequent results in the segment

				$(m).removeClass('currSearchItem');
				$(m)[jQueryFnForNext]('mark.searchMarker').first().addClass('currSearchItem');
				if (unmark)
					$(m).replaceWith($(m).text());
				UI.goingToNext = false;
			} else { // jump to results in subsequents segments
				var seg = (m.length) ? $(m).parents('section') : $('mark.searchMarker').first().parents('section');
				if (seg.length) {
					skipCurrent = $(seg).has("mark.currSearchItem").length;
					this.gotoSearchResultAfter({
						el: 'segment-' + $(seg).attr('id').split('-')[1],
						skipCurrent: skipCurrent,
						unmark: unmark
					}, type);
				} else {
					setTimeout(function() {
						UI.gotoNextResultItem(false, type);
					}, 500);
				}
			}
		}
	},

    /**
     * Go To next Segment
     * @param options
     */
	gotoSearchResultAfter: function(options, type) {

		var el = options.el;
		var $el = $('#' + el);
		var skipCurrent = (options.skipCurrent || false);
		var unmark = (options.unmark || false);

		var p = this.searchParams;
		var jQueryFnForNext = "nextAll";
		if (type === "prev") {
            jQueryFnForNext = "prevAll";
        }

		if (this.searchMode === 'onlyStatus' && p.status !== 'all') { // searchMode: onlyStatus
			var status = '.status-' + p.status;

            if ($el[jQueryFnForNext](status).length) { // there is at least one next result loaded after the currently selected
                var nextToGo = $el[jQueryFnForNext](status).first();
                SegmentActions.addClassToSegment(UI.getSegmentId(nextToGo), 'currSearchSegment');
                this.scrollSegment(nextToGo);
            } else {
                // load new segments
                if (!this.searchResultsSegments) {
                    this.pendingRender = {
                        applySearch: true,
                        detectSegmentToScroll: true
                    };
                } else {
                    var seg2scroll = this.nextUnloadedResultSegment();
                    UI.unmountSegments();
                    this.render({
                        firstLoad: false,
                        applySearch: true,
                        segmentToScroll: seg2scroll
                    });
                }
            }

		} else { // searchMode: source&target or normal
			var wh = (this.searchMode === 'source&target')? ' .targetarea' : '';
			var segmentsWithMarkers = $('section' + wh).has("mark.searchMarker");
			var $currentSegment = (this.searchMode === 'source&target')? $el + '-editarea' : $el.find('.targetarea');
			var found = false;
			var self = this;
			segmentsWithMarkers = (type === "prev" ) ? segmentsWithMarkers.toArray().reverse() : segmentsWithMarkers ;
			$.each(segmentsWithMarkers, function() {
				if ( !( (UI.getSegmentId($(this)) === UI.getSegmentId($currentSegment)) && skipCurrent ) && (
				        (type === "prev" &&  UI.getSegmentId($(this)) <= UI.getSegmentId($currentSegment)) ||
                        (type !== "prev" && UI.getSegmentId($(this)) >= UI.getSegmentId($currentSegment) )
                    )){
						found = true;
                        self.scrollSegment($(this));

						setTimeout(function() {
							UI.goingToNext = false;
						}, 500);

						var mark = $("mark.currSearchItem");
                        $(mark).removeClass('currSearchItem');
						$(this).find('mark.searchMarker').first().addClass('currSearchItem');
						if (unmark) {
                            $(mark).replaceWith($(mark).text());
                        }
						return false;
				}
			});
			if (!found) {
				// load new segments
				if (!this.searchResultsSegments) {
					this.pendingRender = {
						applySearch: true,
						detectSegmentToScroll: true
					};
				} else {
					seg2scroll = this.nextUnloadedResultSegment();
					UI.unmountSegments();
					this.render({
						firstLoad: false,
						applySearch: true,
						segmentToScroll: seg2scroll
					});
				}
			}
		}
	},

    nextUnloadedResultSegment: function() {
        var found = '';
        var last = this.getSegmentId($('section').last());
        $.each(this.searchResultsSegments, function() {
            if ((!$('#segment-' + this).length) && (parseInt(this) > parseInt(last))) {
                found = parseInt(this);
                return false;
            }
        });
        if (found === '') {
            found = this.searchResultsSegments[0];
        }
        return found;
    },

    prevUnloadedResultSegment: function() {
        var found = '';
        var last = this.getSegmentId($('section').last());
        $.each(this.searchResultsSegments, function() {
            if ((!$('#segment-' + this).length) && (parseInt(this) > parseInt(last))) {
                found = parseInt(this);
                return false;
            }
        });
        if (found === '') {
            found = this.searchResultsSegments[0];
        }
        return found;
    },

    /**
     * Close search container
     */
    closeSearch : function() {
		CatToolActions.closeSubHeader();
    },

    /**
     * Toggle the Search container
     * @param e
     */
	toggleSearch: function(e) {
		if (!this.searchEnabled) return;
		e.preventDefault();
		CatToolActions.toggleSearch();
        // this.fixHeaderHeightChange();
	},
});
