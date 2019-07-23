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

const AppDispatcher = require('../dispatcher/AppDispatcher');
const SegmentConstants = require('../constants/SegmentConstants');
const SegmentStore = require('../stores/SegmentStore');
const GlossaryUtils = require('../components/segments/utils/glossaryUtils');


var SegmentActions = {
    /********* SEGMENTS *********/
    /**
     * @param segments
     */
    renderSegments: function (segments, idToOpen) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.RENDER_SEGMENTS,
            segments: segments,
            idToOpen: idToOpen
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
    splitSegment: function(sid, text)  {
        API.SEGMENT.splitSegment(sid , text)
            .done(function (response) {
                if(response.errors.length) {
                    var notification = {
                        title: 'Error',
                        text: d.errors[0].message,
                        type: 'error'
                    };
                    APP.addNotification(notification);
                } else {
                    UI.unmountSegments();
                    UI.render({
                        segmentToOpen: UI.currentSegmentId.split('-')[0]
                    });
                }
            })
            .fail(function (d) {
                var notification = {
                    title: 'Error',
                    text: d.errors[0].message,
                    type: 'error'
                };
                APP.addNotification(notification);
            });
    },
    addSegments: function (segments, where) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.ADD_SEGMENTS,
            segments: segments,
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
    setOpenSegment: function (sid, fid) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.SET_OPEN_SEGMENT,
            sid: sid,
            fid: fid
        });
    },

    openSegment: function (sid) {
        const segment = SegmentStore.getSegmentByIdToJS(sid);
        if ( segment ) {
            AppDispatcher.dispatch({
                actionType: SegmentConstants.OPEN_SEGMENT,
                sid: sid
            });
        } else {
            UI.unmountSegments();
            UI.render({
                firstLoad: false,
                segmentToScroll: sid
            });
        }
    },
    closeSegment: function ( sid, fid ) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.CLOSE_SEGMENT,
        });
        this.closeIssuesPanel();
    },
    scrollToSegment: function (sid, callback) {
        const segment = SegmentStore.getSegmentByIdToJS(sid);
        if ( segment ) {
            AppDispatcher.dispatch({
                actionType: SegmentConstants.SCROLL_TO_SEGMENT,
                sid: sid,
            });
            if (callback) {
                callback.apply(this, [sid]);
            }
        } else {
            UI.unmountSegments();
            UI.render({
                firstLoad: false,
                segmentToScroll: sid
            }).done(()=> callback && setTimeout(()=>callback.apply(this, [sid]), 1000));
        }
    },
    addClassToSegment: function (sid, newClass) {
        setTimeout( function () {
            AppDispatcher.dispatch({
                actionType: SegmentConstants.ADD_SEGMENT_CLASS,
                id: sid,
                newClass: newClass
            });
        }, 0);
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
    setChoosenSuggestion: function( sid, index) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.SET_CHOOSEN_SUGGESTION,
            sid: sid,
            index: index
        });
    },
    addQaCheckMatches: function(sid, matches) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.SET_QA_CHECK_MATCHES,
            sid: sid,
            matches: matches
        });
    },
    addQaBlacklistMatches: function(sid, matches) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.SET_QA_BLACKLIST_MATCHES,
            sid: sid,
            matches: matches
        });
    },
    /******************* EditArea ************/
    highlightEditarea: function(sid) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.HIGHLIGHT_EDITAREA,
            id: sid
        });
    },
    modifiedTranslation: function (sid, fid, status) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.MODIFIED_TRANSLATION,
            sid: sid,
            fid: fid,
            status: status
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
    lockEditArea : function ( sid, fid ) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.LOCK_EDIT_AREA,
            fid: fid,
            id: sid,
        });
    },
    showTagsMenu: function(sid) {
        if ( !UI.checkCurrentSegmentTPEnabled() ) {
            AppDispatcher.dispatch({
                actionType: SegmentConstants.OPEN_TAGS_MENU,
                sid: sid,
            });
        }
    },
    closeTagsMenu: function() {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.CLOSE_TAGS_MENU
        });
    },
    /************ SPLIT ****************/
    openSplitSegment: function(sid) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.OPEN_SPLIT_SEGMENT,
            sid: sid
        });
    },
    closeSplitSegment: function() {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.CLOSE_SPLIT_SEGMENT
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
    setSegmentContributions: function (sid, fid, contributions, errors) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.SET_CONTRIBUTIONS,
            sid: sid,
            fid: fid,
            matches: contributions,
            errors: errors
        });
    },
    setSegmentCrossLanguageContributions: function (sid, fid, contributions, errors) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.SET_CL_CONTRIBUTIONS,
            sid: sid,
            fid: fid,
            matches: contributions,
            errors: errors
        });
    },
    setAlternatives: function (sid, alternatives) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.SET_ALTERNATIVES,
            sid: sid,
            alternatives: alternatives
        });
    },
    chooseContribution: function (sid, index) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.CHOOSE_CONTRIBUTION,
            sid: sid,
            index: index
        });
    },
    deleteContribution: function(source, target, matchId, sid) {
        UI.setDeleteSuggestion(source, target, matchId, sid).done((data) => {
            if (data.errors.length === 0) {
                AppDispatcher.dispatch({
                    actionType: SegmentConstants.DELETE_CONTRIBUTION,
                    sid: sid,
                    matchId: matchId
                });
            }
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

    setTabOpen: function (sid, tabName ) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.SET_DEFAULT_TAB,
            tabName: tabName
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

    // getGlossaryForSegment: function ( text ) {
    //     return API.SEGMENT.getGlossaryForSegment(text)
    //         .fail(function (  ) {
    //             UI.failedConnection( 0, 'glossary' );
    //         });
    // },
    getGlossaryForSegment: function (sid, fid, text) {
        let requestes = [{
            sid: sid,
            fid: fid,
            text: text
        }];
        let nextSegment = SegmentStore.getNextSegment(sid, fid);
        if (nextSegment) {
            requestes.push({
                sid: nextSegment.sid,
                fid: nextSegment.fid,
                text: nextSegment.segment
            });
            let nextSegmentUntranslated = SegmentStore.getNextSegment(sid, fid, 8);
            if (nextSegmentUntranslated && requestes[1].sid != nextSegmentUntranslated.sid) {
                requestes.push({
                    sid: nextSegmentUntranslated.sid,
                    fid: nextSegmentUntranslated.fid,
                    text: nextSegmentUntranslated.segment
                });
            }
        }

        for (let index = 0; index < requestes.length; index++) {
            let request = requestes[index];
            let segment = SegmentStore.getSegmentByIdToJS(request.sid, request.fid);
            if (typeof segment.glossary === 'undefined') {
                API.SEGMENT.getGlossaryForSegment(request.text)
                    .done(function (response) {
                        GlossaryUtils.storeGlossaryData(request.sid, response.data.matches);
                        AppDispatcher.dispatch({
                            actionType: SegmentConstants.SET_GLOSSARY_TO_CACHE,
                            sid: request.sid,
                            fid: request.fid,
                            glossary: response.data.matches.length !== 0 ? response.data.matches : {}
                        });
                    })
                    .fail(function (error) {
                        UI.failedConnection(sid, 'getGlossaryForSegment');
                    });
            }
        }

    },

    searchGlossary: function (sid, fid, text) {
        text = UI.removeAllTags(htmlEncode(text));
        text = text.replace(/\"/g, "");
        API.SEGMENT.getGlossaryMatch(text)
            .done(response => {
                AppDispatcher.dispatch({
                    actionType: SegmentConstants.SET_GLOSSARY_TO_CACHE,
                    sid: sid,
                    fid: fid,
                    glossary: response.data.matches.length !== 0 ? response.data.matches : {}
                });
            })
            .fail(function () {
                UI.failedConnection(0, 'glossary');
            });
    },

    deleteGlossaryItem: function ( source, target, id, name, sid ) {
        return API.SEGMENT.deleteGlossaryItem(source, target, id)
            .fail(function (  ) {
                UI.failedConnection( 0, 'deleteGlossaryItem' );
            }).done(function ( data ) {
                UI.footerMessage( 'A glossary item has been deleted', UI.getSegmentById(id) );
                AppDispatcher.dispatch({
                    actionType: SegmentConstants.DELETE_FROM_GLOSSARY,
                    sid: sid,
                    matchId: id,
                    name: name
                });
            });
    },

    addGlossaryItem: function ( source, target, comment, sid ) {
        return API.SEGMENT.addGlossaryItem(source, target, comment)
            .fail(function (  ) {
                UI.failedConnection( 0, 'addGlossaryItem' );
            }).done(function ( response ) {
                if ( response.data.created_tm_key ) {
                    UI.footerMessage( 'A Private TM Key has been created for this job', UI.getSegmentById( self.props.id_segment ) );
                } else {
                    let msg = (response.errors.length) ? response.errors[0].message : 'A glossary item has been added';
                    UI.footerMessage( msg, UI.getSegmentById( sid ) );
                }
                AppDispatcher.dispatch({
                    actionType: SegmentConstants.ADD_GLOSSARY_ITEM,
                    sid: sid,
                    match: response.data.matches.length !== 0 ? response.data.matches[source] : {},
                    name: source
                });
            });
    },

    updateGlossaryItem: function ( idItem, source, target, newTranslation, comment, name, sid ) {
        return API.SEGMENT.updateGlossaryItem(idItem, source, target, newTranslation, comment)
            .fail(function (  ) {
                UI.failedConnection( 0, 'updateGlossaryItem' );
            }).done( function ( response ) {
                UI.footerMessage( 'A glossary item has been updated', UI.getSegmentById( sid ) );
                AppDispatcher.dispatch({
                    actionType: SegmentConstants.CHANGE_GLOSSARY,
                    sid: sid,
                    matchId: idItem,
                    name: name,
                    comment: comment,
                    target_note: comment,
                    translation: newTranslation
                });
            } );
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

    getContributions: function (sid, fid, target) {
        UI.getContribution(sid, 0);
        UI.getContribution(sid, 1);
        UI.getContribution(sid, 2);
    },

    setConcordanceResult: function (sid, data) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.CONCORDANCE_RESULT,
            sid: sid,
            matches: data.matches
        });
    },

    modifyTabVisibility: function(tabName, visible) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.MODIFY_TAB_VISIBILITY,
            tabName: tabName,
            visible: visible
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

    openIssuesPanel: function (data, openSegment) {
        if ( UI.openIssuesPanel(data, openSegment) ) {

            AppDispatcher.dispatch({
                actionType: SegmentConstants.OPEN_ISSUES_PANEL,
                data: data,
            });
            this.openSideSegments();
        }

    },

    closeIssuesPanel: function () {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.CLOSE_ISSUES_PANEL,
        });
    },

    closeSegmentIssuePanel: function ( sid ) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.CLOSE_ISSUES_PANEL,
            sid: sid
        });
    },

    showIssuesMessage: function ( sid, type ) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.SHOW_ISSUE_MESSAGE,
            sid: sid,
            data: type
        });
    },

    submitIssue: function (sid, data, diff) {
        return UI.submitIssues(sid, data, diff);
    },

    issueAdded: function ( sid, issueId ) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.ISSUE_ADDED,
            sid: sid,
            data: issueId
        });
    },

    openIssueComments: function ( sid, issueId ) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.OPEN_ISSUE_COMMENT,
            sid: sid,
            data: issueId
        });
        this.openSideSegments();
    },

    addPreloadedIssuesToSegment: function ( sid, issues ) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.ADD_SEGMENT_PRELOADED_ISSUES,
            sid: sid,
            data: issues
        });
    },

    addTranslationIssuesToSegment: function (fid, sid, versions) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.ADD_SEGMENT_VERSIONS_ISSUES,
            fid: fid,
            sid: sid,
            versions: versions
        });
    },

    deleteIssue: function (issue, sid, dontShowMessage) {
        UI.deleteIssue(issue, sid, dontShowMessage);
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
            SegmentActions.openSegment(segment.sid);

        }
        AppDispatcher.dispatch({
            actionType: SegmentConstants.SET_UNLOCKED_SEGMENT,
            fid: fid,
            sid: segment.sid,
            unlocked: unlocked
        }, );
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
    },
    setMutedSegments(segmentsArray) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.SET_MUTED_SEGMENTS,
            segmentsArray: segmentsArray
        });
    },
    removeAllMutedSegments() {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.REMOVE_MUTED_SEGMENTS
        });
    },

    openSideSegments() {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.OPEN_SIDE
        });
    },
    closeSideSegments() {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.CLOSE_SIDE
        });
    },
    openSegmentComment(sid) {
        this.openSideSegments();
        AppDispatcher.dispatch({
            actionType: SegmentConstants.OPEN_COMMENTS,
            sid: sid
        });
    },
    closeSegmentComment(sid) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.CLOSE_COMMENTS,
            sid: sid
        });
    }

};

module.exports = SegmentActions;