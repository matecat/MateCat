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

    total: 0,
    searchResults: [],
    occurrencesList: [],
    searchResultsDictionary: {},
    featuredSearchResult: 0,

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
        this.resetSearch();
		this.numSearchResultsItem = response.total;
		this.searchResultsSegments = response.segments;
		this.numSearchResultsSegments = (response.segments) ? response.segments.length : 0;

        let {searchResults, occurrencesList, searchResultsDictionary } = this;
        if ( response.segments.length > 0) {
            let searchProgressiveIndex = 0;
            const elements = response.segments;
            searchResults = elements.map( (sid) => {
                let ignoreCase = (this.searchParams['match-case']) ? '' : 'i';
                let segment = SegmentStore.getSegmentByIdToJS(sid);
                let item = {id: sid, occurrences: []};
                if (segment) {
                    if ( this.searchParams.searchMode === 'source&target' ) {
                        let matches;
                        let textSource = this.searchParams.source.replace( /(\W)/gi, "\\$1" );
                        let regSource = new RegExp( '(' + textSource + ')', "g" + ignoreCase );
                        let textTarget = this.searchParams.target.replace( /(\W)/gi, "\\$1" );
                        let regTarget = new RegExp( '(' + textTarget + ')', "g" + ignoreCase );
                        if ( this.searchParams['exact-match'] ) {
                            regSource = new RegExp( '\\b(' + textSource.replace( /\(/g, '\\(' ).replace( /\)/g, '\\)' ) + ')\\b', "g" + ignoreCase );
                            regTarget = new RegExp( '\\b(' + textTarget.replace( /\(/g, '\\(' ).replace( /\)/g, '\\)' ) + ')\\b', "g" + ignoreCase );
                        }
                        const matchesSource = segment.segment.matchAll( regSource );
                        const matchesTarget = segment.translation.matchAll( regTarget );
                        let sourcesMatches = [], targetMatches = [];
                        for ( const match of matchesSource ) {
                            sourcesMatches.push( match );
                            console.log( `Found ${match[0]} start=${match.index} end=${match.index + match[0].length}.` );
                        }
                        for ( const match of matchesTarget ) {
                            targetMatches.push( match );
                            console.log( `Found ${match[0]} start=${match.index} end=${match.index + match[0].length}.` );
                        }
                        //Check if source and target has the same occurrences
                        matches = (sourcesMatches.length > targetMatches.length) ? targetMatches : sourcesMatches;


                        for ( const match of matches ) {
                            occurrencesList.push( sid );
                            item.occurrences.push( {matchPosition: match.index, searchProgressiveIndex: searchProgressiveIndex} );
                            searchProgressiveIndex++;
                        }

                    } else {
                        if ( this.searchParams.source ) {
                            let textSource = this.searchParams.source.replace( /(\W)/gi, "\\$1" );
                            let regSource = new RegExp( '(' + textSource + ')', "g" + ignoreCase );
                            if ( this.searchParams['exact-match'] ) {
                                regSource = new RegExp( '\\b(' + textSource.replace( /\(/g, '\\(' ).replace( /\)/g, '\\)' ) + ')\\b', "g" + ignoreCase );
                            }
                            const matchesSource = segment.segment.matchAll( regSource );
                            for ( const match of matchesSource ) {
                                occurrencesList.push( sid );
                                item.occurrences.push( {matchPosition: match.index, searchProgressiveIndex: searchProgressiveIndex} );
                                searchProgressiveIndex++;

                            }
                        } else if ( this.searchParams.target ) {
                            let textTarget = this.searchParams.target.replace( /(\W)/gi, "\\$1" );
                            let regTarget = new RegExp( '(' + textTarget + ')', "g" + ignoreCase );
                            if ( this.searchParams['exact-match'] ) {
                                regTarget = new RegExp( '\\b(' + textTarget.replace( /\(/g, '\\(' ).replace( /\)/g, '\\)' ) + ')\\b', "g" + ignoreCase );
                            }
                            const matchesTarget = segment.translation.matchAll( regTarget );
                            for ( const match of matchesTarget ) {
                                occurrencesList.push( sid );
                                item.occurrences.push( {matchPosition: match.index, searchProgressiveIndex: searchProgressiveIndex} );
                                searchProgressiveIndex++;

                            }
                        }
                    }

                } else {
                    searchProgressiveIndex++;
                    occurrencesList.push(sid);
                }
                searchResultsDictionary[sid] = item;
                return item;
            });

            console.log("SearchResults", searchResults);
            console.log("occurrencesList", occurrencesList);
            console.log("searchResultsDictionary", searchResultsDictionary);
            console.log("searchProgressiveIndex", searchProgressiveIndex);

            this.searchParams.current = occurrencesList[0];
            CatToolActions.storeSearchResults({
                total: response.total,
                searchResults: searchResults,
                occurrencesList: occurrencesList,
                searchResultsDictionary: searchResultsDictionary,
                featuredSearchResult: 0,
            });
            SegmentActions.addSearchResultToSegments(occurrencesList, searchResultsDictionary ,0);
        } else {
            SegmentActions.removeSearchResultToSegments();
            this.resetSearch();
            CatToolActions.storeSearchResults({
                total: 0,
                searchResults: [],
                occurrencesList: [],
                searchResultsDictionary: {},
                featuredSearchResult: 0,
            });
        }
	},
    /**
     * Update the results counter in the search container
     */
    updateSearchDisplayCount: function(segment) {

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

    updateFeaturedResult(value) {
        this.featuredSearchResult = value;
    },

    markText: function(text, isSource, sid) {

        if ( this.occurrencesList.indexOf(sid) === -1 ) return text;
	    let reg;
	    const isCurrent = ( this.occurrencesList[this.featuredSearchResult] === sid );
	    const occurrences = this.searchResultsDictionary[sid].occurrences;
	    let params = this.searchParams;
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
        text = text.replace(reg,  ( match, text, index ) => {
            let className = (isCurrent && occurrences[matchIndex] && occurrences[matchIndex].searchProgressiveIndex === this.featuredSearchResult) ? searchMarker + " currSearchItem" : searchMarker;
            matchIndex++;
            return '##LESSTHAN##mark class="' + className + '"##GREATERTHAN##' + match + '##LESSTHAN##/mark##GREATERTHAN##';
        });
        text = text.replace(/(\$&)/g, function ( match, text ) {
            return spanArray.shift();
        });
        text = text.replace(/>/g, '&gt;').replace(/</g, '&lt;');
        //console.log('-- text3: ' + text);
        text = text.replace(/##GREATERTHAN##/g, '>').replace(/##LESSTHAN##/g, '<');
        return text;
    },
    resetSearch: function() {
        this.searchResults = [];
        this.occurrencesList = [];
        this.searchResultsDictionary = {};
        this.featuredSearchResult = 0;
    },
    /**
     * Close search container
     */
    closeSearch : function() {
        this.resetSearch();
        CatToolActions.closeSubHeader();
        SegmentActions.removeSearchResultToSegments();

        CatToolActions.storeSearchResults({
            total: 0,
            searchResults: [],
            occurrencesList: [],
            searchResultsDictionary: {},
            featuredSearchResult: 0,
        });
    },
};

module.exports = SearchUtils;

