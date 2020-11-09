(function($, undefined) {

    $.extend(UI, {
        /*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
         Tag Proj start
         */

        startSegmentTagProjection: function (sid) {
            UI.getSegmentTagsProjection(sid).done(function(response) {
                if (response.errors && !!(response.errors.length || response.errors.code) ) {
                    UI.processErrors(response.errors, 'getTagProjection');
                    SegmentActions.disableTPOnSegment();
                    // Set as Tagged and restore source with taggedText
                    SegmentActions.setSegmentAsTagged(sid);
                    // Add missing tag at the end of the string
                    SegmentActions.autoFillTagsInTarget(sid);
                } else {
                    // Set as Tagged and restore source with taggedText
                    SegmentActions.setSegmentAsTagged(sid);
                    // Unescape HTML
                    let unescapedTranslation = DraftMatecatUtils.unescapeHTMLLeaveTags(response.data.translation);
                    // Update target area
                    SegmentActions.copyTagProjectionInCurrentSegment(sid, unescapedTranslation);
                    // TODO: Autofill target based on Source Map, rewrite
                    //SegmentActions.autoFillTagsInTarget(sid);
                }

            }).fail(function () {
                SegmentActions.setSegmentAsTagged(sid);
                SegmentActions.autoFillTagsInTarget(sid);
                OfflineUtils.startOfflineMode();
            }).always(function () {
                UI.registerQACheck();
            });
        },
        /**
         * Tag Projection: get the tag projection for the current segment
         * @returns translation with the Tag prjection
         */
        getSegmentTagsProjection: function (sid) {
            var segmentObj = SegmentStore.getSegmentByIdToJS(sid);
            var source = segmentObj.segment;
            source = TextUtils.htmlDecode(source).replace(/&quot;/g, '\"');
            // source = TextUtils.htmlDecode(source);
            //Retrieve the chosen suggestion if exist
            var suggestion;
            var currentContribution = SegmentStore.getSegmentChoosenContribution(sid);
            // Send the suggestion to Tag Projection only if is > 89% and is not MT
            if (!_.isUndefined(currentContribution) && currentContribution.match !== "MT" && parseInt(currentContribution.match) > 89) {
                suggestion = currentContribution.translation;
            }

            var target = segmentObj.translation;
            return APP.doRequest({
                data: {
                    action: 'getTagProjection',
                    password: config.password,
                    id_job: config.id_job,
                    source: source,
                    target: target,
                    source_lang: config.source_rfc,
                    target_lang: config.target_rfc,
                    suggestion: suggestion,
                    id_segment: sid
                }
            });

        },

        /**
         * Set the tag projection to true and reload file
         */
        enableTagProjectionInJob: function () {
            config.tag_projection_enabled = 1;
            var path = sprintf(
                APP.getRandomUrl() + 'api/v2/jobs/%s/%s/options',
                config.id_job, config.password
            );
            var data = {
                'tag_projection': true
            };
            $.ajax({
                url: path,
                type: 'POST',
                data : data,
                xhrFields: { withCredentials: true }
            }).done( function( data ) {
                UI.render({
                    segmentToOpen: UI.getSegmentId(UI.currentSegment)
                });
                UI.checkWarnings(false);
            });

        },
        /**
         * Set the tag projection to true and reload file
         */
        disableTagProjectionInJob: function () {
            config.tag_projection_enabled = 0;
            var path = sprintf(
                APP.getRandomUrl() + 'api/v2/jobs/%s/%s/options',
                config.id_job, config.password
            );
            var data = {
                'tag_projection': false
            };
            $.ajax({
                url: path,
                type: 'POST',
                data : data,
                xhrFields: { withCredentials: true }
            }).done( function( data ) {
                UI.render({
                    segmentToOpen: UI.getSegmentId(UI.currentSegment)
                });
                UI.checkWarnings(false);
            });

        },
        /*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
         Tag Proj end
         */


        /** TODO: Remove
         * evalNextSegment
         *
         * Evaluates the next segment and populates this.nextSegmentId ;
         *
         */
        evalNextSegment: function( ) {
            var currentSegment = SegmentStore.getCurrentSegment();
            var nextUntranslated = (currentSegment) ? SegmentStore.getNextSegment(currentSegment.sid, null, 8): null;

            if (nextUntranslated) { // se ci sono sotto segmenti caricati con lo status indicato
                this.nextUntranslatedSegmentId = nextUntranslated.sid;
            } else {
                this.nextUntranslatedSegmentId = UI.nextUntranslatedSegmentIdByServer;
            }
            var next = (currentSegment) ?  SegmentStore.getNextSegment(currentSegment.sid, null, null) : null;
            this.nextSegmentId = (next) ? next.sid : null;
        },
        //Override by  plugin
        gotoNextSegment: function() {
            SegmentActions.gotoNextSegment();
        },
        //Overridden by  plugin
        gotoPreviousSegment: function() {
            var prevSeg = SegmentStore.getPrevSegment();
            if ( prevSeg ) {
                SegmentActions.openSegment( prevSeg.sid );
            }

        },
        /**
         * Search for the next translated segment to propose for revision.
         * This function searches in the current UI first, then falls back
         * to invoke the server and eventually reload the page to the new
         * URL.
         *
         * Overridden by  plugin
         */
        openNextTranslated: function (sid) {
            sid = sid || UI.currentSegmentId;
            var nextTranslatedSegment = SegmentStore.getNextSegment(sid, null, 7, null, true);
            var nextTranslatedSegmentInPrevious = SegmentStore.getNextSegment(-1, null, 7, null, true);
            // find in next segments
            if(nextTranslatedSegment) {
                SegmentActions.openSegment(nextTranslatedSegment.sid);
                // else find from the beginning of the currently loaded segments in all files
            } else if ( this.noMoreSegmentsBefore && nextTranslatedSegmentInPrevious) {
                SegmentActions.openSegment(nextTranslatedSegmentInPrevious.sid);
            } else if ( !this.noMoreSegmentsBefore || !this.noMoreSegmentsAfter) { // find in not loaded segments or go to the next approved
                // Go to the next segment saved before
                var callback = function() {
                    $(window).off('modalClosed');
                    //Check if the next is inside the view, if not render the file
                    UI.nextUntranslatedSegmentIdByServer && SegmentActions.openSegment(UI.nextUntranslatedSegmentIdByServer);
                };
                // If the modal is open wait the close event
                if( $(".modal[data-type='confirm']").length ) {
                    $(window).on('modalClosed', function(e) {
                        callback();
                    });
                } else {
                    callback();
                }
            }
        },
        //Overridden by  plugin
        isReadonlySegment : function( segment ) {
            return ( segment.readonly == 'true' ||UI.body.hasClass('archived')) ;
        },
        //Overridden by  plugin
        getStatusForAutoSave : function( segment ) {
            var status ;
            if (segment.hasClass('status-translated')) {
                status = 'translated';
            }
            else if (segment.hasClass('status-approved')) {
                status = 'approved' ;
            }
            else if (segment.hasClass('status-rejected')) {
                status = 'rejected';
            }
            else if (segment.hasClass('status-new')) {
                status = 'new';
            }
            else {
                status = 'draft';
            }

            if (status == 'new') {
                status = 'draft';
            }
            console.debug('status', status);
            return status;
        },
        setCurrentSegment: function() {
            var reqArguments = arguments;
            var id_segment = this.currentSegmentId;
            CommonUtils.setLastSegmentFromLocalStorage(id_segment.toString());
            APP.doRequest({
                data: {
                    action: 'setCurrentSegment',
                    password: config.password,
                    revision_number: config.revisionNumber,
                    id_segment: id_segment.toString(),
                    id_job: config.id_job
                },
                context: [reqArguments, id_segment],
                error: function() {
                    OfflineUtils.failedConnection(this[0], 'setCurrentSegment');
                },
                success: function(d) {
                    UI.setCurrentSegment_success(this[1], d);
                }
            });
        },
        setCurrentSegment_success: function(id_segment, d) {
            if (d.errors.length) {
                this.processErrors(d.errors, 'setCurrentSegment');
            }

            this.nextUntranslatedSegmentIdByServer = d.nextSegmentId;
            SegmentActions.setNextUntranslatedSegmentFromServer(d.nextSegmentId);

            var segment = SegmentStore.getSegmentByIdToJS(id_segment);
            if ( !segment ) return;
            if (config.alternativesEnabled && !segment.alternatives) {
                this.getTranslationMismatches(id_segment);
            }
            $('html').trigger('setCurrentSegment_success', [d, id_segment]);
        },

        getSegmentById: function(id) {
            return $('#segment-' + id);
        },
        getEditAreaBySegmentId: function(id) {
            return $('#segment-' + id + ' .targetarea');
        },

        segmentIsLoaded: function(segmentId) {
            var segment = SegmentStore.getSegmentByIdToJS(segmentId);
            return segment || UI.getSegmentsSplit(segmentId).length > 0 ;
        },
        getSegmentsSplit: function(id) {
            return SegmentStore.getSegmentsSplitGroup(id);
        },
        getContextBefore: function(segmentId) {
            var segmentBefore = SegmentStore.getPrevSegment(segmentId);
            if ( !segmentBefore ) {
                return null;
            }
            var segmentBeforeId = segmentBefore.splitted;
            var isSplitted = segmentBefore.splitted;
            if (isSplitted) {
                if (segmentBefore.original_sid !== segmentId.split('-')[0]){
                    return this.collectSplittedTranslations(segmentBefore.original_sid, ".source");
                } else {
                    return this.getContextBefore(segmentBeforeId);
                }
            } else {
                return TagUtils.prepareTextToSend(segmentBefore.segment);
            }
        },
        getContextAfter: function(segmentId) {

            var segmentAfter = SegmentStore.getNextSegment(segmentId);
            if ( !segmentAfter ) {
                return null;
            }
            var segmentAfterId = segmentAfter.sid;
            var isSplitted = segmentAfter.splitted;
            if (isSplitted ) {
                if (segmentAfter.firstOfSplit) {
                    return this.collectSplittedTranslations(segmentAfter.original_sid, ".source");
                } else {
                    return this.getContextAfter(segmentAfterId);
                }
            } else   {
                return TagUtils.prepareTextToSend(segmentAfter.segment);
            }
        },
        getIdBefore: function(segmentId) {
            var segmentBefore = SegmentStore.getPrevSegment(segmentId);
            // var segmentBefore = findSegmentBefore();
            if ( !segmentBefore ) {
                return null;
            }
            return segmentBefore.original_sid;
        },
        getIdAfter: function(segmentId) {
            var segmentAfter = SegmentStore.getNextSegment(segmentId);
            if ( !segmentAfter ) {
                return null;
            }
            return segmentAfter.original_sid;
        },

        /**
         * Register tabs in segment footer
         *
         * Overridden by  plugin
         */
        registerFooterTabs: function () {
            SegmentActions.registerTab('concordances', true, false);

            if ( config.translation_matches_enabled ) {
                SegmentActions.registerTab('matches', true, true);
            }

            SegmentActions.registerTab('glossary', true, false);
            SegmentActions.registerTab('alternatives', false, false);
            // SegmentActions.registerTab('messages', false, false);
            if ( ReviewSimple.enabled() ) {
                UI.registerReviseTab();

            }
        }
    });
})(jQuery);
