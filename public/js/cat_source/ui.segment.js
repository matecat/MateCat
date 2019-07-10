(function($, undefined) {

    $( document ).on( 'sse:bulk_segment_status_change', function ( ev, message ) {
        UI.bulkChangeStatusCallback(message.data.segment_ids, message.data.status);
    } );

    $.extend(UI, {

        getSegmentStatus: function (segment) {
            return (segment.status)? segment.status.toLowerCase() : 'new';
        },
        /**
         * Return che Suggestion, if exist, used by the current segment
         * return json
         * TODO retrieve contributions from the store
         */
        getCurrentSegmentContribution: function (segment) {
            var currentSegment = (segment)? segment : UI.currentSegment;
            var currentContribution;
            var chosen_suggestion = $('.editarea', currentSegment).data('lastChosenSuggestion');
            if (!_.isUndefined(chosen_suggestion)) {
                var storedContributions = UI.getFromStorage('contribution-' + config.id_job + '-' + UI.getSegmentId(currentSegment));
                if (storedContributions) {
                    currentContribution = JSON.parse(storedContributions).matches[chosen_suggestion - 1];

                }
            }
            return currentContribution;
        },
        setGlobalTagProjection: function () {
            UI.enableTagProjection = UI.checkTPEnabled();
        },
        /**
         * Tag Projection: check if is enable the Tag Projection
         * @param file
         */
        checkTPEnabled: function () {
            return (this.checkTpCanActivate() && !!config.tag_projection_enabled);
        },
        /**
         * Tag Projection: check if is possible to enable tag projection:
         * Condition: Languages it-IT en-GB en-US, not review
         */
        checkTpCanActivate: function () {
            if (_.isUndefined(this.tpCanActivate)) {
                var acceptedLanguages = config.tag_projection_languages;
                var elemST = config.source_rfc.split("-")[0] + "-" + config.target_rfc.split("-")[0];
                var elemTS = config.target_rfc.split("-")[0] + "-" + config.source_rfc.split("-")[0];
                var supportedPair = (typeof acceptedLanguages[elemST] !== 'undefined' || typeof acceptedLanguages[elemTS] !== 'undefined');
                this.tpCanActivate = supportedPair > 0 &&
                    !config.isReview;
            }
            return this.tpCanActivate;
        },
        startSegmentTagProjection: function () {
            UI.getSegmentTagsProjection().done(function(response) {
                if (response.errors && (response.errors.length || response.errors.code) ) {
                    UI.processErrors(response.errors, 'getTagProjection');
                    UI.disableTPOnSegment()
                } else {
                    UI.copyTagProjectionInCurrentSegment(response.data.translation);
                    UI.autoFillTagsInTarget();
                }

            }).fail(function () {
                UI.copyTagProjectionInCurrentSegment();
                UI.autoFillTagsInTarget();
                UI.startOfflineMode();
            }).always(function () {
                UI.setSegmentAsTagged();
                UI.editarea.focus();
                SegmentActions.highlightEditarea(UI.currentSegment.find(".editarea").data("sid"));
                // UI.createButtons();
                UI.registerQACheck();
            });
        },
        /**
         * Tag Projection: get the tag projection for the current segment
         * @returns translation with the Tag prjection
         */
        getSegmentTagsProjection: function () {
            var source = UI.currentSegment.find('.source').data('original');
            source = htmlDecode(source).replace(/&quot;/g, '\"');
            source = htmlDecode(source);
            //Retrieve the chosen suggestion if exist
            var suggestion;
            var currentContribution = this.getCurrentSegmentContribution();
            // Send the suggestion to Tag Projection only if is > 89% and is not MT
            if (!_.isUndefined(currentContribution) && currentContribution.match !== "MT" && parseInt(currentContribution.match) > 89) {
                suggestion = currentContribution.translation;
            }

            //Before send process with this.postProcessEditarea
            var target = UI.postProcessEditarea(UI.currentSegment, ".editarea");
            return APP.doRequest({
                data: {
                    action: 'getTagProjection',
                    password: config.password,
                    id_job: config.id_job,
                    source: source,
                    target: target,
                    source_lang: config.source_rfc,
                    target_lang: config.target_rfc,
                    sl: suggestion
                }
            });

        },
        /**
         * Tag Projection: set the translation with tag projection to the current segment (source and editor)
         * @param translation
         */
        copyTagProjectionInCurrentSegment: function (translation) {
            this.copySourcefromDataAttribute();
            if (!_.isUndefined(translation) && translation.length > 0) {

                SegmentActions.replaceEditAreaTextContent(UI.getSegmentId(this.editarea), UI.getSegmentFileId(this.editarea), UI.transformPlaceholdersAndTags(translation));

                // $(this.editarea).html(decoded_translation);
            }

        },
        /**
         * Tag Projection: set a segment after tag projection is called, remove the class enableTP and set the data-tagprojection
         * attribute to tagged (after click on Guess Tags button)
         */
        setSegmentAsTagged: function (segment) {
            var currentSegment = (segment)? segment : UI.currentSegment;
            SegmentActions.setSegmentAsTagged(UI.getSegmentId(currentSegment), UI.getSegmentFileId(currentSegment));
            // currentSegment.data('tagprojection', 'tagged');
        },
        /**
         * Check if the  the Tag Projection in the current segment is enabled and still not tagged
         * @returns {boolean}
         */
        checkCurrentSegmentTPEnabled: function (segment) {
            var currentSegment = (segment)? segment : UI.currentSegment;
            if (currentSegment && this.enableTagProjection) {
                // If the segment has tag projection enabled (has tags and has the enableTP class)
                var segmentNoTags = UI.removeAllTags( htmlDecode(currentSegment.find('.source').data('original')));
                var tagProjectionEnabled = this.hasDataOriginalTags( currentSegment) && currentSegment.hasClass('enableTP') && segmentNoTags !== '';
                // The segment is already been tagged
                var dataAttribute = currentSegment.attr('data-tagprojection');
                // If the segment has already be tagged
                var isCurrentAlreadyTagged = ( !_.isUndefined(dataAttribute) && dataAttribute === 'tagged')? true : false;
                return ( tagProjectionEnabled && !isCurrentAlreadyTagged );
            }
            return false;
        },
        /**
         * Disable the Tag Projection, for example after clicking on the Translation Matches
         */
        disableTPOnSegment: function (segment) {
            var currentSegment = (segment)? segment : UI.currentSegment;
            var tagProjectionEnabled = this.hasDataOriginalTags( currentSegment)  && currentSegment.hasClass('enableTP');
            if (this.enableTagProjection && tagProjectionEnabled) {
                SegmentActions.setSegmentAsTagged(UI.getSegmentId(currentSegment), UI.getSegmentFileId(currentSegment));
                currentSegment.data('tagprojection', 'tagged');
                this.copySourcefromDataAttribute(segment);
            }
        },
        /**
         * Copy the source from the data-original to the source decoding the tag
         */
        copySourcefromDataAttribute: function (segment) {
            var currentSegment = (segment)? segment : UI.currentSegment;
            var source = htmlDecode(currentSegment.find('.source').data('original'));
            source = UI.transformPlaceholdersAndTags(source);
            // source = source.replace(/\n/g , config.lfPlaceholder)
            //         .replace(/\r/g, config.crPlaceholder )
            //         .replace(/\r\n/g, config.crlfPlaceholder )
            //         .replace(/\t/g, config.tabPlaceholder )
            //         .replace(String.fromCharCode( parseInt( 0xA0, 10 ) ), config.nbspPlaceholder );
            SegmentActions.replaceSourceText(UI.getSegmentId(currentSegment), UI.getSegmentFileId(currentSegment), source);
            UI.markGlossaryItemsInSource(currentSegment, UI.cachedGlossaryData);
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
                    segmentToScroll: UI.getSegmentId(UI.currentSegment),
                    segmentToOpen: UI.getSegmentId(UI.currentSegment),
                    applySearch: UI.body.hasClass('searchActive')
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
                    segmentToScroll: UI.getSegmentId(UI.currentSegment),
                    segmentToOpen: UI.getSegmentId(UI.currentSegment),
                    applySearch: UI.body.hasClass('searchActive')
                });
                UI.checkWarnings(false);
            });

        },
        filterTagsWithTagProjection: function (array) {
            var returnArray = array;
            if (UI.enableTagProjection) {
                returnArray = array.filter(function (value) {
                    return !UI.checkCurrentSegmentTPEnabled($('#segment-' + value));
                });
            }
            return returnArray;
        },
        decodeText: function(segment, text) {
            var decoded_text;
            if (UI.enableTagProjection && (UI.getSegmentStatus(segment) === 'draft' || UI.getSegmentStatus(segment) === 'new')
                && !UI.checkXliffTagsInText(segment.translation) && UI.removeAllTags(segment.segment) !== '' ) {
                decoded_text = UI.removeAllTags(text);
            } else {
                decoded_text = text;
            }
            decoded_text = UI.decodePlaceholdersToText(decoded_text || '');
            if ( !(config.tagLockCustomizable && !this.tagLockEnabled) ) {
                decoded_text = UI.transformTextForLockTags(decoded_text);
            }
            return decoded_text;
        },
        transformPlaceholdersAndTags: function(text) {
            text = UI.decodePlaceholdersToText(text || '');
            if ( !(config.tagLockCustomizable && !this.tagLockEnabled) ) {
                text = UI.transformTextForLockTags(text);
            }
            return text;
        },
        getPercentuageClass: function(match) {
            var percentageClass = "";
            var m_parse = parseInt(match);

            if (!isNaN(m_parse)) {
                match = m_parse;
            }

            switch (true) {
                case (match == 100):
                    percentageClass = "per-green";
                    break;
                case (match == 101):
                    percentageClass = "per-blue";
                    break;
                case(match > 0 && match <= 99):
                    percentageClass = "per-orange";
                    break;
                case (match == "MT"):
                    percentageClass = "per-yellow";
                    break;
                default :
                    percentageClass = "";
            }
            return percentageClass;
        },
        getSegmentSource: function(seg) {
            segment = (typeof seg == 'undefined') ? this.currentSegment : seg;
            return $('.source', segment).text();
        },
        getStatus: function(segment) {
            status = ($(segment).hasClass('status-new') ? 'new' : $(segment).hasClass('status-draft') ? 'draft' : $(segment).hasClass('status-translated') ? 'translated' : $(segment).hasClass('status-approved') ? 'approved' : 'rejected');
            return status;
        },
        getSegmentTarget: function(seg) {
            var editarea = (typeof seg == 'undefined') ? this.editarea : $('.editarea', seg);
            return editarea.text();
        },
        /**
         * getNextSegment
         *
         * Returns the next segment.
         *
         */
        getNextSegment: function(segment, status) {
            UI.evalNextSegment( segment, status) ;
            return this.nextSegmentId ;
        },

        /**
         * selectorForNextUntranslatedSegment
         *
         * Defines the css selectors to be used to determine the next
         * segment to open.
         */
        selectorForNextUntranslatedSegment : function(status, section) {
            var selector = (status == 'untranslated') ? 'section.status-draft:not(.readonly), section.status-rejected:not(.readonly), section.status-new:not(.readonly)' : 'section.status-' + status + ':not(.readonly)';
            return selector ;
        },

        /**
         * selectorForNextSegment
         */
        selectorForNextSegment : function() {
            return 'section:not(.ice-locked)';
        },

        /**
         * evalNextSegment
         *
         * Evaluates the next segment and populates this.nextSegmentId ;
         *
         */
        evalNextSegment: function( section, status ) {
            var currentSegment = SegmentStore.getCurrentSegment();
            var nextUntranslated = SegmentStore.getNextSegment(currentSegment.original_sid, null, 8);

            if (nextUntranslated) { // se ci sono sotto segmenti caricati con lo status indicato
                this.nextUntranslatedSegmentId = nextUntranslated.original_sid;
            } else {
                this.nextUntranslatedSegmentId = UI.nextUntranslatedSegmentIdByServer;
            }
            var next = SegmentStore.getNextSegment(currentSegment.original_sid, null, null);
            this.nextSegmentId = (next) ? next.original_sid : null;
            var prev = SegmentStore.getPrevSegment(currentSegment.original_sid)
            this.previousSegmentId = ( prev ) ? prev.original_sid : null;
        },
        gotoNextSegment: function() {
            SegmentActions.openSegment(UI.nextSegmentId);
        },
        gotoNextUntranslatedSegment: function() {
            if (!UI.segmentIsLoaded(UI.nextUntranslatedSegmentId)) {
                if (!UI.nextUntranslatedSegmentId) {
                    SegmentActions.closeSegment(UI.currentSegmentId);
                } else {
                    UI.reloadWarning();
                }
            } else {
                SegmentActions.openSegment(UI.nextUntranslatedSegmentId);
            }
        },

        gotoOpenSegment: function(quick) {
            quick = quick || false;

            if (this.currentSegmentId) {
                UI.scrollSegment(this.currentSegmentId);
            } else {
                this.render({
                    firstLoad: false,
                    segmentToOpen: this.currentSegmentId
                });
            }
            $(window).trigger({
                type: "scrolledToOpenSegment",
                segment: this.currentSegment
            });
        },
        gotoPreviousSegment: function() {
            SegmentActions.openSegment( UI.previousSegmentId );
        },
        gotoSegment: function(id) {
            if ( !this.segmentIsLoaded(id) && UI.parsedHash.splittedSegmentId ) {
                id = UI.parsedHash.splittedSegmentId ;
            }
            if ( id ) {
                SegmentActions.openSegment(id);
            }

        },
        /**
         * Search for the next translated segment to propose for revision.
         * This function searches in the current UI first, then falls back
         * to invoke the server and eventually reload the page to the new
         * URL.
         */
        openNextTranslated: function (sid) {
            sid = sid || UI.currentSegmentId;
            var nextTranslatedSegment = SegmentStore.getNextSegment(sid, null, 7);
            var nextTranslatedSegmentInPrevious = SegmentStore.getNextSegment(-1, null, 7);
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
                    var next = SegmentStore.getSegmentByIdToJS(UI.nextUntranslatedSegmentIdByServer);
                    if (next) {
                        SegmentActions.openSegment(UI.nextUntranslatedSegmentIdByServer);
                    } else {
                        UI.renderAfterConfirm(UI.nextUntranslatedSegmentIdByServer);
                    }
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
        renderAfterConfirm: function (nextId) {
            this.render({
                segmentToOpen: nextId
            });
        },
        isReadonlySegment : function( segment ) {
            return ( segment.readonly == 'true' ||UI.body.hasClass('archived')) ;
        },

        isUnlockedSegment: function ( segment ) {
            // var readonly = UI.isReadonlySegment(segment) ;
            // return (segment.ice_locked === "1" && !readonly) && !_.isNull(UI.getFromStorage('unlocked-' + segment.sid));
            return !_.isNull(UI.getFromStorage('unlocked-' + segment.sid));
        },
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

        saveSegment: function(segment) {
            SegmentActions.addClassToSegment(UI.getSegmentId( segment ), 'saved');
            return this.setTranslation({
                id_segment: this.getSegmentId(segment),
                status: this.getStatusForAutoSave( segment ) ,
                caller: 'autosave'
            });
        },
        setCurrentSegment: function() {
            var reqArguments = arguments;
            var id_segment = this.currentSegmentId;
            this.setLastSegmentFromLocalStorage(id_segment.toString());
            APP.doRequest({
                data: {
                    action: 'setCurrentSegment',
                    password: config.password,
                    id_segment: id_segment.toString(),
                    id_job: config.id_job
                },
                context: [reqArguments, id_segment],
                error: function() {
                    UI.failedConnection(this[0], 'setCurrentSegment');
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
            this.getNextSegment(this.currentSegment, 'untranslated');

            if (config.alternativesEnabled) {
                this.getTranslationMismatches(id_segment);
            }
            $('html').trigger('setCurrentSegment_success', [d, id_segment]);
        },
        /**
         * setStatus
         *
         * Set the status at UI level, with potential inconsistent state against what is saved server side.
         * This is necessary for CSS but also for changeStatus function, which relies on this class to
         * determine the status to assign to the setTranslation during the autosave.
         *
         * @param segment DOM element
         * @param status
         */
        setStatus: function(segment, status) {
            var fid = UI.getSegmentFileId(segment);
            SegmentActions.setStatus(UI.getSegmentId(segment), fid, status);
        },
        /**
         * This function is an attempt to centralize all distributed logic used to mark
         * the segment as modified. When a segment is modified we set the class and we set
         * data. And we trigger an event.
         *
         * Preferred way would be to use MateCat.db.segments to save this data, and have the
         * UI redraw after this change. This would help transition to component based architecture.
         *
         * @param el
         */
        setSegmentModified : function( el, isModified ) {
            if ( typeof isModified == 'undefined' ) {
                throw new Exception('isModified parameter is missing.');
            }

            if ( isModified ) {
                SegmentActions.modifiedTranslation(UI.getSegmentId( el ), UI.getSegmentFileId(el), true);
                el.data('modified', true);
                el.trigger('modified');
            } else {
                SegmentActions.modifiedTranslation(UI.getSegmentId( el ), UI.getSegmentFileId(el), false);
                el.data('modified', false);
                el.trigger('modified');
            }
        },

        focusSegment: function(segment) {
            SegmentActions.openSegment(UI.getSegmentId(segment));
            $(document).trigger('ui:segment:focus', UI.getSegmentId( segment ) );
        },

        getSegmentById: function(id) {
            return $('#segment-' + id);
        },

        getSegmentsSplit: function(id) {
            return SegmentStore.getSegmentsSplitGroup(id);
        },

        getEditAreaBySegmentId: function(id) {
            return $('#segment-' + id + ' .targetarea');
        },

        segmentIsLoaded: function(segmentId) {
            var segment = SegmentStore.getSegmentByIdToJS(segmentId);
            return segment || UI.getSegmentsSplit(segmentId).length > 0 ;
        },
        getContextBefore: function(segmentId) {
            var segment = $('#segment-' + segmentId);
            var originalId = segment.attr('data-split-original-id');
            var segmentBefore = (function  findBefore(segment) {
                var before = segment.prev('section');
                if (before.length === 0 ) {
                    return undefined;
                }
                else if (before.attr('data-split-original-id') && before.attr('data-split-original-id') !== originalId) {
                    return before;
                } else {
                    return findBefore(before);
                }

            })(segment);
            // var segmentBefore = findSegmentBefore();
            if (_.isUndefined(segmentBefore)) {
                return null;
            }
            var segmentBeforeId = UI.getSegmentId(segmentBefore);
            var isSplitted = segmentBeforeId.split('-').length > 1;
            if (isSplitted) {
                return this.collectSplittedTranslations(segmentBeforeId, ".source");
            } else if (config.brPlaceholdEnabled)  {
                return this.postProcessEditarea(segmentBefore, '.source');
            } else {
                return $('.source', segmentBefore ).text();
            }
        },
        getContextAfter: function(segmentId) {
            var segment = $('#segment-' + segmentId);
            var originalId = segment.attr('data-split-original-id');
            var segmentAfter = (function findAfter(segment) {
                var after = segment.next('section');
                if (after.length === 0 ) {
                    return undefined;
                }
                else if (after.attr('data-split-original-id') && after.attr('data-split-original-id') !== originalId) {
                    return after;
                } else {
                    return findAfter(after);
                }

            })(segment);
            if (_.isUndefined(segmentAfter)) {
                return null;
            }
            var segmentAfterId = UI.getSegmentId(segmentAfter);
            var isSplitted = segmentAfterId.split('-').length > 1;
            if (isSplitted) {
                return this.collectSplittedTranslations(segmentAfterId, ".source");
            } else if (config.brPlaceholdEnabled)  {
                return this.postProcessEditarea(segmentAfter, '.source');
            } else {
                return $('.source', segmentAfter ).text();
            }
        },
        getIdBefore: function(segmentId) {
            var segment = $('#segment-' + segmentId);
            var originalId = segment.attr('data-split-original-id');
            var segmentBefore = (function  findBefore(segment) {
                var before = segment.prev('section');
                if (before.length === 0 ) {
                    return undefined;
                }
                else if (before.attr('data-split-original-id') !== originalId) {
                    return before;
                } else {
                    return findBefore(before);
                }

            })(segment);
            // var segmentBefore = findSegmentBefore();
            if (_.isUndefined(segmentBefore)) {
                return null;
            }
            var segmentBeforeId = UI.getSegmentId(segmentBefore);
            return segmentBeforeId;
        },
        getIdAfter: function(segmentId) {
            var segment = $('#segment-' + segmentId);
            var originalId = segment.attr('data-split-original-id');
            var segmentAfter = (function findAfter(segment) {
                var after = segment.next('section');
                if (after.length === 0 ) {
                    return undefined;
                }
                else if (after.attr('data-split-original-id') !== originalId) {
                    return after;
                } else {
                    return findAfter(after);
                }

            })(segment);
            if (_.isUndefined(segmentAfter)) {
                return null;
            }
            var segmentAfterId = UI.getSegmentId(segmentAfter);
            return segmentAfterId;
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
            var self = this;
            if (segmentsArray.length >= 500) {
                var subArray = segmentsArray.slice(0, 499);
                var todoArray = segmentsArray.slice(500, segmentsArray.length-1);
                return this.approveFilteredSegments(subArray).then(function (  ) {
                    return self.approveFilteredSegments(todoArray);
                });
            } else {
                return API.SEGMENT.approveSegments(segmentsArray).then(function ( response ) {
                    self.checkUnchangebleSegments(response, segmentsArray, "APPROVED");
                    UI.retrieveStatistics();
                });
            }
        },
        translateFilteredSegments: function(segmentsArray) {
            var self = this;
            if (segmentsArray.length >= 500) {
                var subArray = segmentsArray.slice(0, 499);
                var todoArray = segmentsArray.slice(499, segmentsArray.length);
                return this.translateFilteredSegments(subArray).then(function (  ) {
                    return self.translateFilteredSegments(todoArray);
                });
            } else {
                return API.SEGMENT.translateSegments(segmentsArray).then(function ( response ) {
                    self.checkUnchangebleSegments(response, segmentsArray, "TRANSLATED");
                    UI.retrieveStatistics();
                });
            }
        },
        checkUnchangebleSegments: function(response) {
            if (response.unchangeble_segments.length > 0) {
                UI.showTranslateAllModalWarnirng();
            }
        },
        bulkChangeStatusCallback: function( segmentsArray, status) {
            if (segmentsArray.length > 0) {
                segmentsArray.forEach(function ( item ) {
                    var $segment = UI.getSegmentById(item);
                    if ( $segment.length > 0) {
                        var fileId = UI.getSegmentFileId(UI.getSegmentById(item));
                        SegmentActions.setStatus(item, fileId, status);
                        UI.setSegmentModified( $segment, false ) ;
                        UI.disableTPOnSegment( $segment )
                    }
                });
                setTimeout(CatToolActions.reloadSegmentFilter, 500);
            }
        },
        scrollSegment: function(idSegment) {
            SegmentActions.scrollToSegment( idSegment );
        }
    });
})(jQuery); 
