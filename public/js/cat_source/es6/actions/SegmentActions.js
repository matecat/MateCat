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

import AppDispatcher  from '../stores/AppDispatcher';
import SegmentConstants  from '../constants/SegmentConstants';
import EditAreaConstants  from '../constants/EditAreaConstants';
import SegmentStore  from '../stores/SegmentStore';
import TranslationMatches  from '../components/segments/utils/translationMatches';
import TagUtils from "../utils/tagUtils";
import TextUtils from "../utils/textUtils";
import OfflineUtils from "../utils/offlineUtils";
import CommonUtils from "../utils/commonUtils";
import SegmentUtils from "../utils/segmentUtils";
import QaCheckGlossary from '../components/segments/utils/qaCheckGlossaryUtils';
import QaCheckBlacklist from '../components/segments/utils/qaCheckBlacklistUtils';
import CopySourceModal from '../components/modals/CopySourceModal';
import {unescapeHTMLLeaveTags} from "../components/segments/utils/DraftMatecatUtils/textUtils";

const SegmentActions = {
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
                        segmentToOpen: sid.split('-')[0]
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

    addSearchResultToSegments: function (occurrencesList, searchResultsDictionary, currentIndex, text) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.ADD_SEARCH_RESULTS,
            occurrencesList,
            searchResultsDictionary,
            currentIndex,
            text
        });
    },
    changeCurrentSearchSegment: function (currentIndex) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.ADD_CURRENT_SEARCH,
            currentIndex
        });
    },
    removeSearchResultToSegments: function () {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.REMOVE_SEARCH_RESULTS
        });
    },
    replaceCurrentSearch: function(text) {
        AppDispatcher.dispatch({
            actionType: EditAreaConstants.REPLACE_SEARCH_RESULTS,
            text: text
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

    openSegment:  function (sid) {
        const segment = SegmentStore.getSegmentByIdToJS(sid);

        if ( segment ) {
            //Check first if the segment is in the view
            if ( UI.isReadonlySegment(segment) ) {
                UI.readonlyClickDisplay();
                return;
            }
            let $segment = (segment.splitted && sid.indexOf('-') === -1) ? UI.getSegmentById(sid + "-1") : UI.getSegmentById(sid);
            if ( $segment.length === 0 ) {
                this.scrollToSegment(sid, this.openSegment);
                return;
            }
            AppDispatcher.dispatch({
                actionType: SegmentConstants.OPEN_SEGMENT,
                sid: sid
            });
            UI.updateJobMenu(segment);
        } else {
            UI.unmountSegments();
            UI.render({
                firstLoad: false,
                segmentToOpen: sid
            });
        }

    },
    closeSegment: function ( sid, fid ) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.CLOSE_SEGMENT,
        });
        this.closeIssuesPanel();
    },
    saveSegmentBeforeClose: function (segment) {
        if ( UI.translationIsToSaveBeforeClose( segment ) ) {
            return UI.setTranslation({
                id_segment: segment.sid,
                status: (segment.status.toLowerCase() === 'new') ? 'draft' : segment.status ,
                caller: 'autosave'
            });
        } else {
            var deferred = $.Deferred();
            deferred.resolve();
            return deferred.promise();
        }
    },
    scrollToCurrentSegment() {
        this.scrollToSegment(SegmentStore.getCurrentSegment().sid);
    },
    scrollToSegment: function (sid, callback) {
        const segment = SegmentStore.getSegmentByIdToJS(sid);
        if ( segment && (SegmentStore.segmentScrollableToCenter(sid) || UI.noMoreSegmentsAfter || config.last_job_segment == sid || SegmentStore._segments.size < UI.moreSegNum || SegmentStore.getLastSegmentId() === config.last_job_segment) ) {
            AppDispatcher.dispatch({
                actionType: SegmentConstants.SCROLL_TO_SEGMENT,
                sid: sid,
            });
            if (callback) {
                setTimeout(()=>callback.apply(this, [sid]));
            }
        } else {
            UI.unmountSegments();
            UI.render({
                firstLoad: false,
                segmentToOpen: sid
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
        if ( sid ) {
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

    propagateTranslation: function(segmentId, propagatedSegments, status) {
        let segment = SegmentStore.getSegmentByIdToJS(segmentId);
        if ( segment.splitted > 2 ) return false;

        for (var i = 0, len = propagatedSegments.length; i < len; i++) {
            var sid = propagatedSegments[i];
            if ( sid !== segmentId && SegmentStore.getSegmentByIdToJS(sid)) {
                SegmentActions.updateOriginalTranslation(sid, segment.translation);
                SegmentActions.replaceEditAreaTextContent( sid, segment.translation );
                //Tag Projection: disable it if enable
                SegmentActions.setSegmentAsTagged( sid );
                SegmentActions.setStatus( sid, null, status ); // now the status, too, is propagated
                SegmentActions.setSegmentPropagation( sid, null, true, segment.sid );
                SegmentActions.modifiedTranslation( sid, false );
            }
            SegmentActions.setAlternatives(sid, undefined);
        }

        SegmentActions.setSegmentPropagation( segmentId, null, false );
        SegmentActions.setAlternatives(segmentId, undefined);
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
    /**
     * Disable the Tag Projection, for example after clicking on the Translation Matches
     */
    disableTPOnSegment: function (segmentObj) {
        var currentSegment = (segmentObj) ? segmentObj : SegmentStore.getCurrentSegment();
        var tagProjectionEnabled = TagUtils.hasDataOriginalTags( currentSegment.segment )  && !currentSegment.tagged;
        if (SegmentUtils.checkTPEnabled() && tagProjectionEnabled) {
            SegmentActions.setSegmentAsTagged(currentSegment.sid, currentSegment.id_file);
            UI.getSegmentById(currentSegment.sid).data('tagprojection', 'tagged');
        }
    },
    setSegmentAsTagged: function (sid, fid) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.SET_SEGMENT_TAGGED,
            id: sid,
            fid: fid,
        });
    },
    disableTagLock: function (  ) {
        UI.tagLockEnabled = false;
    },
    enableTagLock: function (  ) {
        UI.tagLockEnabled = true;
    },

    setSegmentWarnings: function(sid, warnings, tagMismatch){
        AppDispatcher.dispatch({
            actionType: SegmentConstants.SET_SEGMENT_WARNINGS,
            sid: sid,
            warnings: warnings,
            tagMismatch: tagMismatch
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
    addQaCheckMatches: function(matches) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.SET_QA_CHECK_MATCHES,
            matches: matches,
        });
    },
    addQaBlacklistMatches: function(sid, matches) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.SET_QA_BLACKLIST_MATCHES,
            sid: sid,
            matches: matches
        });
    },
    addLexiqaHighlight: function(sid, matches, type) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.ADD_LXQ_HIGHLIGHT,
            sid: sid,
            matches: matches,
            type: type
        });
    },
    selectNextSegmentDebounced:  _.debounce(() => {
        SegmentActions.selectNextSegment();
    }, 100),

    selectNextSegment: function(sid) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.SELECT_SEGMENT,
            sid: sid,
            direction: 'next'
        });
    },
    selectPrevSegmentDebounced:  _.debounce(() => {
        SegmentActions.selectPrevSegment();
    }, 100),
    selectPrevSegment: function(sid) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.SELECT_SEGMENT,
            sid: sid,
            direction: 'prev'
        });
    },
    openSelectedSegment: function( ) {
        let sid = SegmentStore.getSelectedSegmentId();
        if ( sid ) {
            this.openSegment( sid );
        }
    },
    copySourceToTarget: function(  ) {
        let currentSegment = SegmentStore.getCurrentSegment();

        if ( currentSegment ) {
            let source = currentSegment.segment;
            let sid = currentSegment.sid;
            // Escape html
            source = unescapeHTMLLeaveTags(source);
            SegmentActions.replaceEditAreaTextContent( sid, source );
            SegmentActions.modifiedTranslation( sid, true );
            UI.segmentQA( UI.currentSegment );

            if ( config.translation_matches_enabled ) {
                SegmentActions.setChoosenSuggestion( sid, null );
            }

            if ( !config.isReview ) {
                var alreadyCopied = false;
                $.each( SegmentStore.consecutiveCopySourceNum, function ( index ) {
                    if ( this === sid ) alreadyCopied = true;
                } );
                if ( !alreadyCopied ) {
                    SegmentStore.consecutiveCopySourceNum.push( this.currentSegmentId );
                }
                if ( SegmentStore.consecutiveCopySourceNum.length > 2 ) {
                    this.copyAllSources();
                }
            }
        }
    },
    copyAllSources: function() {
        if(typeof Cookies.get('source_copied_to_target-' + config.id_job + "-" + config.password) == 'undefined') {
            var props = {
                confirmCopyAllSources: SegmentActions.continueCopyAllSources.bind(this),
                abortCopyAllSources: SegmentActions.abortCopyAllSources.bind(this)
            };

            APP.ModalWindow.showModalComponent(CopySourceModal, props, "Copy source to ALL segments");
        } else {
            SegmentStore.consecutiveCopySourceNum = [];
        }

    },
    continueCopyAllSources: function () {
        SegmentStore.consecutiveCopySourceNum = [];

        UI.unmountSegments(); //TODO
        $('#outer').addClass('loading');

        APP.doRequest({
            data: {
                action: 'copyAllSource2Target',
                id_job: config.id_job,
                pass: config.password,
                revision_number: config.revisionNumber
            },
            error: function() {
                var notification = {
                    title: 'Error',
                    text: 'Error copying all sources to target. Try again!',
                    type: 'error',
                    position: "bl"
                };
                APP.addNotification(notification);
                UI.render({
                    segmentToOpen: UI.currentSegmentId
                });
            },
            success: function(d) {
                if(d.errors.length) {
                    APP.closePopup();
                    var notification = {
                        title: 'Error',
                        text: d.errors[0].message,
                        type: 'error',
                        position: "bl"
                    };
                    APP.addNotification(notification);
                } else {
                    UI.unmountSegments();
                    UI.render({
                        segmentToOpen: UI.currentSegmentId
                    });
                }

            }
        });
    },
    abortCopyAllSources: function () {
        SegmentStore.consecutiveCopySourceNum = [];
    },
    recomputeSegment: function(sid) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.RECOMPUTE_SIZE,
            sid: sid
        });
    },
    /******************* EditArea ************/
    modifiedTranslation: function (sid, status) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.MODIFIED_TRANSLATION,
            sid: sid,
            status: status
        });
    },
    replaceEditAreaTextContent: function(sid, text) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.REPLACE_TRANSLATION,
            id: sid,
            translation: text
        });
    },

    updateTranslation: function(sid, translation, decodedTranslation, tagMap, missingTagsInTarget, lxqDecodedTranslation) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.UPDATE_TRANSLATION,
            id: sid,
            translation: translation,
            decodedTranslation,
            tagMap,
            missingTagsInTarget,
            lxqDecodedTranslation
        });
    },
    /**
     * Set the original translation of a segment.
     * Used to create the revision trackChanges
     * @param sid
     * @param fid
     * @param originalTranslation
     */
    updateOriginalTranslation: function (sid, originalTranslation) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.SET_SEGMENT_ORIGINAL_TRANSLATION,
            id: sid,
            originalTranslation: originalTranslation
        });
    },
    updateSource: function(sid, source, decodedSource, tagMap, lxqDecodedSource) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.UPDATE_SOURCE,
            id: sid,
            source: source,
            decodedSource,
            tagMap,
            lxqDecodedSource
        });
    },
    lockEditArea : function ( sid, fid ) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.LOCK_EDIT_AREA,
            fid: fid,
            id: sid,
        });
    },
    undoInSegment: function() {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.UNDO_TEXT
        });
    },
    redoInSegment: function() {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.REDO_TEXT
        });
    },
    setFocusOnEditArea: function() {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.FOCUS_EDITAREA
        });
    },
    autoFillTagsInTarget: function(sid) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.FILL_TAGS_IN_TARGET,
            sid: sid
        });
    },
    copyTagProjectionInCurrentSegment(sid, translation) {
        if (!_.isUndefined(translation) && translation.length > 0) {
            SegmentActions.replaceEditAreaTextContent( sid, translation );
        }
    },
    /************ SPLIT ****************/
    openSplitSegment: function(sid) {
        if ( OfflineUtils.offline ) {
            APP.alert('Split is disabled in Offline Mode');
            return;
        }
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
        TranslationMatches.setDeleteSuggestion(source, target, matchId, sid).done((data) => {
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

    getGlossaryMatch: function ( text ) {
        return API.SEGMENT.getGlossaryMatch(text)
            .fail(function (  ) {
                OfflineUtils.failedConnection( 0, 'glossary' );
        });
    },

    // getGlossaryForSegment: function ( text ) {
    //     return API.SEGMENT.getGlossaryForSegment(text)
    //         .fail(function (  ) {
    //             OfflineUtils.failedConnection( 0, 'glossary' );
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
                        AppDispatcher.dispatch({
                            actionType: SegmentConstants.SET_GLOSSARY_TO_CACHE,
                            sid: request.sid,
                            fid: request.fid,
                            glossary: response.data.matches.length !== 0 ? response.data.matches : {}
                        });
                    })
                    .fail(function (error) {
                        OfflineUtils.failedConnection(sid, 'getGlossaryForSegment');
                    });
            }
        }

    },

    searchGlossary: function (sid, fid, text, fromTarget) {
        text = TagUtils.removeAllTags(TextUtils.htmlEncode(text));
        text = text.replace(/\"/g, "");
        API.SEGMENT.getGlossaryMatch(text, fromTarget)
            .done(response => {
                AppDispatcher.dispatch({
                    actionType: SegmentConstants.SET_GLOSSARY_TO_CACHE,
                    sid: sid,
                    fid: fid,
                    glossary: response.data.matches.length !== 0 ? response.data.matches : {}
                });
            })
            .fail(function () {
                OfflineUtils.failedConnection(0, 'glossary');
            });
    },

    deleteGlossaryItem: function ( source, target, id, name, sid ) {
        return API.SEGMENT.deleteGlossaryItem(source, target, id)
            .fail(function (  ) {
                OfflineUtils.failedConnection( 0, 'deleteGlossaryItem' );
            }).done(function ( data ) {
                AppDispatcher.dispatch({
                    actionType: SegmentConstants.SHOW_FOOTER_MESSAGE,
                    sid: sid,
                    message: 'A glossary item has been deleted'
                });
                AppDispatcher.dispatch({
                    actionType: SegmentConstants.DELETE_FROM_GLOSSARY,
                    sid: sid,
                    matchId: id,
                    name: name
                });
            });
    },

    addGlossaryItem: function ( source, target, comment, sid ) {
        source = TextUtils.htmlEncode(source);
        return API.SEGMENT.addGlossaryItem(source, target, comment)
            .fail(function (  ) {
                OfflineUtils.failedConnection( 0, 'addGlossaryItem' );
            }).done(function ( response ) {
                let msg;
                if ( response.data.created_tm_key ) {
                    msg = 'A Private TM Key has been created for this job';
                } else {
                    msg = (response.errors.length) ? response.errors[0].message : 'A glossary item has been added';
                }
                AppDispatcher.dispatch({
                    actionType: SegmentConstants.SHOW_FOOTER_MESSAGE,
                    sid: sid,
                    message: msg
                });
                AppDispatcher.dispatch({
                    actionType: SegmentConstants.ADD_GLOSSARY_ITEM,
                    sid: sid,
                    match: response.data.matches.length !== 0 ? response.data.matches[source.replace(/\'/g, '&apos;')] : {},
                    name: source.replace(/\'/g, '&apos;')
                });
            });
    },
    updateGlossaryItem: function ( idItem, source, target, newTranslation, comment, name, sid ) {
        return API.SEGMENT.updateGlossaryItem(idItem, source, target, newTranslation, comment)
            .fail(function (  ) {
                OfflineUtils.failedConnection( 0, 'updateGlossaryItem' );
            }).done( function ( response ) {
                AppDispatcher.dispatch({
                    actionType: SegmentConstants.SHOW_FOOTER_MESSAGE,
                    sid: sid,
                    message: 'A glossary item has been updated'
                });
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

    updateGlossaryData (data) {
        if (QaCheckGlossary.enabled() && data.glossary) {
            QaCheckGlossary.update(data.glossary);
        }
        if (QaCheckBlacklist.enabled() && data.blacklist) {
            QaCheckBlacklist.update(data.blacklist);
        }
    },

    copyGlossaryItemInEditarea: function (glossaryTranslation, segment) {
        AppDispatcher.dispatch({
            actionType: EditAreaConstants.COPY_GLOSSARY_IN_EDIT_AREA,
            segment: segment,
            glossaryTranslation: glossaryTranslation
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

    openConcordance: function(sid, currentSelectedText, inTarget) {
        SegmentActions.activateTab(sid, 'concordances');
        SegmentActions.findConcordance(sid, {text: currentSelectedText, inTarget: inTarget});
    },

    findConcordance: function ( sid, data ) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.FIND_CONCORDANCE,
            sid: sid,
            data: data
        });
    },

    getContributions: function (sid) {
        TranslationMatches.getContribution(sid, 0);
        TranslationMatches.getContribution(sid, 1);
        TranslationMatches.getContribution(sid, 2);
    },

    getContribution: function (sid, force) {
        TranslationMatches.getContribution(sid, 0, force);
    },

    getContributionsSuccess: function(data, sid) {
        TranslationMatches.processContributions(data, sid);
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
        }

    },

    closeIssuesPanel: function () {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.CLOSE_ISSUES_PANEL,
        });
        $('body').removeClass('side-tools-opened review-side-panel-opened review-extended-opened');
        localStorage.setItem(ReviewExtended.localStoragePanelClosed, true);
    },

    closeSegmentIssuePanel: function ( sid ) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.CLOSE_ISSUES_PANEL,
            sid: sid
        });
        localStorage.setItem(ReviewExtended.localStoragePanelClosed, true);
        this.scrollToSegment(sid);
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
    },

    addPreloadedIssuesToSegment: function ( issues ) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.ADD_SEGMENT_PRELOADED_ISSUES,
            versionsIssues: issues
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

    showApproveAllModalWarnirng: function (  ) {
        var props = {
            text: "It was not possible to approve all segments. There are some segments that have not been translated.",
            successText: "Ok",
            successCallback: function() {
                APP.ModalWindow.onCloseModal();
            }
        };
        APP.ModalWindow.showModalComponent(ConfirmMessageModal, props, "Warning");
    },
    showTranslateAllModalWarnirng: function (  ) {
        var props = {
            text: "It was not possible to translate all segments.",
            successText: "Ok",
            successCallback: function() {
                APP.ModalWindow.onCloseModal();
            }
        };
        APP.ModalWindow.showModalComponent(ConfirmMessageModal, props, "Warning");
    },
    approveFilteredSegments: function(segmentsArray) {
        if (segmentsArray.length >= 500) {
            var subArray = segmentsArray.slice(0, 499);
            var todoArray = segmentsArray.slice(500, segmentsArray.length-1);
            return this.approveFilteredSegments(subArray).then( ( ) => {
                return this.approveFilteredSegments(todoArray);
            });
        } else {
            return API.SEGMENT.approveSegments(segmentsArray).then( ( response ) => {
                this.checkUnchangebleSegments(response, segmentsArray, "APPROVED");
                setTimeout(CatToolActions.updateFooterStatistics(), 2000);
            });
        }
    },
    translateFilteredSegments: function(segmentsArray) {
        if (segmentsArray.length >= 500) {
            var subArray = segmentsArray.slice(0, 499);
            var todoArray = segmentsArray.slice(499, segmentsArray.length);
            return this.translateFilteredSegments(subArray).then((  ) => {
                return this.translateFilteredSegments(todoArray);
            });
        } else {
            return API.SEGMENT.translateSegments(segmentsArray).then( ( response ) => {
                this.checkUnchangebleSegments(response, segmentsArray, "TRANSLATED");
                setTimeout(CatToolActions.updateFooterStatistics(), 2000);
            });
        }
    },
    checkUnchangebleSegments: function(response, status) {
        if (response.unchangeble_segments.length > 0) {
            if ( status ===  'APPROVED') {
                this.showTranslateAllModalWarnirng();
            } else {
                this.showApproveAllModalWarnirng();
            }
        }
    },
    bulkChangeStatusCallback: function( segmentsArray, status) {
        if (segmentsArray.length > 0) {
            segmentsArray.forEach( ( item ) => {
                var segment = SegmentStore.getSegmentByIdToJS(item);
                if ( segment ) {
                    SegmentActions.setStatus(item, segment.id_file, status);
                    SegmentActions.modifiedTranslation(item, false);
                    SegmentActions.disableTPOnSegment( segment )
                }
            });
            setTimeout(CatToolActions.reloadSegmentFilter, 500);
        }
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

        AppDispatcher.dispatch({
            actionType: SegmentConstants.SET_UNLOCKED_SEGMENT,
            fid: fid,
            sid: segment.sid,
            unlocked: unlocked
        }, );

        if (!unlocked) {
            //TODO: move this to SegmentActions
            CommonUtils.removeFromStorage('unlocked-' + segment.sid);
            if (segment.inBulk) {
                this.toggleSegmentOnBulk(segment.sid, fid);
            }
        } else {
            CommonUtils.addInStorage('unlocked-'+ segment.sid, true);
            SegmentActions.openSegment(segment.sid);

        }
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
    },
    gotoNextSegment() {
        let next = SegmentStore.getNextSegment();
        if ( next ) {
            SegmentActions.openSegment(next.sid);
        } else {
            this.closeSegment();
        }
    },
    gotoNextUntranslatedSegment() {
        let next = SegmentStore.getNextUntranslatedSegmentId();
        SegmentActions.openSegment(next);
    },
    setNextUntranslatedSegmentFromServer(sid) {
        SegmentStore.nextUntranslatedFromServer = sid;
    },
    copyFragmentToClipboard: function (fragment, plainText) {
        AppDispatcher.dispatch({
            actionType: EditAreaConstants.COPY_FRAGMENT_TO_CLIPBOARD,
            fragment,
            plainText
        });
    },
    focusOnSegment: function (sid, focused = false) {
        AppDispatcher.dispatch({
            actionType: SegmentConstants.SEGMENT_FOCUSED,
            focused,
            sid
        });
    }


};

module.exports = SegmentActions;
