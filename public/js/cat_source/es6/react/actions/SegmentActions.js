/*
 * Copyright (c) 2014-2015, Facebook, Inc.
 * All rights reserved.
 *
 * This source code is licensed under the BSD-style license found in the
 * LICENSE file in the root directory of this source tree. An additional grant
 * of patent rights can be found in the PATENTS file in the same directory.
 *
 * TodoActions
 */

var AppDispatcher = require('../dispatcher/AppDispatcher');
var SegmentConstants = require('../constants/SegmentConstants');


var SegmentActions = {
    /********* SEGMENTS *********/
    /**
     * @param segments
     * @param fid
     */
    renderSegments: function (segments, fid) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.RENDER_SEGMENTS,
            segments: segments,
            fid: fid
        });
    },
    splitSegments: function (oldSid, newSegments, splitGroup, fid) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.SPLIT_SEGMENT,
            oldSid: oldSid,
            newSegments: newSegments,
            splitGroup: splitGroup,
            fid: fid
        });
    },
    /* TODO
     */
    propagateTranslation: function (sid, status) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.PROPAGATE_TRANSLATION,
            id: sid,
            status: status
        });
    },

    updateAllSegments: function () {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.UPDATE_ALL_SEGMENTS
        });
    },

    /********** Segment **********/

    addClassToSegment: function (sid, newClass) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.ADD_SEGMENT_CLASS,
            id: sid,
            newClass: newClass
        });
    },

    setStatus: function (sid, fid, status) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.SET_SEGMENT_STATUS,
            id: sid,
            fid: fid,
            status: status
        });
    },
    
    setHeaderPercentuage: function (sid, fid, perc, className, createdBy) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.SET_SEGMENT_HEADER,
            id: sid,
            fid: fid,
            perc: perc,
            className: className,
            createdBy: createdBy
        });
    },

    hideSegmentHeader: function (sid, fid) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.HIDE_SEGMENT_HEADER,
            id: sid,
            fid: fid
        });
    },

    setSegmentPropagation: function (sid, fid, propagation, from) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.SET_SEGMENT_PROPAGATION,
            id: sid,
            fid: fid,
            propagation: propagation,
            from: from
        });
    },

    replaceSourceText: function(sid, fid, text) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.REPLACE_SOURCE,
            id: sid,
            fid: fid,
            source: text
        });
    },

    /******************* EditArea ************/
    highlightEditarea: function(sid) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.HIGHLIGHT_EDITAREA,
            id: sid
        });
    },

    replaceEditAreaTextContent: function(sid, fid, text) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.REPLACE_TRANSLATION,
            id: sid,
            fid: fid,
            translation: text
        });
    },

    addClassToEditArea: function(sid, fid, className) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.ADD_EDITAREA_CLASS,
            id: sid,
            fid: fid,
            className: className
        });
    }




};

module.exports = SegmentActions;