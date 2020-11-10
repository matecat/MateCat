/*
 * TodoStore
 * Segment structure example
 * {
     "last_opened_segment":"61079",
     "sid":"60984",
     "segment":"INDIETRO",
     "segment_hash":"0a7e4ea10d93b636d9de15132300870c",
     "raw_word_count":"1.00",
     "internal_id":"P147242AB-tu19",
     "translation":"",
     "version":null,
     "original_target_provied":"0",
     "status":"NEW",
     "time_to_edit":"0",
     "xliff_ext_prec_tags":"",
     "xliff_ext_succ_tags":"",
     "warning":"0",
     "suggestion_match":"85",
     "source_chunk_lengths":[],
     "target_chunk_lengths":{
         "len":[0],
         "statuses":["DRAFT"]
     },
     "readonly":"false",
     "autopropagated_from":"0",
     "repetitions_in_chunk":"1",
     "has_reference":"false",
     "parsed_time_to_edit":["00","00","00","00"],
     "notes":null
 }
 */

import AppDispatcher  from './AppDispatcher';
import {EventEmitter} from 'events';
import SegmentConstants  from '../constants/SegmentConstants';
import assign  from 'object-assign';
import TagUtils  from '../utils/tagUtils';
import TextUtils  from '../utils/textUtils';
import SegmentUtils  from '../utils/segmentUtils';
import Immutable  from 'immutable';
import EditAreaConstants from "../constants/EditAreaConstants";
import DraftMatecatUtils from './../components/segments/utils/DraftMatecatUtils'

EventEmitter.prototype.setMaxListeners(0);

