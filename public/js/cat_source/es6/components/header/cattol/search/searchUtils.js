import SegmentActions from '../../../../actions/SegmentActions';
// import CatToolActions  from "../../../../actions/CatToolActions";
import SegmentStore from '../../../../stores/SegmentStore';
import TextUtils from '../../../../utils/textUtils';

let SearchUtils = {
    searchEnabled: true,
    searchParams: {
        search: 0,
    },
    total: 0,
    searchResults: [],
    occurrencesList: [],
    searchResultsDictionary: {},
    featuredSearchResult: 0,
    searchSegmentsResult: [],

    /**
     * Called by the search component to execute search
     * @returns {boolean}
     */
    execFind: function (params) {
        $('section.currSearchSegment').removeClass('currSearchSegment');

        let searchSource = params.searchSource;
        if (searchSource !== '' && searchSource !== ' ') {
            this.searchParams.source = searchSource;
        } else {
            delete this.searchParams.source;
        }
        let searchTarget = params.searchTarget;
        if (searchTarget !== '' && searchTarget !== ' ') {
            this.searchParams.target = searchTarget;
        } else {
            delete this.searchParams.target;
        }

        let selectStatus = params.selectStatus;
        if (selectStatus !== '') {
            this.searchParams.status = selectStatus;
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
        if (
            _.isUndefined(this.searchParams.source) &&
            _.isUndefined(this.searchParams.target) &&
            this.searchParams.status == 'all'
        ) {
            APP.alert({ msg: 'Enter text in source or target input boxes<br /> or select a status.' });
            return false;
        }
        SegmentActions.disableTagLock();

        let p = this.searchParams;

        this.searchMode = !_.isUndefined(p.source) && !_.isUndefined(p.target) ? 'source&target' : 'normal';
        this.whereToFind = '';
        if (this.searchMode === 'normal') {
            if (!_.isUndefined(p.target)) {
                this.whereToFind = '.targetarea';
            } else if (!_.isUndefined(p.source)) {
                this.whereToFind = '.source';
            }
        }

        this.searchParams.searchMode = this.searchMode;

        let source = p.source ? TextUtils.htmlEncode(p.source) : '';
        let target = p.target ? TextUtils.htmlEncode(p.target) : '';
        let replace = p.replace ? p.replace : '';

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
                    revision_number: config.revisionNumber,
                },
                success: function (d) {
                    SearchUtils.execFind_success(d);
                },
            });
        };
        //Save the current segment to not lose the translation
        try {
            let segment = SegmentStore.getSegmentByIdToJS(UI.currentSegmentId);
            if (UI.translationIsToSaveBeforeClose(segment)) {
                UI.saveSegment(UI.currentSegment).then(() => {
                    makeSearchFn();
                });
            } else {
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
    execFind_success: function (response) {
        this.resetSearch();
        this.searchSegmentsResult = response.segments;
        if (response.segments.length > 0) {
            let searchObject = this.createSearchObject(response.segments);

            this.occurrencesList = _.clone(searchObject.occurrencesList);
            this.searchResultsDictionary = _.clone(searchObject.searchResultsDictionary);

            this.searchParams.current = searchObject.occurrencesList[0];

            CatToolActions.storeSearchResults({
                total: response.total,
                searchResults: searchObject.searchResults,
                occurrencesList: this.occurrencesList,
                searchResultsDictionary: _.clone(this.searchResultsDictionary),
                featuredSearchResult: 0,
            });
            SegmentActions.addSearchResultToSegments(this.occurrencesList, this.searchResultsDictionary ,0, searchObject.searchParams);
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

    updateSearchObjectAfterReplace: function (segmentsResult) {
        this.searchSegmentsResult = segmentsResult ? segmentsResult : this.searchSegmentsResult;
        let searchObject = this.createSearchObject(this.searchSegmentsResult);
        this.occurrencesList = searchObject.occurrencesList;
        this.searchResultsDictionary = searchObject.searchResultsDictionary;
        return searchObject;
    },

    updateSearchObject: function () {
        let currentFeaturedSegment = this.occurrencesList[this.featuredSearchResult];
        let searchObject = this.createSearchObject(this.searchSegmentsResult);
        this.occurrencesList = searchObject.occurrencesList;
        this.searchResultsDictionary = searchObject.searchResultsDictionary;
        let newIndex = _.findIndex(this.occurrencesList, (item) => item === currentFeaturedSegment);
        if (newIndex > -1) {
            this.featuredSearchResult = newIndex;
        } else {
            this.featuredSearchResult = this.featuredSearchResult + 1;
        }
        searchObject.featuredSearchResult = this.featuredSearchResult;
        return searchObject;
    },

    getSearchRegExp(textToMatch, ignoreCase, isExactMatch) {
        let ignoreFlag = (ignoreCase)? "" : "i";
        textToMatch = TextUtils.escapeRegExp(textToMatch);
        let reg = new RegExp( '(' + textToMatch + ')', "g" + ignoreFlag );
        if (isExactMatch) {
            reg = new RegExp( '\\b(' + textToMatch + ')\\b', "g" + ignoreFlag );
        }
        return reg;
    },

    getMatchesInText: function(text, textToMatch, ignoreCase, isExactMatch) {
        let reg = this.getSearchRegExp(textToMatch, ignoreCase, isExactMatch);
        return text.matchAll( reg );
    },

    createSearchObject: function (segments) {
        let searchProgressiveIndex = 0;
        let occurrencesList = [], searchResultsDictionary = {};
        let searchParams = {};
        searchParams.source = this.searchParams.source;
        searchParams.target = this.searchParams.target;
        searchParams.ingnoreCase = !!(this.searchParams['match-case']);
        searchParams.exactMatch = this.searchParams['exact-match'];
        let searchResults = segments.map( (sid) => {
            let segment = SegmentStore.getSegmentByIdToJS(sid);
            let item = { id: sid, occurrences: [] };
            if (segment) {
                if ( this.searchParams.searchMode === 'source&target' ) {
                    let textSource = segment.decodedSource;
                    const matchesSource = this.getMatchesInText(textSource, this.searchParams.source, searchParams.ingnoreCase, this.searchParams['exact-match']);
                    let textTarget = segment.decodedTranslation;
                    const matchesTarget = this.getMatchesInText(textTarget, this.searchParams.target, searchParams.ingnoreCase, this.searchParams['exact-match']);

                    let sourcesMatches = [], targetMatches = [];
                    for ( const match of matchesSource ) {
                        sourcesMatches.push( match );
                    }
                    for (const match of matchesTarget) {
                        targetMatches.push(match);
                    }
                    //Check if source and target has the same occurrences
                    let matches = (sourcesMatches.length > targetMatches.length) ? sourcesMatches : targetMatches;
                    for ( const match of matches ) {
                        occurrencesList.push( sid );
                        item.occurrences.push( {matchPosition: match.index, searchProgressiveIndex: searchProgressiveIndex} );
                        searchProgressiveIndex++;

                    }
                } else {
                    if ( this.searchParams.source ) {
                        let text = segment.decodedSource;
                        const matchesSource = this.getMatchesInText(text, this.searchParams.source, searchParams.ingnoreCase, this.searchParams['exact-match']);
                        for ( const match of matchesSource ) {
                            occurrencesList.push( sid );
                            item.occurrences.push( {matchPosition: match.index, searchProgressiveIndex: searchProgressiveIndex} );
                            searchProgressiveIndex++;
                        }
                    } else if ( this.searchParams.target ) {
                        let text = segment.decodedTranslation;
                        const matchesTarget = this.getMatchesInText(text, this.searchParams.target, searchParams.ingnoreCase, this.searchParams['exact-match']);
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
        console.log('SearchResults', searchResults);
        console.log('occurrencesList', occurrencesList);
        console.log('searchResultsDictionary', searchResultsDictionary);
        return {
            searchParams: searchParams,
            searchResults: searchResults,
            occurrencesList: occurrencesList,
            searchResultsDictionary: searchResultsDictionary,
        };
    },

    /**
     * Toggle the Search container
     * @param e
     */
    toggleSearch: function (e) {
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
    execReplaceAll: function (params) {
        // $('.search-display .numbers').text('No segments found');
        // this.applySearch();

        let searchSource = params.searchSource;
        if (searchSource !== '' && searchSource !== ' ' && searchSource !== "'" && searchSource !== '"') {
            this.searchParams.source = searchSource;
        } else {
            delete this.searchParams.source;
        }

        let searchTarget = params.searchTarget;
        if (searchTarget !== '' && searchTarget !== ' ' && searchTarget !== '"') {
            this.searchParams.target = searchTarget;
        } else {
            APP.alert({ msg: 'You must specify the Target value to replace.' });
            delete this.searchParams.target;
            return false;
        }

        let replaceTarget = params.replaceTarget;
        if (replaceTarget !== '"') {
            this.searchParams.replace = replaceTarget;
        } else {
            APP.alert({ msg: 'You must specify the replacement value.' });
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
        let source = p.source ? TextUtils.htmlEncode(p.source) : '';
        let target = p.target ? TextUtils.htmlEncode(p.target) : '';
        let replace = p.replace ? p.replace : '';
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
                revision_number: config.revisionNumber,
            },
            success: function (d) {
                if (d.errors.length) {
                    APP.alert({ msg: d.errors[0].message });
                    return false;
                }
                UI.unmountSegments();
                UI.render({
                    firstLoad: false,
                });
            },
        });
    },

    updateFeaturedResult(value) {
        this.featuredSearchResult = value;
    },
    resetSearch: function() {
        this.searchResults = [];
        this.occurrencesList = [];
        this.searchResultsDictionary = {};
        this.featuredSearchResult = 0;
        this.searchSegmentsResult = [];
        SegmentActions.removeSearchResultToSegments();
    },
    /**
     * Close search container
     */
    closeSearch: function () {
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
