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
    addSegments: function (segments, fid, where) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.ADD_SEGMENTS,
            segments: segments,
            fid: fid,
            where: where
        });
    },

    updateAllSegments: function () {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.UPDATE_ALL_SEGMENTS
        });
    },

    mountTranslationIssues: function () {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.MOUNT_TRANSLATIONS_ISSUES
        });
    },

    /********** Segment **********/

    addClassToSegment: function (sid, newClass) {
        setTimeout( function () {
            AppDispatcher.dispatch({
                actionType: SegmentConstants.ADD_SEGMENT_CLASS,
                id: sid,
                newClass: newClass
            });
        }, 0)
    },

    removeClassToSegment: function (sid, className) {
        setTimeout( function () {
            AppDispatcher.dispatch({
                actionType: SegmentConstants.REMOVE_SEGMENT_CLASS,
                id: sid,
                className: className
            });
        }, 0)
    },

    setStatus: function (sid, fid, status) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.SET_SEGMENT_STATUS,
            id: sid,
            fid: fid,
            status: status
        });
    },
    
    setHeaderPercentage: function (sid, fid, perc, className, createdBy) {
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

    setSegmentAsTagged: function (sid, fid) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.SET_SEGMENT_TAGGED,
            id: sid,
            fid: fid,
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
    },
    updateTranslation: function (sid, editAreaText) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.UPDATE_TRANSLATION,
            id: sid,
            text: editAreaText
        });
    },
    /************ FOOTER ***************/
    registerTab: function (tab, visible, open) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.REGISTER_TAB,
            tab: tab,
            visible: visible,
            open: open
        });
    },
    createFooter: function (sid) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.CREATE_FOOTER,
            sid: sid
        });
    },
    setSegmentContributions: function (sid, contributions, fieldTest) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.SET_CONTRIBUTIONS,
            sid: sid,
            matches: contributions,
            fieldTest: fieldTest
        });
    },
    chooseContribution: function (sid, index) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.CHOOSE_CONTRIBUTION,
            sid: sid,
            index: index
        });
    },
    renderSegmentGlossary: function(sid, matches) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.RENDER_GLOSSARY,
            sid: sid,
            matches: matches
        });
    },

    activateTab: function (sid, tab) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.OPEN_TAB,
            sid: sid,
            data: tab
        });
    },


    /************ Revise ***************/
    showSelection: function (sid, data) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.SHOW_SELECTION,
            sid: sid,
            data: data
        });
    },

    openIssuesPanel: function (data) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.OPEN_ISSUES_PANEL,
            data: data,
        });

        UI.openIssuesPanel(data);
    },

    closeIssuesPanel: function () {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.CLOSE_ISSUES_PANEL
        });
    },

    renderReviseErrors: function (sid, data) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.RENDER_REVISE_ISSUES,
            sid: sid,
            data: data
        });
    },

    submitIssue: function (sid, data, diff) {
        return UI.submitIssues(sid, data, diff);
    },

    addTranslationIssuesToSegment: function (fid, sid, versions) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.ADD_SEGMENT_VERSIONS_ISSUES,
            fid: fid,
            sid: sid,
            versions: versions
        });
    },

    addSegmentVersionIssue: function (fid, sid, issue, versionNumber) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.ADD_SEGMENT_VERSION_ISSUE,
            fid: fid,
            sid: sid,
            issue: issue,
            versionNumber: versionNumber
        });
    },

    deleteIssue: function (issue) {
        UI.deleteIssue(issue);
    },

    submitComment: function (sid, idIssue, data) {
        return UI.submitComment(sid, idIssue, data);
    }



};

module.exports = SegmentActions;