const SegmentStore = assign({}, EventEmitter.prototype, {

    _segments: Immutable.fromJS([]),
    _globalWarnings: {
        lexiqa: [],
        matecat: {
            ERROR: {
                Categories: []
            },
            WARNING: {
                Categories: []
            },
            INFO: {
                Categories: []
            }
        }
    },
    segmentsInBulk: [],
    _footerTabsConfig: Immutable.fromJS({}),
    searchOccurrences : [],
    searchResultsDictionary : {},
    currentInSearch : 0,
    searchParams: {},
    nextUntranslatedFromServer: null,
    consecutiveCopySourceNum: [],
    clipboardFragment: '',
    clipboardPlainText: '',
    sideOpen: false,
    /**
     * Update all
     */
    updateAll: function (segments, where) {
        if (this._segments.size > 0 && where === "before") {
            this._segments = this._segments.unshift(...Immutable.fromJS(this.normalizeSplittedSegments(segments)));
        } else if (this._segments.size > 0 && where === "after") {
            this._segments = this._segments.push(...Immutable.fromJS(this.normalizeSplittedSegments(segments)));
        } else {
            this._segments = Immutable.fromJS(this.normalizeSplittedSegments(segments));
        }

        if (this.segmentsInBulk.length > 0) {
            this.setBulkSelectionSegments(this.segmentsInBulk);
        }
    },
    removeAllSegments: function() {
        this._segments = Immutable.fromJS([]);
    },
    normalizeSplittedSegments: function (segments, fid) {
        let newSegments = [];
        let self = this;
        $.each(segments, function (index) {
            let splittedSourceAr = this.segment.split(UI.splittedTranslationPlaceholder);
            let segment = this;
            let inSearch = false;
            let currentInSearch = false;
            let occurrencesInSearch = null;
            //if search active
            if ( self.searchOccurrences.length > 0 ) {
                inSearch = (self.searchOccurrences.indexOf(segment.sid) > -1);
                currentInSearch =  (segment.sid === self.searchOccurrences[self.currentInSearch]);
                occurrencesInSearch = self.searchResultsDictionary[segment.sid];
            }
            if (splittedSourceAr.length > 1) {
                var splitGroup = [];
                $.each(splittedSourceAr, function (i) {
                    splitGroup.push(segment.sid + '-' + (i + 1));
                });

                $.each(splittedSourceAr, function (i) {
                    let translation = segment.translation.split(UI.splittedTranslationPlaceholder)[i];
                    let status = segment.target_chunk_lengths.statuses[i];
                    let segData = {
                        splitted: true,
                        autopropagated_from: "0",
                        has_reference: "false",
                        parsed_time_to_edit: ["00", "00", "00", "00"],
                        readonly: "false",
                        segment: splittedSourceAr[i],
                        decodedSource : DraftMatecatUtils.unescapeHTML(DraftMatecatUtils.decodeTagsToPlainText(segment.segment)),
                        segment_hash: segment.segment_hash,
                        original_sid: segment.sid,
                        sid: segment.sid + '-' + (i + 1),
                        split_group: splitGroup,
                        split_points_source: [],
                        status: status,
                        time_to_edit: "0",
                        originalDecodedTranslation: DraftMatecatUtils.unescapeHTML(DraftMatecatUtils.decodeTagsToPlainText(translation)),
                        translation: (translation) ? translation : '',
                        decodedTranslation: DraftMatecatUtils.unescapeHTML(DraftMatecatUtils.decodeTagsToPlainText(translation)),
                        version: segment.version,
                        warning: "0",
                        warnings: {},
                        tagged: !self.hasSegmentTagProjectionEnabled(segment),
                        unlocked: false,
                        edit_area_locked: false,
                        notes: segment.notes,
                        modified: false,
                        opened: false,
                        selected: false,
                        id_file: segment.id_file,
                        originalSource: segment.segment,
                        firstOfSplit: (i===0),
                        inSearch: inSearch,
                        currentInSearch: currentInSearch,
                        occurrencesInSearch: occurrencesInSearch,
                        searchParams: self.searchParams
                    };
                    newSegments.push(segData);
                    segData = null;
                });
            } else {
                segment.original_translation = segment.translation;
                segment.unlocked = SegmentUtils.isUnlockedSegment(segment);
                segment.warnings = {};
                segment.tagged = !self.hasSegmentTagProjectionEnabled(segment);
                segment.edit_area_locked = false;
                segment.original_sid = segment.sid;
                segment.modified = false;
                segment.opened = false;
                segment.selected = false;
                segment.propagable = (segment.repetitions_in_chunk !== "1");
                segment.inSearch = inSearch;
                segment.currentInSearch = currentInSearch;
                segment.occurrencesInSearch = occurrencesInSearch;
                segment.searchParams = self.searchParams;
                segment.originalDecodedTranslation = DraftMatecatUtils.unescapeHTML(DraftMatecatUtils.decodeTagsToPlainText(segment.translation));
                segment.decodedTranslation = DraftMatecatUtils.unescapeHTML(DraftMatecatUtils.decodeTagsToPlainText(segment.translation));
                segment.decodedSource = DraftMatecatUtils.unescapeHTML(DraftMatecatUtils.decodeTagsToPlainText(segment.segment));
                newSegments.push(this);
            }

        });
        return newSegments;
    },

    openSegment(sid) {
        var index = this.getSegmentIndex(sid);
        this.closeSegments();
        this._segments = this._segments.setIn([index, 'opened'], true);
    },
    selectNextSegment() {
        let selectedSegment = this._segments.find((segment) => {
            return segment.get('selected') === true
        });
        if ( !selectedSegment ) {
            selectedSegment = this.getCurrentSegment();
        } else {
            selectedSegment = selectedSegment.toJS();
        }
        let next = this.getNextSegment(selectedSegment.sid);
        if ( next ) {
            var index = this.getSegmentIndex(next.sid);
            this._segments = this._segments.map(segment => segment.set('selected', false));
            this._segments = this._segments.setIn([index, 'selected'], true);
            return next.sid;
        }

    },
    selectPrevSegment() {
        let selectedSegment = this._segments.find((segment) => {
            return segment.get('selected') === true
        });
        if ( !selectedSegment ) {
            selectedSegment = this.getCurrentSegment();
        } else {
            selectedSegment = selectedSegment.toJS();
        }
        let prev = this.getPrevSegment(selectedSegment.sid);
        if ( prev ) {
            var index = this.getSegmentIndex(prev.sid);
            this._segments = this._segments.map(segment => segment.set('selected', false));
            this._segments = this._segments.setIn([index, 'selected'], true);
            return prev.sid;
        }
    },
    getSelectedSegmentId( ) {
        let selectedSegment = this._segments.find((segment) => {
            return segment.get('selected') === true
        });
        if ( selectedSegment ) {
            return selectedSegment.get('sid');
        }
        return null;
    },
    closeSegments() {
        this._segments = this._segments.map(segment => segment.set('opened', false));
        this._segments = this._segments.map(segment => segment.set('selected', false));
    },
    removeSplit(oldSid, newSegments, fid, splitGroup) {
        var self = this;
        var elementsToRemove = [];

        newSegments.forEach(function (element) {
            element.split_group = splitGroup;
        });

        newSegments = Immutable.fromJS(newSegments);
        var indexes = [];
        this._segments.map(function (segment, index) {
            if (segment.get('sid').split('-').length && segment.get('sid').split('-')[0] == oldSid) {
                elementsToRemove.push(segment);
                indexes.push(index);
                return index;
            }
        });
        if (elementsToRemove.length) {
            elementsToRemove.forEach(function (seg) {
                self._segments = self._segments.splice(self._segments.indexOf(seg), 1)
            });
            this._segments = this._segments.splice(indexes[0], 0, ...newSegments);

            // Array.prototype.splice.apply(currentSegments, [indexes[0], 0].concat(newSegments));
        }
    },

    setStatus(sid, fid, status) {
        var index = this.getSegmentIndex(sid);
        if ( index === -1 ) return;
        this._segments = this._segments.setIn([index, 'status'], status);
        this._segments = this._segments.setIn([index, 'revision_number'], config.revisionNumber);
    },

    setSuggestionMatch(sid, fid, perc) {
        var index = this.getSegmentIndex(sid);
        if ( index === -1 ) return;
        this._segments = this._segments.setIn([index, 'suggestion_match'], perc.replace('%', ''));
    },

    setPropagation(sid, fid, propagation, from) {
        let index = this.getSegmentIndex(sid);
        if ( index === -1 ) return;
        if (propagation) {
            this._segments = this._segments.setIn([index, 'autopropagated_from'], from);
        } else {
            this._segments = this._segments.setIn([index, 'autopropagated_from'], "0");
        }
    },
    replaceTranslation(sid, translation) {
        var index = this.getSegmentIndex(sid);
        if ( index === -1 ) return;
        this._segments = this._segments.setIn([index, 'translation'], translation);
    },
    updateOriginalTranslation(sid, translation) {
        const index = this.getSegmentIndex(sid);
        if ( index === -1 ) return;
        const newTrans = DraftMatecatUtils.unescapeHTML(DraftMatecatUtils.decodeTagsToPlainText(translation))
        this._segments = this._segments.setIn([index, 'originalDecodedTranslation'], newTrans);
        this._segments = this._segments.setIn([index, 'decodedTranslation'], newTrans);
    },
    updateTranslation(sid, translation, decodedTranslation, tagMap, missingTagsInTarget, lxqDecodedTranslation) {
        var index = this.getSegmentIndex(sid);
        if ( index === -1 ) return;
        let segment = this._segments.get(index);

        //Check segment is modified
        if ( segment.get('originalDecodedTranslation') !== decodedTranslation) {
            this._segments = this._segments.setIn([index, 'modified'], true);
        } else {
            this._segments = this._segments.setIn([index, 'modified'], false);
        }
        this._segments = this._segments.setIn([index, 'translation'], translation);
        this._segments = this._segments.setIn([index, 'decodedTranslation'], decodedTranslation);
        this._segments = this._segments.setIn([index, 'targetTagMap'], tagMap);
        this._segments = this._segments.setIn([index, 'missingTagsInTarget'], missingTagsInTarget);
        this._segments = this._segments.setIn([index, 'lxqDecodedTranslation'], lxqDecodedTranslation);
    },
    updateSource(sid, source, decodedSource, tagMap, lxqDecodedSource) {
        var index = this.getSegmentIndex(sid);
        if ( index === -1 ) return;

        this._segments = this._segments.setIn([index, 'decodedSource'], decodedSource);
        this._segments = this._segments.setIn([index, 'updatedSource'], source);
        this._segments = this._segments.setIn([index, 'sourceTagMap'], tagMap);
        this._segments = this._segments.setIn([index, 'lxqDecodedSource'], lxqDecodedSource);

    },
    modifiedTranslation(sid, modified) {
        const index = this.getSegmentIndex(sid);
        if ( index === -1 ) return;
        this._segments = this._segments.setIn([index, 'modified'], modified);
        if ( !modified ) {
            let segment = this._segments.get(index);
            this._segments = this._segments.setIn([index, 'originalDecodedTranslation'], segment.get('decodedTranslation'));
        }
    },
    setSegmentAsTagged(sid) {
        var index = this.getSegmentIndex(sid);
        if ( index === -1 ) return;
        this._segments = this._segments.setIn([index, 'tagged'], true);
    },

    addSegmentVersions(fid, sid, versions) {
        //If is a splitted segment the versions are added to the first of the split
        let index = this.getSegmentIndex(sid);
        if ( index === -1 ) return;
        if (versions.length === 1 && versions[0].id === 0 && versions[0].translation == "") {
            // TODO Remove this if
            this._segments = this._segments.setIn([index, 'versions'], Immutable.fromJS([]));
            return this._segments.get(index);
        }
        this._segments = this._segments.setIn([index, 'versions'], Immutable.fromJS(versions));
        return this._segments.get(index);
    },
    addSegmentPreloadedIssues(sid, issues) {
        //If is a splitted segment the versions are added to the first of the split
        let index = this.getSegmentIndex(sid);
        if ( index === -1 ) return;
        let versions = [];
        versions.push({
            issues: issues
        });
        this._segments = this._segments.setIn([index, 'versions'], Immutable.fromJS(versions));
        return this._segments.get(index);
    },
    lockUnlockEditArea(sid, fid) {
        let index = this.getSegmentIndex(sid);
        if ( index === -1 ) return;
        let segment = this._segments.get(index);
        let lockedEditArea = segment.get('edit_area_locked');
        this._segments = this._segments.setIn([index, 'edit_area_locked'], !lockedEditArea);
    },
    setToggleBulkOption: function (sid, fid) {
        let index = this.getSegmentIndex(sid);
        if ( index === -1 ) return;
        if (this._segments.getIn([index, 'inBulk'])) {
            let indexArray = this.segmentsInBulk.indexOf(sid);
            this.segmentsInBulk.splice(indexArray, 1);
            this._segments = this._segments.setIn([index, 'inBulk'], false);
        } else {
            this.segmentsInBulk.push(sid);
            this._segments = this._segments.setIn([index, 'inBulk'], true);
        }
    },
    removeBulkOption: function () {
        let self = this;
        this._segments = self._segments.map(segment => segment.set('inBulk', false));
        this.segmentsInBulk = [];
    },
    setBulkSelectionInterval: function (from, to, fid) {
        let index = this.getSegmentIndex(from);
        if (index > -1 &&
            this._segments.get(index).get("readonly") == "false" &&  //not readonly
            (this._segments.get(index).get("ice_locked") === "0" ||  //not ice_locked
                (this._segments.get(index).get("ice_locked") === "1" && this._segments.get(index).get("unlocked"))  //unlocked
            )
        ) {
            this._segments = this._segments.setIn([index, 'inBulk'], true);
            if (this.segmentsInBulk.indexOf(from.toString()) === -1) {
                this.segmentsInBulk.push(from.toString());
            }
        }
        if (from < to) {
            this.setBulkSelectionInterval(from + 1, to, fid);
        }
    },
    setBulkSelectionSegments: function (segmentsArray) {
        this.segmentsInBulk = segmentsArray;
        this._segments = this._segments.map( (segment) => {
            if (segmentsArray.indexOf(segment.get('sid')) > -1) {
                if (segment.get('ice_locked') == "1" && !segment.get('unlocked')) {
                    let index = segmentsArray.indexOf(segment.get('sid'));
                    this.segmentsInBulk.splice(index, 1);  // if is a locked segment remove it from bulk
                } else {
                    return segment.set('inBulk', true);
                }
            }
            return segment.set('inBulk', false);
        });

    },
    setMutedSegments: function (segmentsArray) {
        this._segments = this._segments.map(segment => segment.set('filtering', true));
        this._segments = this._segments.map( (segment) => {
            if (segmentsArray.indexOf(segment.get('sid')) === -1) {
                return segment.set('muted', true);
            }
            return segment;
        });
    },
    removeAllMutedSegments: function () {
        this._segments = this._segments.map(segment => segment.set('filtering', false));
        this._segments = this._segments.map(segment => segment.set('muted', false));
    },
    setUnlockedSegment: function (sid, fid, unlocked) {
        let index = this.getSegmentIndex(sid);
        if ( index === -1 ) return;
        this._segments = this._segments.setIn([index, 'unlocked'], unlocked);
    },

    setConcordanceMatches: function (sid, matches ,errors) {
        const index = this.getSegmentIndex(sid);
        if ( index === -1 ) return;
        this._segments = this._segments.setIn([index, 'concordance'], Immutable.fromJS(matches));
    },
    setContributionsToCache: function (sid, fid, contributions,errors) {
        const index = this.getSegmentIndex(sid);
        if ( index === -1 ) return;
        this._segments = this._segments.setIn([index, 'contributions'], Immutable.fromJS({
            matches: contributions,
            errors: errors
        }));
    },
    setAlternatives: function (sid, alternatives) {
        const index = this.getSegmentIndex(sid);
        if ( index === -1 ) return;
        if ( _.isUndefined(alternatives) ) {
            this._segments = this._segments.deleteIn([index, 'alternatives']);
        } else {
            this._segments = this._segments.setIn([index, 'alternatives'], Immutable.fromJS(alternatives));
        }
    },
    deleteContribution: function(sid, matchId) {
        const index = this.getSegmentIndex(sid);
        let contributions = this._segments.get(index).get('contributions');
        const indexCont = contributions.get('matches').findIndex((contr, index) => contr.get("id") === matchId);
        let matches = contributions.get('matches').splice(indexCont, 1);
        this._segments = this._segments.setIn([index, 'contributions', 'matches'], matches);

    },
    setGlossaryToCache: function (sid, fid, glossary) {
        let index = this.getSegmentIndex(sid, fid);
        this._segments = this._segments.setIn([index, 'glossary'], Immutable.fromJS(glossary));
    },
    deleteFromGlossary: function(sid, matchId, name) {
        let index = this.getSegmentIndex(sid);
        let glossary = this._segments.get(index).get('glossary').toJS();
        delete glossary[name];
        this._segments = this._segments.setIn([index, 'glossary'], Immutable.fromJS(glossary));
    },
    changeGlossaryItem: function(sid, matchId, name, comment, target_note, translation) {
        let index = this.getSegmentIndex(sid);
        let glossary = this._segments.get(index).get('glossary').toJS();
        glossary[name].comment = comment;
        glossary[name].target_note = comment;
        glossary[name].translation = translation;
        this._segments = this._segments.setIn([index, 'glossary'], Immutable.fromJS(glossary));
    },
    addGlossaryItem: function(sid, match, name) {
        let index = this.getSegmentIndex(sid);
        let glossary = this._segments.get(index).get('glossary').toJS();
        glossary[name] = match;
        this._segments = this._segments.setIn([index, 'glossary'], Immutable.fromJS(glossary));
    },
    setCrossLanguageContributionsToCache: function (sid, fid, contributions,errors) {
        const index = this.getSegmentIndex(sid);
        if ( index === -1 ) return;
        this._segments = this._segments.setIn([index, 'cl_contributions'], {
            matches: contributions,
            errors: errors
        });
    },
    closeSide: function() {
        this.sideOpen = false;
    },
    openSide: function()  {
        this.sideOpen = true;
    },
    isSideOpen: function (  ) {
        return this.sideOpen;
    },
    segmentHasIssues: function ( sid ){
        const segment = this.getSegmentByIdToJS(sid);
        if ( !segment ) return false;
        const versionWithIssues = segment.versions && segment.versions.find((item)=>item.issues && item.issues.length > 0);
        return versionWithIssues && versionWithIssues.issues.length > 0

    },
    openSegmentIssuePanel: function(sid) {
        // const index = this.getSegmentIndex(sid);
        // if ( index === -1 ) return;
        // this._segments = this._segments.setIn([index, 'openIssues'], true);
        this._segments = this._segments.map((segment)=>segment.set('openIssues', true));
    },
    closeSegmentIssuePanel: function() {
        this._segments = this._segments.map((segment)=>segment.set('openIssues', false));
    },
    openSegmentComments: function(sid) {
        const index = this.getSegmentIndex(sid);
        if ( index === -1 ) return;
        this._segments = this._segments.map((segment)=>segment.set('openComments', false));
        this._segments = this._segments.setIn([index, 'openComments'], true);
    },
    closeSegmentComments: function(sid) {
        if ( sid ) {
            const index = this.getSegmentIndex(sid);
            try {
                this._segments = this._segments.setIn([index, 'openComments'], false);
            } catch ( e ) {
                console.log("closeSegmentComments fail");
            }
        } else {
            this._segments = this._segments.map((segment)=>segment.set('openComments', false));
        }
    },

    setConfigTabs: function (tabName, visible, open) {
        if ( open ) {
            this._footerTabsConfig = this._footerTabsConfig.map((tab)=>tab.set('open', false));
        }
        this._footerTabsConfig = this._footerTabsConfig.setIn([tabName, 'visible'], visible);
        this._footerTabsConfig = this._footerTabsConfig.setIn([tabName, 'open'], open);
        this._footerTabsConfig = this._footerTabsConfig.setIn([tabName, 'enabled'], true);
    },
    setChoosenSuggestion: function(sid, sugIndex) {
        sugIndex = (sugIndex) ? sugIndex : undefined;
        this._segments = this._segments.map((segment)=>segment.set('choosenSuggestionIndex', sugIndex));
    },
    filterGlobalWarning: function (type, sid) {
        if (type === "TAGS") {
            let index = this.getSegmentIndex(sid);
            if ( index !== -1 ) {
                let segment = this._segments.get(index);
                return segment.get('tagged');
            }
        }

        return sid > -1
    },
    // Local warnings
    setSegmentWarnings(sid, warning, tagMismatch) {
        let index = this.getSegmentIndex(sid);
        if ( index === -1 ) return;
        this._segments = this._segments.setIn([index, 'warnings'], Immutable.fromJS(warning));
        this._segments = this._segments.setIn([index, 'tagMismatch'], Immutable.fromJS(tagMismatch));
    },
    setQACheckMatches(matches) {
        this._segments = this._segments.map((segment)=>segment.remove('qaCheckGlossary'));
        Object.keys(matches).map(sid => {
            let index = this.getSegmentIndex(sid);
            if ( index === -1 ) return;
            this._segments = this._segments.setIn([index, 'qaCheckGlossary'], matches[sid]);
        });

    },
    setQABlacklistMatches(sid, matches) {
        const index = this.getSegmentIndex(sid);
        if ( index === -1 ) return;
        this._segments = this._segments.setIn([index, 'qaBlacklistGlossary'], matches);
    },
    /**
     *
     * @param sid
     * @param matches
     * @param type 1 -> source, 2->target
     */
    addLexiqaHighlight(sid, matches, type) {
        const index = this.getSegmentIndex(sid);
        if ( type === 1 ) {
            this._segments = this._segments.setIn([index, 'lexiqa', 'source'], Immutable.fromJS(matches));
        } else if ( type === 2 ){
            this._segments = this._segments.setIn([index, 'lexiqa', 'target'], Immutable.fromJS(matches));
        } else {
            this._segments = this._segments.setIn([index, 'lexiqa'], Immutable.fromJS(matches));
        }

    },
    updateGlobalWarnings: function (warnings) {
        Object.keys(warnings).map(key => {
            Object.keys(warnings[key].Categories).map(key2 => {
                warnings[key].Categories[key2] = warnings[key].Categories[key2].filter(this.filterGlobalWarning.bind(this, key2));
            });
        });
        this._globalWarnings.matecat = warnings;
    },
    addSearchResult: function(occurrencesList, searchResultsDictionary, current, params) {
        this.searchOccurrences = occurrencesList;
        this.searchResultsDictionary = searchResultsDictionary;
        this.currentInSearch = current;
        this.searchParams = params;
        this._segments = this._segments.map((segment)=> {
            segment = segment.set('inSearch', occurrencesList.indexOf(segment.get('sid')) > -1);
            segment = segment.set('currentInSearch', segment.get('sid') == occurrencesList[current]);
            segment = segment.set('occurrencesInSearch', searchResultsDictionary[segment.get('sid')]);
            segment = segment.set('searchParams', params);
            if ( segment.get('sid') === this.searchOccurrences[current] ) {
                segment = segment.set('currentInSearchIndex', current);
            }
            return segment;
        });
    },
    addCurrentSearchSegment: function(current) {
        this.currentInSearch = current;
        let currentSegment;
        this._segments = this._segments.map((segment)=> {
            segment = segment.set('currentInSearch', segment.get('sid') == this.searchOccurrences[current]);
            if ( segment.get('sid') == this.searchOccurrences[current] ) {
                segment = segment.set('currentInSearchIndex', current);
                currentSegment = segment;
            } else {
                segment = segment.set('currentInSearchIndex', false);
            }
            return segment
        });
        return currentSegment;
    },
    removeSearchResults: function() {
        this._segments = this._segments.map((segment)=>segment.set('inSearch', null));
        this._segments = this._segments.map((segment)=>segment.set('currentInSearch', null));
        this._segments = this._segments.map((segment)=>segment.set('occurrencesInSearch', null));
        this._segments = this._segments.map((segment)=>segment.set('searchParams', null));
        this.searchOccurrences = [];
        this.searchResultsDictionary = {};
        this.currentInSearch = 0;
        this.searchParams = {};
    },
    openSegmentSplit: function(sid) {
        let index = this.getSegmentIndex(sid);
        if ( index === -1 ) return;
        this._segments = this._segments.setIn([index, 'openSplit'], true);
    },
    closeSegmentsSplit: function(sid) {
        this._segments = this._segments.map((segment)=>segment.set('openSplit', false));
    },
    updateLexiqaWarnings: function(warnings){
        this._globalWarnings.lexiqa = warnings.filter(this.filterGlobalWarning.bind(this, "LXQ"));
    },
    hasSegmentTagProjectionEnabled: function ( segment ) {
        if ( SegmentUtils.checkTPEnabled() ) {
            if ( (segment.status === "NEW" || segment.status === "DRAFT") && (TagUtils.checkXliffTagsInText(segment.segment) && (!TagUtils.checkXliffTagsInText(segment.translation))) ) {
                return true;
            }
        }
        return false;
    },
    /**
     *
     * @param current_sid
     * @param current_fid
     * @param status
     * status values:
     * null|undefined|false NEXT WITHOUT CHECK STATUS
     * 1 APPROVED
     * 2 DRAFT
     * 3 FIXED
     * 4 NEW
     * 5 REBUTTED
     * 6 REJECTED
     * 7 TRANSLATED
     * 8 UNTRANSLATED | is draft or new
     * @param revisionNumber
     * @param autopropagated
     */
    getNextSegment(current_sid, current_fid, status, revisionNumber, autopropagated = false ) {
        let currentSegment = this.getCurrentSegment();
        if ( !current_sid && !currentSegment) return null;
        current_sid = ( !current_sid) ? this.getCurrentSegment().sid : current_sid;
        let allStatus = {
            1: "APPROVED",
            2: "DRAFT",
            3: "FIXED",
            4: "NEW",
            5: "REBUTTED",
            6: "REJECTED",
            7: "TRANSLATED",
            8: "UNTRANSLATED",
            9: "UNAPPROVED"
        };
        let result,
            currentFind = false;
        this._segments.forEach((segment, key) => {
            if (_.isUndefined(result)) {
                if ( currentFind || current_sid === -1) {
                    if ( segment.get('readonly') === 'true' ) {
                        return false;
                    } else if ( status === 8 && ( (segment.get( 'status' ).toUpperCase() === allStatus[2] || segment.get( 'status' ).toUpperCase() === allStatus[4]) || ( autopropagated && segment.get('status').toUpperCase() === allStatus[7] && segment.get('autopropagated_from') != 0 )) && !segment.get('muted') ) {
                        result = segment.toJS();
                        return false;
                    } else if ( status === 9 && revisionNumber ) { // Second pass
                        if ( ( (segment.get('status').toUpperCase() === allStatus[1] || segment.get('status').toUpperCase() === allStatus[7] ) && segment.get('revision_number') === revisionNumber )
                            || ( autopropagated && segment.get('status').toUpperCase() === allStatus[1] && segment.get('autopropagated_from') != 0 && segment.get('revision_number') !== revisionNumber) ){
                            result = segment.toJS();
                            return false;
                        }
                    } else if ( ((status && segment.get( 'status' ).toUpperCase() === allStatus[status]) || !status) && !segment.get('muted') ) {
                        result = segment.toJS();
                        return false;
                    }
                }
                if ( segment.get( 'sid' ) === current_sid ) {
                    currentFind = true;
                }
            } else {
                return null;
            }
        });
        return result;
    },
    getNextUntranslatedSegmentId() {
        let current = this.getCurrentSegment();
        let next = this.getNextSegment(current.sid, null, 8, null, true);
        return ( next ) ? next.sid : this.nextUntranslatedFromServer;
    },
    getPrevSegment(sid, alsoMutedSegments) {
        let currentSegment = this.getCurrentSegment();
        if ( !sid && !currentSegment) return null;
        sid = ( !sid ) ? this.getCurrentSegment().sid : sid;
        var index = this.getSegmentIndex(sid);
        let segment = (index > 0) ? this._segments.get(index-1).toJS() : null;
        if ( segment && !alsoMutedSegments && !segment.muted || !segment || segment && alsoMutedSegments) {
            return segment;
        }
        return this.getPrevSegment(segment.sid);
    },
    getSegmentByIdToJS(sid) {
        let segment = this._segments.find(function (seg) {
            return seg.get('sid') == sid || seg.get('original_sid') === sid;
        });
        return (segment) ? segment.toJS() : null;
    },

    segmentScrollableToCenter(sid) {
        //If a segment is in the last 5 segment loaded in the UI is scrollable
        let index = this.getSegmentIndex(sid);
        return index !== -1 && this._segments.size - 5 > index;
    },

    getSegmentsSplitGroup(sid) {
        let segments = this._segments.filter(function (seg) {
            return seg.get('original_sid') == sid;
        });
        return (segments) ? segments.toJS() : null;
    },

    getAllSegments: function () {
        var result = [];
        $.each(this._segments, function (key, value) {
            result = result.concat(value.toJS());
        });
        return result;
    },
    getSegmentById(sid) {
        return this._segments.find(function (seg) {
            return seg.get('sid') == sid;
        });
    },
    getSegmentIndex(sid) {
        return this._segments.findIndex(function (segment, index) {
            if (sid.toString().indexOf("-") === -1) {
                return parseInt(segment.get('sid')) === parseInt(sid);
            } else {
                return segment.get('sid') === sid;
            }
        });

    },
    getLastSegmentId() {
        return this._segments.last().get('sid');
    },
    getFirstSegmentId() {
        return this._segments.first().get('sid');
    },
    getCurrentSegment: function(){
        let current = null,
            tmpCurrent = null;
        tmpCurrent = this._segments.find((segment) => {
            return segment.get('opened') === true
        });
        if(tmpCurrent){
            current = Object.assign({},tmpCurrent.toJS());
        }
        return current;
    },
    getCurrentSegmentId: function() {
        let current = this.getCurrentSegment();
        if ( current ) {
            return current.sid;
        }
        return undefined;
    },
    getSegmentsInPropagation(hash, isReview) {
        let reviewStatus = [
            "DRAFT",
            "NEW",
            "REBUTTED",
            "REJECTED",
            "TRANSLATED",
            "APPROVED"
        ];
        let translateStatus = [
            "DRAFT",
            "NEW",
            "REBUTTED",
            "REJECTED",
            "TRANSLATED",

        ];
        return this._segments.filter((segment)=>{
            if ( isReview && reviewStatus.indexOf(segment.get('status').toUpperCase() > -1) ){
                return segment.get('segment_hash') === hash;
            } else if (!isReview && translateStatus.indexOf(segment.status)){
                return segment.get('segment_hash') === hash;
            }
            return false;
        }).toJS();
    },
    getSegmentsInSplit(sid) {
        return this._segments.filter((segment)=>{
              return segment.get('original_sid') === sid;
        }).toJS();
    },
    getSegmentChoosenContribution(sid) {
        let seg = this.getSegmentById(sid);
        let currContrIndex = seg.get('choosenSuggestionIndex');
        if ( currContrIndex ) {
            return seg.get('contributions').get('matches').get(currContrIndex-1).toJS();
        }
        return;
    },
    isSidePanelToOpen: function() {
        const commentOpen = this._segments.findIndex((segment)=>segment.get('openComments') === true);
        const issueOpen = this._segments.findIndex((segment)=>segment.get('openIssues') === true);
        return ( commentOpen !== -1 ||  issueOpen !== -1);
    },
    copyFragmentToClipboard: function(fragment, plainText){
        this.clipboardFragment = fragment;
        this.clipboardPlainText = plainText;
    },
    getFragmentFromClipboard: function(){
        const fragment = this.clipboardFragment;
        const plainText = this.clipboardPlainText;
        return {
            fragment,
            plainText
        };
    },
    emitChange: function (event, args) {
        this.emit.apply(this, arguments);
    }
});


