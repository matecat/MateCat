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

let AppDispatcher = require('../dispatcher/AppDispatcher');
let EventEmitter = require('events').EventEmitter;
let SegmentConstants = require('../constants/SegmentConstants');
let assign = require('object-assign');
let Immutable = require('immutable');

EventEmitter.prototype.setMaxListeners(0);

var SegmentStore = assign({}, EventEmitter.prototype, {

    _segments: {},
    _segmentsFiles: Immutable.fromJS({}),
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
    /**
     * Update all
     */
    updateAll: function (segments, fid, where) {
        console.time("Time: updateAll segments" + fid);
        if (this._segments[fid] && where === "before") {
            this._segments[fid] = this._segments[fid].unshift(...Immutable.fromJS(this.normalizeSplittedSegments(segments)));
        } else if (this._segments[fid] && where === "after") {
            this._segments[fid] = this._segments[fid].push(...Immutable.fromJS(this.normalizeSplittedSegments(segments)));
        } else {
            this._segments[fid] = Immutable.fromJS(this.normalizeSplittedSegments(segments));
        }

        this.buildSegmentsFiles(fid, segments);
        console.log(this._segmentsFiles);

        if (this.segmentsInBulk.length > 0) {
            this.setBulkSelectionSegments(this.segmentsInBulk);
        }
        // console.timeEnd("Time: updateAll segments"+fid);
    },

    normalizeSplittedSegments: function (segments) {
        let newSegments = [];
        let self = this;
        $.each(segments, function (index) {
            let splittedSourceAr = this.segment.split(UI.splittedTranslationPlaceholder);
            let segment = this;
            if (splittedSourceAr.length > 1) {
                var splitGroup = [];
                $.each(splittedSourceAr, function (i) {
                    splitGroup.push(segment.sid + '-' + (i + 1));
                });

                $.each(splittedSourceAr, function (i) {
                    let translation = segment.translation.split(UI.splittedTranslationPlaceholder)[i];
                    let status = segment.target_chunk_lengths.statuses[i];
                    let segData = {
                        autopropagated_from: "0",
                        has_reference: "false",
                        parsed_time_to_edit: ["00", "00", "00", "00"],
                        readonly: "false",
                        segment: splittedSourceAr[i],
                        decoded_source: UI.decodeText(segment, splittedSourceAr[i]),
                        segment_hash: segment.segment_hash,
                        sid: segment.sid + '-' + (i + 1),
                        split_group: splitGroup,
                        split_points_source: [],
                        status: status,
                        time_to_edit: "0",
                        translation: translation,
                        decoded_translation: UI.decodeText(segment, translation),
                        version: segment.version,
                        warning: "0",
                        warnings: {},
                        tagged: !self.hasSegmentTagProjectionEnabled(segment),
                        unlocked: false
                    };
                    newSegments.push(segData);
                    segData = null;
                });
            } else {
                segment.decoded_translation = UI.decodeText(segment, segment.translation);
                segment.decoded_source = UI.decodeText(segment, segment.segment);
                segment.unlocked = UI.isUnlockedSegment(segment);
                segment.warnings = {};
                segment.tagged = !self.hasSegmentTagProjectionEnabled(segment);
                newSegments.push(this);
            }

        });
        return newSegments;
    },
    hasSegmentTagProjectionEnabled: function ( segment ) {
        if (UI.enableTagProjection) {
            if ( (segment.status === "NEW" || segment.status === "DRAFT") && (UI.checkXliffTagsInText(segment.segment) && (!UI.checkXliffTagsInText(segment.translation))) ) {
                return true;
            }
        }
        return false;
    },
    buildSegmentsFiles: function (fid, segments) {
        segments.map(segment => {
            var splittedSourceAr = segment.segment.split(UI.splittedTranslationPlaceholder);
            if (splittedSourceAr.length > 1) {
                let self = this;
                $.each(splittedSourceAr, function (i) {
                    self._segmentsFiles = self._segmentsFiles.set(segment.sid + '-' + (i + 1), fid);
                });
            } else {
                this._segmentsFiles = this._segmentsFiles.set(segment.sid, fid);
            }
        });
    },

    getSegmentById(sid, fid) {
        return this._segments[fid].find(function (seg) {
            return seg.get('sid') == sid;
        });
    },
    getSegmentIndex(sid, fid) {
        return this._segments[fid].findIndex(function (segment, index) {
            return parseInt(segment.get('sid')) === parseInt(sid);
        });

    },

    splitSegment(oldSid, newSegments, fid, splitGroup) {
        var index = this._segments[fid].findIndex(function (segment, index) {
            return (segment.get('sid') == oldSid);
        });
        if (index > -1) {

            newSegments.forEach(function (element) {
                element.split_group = splitGroup;
                element.decoded_translation = UI.decodeText(element, element.translation);
                element.decoded_source = UI.decodeText(element, element.segment);
            });

            newSegments = Immutable.fromJS(newSegments);
            this._segments[fid] = this._segments[fid].splice(index, 1, ...newSegments);
        } else {
            this.removeSplit(oldSid, newSegments, fid, splitGroup);
        }
    },

    removeSplit(oldSid, newSegments, fid, splitGroup) {
        var self = this;
        var elementsToRemove = [];

        newSegments.forEach(function (element) {
            element.split_group = splitGroup;
            element.decoded_translation = UI.decodeText(element, element.translation);
            element.decoded_source = UI.decodeText(element, element.segment);
        });

        newSegments = Immutable.fromJS(newSegments);
        var indexes = [];
        this._segments[fid].map(function (segment, index) {
            if (segment.get('sid').split('-').length && segment.get('sid').split('-')[0] == oldSid) {
                elementsToRemove.push(segment);
                indexes.push(index);
                return index;
            }
        });
        if (elementsToRemove.length) {
            elementsToRemove.forEach(function (seg) {
                self._segments[fid] = self._segments[fid].splice(self._segments[fid].indexOf(seg), 1)
            });
            this._segments[fid] = this._segments[fid].splice(indexes[0], 0, ...newSegments);

            // Array.prototype.splice.apply(currentSegments, [indexes[0], 0].concat(newSegments));
        }
    },

    setStatus(sid, fid, status) {
        var index = this.getSegmentIndex(sid, fid);
        this._segments[fid] = this._segments[fid].setIn([index, 'status'], status);
    },

    setSuggestionMatch(sid, fid, perc) {
        var index = this.getSegmentIndex(sid, fid);
        this._segments[fid] = this._segments[fid].setIn([index, 'suggestion_match'], perc.replace('%', ''));
    },

    setPropagation(sid, fid, propagation, from) {
        var index = this.getSegmentIndex(sid, fid);
        if (propagation) {
            this._segments[fid] = this._segments[fid].setIn([index, 'autopropagated_from'], from);
        } else {
            this._segments[fid] = this._segments[fid].setIn([index, 'autopropagated_from'], "0");
        }
    },
    replaceTranslation(sid, fid, translation) {
        var index = this.getSegmentIndex(sid, fid);
        var trans = htmlEncode(this.removeLockTagsFromString(translation));
        this._segments[fid] = this._segments[fid].setIn([index, 'translation'], trans);
        return translation;
    },
    replaceSource(sid, fid, source) {
        var index = this.getSegmentIndex(sid, fid);
        var trans = htmlEncode(this.removeLockTagsFromString(source));
        this._segments[fid] = this._segments[fid].setIn([index, 'segment'], trans);
        return source;
    },
    decodeSegmentsText: function () {
        let self = this;
        _.forEach(this._segments, function (item, index) {
            self._segments[index] = self._segments[index].map(segment => segment.set('decoded_translation', UI.decodeText(segment.toJS(), segment.get('translation'))));
            self._segments[index] = self._segments[index].map(segment => segment.set('decoded_source', UI.decodeText(segment.toJS(), segment.get('segment'))));
        });
    },
    setSegmentAsTagged(sid, fid) {
        var index = this.getSegmentIndex(sid, fid);
        this._segments[fid] = this._segments[fid].setIn([index, 'tagged'], true);
    },

    setSegmentOriginalTranslation(sid, fid, translation) {
        var index = this.getSegmentIndex(sid, fid);
        this._segments[fid] = this._segments[fid].setIn([index, 'original_translation'], translation);
    },

    removeLockTagsFromString(str) {
        return UI.cleanTextFromPlaceholdersSpan(str);
    },

    addSegmentVersions(fid, sid, versions) {

        let index = this.getSegmentIndex(sid, fid);
        if (versions.length === 1 && versions[0].id === 0 && versions[0].translation == "") {
            // TODO Remove this if
            this._segments[fid] = this._segments[fid].setIn([index, 'versions'], Immutable.fromJS([]));
            return this._segments[fid].get(index);
        }
        this._segments[fid] = this._segments[fid].setIn([index, 'versions'], Immutable.fromJS(versions));
        return this._segments[fid].get(index);
    },

    addSegmentVersionIssue(fid, sid, issue, versionNumber) {
        let index = this.getSegmentIndex(sid, fid);
        let versionIndex = this._segments[fid].get(index).get('versions').findIndex(function (item) {
            return item.get('version_number') === versionNumber;
        });

        this._segments[fid] = this._segments[fid].updateIn([index, 'versions', versionIndex, 'issues'], arr => arr.push(Immutable.fromJS(issue)));

        return this._segments[fid].get(index);
    },
    getSegmentByIdToJS(sid, fid) {
        return this._segments[fid].find(function (seg) {
            return seg.get('sid') == sid;
        }).toJS();

    },

    getAllSegments: function () {
        var result = [];
        $.each(this._segments, function (key, value) {
            result = result.concat(value.toJS());
        });
        return result;
    },
    setToggleBulkOption: function (sid, fid) {
        let index = this.getSegmentIndex(sid, fid);
        if (this._segments[fid].getIn([index, 'inBulk'])) {
            let indexArray = this.segmentsInBulk.indexOf(sid);
            this.segmentsInBulk.splice(indexArray, 1);
            this._segments[fid] = this._segments[fid].setIn([index, 'inBulk'], false);
        } else {
            this.segmentsInBulk.push(sid);
            this._segments[fid] = this._segments[fid].setIn([index, 'inBulk'], true);
        }
    },
    removeBulkOption: function () {
        let self = this;
        _.forEach(this._segments, function (item, index) {
            self._segments[index] = self._segments[index].map(segment => segment.set('inBulk', false));
        });
        this.segmentsInBulk = [];
    },
    setBulkSelectionInterval: function (from, to, fid) {
        let index = this.getSegmentIndex(from, fid);
        if (index > -1 &&
            this._segments[fid].get(index).get("readonly") == "false" &&  //not readonly
            (this._segments[fid].get(index).get("ice_locked") === "0" ||  //not ice_locked
                (this._segments[fid].get(index).get("ice_locked") === "1" && this._segments[fid].get(index).get("unlocked"))  //unlocked
            )
        ) {
            this._segments[fid] = this._segments[fid].setIn([index, 'inBulk'], true);
            if (this.segmentsInBulk.indexOf(from.toString()) === -1) {
                this.segmentsInBulk.push(from.toString());
            }
        }
        if (from < to) {
            this.setBulkSelectionInterval(from + 1, to, fid);
        }
    },
    setBulkSelectionSegments: function (segmentsArray) {
        let self = this;
        this.segmentsInBulk = segmentsArray;
        _.forEach(this._segments, function (item, index) {
            self._segments[index] = self._segments[index].map(function (segment) {
                if (segmentsArray.indexOf(segment.get('sid')) > -1) {
                    if (segment.get('ice_locked') == "1" && !segment.get('unlocked')) {
                        let index = segmentsArray.indexOf(segment.get('sid'));
                        self.segmentsInBulk.splice(index, 1);  // if is a locked segment remove it from bulk
                    } else {
                        return segment.set('inBulk', true);
                    }
                }
                return segment.set('inBulk', false);
            });
        });
    },
    setMutedSegments: function (segmentsArray) {
        let self = this;
        _.forEach(this._segments, function (item, index) {
            self._segments[index] = self._segments[index].map(function (segment) {
                if (segmentsArray.indexOf(segment.get('sid')) === -1) {
                    return segment.set('muted', true);
                }
                return segment;
            });
        });
    },
    removeAllMutedSegments: function () {
        let self = this;
        _.forEach(this._segments, function (item, index) {
            self._segments[index] = self._segments[index].map(segment => segment.set('muted', false));
        });
    },
    setUnlockedSegment: function (sid, fid, unlocked) {
        let index = this.getSegmentIndex(sid, fid);
        this._segments[fid] = this._segments[fid].setIn([index, 'unlocked'], unlocked);
    },

    filterGlobalWarning: function (type, sid) {
        if (type === "TAGS") {

            let fid = this._segmentsFiles.get(sid);
            if ( !fid) {
                fid = this._segmentsFiles.get(sid + "-1");
            }
            let index = this.getSegmentIndex(sid, fid);
            let segment = this._segments[fid].get(index);
            return segment.get('tagged');
        }

        return sid > -1
    },
    // Local warnings
    setSegmentWarnings(sid, warning) {
        const fid = this._segmentsFiles.get(sid);
        let index = this.getSegmentIndex(sid, fid);
        this._segments[fid] = this._segments[fid].setIn([index, 'warnings'], Immutable.fromJS(warning));
    },
    updateGlobalWarnings: function (warnings) {
        Object.keys(warnings).map(key => {
            Object.keys(warnings[key].Categories).map(key2 => {
                warnings[key].Categories[key2] = warnings[key].Categories[key2].filter(this.filterGlobalWarning.bind(this, key2));
            });
        });
        this._globalWarnings.matecat = warnings;
    },
    updateLexiqaWarnings: function(warnings){
        this._globalWarnings.lexiqa = warnings.filter(this.filterGlobalWarning.bind(this, "LXQ"));
    },
    emitChange: function (event, args) {
        this.emit.apply(this, arguments);
    }
});


