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

    _segments : {},

    /**
     * Update all
     */
    updateAll: function(segments, fid, where) {
        console.time("Time: updateAll segments"+fid);
        if ( this._segments[fid] && where === "before" ) {
            this._segments[fid] = this._segments[fid].unshift(Immutable.fromJS(this.normalizeSplittedSegments(segments)));
        } else if( this._segments[fid] && where === "after" ) {
            this._segments[fid] = this._segments[fid].push(Immutable.fromJS(this.normalizeSplittedSegments(segments)));
        } else {
            this._segments[fid] = Immutable.fromJS(this.normalizeSplittedSegments(segments));
        }
        console.timeEnd("Time: updateAll segments"+fid);
    },

    normalizeSplittedSegments: function(segments) {
        var newSegments = [];
        $.each(segments, function (index) {
            var splittedSourceAr = this.segment.split(UI.splittedTranslationPlaceholder);
            if(splittedSourceAr.length > 1) {
                var segment = this;
                var splitGroup = [];
                $.each(splittedSourceAr, function (i) {
                    splitGroup.push(segment.sid + '-' + (i + 1));
                });

                $.each(splittedSourceAr, function (i) {
                    var translation = segment.translation.split(UI.splittedTranslationPlaceholder)[i];
                    var status = segment.target_chunk_lengths.statuses[i];
                    var segData = {
                        autopropagated_from: "0",
                        has_reference: "false",
                        parsed_time_to_edit: ["00", "00", "00", "00"],
                        readonly: "false",
                        segment: splittedSourceAr[i],
                        segment_hash: segment.segment_hash,
                        sid: segment.sid + '-' + (i + 1),
                        split_group: splitGroup,
                        split_points_source: [],
                        status: status,
                        time_to_edit: "0",
                        translation: translation,
                        version: segment.version,
                        warning: "0",
                        tagged: false
                    };
                    newSegments.push(segData);
                    segData = null;
                });
            } else {
                newSegments.push(this);
            }

        });
        return newSegments;
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
            });

            newSegments = Immutable.fromJS(newSegments);
            this._segments[fid] = this._segments[fid].splice(index, 1, ...newSegments);
        } else {
            this.removeSplit(oldSid, newSegments, fid);
        }
    },

    removeSplit(oldSid, newSegments, fid) {
        var self = this;
        var elementsToRemove = [];
        newSegments = Immutable.fromJS(newSegments);
        var indexes = [];
        this._segments[fid].map(function (segment, index) {
            if (segment.get('sid').split('-').length && segment.get('sid').split('-')[0] == oldSid){
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
        var trans = this.removeLockTagsFromString(translation);
        this._segments[fid] = this._segments[fid].setIn([index, 'translation'], trans);
        return trans;
    },
    replaceSource(sid, fid, source) {
        var index = this.getSegmentIndex(sid, fid);
        var trans = this.removeLockTagsFromString(source);
        this._segments[fid] = this._segments[fid].setIn([index, 'translation'], trans);
        return trans;
    },

    setSegmentAsTagged(sid, fid) {
        var index = this.getSegmentIndex(sid, fid);
        this._segments[fid] = this._segments[fid].setIn([index, 'tagged'], true);
    },
    removeLockTagsFromString(str) {
        return str.replace(/<span contenteditable=\"false\" class=\"locked[^>]*\>(.*?)<\/span\>/gi, "$1");
    },

    addSegmentVersions(fid, sid, versions) {

        let index = this.getSegmentIndex(sid, fid);
        if (versions.length === 1 && versions[0].id === 0 && versions[0].translation == "")  {
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

    getAllSegments: function () {
        var result = [];
        $.each(this._segments, function(key, value) {
            result = result.concat(value.toJS());
        });
        return result;
    },
    emitChange: function(event, args) {
        this.emit.apply(this, arguments);
    }
});


// Register callback to handle all updates
AppDispatcher.register(function(action) {

    switch(action.actionType) {
        case SegmentConstants.RENDER_SEGMENTS:
            SegmentStore.updateAll(action.segments, action.fid);
            SegmentStore.emitChange(action.actionType, SegmentStore._segments[action.fid], action.fid);
            break;
        case SegmentConstants.ADD_SEGMENTS:
            SegmentStore.updateAll(action.segments, action.fid, action.where);
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments[action.fid].toJS(), action.fid);
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
            var trans = SegmentStore.replaceTranslation(action.id, action.fid, action.translation);
            SegmentStore.emitChange(action.actionType, action.id, trans);
            break;
        case SegmentConstants.REPLACE_SOURCE:
            var source = SegmentStore.replaceSource(action.id, action.fid, action.source);
            SegmentStore.emitChange(action.actionType, action.id, source);
            break;
        case SegmentConstants.ADD_EDITAREA_CLASS:
            SegmentStore.emitChange(action.actionType, action.id, action.className);
            break;
        case SegmentConstants.UPDATE_TRANSLATION:
            SegmentStore.emitChange(action.actionType, action.id, action.text);
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
            SegmentStore.emitChange(action.actionType, action.sid, action.matches);
            break;
        case SegmentConstants.MOUNT_TRANSLATIONS_ISSUES:
            SegmentStore.emitChange(action.actionType);
            break;
        case SegmentConstants.SET_SEGMENT_TAGGED:
            SegmentStore.setSegmentAsTagged(action.id, action.fid)
            SegmentStore.emitChange(SegmentConstants.RENDER_SEGMENTS, SegmentStore._segments[action.fid], action.fid);
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
        default:
            SegmentStore.emitChange(action.actionType, action.sid, action.data);
    }
});

module.exports = SegmentStore;