// Register callback to handle all updates
AppDispatcher.register(function (action) {

    switch (action.actionType) {
        case SegmentConstants.RENDER_SEGMENTS:
            SegmentStore.updateAll(action.segments);
            if ( action.idToOpen ) {
                SegmentStore.openSegment(action.idToOpen);
                SegmentStore.emitChange(SegmentConstants.OPEN_SEGMENT, action.idToOpen);
            }
            SegmentStore.emitChange(action.actionType, SegmentStore._segments);
            if ( SegmentStore.searchOccurrences.length > 0 ) {// Search Active
                SegmentStore.emitChange(SegmentConstants.UPDATE_SEARCH);
            }
            break;
        case SegmentConstants.SET_OPEN_SEGMENT:
            SegmentStore.openSegment(action.sid);
            SegmentStore.closeSegmentsSplit();
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments);
            break;
        case SegmentConstants.OPEN_SEGMENT:
            SegmentStore.openSegment(action.sid);
            SegmentStore.emitChange(SegmentConstants.OPEN_SEGMENT, action.sid);
            // SegmentStore.emitChange(SegmentConstants.SCROLL_TO_SEGMENT, action.sid);
            break;
        case SegmentConstants.SELECT_SEGMENT:
            let idToScroll;
            if ( action.direction === 'next' ) {
                idToScroll = SegmentStore.selectNextSegment(action.sid);
            } else {
                idToScroll = SegmentStore.selectPrevSegment(action.sid);
            }
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments);
            if ( idToScroll ) {
                SegmentStore.emitChange(SegmentConstants.SCROLL_TO_SELECTED_SEGMENT, idToScroll);
            }
            break;
        case SegmentConstants.CLOSE_SEGMENT:
            SegmentStore.closeSegments(action.sid);
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments);
            break;
        case SegmentConstants.ADD_SEGMENTS:
            SegmentStore.updateAll(action.segments, action.where);
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments);
            if ( SegmentStore.searchOccurrences.length > 0 ) {// Search Active
                SegmentStore.emitChange(SegmentConstants.UPDATE_SEARCH);
            }
            break;
        case SegmentConstants.SCROLL_TO_SEGMENT:
            SegmentStore.emitChange(action.actionType, action.sid);
            break;
        case SegmentConstants.ADD_SEGMENT_CLASS:
            SegmentStore.emitChange(action.actionType, action.id, action.newClass);
            break;
        case SegmentConstants.REMOVE_SEGMENT_CLASS:
            SegmentStore.emitChange(action.actionType, action.id, action.className);
            break;
        case SegmentConstants.SET_SEGMENT_STATUS:
            SegmentStore.setStatus(action.id, action.fid, action.status);
            // SegmentStore.emitChange(SegmentConstants.SET_SEGMENT_STATUS, action.id, action.status);
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments);
            break;
        case SegmentConstants.UPDATE_ALL_SEGMENTS:
            SegmentStore.emitChange(SegmentConstants.UPDATE_ALL_SEGMENTS);
            break;
        case SegmentConstants.SET_SEGMENT_HEADER:
            SegmentStore.setSuggestionMatch(action.id, action.fid, action.perc);
            SegmentStore.emitChange(SegmentConstants.SET_SEGMENT_PROPAGATION, action.id, false);
            SegmentStore.emitChange(action.actionType, action.id, action.perc, action.className, action.createdBy);
            break;
        case SegmentConstants.HIDE_SEGMENT_HEADER:
            SegmentStore.emitChange(SegmentConstants.SET_SEGMENT_PROPAGATION, action.id, false);
            SegmentStore.emitChange(action.actionType, action.id, action.fid);
            break;
        case SegmentConstants.SET_SEGMENT_PROPAGATION:
            SegmentStore.setPropagation(action.id, action.fid, action.propagation, action.from);
            SegmentStore.emitChange(action.actionType, action.id, action.propagation);
            break;
        case SegmentConstants.SET_SEGMENT_ORIGINAL_TRANSLATION:
            SegmentStore.updateOriginalTranslation(action.id, action.originalTranslation);
            break;
        case SegmentConstants.REPLACE_TRANSLATION:
            SegmentStore.replaceTranslation(action.id, action.translation);
            SegmentStore.emitChange(action.actionType, action.id, action.translation);
            break;
        case SegmentConstants.UPDATE_TRANSLATION:
            SegmentStore.updateTranslation(action.id,
                action.translation,
                action.decodedTranslation,
                action.tagMap,
                action.missingTagsInTarget,
                action.lxqDecodedTranslation);
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments);
            break;
        case SegmentConstants.UPDATE_SOURCE:
            SegmentStore.updateSource(action.id, action.source, action.decodedSource, action.tagMap, action.lxqDecodedSource);
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments);
            break;
        case SegmentConstants.MODIFIED_TRANSLATION:
            SegmentStore.modifiedTranslation(action.sid, action.status);
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments);
            break;
        case SegmentConstants.LOCK_EDIT_AREA:
            SegmentStore.lockUnlockEditArea(action.id, action.fid);
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments);
            break;
        case SegmentConstants.REGISTER_TAB:
            SegmentStore.setConfigTabs(action.tab, action.visible, action.open);
            SegmentStore.emitChange(action.actionType, action.tab, SegmentStore._footerTabsConfig.toJS());
            break;
        case SegmentConstants.SET_DEFAULT_TAB:
            SegmentStore.setConfigTabs(action.tabName, true, true);
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments, action.fid);
            break;
        case SegmentConstants.MODIFY_TAB_VISIBILITY:
            SegmentStore.emitChange(action.actionType, action.tabName, action.visible);
            break;
        case SegmentConstants.SHOW_FOOTER_MESSAGE:
            SegmentStore.emitChange(action.actionType, action.sid, action.message);
            break;
        case SegmentConstants.SET_CONTRIBUTIONS:
            SegmentStore.setContributionsToCache(action.sid, action.fid, action.matches, action.errors);
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments, action.fid);
            break;
        case SegmentConstants.SET_CL_CONTRIBUTIONS:
            SegmentStore.setCrossLanguageContributionsToCache(action.sid, action.fid, action.matches, action.errors);
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments, action.fid);
            break;
        case SegmentConstants.SET_ALTERNATIVES:
            SegmentStore.setAlternatives(action.sid, action.alternatives);
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments, action.fid);
            break;
        case SegmentConstants.CHOOSE_CONTRIBUTION:
            SegmentStore.emitChange(action.actionType, action.sid, action.index);
            break;
        case SegmentConstants.DELETE_CONTRIBUTION:
            SegmentStore.deleteContribution(action.sid, action.matchId);
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments, action.fid);
            break;
        case SegmentConstants.SET_GLOSSARY_TO_CACHE:
            SegmentStore.setGlossaryToCache(action.sid, action.fid, action.glossary);
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments, action.fid);
            SegmentStore.emitChange(action.actionType, action.sid);
            break;
        case SegmentConstants.DELETE_FROM_GLOSSARY:
            SegmentStore.deleteFromGlossary(action.sid, action.matchId, action.name);
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments, action.fid);
            break;
        case SegmentConstants.CHANGE_GLOSSARY:
            SegmentStore.changeGlossaryItem(action.sid, action.matchId, action.name, action.comment, action.target_note, action.translation);
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments, action.fid);
            break;
        case SegmentConstants.ADD_GLOSSARY_ITEM:
            SegmentStore.addGlossaryItem(action.sid, action.match, action.name);
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments, action.fid);
            break;
        case EditAreaConstants.COPY_GLOSSARY_IN_EDIT_AREA:
            SegmentStore.emitChange(action.actionType, action.segment, action.glossaryTranslation);
            break;
        case SegmentConstants.CONCORDANCE_RESULT:
            SegmentStore.setConcordanceMatches(action.sid, action.matches);
            SegmentStore.emitChange(action.actionType, action.sid, action.matches);
            break;
        case SegmentConstants.RENDER_GLOSSARY:
            SegmentStore.emitChange(action.actionType, action.sid, action.segment);
            break;
        case SegmentConstants.SET_SEGMENT_TAGGED:
            SegmentStore.setSegmentAsTagged(action.id, action.fid);
            SegmentStore.emitChange(SegmentConstants.SET_SEGMENT_TAGGED, action.id);
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments, action.fid);
            break;
        case SegmentConstants.ADD_SEGMENT_VERSIONS_ISSUES:
            let seg = SegmentStore.addSegmentVersions(action.fid, action.sid, action.versions);
            if ( seg ) {
                SegmentStore.emitChange(action.actionType, action.sid, seg.toJS());
            }
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments, action.fid);
            break;
        case SegmentConstants.ADD_SEGMENT_PRELOADED_ISSUES:
            _.each(action.versionsIssues, function ( issues, segmentId ) {
                SegmentStore.addSegmentPreloadedIssues(segmentId, issues);
            });
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments, action.fid);
            break;
        case SegmentConstants.ADD_TAB_INDEX:
            SegmentStore.emitChange(action.actionType, action.sid, action.tab, action.data);
            break;
        case SegmentConstants.TOGGLE_SEGMENT_ON_BULK:
            SegmentStore.setToggleBulkOption(action.sid, action.fid);
            SegmentStore.emitChange(SegmentConstants.SET_BULK_SELECTION_SEGMENTS, SegmentStore.segmentsInBulk);
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments, action.fid);
            break;
        case SegmentConstants.REMOVE_SEGMENTS_ON_BULK:
            SegmentStore.removeBulkOption();
            SegmentStore.emitChange(SegmentConstants.REMOVE_SEGMENTS_ON_BULK, []);
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments);

            break;
        case SegmentConstants.SET_BULK_SELECTION_INTERVAL:
            SegmentStore.setBulkSelectionInterval(action.from, action.to, action.fid);
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments, action.fid);
            SegmentStore.emitChange(SegmentConstants.SET_BULK_SELECTION_SEGMENTS, SegmentStore.segmentsInBulk);
            break;
        case SegmentConstants.SET_BULK_SELECTION_SEGMENTS:
            SegmentStore.setBulkSelectionSegments(action.segmentsArray);
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments);
            SegmentStore.emitChange(SegmentConstants.SET_BULK_SELECTION_SEGMENTS, SegmentStore.segmentsInBulk);
            break;
        case SegmentConstants.SET_UNLOCKED_SEGMENT:
            SegmentStore.setUnlockedSegment(action.sid, action.fid, action.unlocked);
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments, action.fid);
            break;
        case SegmentConstants.SET_MUTED_SEGMENTS:
            SegmentStore.setMutedSegments(action.segmentsArray);
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments);
            break;
        case SegmentConstants.REMOVE_MUTED_SEGMENTS:
            SegmentStore.removeAllMutedSegments();
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments);
            break;
        case SegmentConstants.SET_SEGMENT_WARNINGS: // LOCAL
            SegmentStore.setSegmentWarnings(action.sid, action.warnings, action.tagMismatch);
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments);
            break;
        case SegmentConstants.UPDATE_GLOBAL_WARNINGS:
            SegmentStore.updateGlobalWarnings(action.warnings);
            SegmentStore.emitChange(action.actionType, SegmentStore._globalWarnings);
            break;

        case SegmentConstants.QA_LEXIQA_ISSUES:
            SegmentStore.updateLexiqaWarnings(action.warnings);
            SegmentStore.emitChange(SegmentConstants.UPDATE_GLOBAL_WARNINGS, SegmentStore._globalWarnings);
            break;
        case SegmentConstants.OPEN_ISSUES_PANEL:
            SegmentStore.openSegmentIssuePanel(action.data.sid);
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments);
            SegmentStore.emitChange(action.actionType, action.data);
            if ( SegmentStore.isSidePanelToOpen() && !SegmentStore.sideOpen ) {
                SegmentStore.openSide();
                SegmentStore.emitChange(SegmentConstants.OPEN_SIDE, SegmentStore._segments);
            }
            break;
        case SegmentConstants.CLOSE_ISSUES_PANEL:
            SegmentStore.closeSegmentIssuePanel();
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments);
            SegmentStore.emitChange(action.actionType);
            if ( !SegmentStore.isSidePanelToOpen() && SegmentStore.sideOpen) {
                SegmentStore.closeSide();
                SegmentStore.emitChange(SegmentConstants.CLOSE_SIDE, SegmentStore._segments);
            }
            break;
        case SegmentConstants.CLOSE_SIDE:
            SegmentStore.closeSegmentIssuePanel();
            SegmentStore.closeSegmentComments();
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments);
            SegmentStore.emitChange(action.actionType);
            if ( !SegmentStore.isSidePanelToOpen() && SegmentStore.sideOpen) {
                SegmentStore.closeSide();
                SegmentStore.emitChange(SegmentConstants.CLOSE_SIDE, SegmentStore._segments);
            }
            break;
        case SegmentConstants.OPEN_SIDE:
            if ( SegmentStore.isSidePanelToOpen() && !SegmentStore.sideOpen) {
                SegmentStore.openSide();
                SegmentStore.emitChange(SegmentConstants.OPEN_SIDE, SegmentStore._segments);
            }
            break;
        case SegmentConstants.OPEN_COMMENTS:
            SegmentStore.openSegmentComments(action.sid);
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments);
            if ( SegmentStore.isSidePanelToOpen() && !SegmentStore.sideOpen) {
                SegmentStore.openSide();
                SegmentStore.emitChange(SegmentConstants.OPEN_SIDE, SegmentStore._segments);
            }
            break;
        case SegmentConstants.CLOSE_COMMENTS:
            SegmentStore.closeSegmentComments();
            if ( !SegmentStore.isSidePanelToOpen() && SegmentStore.sideOpen) {
                SegmentStore.closeSide();
                SegmentStore.emitChange(SegmentConstants.CLOSE_SIDE, SegmentStore._segments);
            }
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments);
            break;
        case SegmentConstants.OPEN_SPLIT_SEGMENT:
            SegmentStore.openSegmentSplit(action.sid);
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments);
            break;
        case SegmentConstants.CLOSE_SPLIT_SEGMENT:
            SegmentStore.closeSegmentsSplit();
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments);
            SegmentStore.emitChange(SegmentConstants.CLOSE_SPLIT_SEGMENT);
            break;
        case SegmentConstants.SET_CHOOSEN_SUGGESTION:
            SegmentStore.setChoosenSuggestion(action.sid, action.index);
            break;
        case SegmentConstants.SET_QA_CHECK_MATCHES:
            SegmentStore.setQACheckMatches(action.matches);
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments);
            break;
        case SegmentConstants.SET_QA_BLACKLIST_MATCHES:
            SegmentStore.setQABlacklistMatches(action.sid, action.matches);
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments);
            break;
        case SegmentConstants.ADD_LXQ_HIGHLIGHT:
            SegmentStore.addLexiqaHighlight(action.sid, action.matches, action.type);
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments);
            break;
        case SegmentConstants.ADD_SEARCH_RESULTS:
            SegmentStore.addSearchResult(action.occurrencesList, action.searchResultsDictionary, action.currentIndex, action.text);
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments);
            _.forEach(action.segments, (sid) => {
                SegmentStore.emitChange(SegmentConstants.ADD_SEARCH_RESULTS, sid);
            });
            break;
        case SegmentConstants.REMOVE_SEARCH_RESULTS:
            SegmentStore.removeSearchResults();
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments);
            break;
        case SegmentConstants.ADD_CURRENT_SEARCH:
            let currentSegment = SegmentStore.addCurrentSearchSegment(action.currentIndex);
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments);
            if ( currentSegment ) {
                SegmentStore.emitChange(SegmentConstants.FORCE_UPDATE_SEGMENT, currentSegment.get('sid'));
            }
            break;
        case EditAreaConstants.REPLACE_SEARCH_RESULTS:
            SegmentStore.emitChange(EditAreaConstants.REPLACE_SEARCH_RESULTS, action.text);
            break;
        case EditAreaConstants.COPY_FRAGMENT_TO_CLIPBOARD:
            SegmentStore.copyFragmentToClipboard(action.fragment, action.plainText);
            break;
        case SegmentConstants.SEGMENT_FOCUSED:
            SegmentStore.emitChange(SegmentConstants.SEGMENT_FOCUSED, action.sid, action.focused)
            break;
        default:
            SegmentStore.emitChange(action.actionType, action.sid, action.data);
    }
});

module.exports = SegmentStore;
