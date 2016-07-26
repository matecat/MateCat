/*
 * TodoStore
 */

var AppDispatcher = require('../dispatcher/AppDispatcher');
var EventEmitter = require('events').EventEmitter;
var SegmentConstants = require('../constants/SegmentConstants');
var assign = require('object-assign');

EventEmitter.prototype.setMaxListeners(0);
// Todo : Possiamo gestire la persistenza qui dentro con LokiJS

var _segments = {};

/**
 * Update all
 */
function updateAll(segments, fid) {
    // if ( _segments[fid] ) {
    //
    // } else {
        _segments[fid] = normalizeSplittedSegments(segments);
    // }
}

function normalizeSplittedSegments(segments) {
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
                    warning: "0"
                };
                newSegments.push(segData);
                segData = null;
            });
        } else {
            newSegments.push(this);
        }

    });
    return newSegments;
}

function splitSegment(oldSid, newSegments, fid) {
    var currentSegments = _segments[fid];
    var index = currentSegments.findIndex(function (segment, index) {
        return (segment.sid == oldSid);
    });
    if (index > -1) {
        Array.prototype.splice.apply(currentSegments, [index, 1].concat(newSegments));
    } else {
        removeSplit(oldSid, newSegments, currentSegments);
    }
}

function removeSplit(oldSid, newSegments, currentSegments) {
    var elementsToRemove = [];
    var indexes = [];
    currentSegments.map(function (segment, index) {
        if (segment.sid.split('-').length && segment.sid.split('-')[0] == oldSid){
            elementsToRemove.push(segment);
            indexes.push(index);
            return index;
        }
    });
    if (elementsToRemove.length) {
        elementsToRemove.forEach(function (seg) {
            currentSegments.splice(currentSegments.indexOf(seg), 1)
        });
        Array.prototype.splice.apply(currentSegments, [indexes[0], 0].concat(newSegments));
    }
}


var SegmentStore = assign({}, EventEmitter.prototype, {

    emitChange: function(event, args) {
        this.emit.apply(this, arguments);
    },
    getAllSegments: function () {
        var result = [];
        $.each(_segments, function(key, value) {
            result = result.concat(value);
        });
        return result;
    }
});


// Register callback to handle all updates
AppDispatcher.register(function(action) {

    switch(action.actionType) {
        case SegmentConstants.RENDER_SEGMENTS:
            updateAll(action.segments, action.fid);
            SegmentStore.emitChange(action.actionType, _segments[action.fid], action.fid);
            break;
        case SegmentConstants.SPLIT_SEGMENT:
            splitSegment(action.oldSid, action.newSegments, action.fid);
            SegmentStore.emitChange(action.actionType, _segments[action.fid], action.splitAr, action.splitGroup, action.fid);
            break;
        case SegmentConstants.HIGHLIGHT_EDITAREA:
            SegmentStore.emitChange(action.actionType, action.id);
            break;
        case SegmentConstants.ADD_SEGMENT_CLASS:
            SegmentStore.emitChange(action.actionType, action.id, action.newClass);
            break;
        // case SegmentConstants.REPLACE_CONTENT:
        //     SegmentStore.emitChange(action.actionType, action.id, action.text);
        //     break;
        default:
    }
});

module.exports = SegmentStore;