// Register callback to handle all updates
AppDispatcher.register(function (action) {

    switch (action.actionType) {
        case SegmentConstants.RENDER_SEGMENTS:
            SegmentStore.updateAll(action.segments, action.fid);
            SegmentStore.emitChange(action.actionType, SegmentStore._segments[action.fid], action.fid);
            break;
        case SegmentConstants.ADD_SEGMENTS:
            SegmentStore.updateAll(action.segments, action.fid, action.where);
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments[action.fid], action.fid);
            break;
        case SegmentConstants.SPLIT_SEGMENT:
            SegmentStore.splitSegment(action.oldSid, action.newSegments, action.fid, action.splitGroup);
            SegmentStore.emitChange(action.actionType, SegmentStore._segments[action.fid], action.splitGroup, action.fid);
            break;
        case SegmentConstants.HIGHLIGHT_EDITAREA:
            SegmentStore.emitChange(action.actionType, action.id);
            break;
        case SegmentConstants.ADD_SEGMENT_CLASS:
            SegmentStore.emitChange(action.actionType, action.id, action.newClass);
            break;
        case SegmentConstants.ADD_SEGMENTS_CLASS:
            SegmentStore.emitChange(action.actionType, action.sidList, action.newClass);
            break;
        case SegmentConstants.REMOVE_SEGMENT_CLASS:
            SegmentStore.emitChange(action.actionType, action.id, action.className);
            break;
        case SegmentConstants.SET_SEGMENT_STATUS:
            SegmentStore.setStatus(action.id, action.fid, action.status);
            SegmentStore.emitChange(SegmentConstants.SET_SEGMENT_STATUS, action.id, action.status);
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
        case SegmentConstants.REPLACE_TRANSLATION:
            let trans = SegmentStore.replaceTranslation(action.id, action.fid, action.translation);
            SegmentStore.emitChange(action.actionType, action.id, trans);
            break;
        case SegmentConstants.REPLACE_SOURCE:
            let source = SegmentStore.replaceSource(action.id, action.fid, action.source);
            // SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments[action.fid], action.fid);
            SegmentStore.emitChange(action.actionType, action.id, source);
            break;
        case SegmentConstants.ADD_EDITAREA_CLASS:
            SegmentStore.emitChange(action.actionType, action.id, action.className);
            break;
        case SegmentConstants.TRANSLATION_EDITED:
            let translation = SegmentStore.replaceTranslation(action.id, action.fid, action.translation);
            SegmentStore.emitChange(action.actionType, action.id, action.translation);
            break;
        case SegmentConstants.REGISTER_TAB:
            SegmentStore.emitChange(action.actionType, action.tab, action.visible, action.open);
            break;
        case SegmentConstants.CREATE_FOOTER:
            SegmentStore.emitChange(action.actionType, action.sid);
            break;
        case SegmentConstants.SET_CONTRIBUTIONS:
            SegmentStore.emitChange(action.actionType, action.sid, action.matches, action.fieldTest);
            break;
        case SegmentConstants.CHOOSE_CONTRIBUTION:
            SegmentStore.emitChange(action.actionType, action.sid, action.index);
            break;
        case SegmentConstants.RENDER_GLOSSARY:
            SegmentStore.emitChange(action.actionType, action.sid, action.segment);
            break;
        case SegmentConstants.MOUNT_TRANSLATIONS_ISSUES:
            SegmentStore.emitChange(action.actionType);
            break;
        case SegmentConstants.SET_SEGMENT_TAGGED:
            SegmentStore.setSegmentAsTagged(action.id, action.fid);
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments[action.fid], action.fid);
            break;
        case SegmentConstants.SET_SEGMENT_ORIGINAL_TRANSLATION:
            SegmentStore.setSegmentOriginalTranslation(action.id, action.fid, action.originalTranslation);
            SegmentStore.emitChange(SegmentConstants.SET_SEGMENT_ORIGINAL_TRANSLATION, action.id, action.originalTranslation);
            break;
        case SegmentConstants.RENDER_REVISE_ISSUES:
            SegmentStore.emitChange(SegmentConstants.RENDER_REVISE_ISSUES, action.sid, action.data);
            break;
        case SegmentConstants.ADD_SEGMENT_VERSIONS_ISSUES:
            let seg = SegmentStore.addSegmentVersions(action.fid, action.sid, action.versions);
            SegmentStore.emitChange(action.actionType, action.sid, seg.toJS());
            break;
        case SegmentConstants.ADD_SEGMENT_VERSION_ISSUE:
            let segIssue = SegmentStore.addSegmentVersionIssue(action.fid, action.sid, action.issue, action.versionNumber);
            SegmentStore.emitChange(SegmentConstants.ADD_SEGMENT_VERSIONS_ISSUES, action.sid, segIssue.toJS());
            break;
        case SegmentConstants.ADD_TAB_INDEX:
            SegmentStore.emitChange(action.actionType, action.sid, action.tab, action.data);
            break;
        case SegmentConstants.TOGGLE_SEGMENT_ON_BULK:
            SegmentStore.setToggleBulkOption(action.sid, action.fid);
            SegmentStore.emitChange(SegmentConstants.SET_BULK_SELECTION_SEGMENTS, SegmentStore.segmentsInBulk);
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments[action.fid], action.fid);
            break;
        case SegmentConstants.REMOVE_SEGMENTS_ON_BULK:
            SegmentStore.removeBulkOption();
            SegmentStore.emitChange(SegmentConstants.REMOVE_SEGMENTS_ON_BULK, []);
            _.forEach(SegmentStore._segments, function (item, index) {
                SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments[index], index);
            });
            break;
        case SegmentConstants.SET_BULK_SELECTION_INTERVAL:
            SegmentStore.setBulkSelectionInterval(action.from, action.to, action.fid);
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments[action.fid], action.fid);
            SegmentStore.emitChange(SegmentConstants.SET_BULK_SELECTION_SEGMENTS, SegmentStore.segmentsInBulk);
            break;
        case SegmentConstants.SET_BULK_SELECTION_SEGMENTS:
            SegmentStore.setBulkSelectionSegments(action.segmentsArray);
            _.forEach(SegmentStore._segments, function (item, index) {
                SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments[index], index);
            });
            SegmentStore.emitChange(SegmentConstants.SET_BULK_SELECTION_SEGMENTS, SegmentStore.segmentsInBulk);
            break;
        case SegmentConstants.SET_UNLOCKED_SEGMENT:
            SegmentStore.setUnlockedSegment(action.sid, action.fid, action.unlocked);
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments[action.fid], action.fid);
            break;
        case SegmentConstants.SET_MUTED_SEGMENTS:
            SegmentStore.setMutedSegments(action.segmentsArray);
            _.forEach(SegmentStore._segments, function (item, index) {
                SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments[index], index);
            });
            break;
        case SegmentConstants.REMOVE_MUTED_SEGMENTS:
            SegmentStore.removeAllMutedSegments();
            _.forEach(SegmentStore._segments, function (item, index) {
                SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments[index], index);
            });
            break;
        case SegmentConstants.DISABLE_TAG_LOCK:
        case SegmentConstants.ENABLE_TAG_LOCK:
            SegmentStore.decodeSegmentsText();
            _.forEach(SegmentStore._segments, function (item, index) {
                SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments[index], index);
            });
            // Todo remove this
            SegmentStore.emitChange(action.actionType);
            break;
        case SegmentConstants.SET_SEGMENT_WARNINGS:  // LOCAL
            SegmentStore.setSegmentWarnings(action.sid, action.warnings);
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments[SegmentStore._segmentsFiles.get(action.sid)], SegmentStore._segmentsFiles.get(action.sid));
            break;
        case SegmentConstants.UPDATE_GLOBAL_WARNINGS:
            SegmentStore.updateGlobalWarnings(action.warnings);
            SegmentStore.emitChange(action.actionType, SegmentStore._globalWarnings);
            break;

        case SegmentConstants.QA_LEXIQA_ISSUES:
            SegmentStore.updateLexiqaWarnings(action.warnings);
            SegmentStore.emitChange(SegmentConstants.UPDATE_GLOBAL_WARNINGS, SegmentStore._globalWarnings);
            break;
        default:
            SegmentStore.emitChange(action.actionType, action.sid, action.data);
    }
});

module.exports = SegmentStore;