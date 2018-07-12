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
var SegmentStore = require('../stores/SegmentStore');

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
        }, 0);
    },

    addClassToSegments: function (sidList, newClass) {
        setTimeout( function () {
            AppDispatcher.dispatch({
                actionType: SegmentConstants.ADD_SEGMENTS_CLASS,
                sidList: sidList,
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
        if ( sid && fid ) {
            AppDispatcher.dispatch({
                actionType: SegmentConstants.SET_SEGMENT_STATUS,
                id: sid,
                fid: fid,
                status: status
            });
        }
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
    /**
     * Set the original translation of a segment.
     * Used to create the revision trackChanges
     * @param sid
     * @param fid
     * @param originalTranslation
     */
    addOriginalTranslation: function (sid, fid, originalTranslation) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.SET_SEGMENT_ORIGINAL_TRANSLATION,
            id: sid,
            fid: fid,
            originalTranslation: originalTranslation
        });
    },

    disableTagLock: function (  ) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.DISABLE_TAG_LOCK
        });
    },
    enableTagLock: function (  ) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.ENABLE_TAG_LOCK
        });
    },

    setSegmentWarnings: function(sid, warnings){
        AppDispatcher.dispatch({
            actionType: SegmentConstants.SET_SEGMENT_WARNINGS,
            sid: sid,
            warnings: warnings
        });
    },

    updateGlobalWarnings: function(warnings){
        AppDispatcher.dispatch({
            actionType: SegmentConstants.UPDATE_GLOBAL_WARNINGS,
            warnings: warnings
        });
    },

    qaComponentsetLxqIssues: function ( issues ) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.QA_LEXIQA_ISSUES,
            warnings: issues
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
    updateTranslation: function (fid, sid, editAreaText) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.TRANSLATION_EDITED,
            fid: fid,
            id: sid,
            translation: editAreaText
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
    renderSegmentGlossary: function(sid, segment) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.RENDER_GLOSSARY,
            sid: sid,
            segment: segment
        });
    },

    activateTab: function (sid, tab) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.OPEN_TAB,
            sid: sid,
            data: tab
        });
    },
    closeTabs: function ( sid ) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.CLOSE_TABS,
            sid: sid,
            data: null
        });
    },


    renderPreview: function ( sid, data ) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.RENDER_PREVIEW,
            sid: sid,
            data: data
        });
    },

    getGlossaryMatch: function ( text ) {
        return API.SEGMENT.getGlossaryMatch(text)
            .fail(function (  ) {
                UI.failedConnection( 0, 'glossary' );
        });
    },

    getGlossaryForSegment: function ( text ) {
        return API.SEGMENT.getGlossaryForSegment(text)
            .fail(function (  ) {
                UI.failedConnection( 0, 'glossary' );
            });
    },

    deleteGlossaryItem: function ( source, target ) {
        return API.SEGMENT.deleteGlossaryItem(source, target)
            .fail(function (  ) {
                UI.failedConnection( 0, 'deleteGlossaryItem' );
            });
    },

    addGlossaryItem: function ( source, target, comment ) {
        return API.SEGMENT.addGlossaryItem(source, target, comment)
            .fail(function (  ) {
                UI.failedConnection( 0, 'addGlossaryItem' );
            });
    },

    updateGlossaryItem: function ( idItem, source, target, newTranslation, comment, newComment ) {
        return API.SEGMENT.updateGlossaryItem(idItem, source, target, newTranslation, comment, newComment)
            .fail(function (  ) {
                UI.failedConnection( 0, 'updateGlossaryItem' );
            });
    },

    setTabIndex: function ( sid, tab, index ) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.ADD_TAB_INDEX,
            sid: sid,
            tab: tab,
            data: index
        });
    },

    findConcordance: function ( sid, data ) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.FIND_CONCORDANCE,
            sid: sid,
            data: data
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

    showIssuesMessage: function ( sid ) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.SHOW_ISSUE_MESSAGE,
            sid: sid,
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

    confirmDeletedIssue: function (sid,issue_id) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.ISSUE_DELETED,
            sid: sid,
            data: issue_id
        });
    },

    submitComment: function (sid, idIssue, data) {
        return UI.submitComment(sid, idIssue, data);
    },

    toggleSegmentOnBulk: function (sid, fid) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.TOGGLE_SEGMENT_ON_BULK,
            fid: fid,
            sid: sid
        });
    },

    removeSegmentsOnBulk: function() {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.REMOVE_SEGMENTS_ON_BULK,
        });
        UI.setWaypoints();
    },

    setSegmentLocked( segment, fid, unlocked) {
        if (!unlocked) {
            //TODO: move this to SegmentActions
            UI.removeFromStorage('unlocked-' + segment.sid);
            if (segment.inBulk) {
                this.toggleSegmentOnBulk(segment.sid, fid);
            }
        } else {
            UI.addInStorage('unlocked-'+ segment.sid, true);
            UI.editAreaClick(UI.getEditAreaBySegmentId(segment.sid));
        }
        AppDispatcher.dispatch({
            actionType: SegmentConstants.SET_UNLOCKED_SEGMENT,
            fid: fid,
            sid: segment.sid,
            unlocked: unlocked
        });
    },

    setBulkSelectionInterval(from, to, fid) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.SET_BULK_SELECTION_INTERVAL,
            from: from,
            to: to,
            fid: fid
        });
    },
    setBulkSelectionSegments(segmentsArray) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.SET_BULK_SELECTION_SEGMENTS,
            segmentsArray: segmentsArray
        });
        UI.setWaypoints();
    },
    setMutedSegments(segmentsArray) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.SET_MUTED_SEGMENTS,
            segmentsArray: segmentsArray
        });
        UI.setWaypoints();
    },
    removeAllMutedSegments() {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.REMOVE_MUTED_SEGMENTS
        });
    }



};

module.exports = SegmentActions;