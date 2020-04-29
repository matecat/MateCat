import SegmentActions  from "../../../../actions/SegmentActions";
// import CatToolActions  from "../../../../actions/CatToolActions";
import SegmentStore  from "../../../../stores/SegmentStore";
import TextUtils from "../../../../utils/textUtils";

let SearchUtils = {

    searchEnabled: true,
    searchParams: {
        search: 0
    },
    searchResultsSegments: false,
    /**
     * Called by the search component to execute search
     * @returns {boolean}
     */
    execFind: function(params) {

		this.searchResultsSegments = false;
		$('section.currSearchSegment').removeClass('currSearchSegment');

		let searchSource = params.searchSource;
		if (searchSource !== '' && searchSource !== ' ') {
			this.searchParams.source = searchSource;
		} else {
			delete this.searchParams.source;
		}
		let searchTarget = params.searchTarget;
		if (searchTarget !== '' && searchTarget !== ' ')  {
			this.searchParams.target = searchTarget;
		} else {
			delete this.searchParams.target;
		}

		let selectStatus = params.selectStatus;
		if (selectStatus !== '') {
			this.searchParams.status = selectStatus ;
			this.searchParams.status = this.searchParams.status.toLowerCase();

			UI.body.attr('data-filter-status', this.searchParams.status);
		} else {
			delete this.searchParams.status;
		}

		let replaceTarget = params.replaceTarget;
		if (replaceTarget !== '') {
			this.searchParams.replace = replaceTarget;
		} else {
			delete this.searchParams.replace;
		}
		this.searchParams['match-case'] = params.matchCase;
		this.searchParams['exact-match'] = params.exactMatch;
		this.searchParams.search = 1;
		if (_.isUndefined(this.searchParams.source) && _.isUndefined(this.searchParams.target) && (this.searchParams.status == 'all')) {
			APP.alert({msg: 'Enter text in source or target input boxes<br /> or select a status.'});
			return false;
		}
		SegmentActions.disableTagLock();

		let p = this.searchParams;

		this.searchMode = (!_.isUndefined(p.source) && !_.isUndefined(p.target)) ? 'source&target' : 'normal';
		this.whereToFind = "";
		if ( this.searchMode === 'normal') {
		    if (!_.isUndefined(p.target)) {
                this.whereToFind = ".targetarea";
            } else if (!_.isUndefined(p.source)) {
                this.whereToFind = ".source";
            }
        }

        this.searchParams.searchMode = this.searchMode;

		let source = (p.source) ? TextUtils.htmlEncode(p.source) : '';
		let target = (p.target) ? TextUtils.htmlEncode(p.target) : '';
		let replace = (p.replace) ? p.replace : '';

		// this.clearSearchMarkers();
		this.pendingRender = {
			// applySearch: true,
			detectSegmentToScroll: true
		};
		UI.body.addClass('searchActive');
		let makeSearchFn = () => {
            let dd = new Date();
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
                    replace: replace,
                    revision_number: config.revisionNumber
                },
                success: function(d) {
                    SearchUtils.execFind_success(d);
                }
            });
        };
		//Save the current segment to not lose the translation
        try {
            let segment = SegmentStore.getSegmentByIdToJS(UI.currentSegmentId);
            if ( UI.translationIsToSaveBeforeClose( segment ) ) {
                UI.saveSegment(UI.currentSegment).then(() => {
                    makeSearchFn()
                });
            } else  {
                makeSearchFn();
            }
        } catch (e) {
            makeSearchFn();
        }


	},
    /**
     * Call in response to request getSearch
     * @param response
     */
    execFind_success: function(response) {
		this.numSearchResultsItem = response.total;
		this.searchResultsSegments = response.segments;
		this.numSearchResultsSegments = (response.segments) ? response.segments.length : 0;
        CatToolActions.setSearchResults(response);
		this.updateSearchDisplay();
        if ( response.segments.length > 0) {
            this.searchParams.current = response.segments[0];
            this.searchParams.indexInSegment = 0;
            SegmentActions.addSearchResultToSegments(response.segments, this.searchParams);
            if (this.pendingRender) {
                if (this.pendingRender.detectSegmentToScroll) {
                    this.pendingRender.segmentToOpen = this.nextResultSegment();
                }
                let segment = SegmentStore.getSegmentByIdToJS(this.pendingRender.segmentToOpen);
                if (segment) {
                    SegmentActions.openSegment(segment.sid);
                } else {
                    UI.unmountSegments();
                    UI.render(this.pendingRender);
                }
                this.pendingRender = false;
            }
        }
	},
    /**
     * Displays the message under the search container that contains the number of results and segments found
     */
    updateSearchDisplay: function() {
        this.updateSearchItemsCount();
    },
    /**
     * Update the results counter in the search container
     */
    updateSearchDisplayCount: function(segment) {
        let numRes = $('.search-display .numbers .results'),
            currRes = parseInt(numRes.text()),
            newRes = (currRes == 0)? 0 : currRes - 1;
        numRes.text(" " + newRes);
        if (($('.targetarea mark.searchMarker', segment).length - 1) <= 0) {
            let numSeg = $('.search-display .numbers .segments'),
                currSeg = parseInt(numSeg.text()),
                newSeg = (currSeg == 0)? 0 : currSeg - 1;
            numSeg.text(" " + newSeg);
        }
        this.updateSearchItemsCount();
    },
    /**
     * Toggle the Search container
     * @param e
     */
	toggleSearch: function(e) {
		if (!this.searchEnabled) return;
        if (UI.body.hasClass('searchActive')) {
            CatToolActions.closeSearch();
        } else {
            e.preventDefault();
            CatToolActions.toggleSearch();
            // this.fixHeaderHeightChange();
        }
	},
    /**
     * Executes the replace all for segments if all the params are ok
     * @returns {boolean}
     */
    execReplaceAll: function(params) {
        // $('.search-display .numbers').text('No segments found');
        // this.applySearch();

        let searchSource = params.searchSource;
        if (searchSource !== '' && searchSource !== ' ' && searchSource !== '\'' && searchSource !== '"' ) {
            this.searchParams.source = searchSource;
        } else {
            delete this.searchParams.source;
        }

        let searchTarget = params.searchTarget;
        if (searchTarget !== '' && searchTarget !== ' '  && searchTarget !== '"')  {
            this.searchParams.target = searchTarget;
        } else {
            APP.alert({msg: 'You must specify the Target value to replace.'});
            delete this.searchParams.target;
            return false;
        }

        let replaceTarget =  params.replaceTarget;
        if ( replaceTarget !== '"')  {
            this.searchParams.replace = replaceTarget;
        } else {
            APP.alert({msg: 'You must specify the replacement value.'});
            delete this.searchParams.replace;
            return false;
        }

        if (params.selectStatus !== '' && params.selectStatus !== 'all') {
            this.searchParams.status = params.selectStatus;
            UI.body.attr('data-filter-status', params.selectStatus);
        } else {
            delete this.searchParams.status;
        }

        this.searchParams['match-case'] = params.matchCase;
        this.searchParams['exact-match'] = params.exactMatch;

        let p = this.searchParams;
        let source = (p.source) ? TextUtils.htmlEncode(p.source) : '';
        let target = (p.target) ? TextUtils.htmlEncode(p.target) : '';
        let replace = (p.replace) ? p.replace : '';
        let dd = new Date();

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
                replace: replace,
                revision_number: config.revisionNumber
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
     * Removes the warning message that appears when there is an unsaved segment
     */
    removeWarningFromSearchDisplay: function() {
        $('.search-display .found .warning').remove();
    },
    /**
     * Update the results counter in the header container
     */
    updateSearchItemsCount: function() {
        let c = parseInt($('.search-display .numbers .results').text());
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
    markText: function(text, params, isSource, sid) {
	    let reg;
        var LTPLACEHOLDER = "##LESSTHAN##";
        var GTPLACEHOLDER = "##GREATERTHAN##";
        let searchMarker = 'searchMarker';
        let ignoreCase = (params['match-case']) ? '' : 'i';
        if ( this.searchMode === 'source&target' ) {
            let txt = (isSource) ? params.source : params.target;
            txt = txt.replace(/(\W)/gi, "\\$1");
            reg = new RegExp('(' + TextUtils.htmlEncode(txt).replace(/\(/g, '\\(').replace(/\)/g, '\\)') + ')', "g" + ignoreCase);

        } else if ( params.source || params.target ) {
	        let txt = params.source ? params.source : params.target ;

            let regTxt = txt.replace(/(\W)/gi, "\\$1");
            // regTxt = regTxt.replace(/\(/gi, "\\(").replace(/\)/gi, "\\)");

            reg = new RegExp('(' + TextUtils.htmlEncode(regTxt)+ ')', "g" + ignoreCase);

            if (params['exact-match'] ) {
                reg = new RegExp('\\b(' + TextUtils.htmlEncode(regTxt).replace(/\(/g, '\\(').replace(/\)/g, '\\)') + ')\\b', "g" + ignoreCase);
            }

            // Finding double spaces
            if (txt === "  ") {
                reg = new RegExp(/(&nbsp; )/, 'gi');
            }
        }
        let spanArray = [];
        text = text.replace(/>/g, GTPLACEHOLDER).replace(/</g, LTPLACEHOLDER);
        text = text.replace(/\&gt;/g, '>').replace(/\&lt;/g, '<');
        text = text.replace(/(<[/]*(span|mark|a).*?>)/g, function ( match, text ) {
            spanArray.push(text);
            return "$&";
        });
        let matchIndex = 0;
        text = text.replace(reg, function ( match, text, index ) {
            if ( params.current === sid && matchIndex === params.indexInSegment &&
                ( (!isSource && params.target) || (isSource && !params.target))
            ) {
                matchIndex++;
                return '##LESSTHAN##mark class="' + searchMarker + ' currSearchItem"##GREATERTHAN##' + match + '##LESSTHAN##/mark##GREATERTHAN##';
            }
            matchIndex++;
            return '##LESSTHAN##mark class="' + searchMarker + '"##GREATERTHAN##' + match + '##LESSTHAN##/mark##GREATERTHAN##';
        });
        text = text.replace(/(\$&)/g, function ( match, text ) {
            return spanArray.shift();
        });
        text = text.replace(/>/g, '&gt;').replace(/</g, '&lt;');
        text = text.replace(/##GREATERTHAN##/g, '>').replace(/##LESSTHAN##/g, '<');
        return text;
    },
    /**
     * Go to next match inside the segment,
     * @param unmark
     * @param type next or prev
     */
    gotoNextResultItem: function(unmark, type) {

        let $current = ( $("mark.currSearchItem").length > 0) ? $("mark.currSearchItem") : $($('section.opened .searchMarker').get(0));
        let currentSegmentFind = $current.closest("div");
        let marksArray = currentSegmentFind.find("mark.searchMarker").toArray();
        let currentMarkIndex = marksArray.indexOf($current[0]);
        let nextIndex = (type === "prev") ? currentMarkIndex - 1 : currentMarkIndex + 1;
        if ( $current && marksArray.length > 1 && !_.isUndefined( marksArray[nextIndex] ) ) {
            this.searchParams.indexInSegment = nextIndex;
            SegmentActions.addSearchResultToSegments(this.searchResultsSegments, this.searchParams);
        } else { // jump to results in subsequents segments
            let $currentSegment = $current.length ? $current.parents('section') : UI.currentSegment;
            if ($currentSegment.length) {
                this.gotoSearchResultAfter(UI.getSegmentId($currentSegment), type);
            }
        }
        UI.goingToNext = false;

    },
    /**
     * Go To next Segment
     */
    gotoSearchResultAfter: function(sid, type) {

        // searchMode: source&target or normal
        let seg2scroll = (type === 'prev' ) ? this.prevResultSegment(sid): this.nextResultSegment(sid);
        this.searchParams.current = seg2scroll;
        this.searchParams.indexInSegment = 0;
        SegmentActions.addSearchResultToSegments(this.searchResultsSegments, this.searchParams);
        SegmentActions.scrollToSegment(seg2scroll);

    },
    nextResultSegment: function( sid) {
        let found ;
        if ( sid ) {
            let index = this.searchResultsSegments.indexOf(sid);
            found = (index > -1 && index+1 <= this.searchResultsSegments.length -1) ? this.searchResultsSegments[index+1] : null ;
        }
        if (!found) {
            found = this.searchResultsSegments[0];
        }
        return found;
    },
    prevResultSegment: function( sid) {
        let found ;
        if ( sid ) {
            let index = this.searchResultsSegments.indexOf(sid);
            found = (index > -1 && index-1 !== -1) ? this.searchResultsSegments[index-1] : null ;
        }
        if (!found) {
            found = this.searchResultsSegments[this.searchResultsSegments.length -1 ];
        }
        return found;
    },
    /**
     * Close search container
     */
    closeSearch : function() {
        CatToolActions.closeSubHeader();
        SegmentActions.removeSearchResultToSegments();
    },
};

module.exports = SearchUtils;

