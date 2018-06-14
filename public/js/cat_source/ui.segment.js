(function($, undefined) {
    $.extend(UI, {

        getSegmentStatus: function (segment) {
            return (segment.status)? segment.status.toLowerCase() : 'new';
        },
        /**
         * Return che Suggestion, if exist, used by the current segment
         * return json
         */
        getCurrentSegmentContribution: function (segment) {
            var currentSegment = (segment)? segment : UI.currentSegment;
            var currentContribution;
            var chosen_suggestion = $('.editarea', currentSegment).data('lastChosenSuggestion');
            if (!_.isUndefined(chosen_suggestion)) {
                var storedContributions = UI.getFromStorage('contribution-' + config.id_job + '-' + UI.getSegmentId(currentSegment));
                if (storedContributions) {
                    currentContribution = JSON.parse(storedContributions).data.matches[chosen_suggestion - 1];

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
                UI.createButtons();
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
                var tagProjectionEnabled = this.hasDataOriginalTags( currentSegment) && currentSegment.hasClass('enableTP');
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
                UI.createButtons();
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
            UI.markGlossaryItemsInSource(UI.cachedGlossaryData);
        },
        /**
         * Set the tag projection to true and reload file
         */
        enableTagProjectionInJob: function () {
            config.tag_projection_enabled = 1;
            var path = sprintf(
                '/api/v2/jobs/%s/%s/options',
                config.id_job, config.password
            );
            var data = {
                'tag_projection': true
            };
            $.ajax({
                url: path,
                type: 'POST',
                data : data
            }).done( function( data ) {
                UI.render({
                    segmentToScroll: UI.getSegmentId(UI.currentSegment),
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
                '/api/v2/jobs/%s/%s/options',
                config.id_job, config.password
            );
            var data = {
                'tag_projection': false
            };
            $.ajax({
                url: path,
                type: 'POST',
                data : data
            }).done( function( data ) {
                UI.render({
                    segmentToScroll: UI.getSegmentId(UI.currentSegment),
                    segmentToOpen: UI.getSegmentId(UI.currentSegment)
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
                && !UI.checkXliffTagsInText(segment.translation) ) {
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
            var selector = UI.selectorForNextUntranslatedSegment( status, section );
            var n = $(section).nextAll(selector).first();

            if (!n.length) {
                n = $(section).parents('article').next().find(selector).first();
            }

            if (n.length) { // se ci sono sotto segmenti caricati con lo status indicato
                this.nextUntranslatedSegmentId = this.getSegmentId($(n));
            } else {
                this.nextUntranslatedSegmentId = UI.nextUntranslatedSegmentIdByServer;
            }
            var i = $(section).next();

            if (!i.length) {
                i = $(section).parents('article').next().find( UI.selectorForNextSegment() ).first();
            }
            if (i.length) {
                this.nextSegmentId = this.getSegmentId($(i));
            } else {
                this.nextSegmentId = 0;
            }
        },
        gotoNextSegment: function() {
            var selector = UI.selectorForNextSegment() ;
            var next = $('.editor').nextAll( selector  ).first();

            if (next.is('section')) {
                UI.scrollSegment(next);
                UI.editAreaClick($(UI.targetContainerSelector(), next), 'moving');
                // $(UI.targetContainerSelector(), next).trigger("click", "moving");
            } else {
                next = UI.currentFile.next().find( selector ).first();
                if (next.length) {
                    UI.scrollSegment(next);
                    UI.editAreaClick($(UI.targetContainerSelector(), next), 'moving');
                    // $(UI.targetContainerSelector(), next).trigger("click", "moving");
                } else {
                    UI.closeSegment(UI.currentSegment, 1, 'save');
                }
            }
        },
        gotoNextUntranslatedSegment: function() {
            console.log('gotoNextUntranslatedSegment');
            if (!UI.segmentIsLoaded(UI.nextUntranslatedSegmentId)) {
                if (!UI.nextUntranslatedSegmentId) {
                    UI.closeSegment(UI.currentSegment);
                } else {
                    UI.reloadWarning();
                }
            } else {
                $("#segment-" + UI.nextUntranslatedSegmentId +
                    " " + UI.targetContainerSelector() ).trigger("click");
            }
        },

        gotoOpenSegment: function(quick) {
            quick = quick || false;

            if ($('#segment-' + this.currentSegmentId).length) {
                UI.scrollSegment(this.currentSegment, this.currentSegmentId, false, quick);
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
            var selector = UI.selectorForNextSegment() ;
            var prev = $('.editor').prevAll( selector ).first();
            if (prev.is('section')) {
                UI.scrollSegment(prev);
                UI.editAreaClick($(UI.targetContainerSelector(), prev), 'moving');
                // $(UI.targetContainerSelector(), prev).click();
            } else {
                prev = $('.editor').parents('article').prevAll( selector ).first();
                if (prev.length) {
                    // $(UI.targetContainerSelector() , prev).click();
                    UI.editAreaClick($(UI.targetContainerSelector(), prev), 'moving');
                    UI.scrollSegment(prev);
                }
            }
            if (prev.length)
                UI.scrollSegment(prev);
        },
        gotoSegment: function(id) {
            if ( !this.segmentIsLoaded(id) && UI.parsedHash.splittedSegmentId ) {
                id = UI.parsedHash.splittedSegmentId ;
            }

            if ( MBC.enabled() && MBC.wasAskedByCommentHash( id ) ) {
                MBC.openSegmentComment( UI.Segment.findEl( id ) ) ;
            } else {
                SegmentActivator.activate(id);
            }
        },
        isReadonlySegment : function( segment ) {
            return ( (segment.readonly == 'true') ||(UI.body.hasClass('archived'))) ? true : false;
        },

        isUnlockedSegment: function ( segment ) {
            let readonly = UI.isReadonlySegment(segment);
            return (segment.ice_locked === "1" && !readonly) && !_.isNull(UI.getFromStorage('unlocked-' + segment.sid))
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
            this.setTranslation({
                id_segment: this.getSegmentId(segment),
                status: this.getStatusForAutoSave( segment ) ,
                caller: 'autosave'
            });
            SegmentActions.addClassToSegment(UI.getSegmentId( segment ), 'saved');
        },
        setCurrentSegment: function(closed) {
            var reqArguments = arguments;
            var id_segment = this.currentSegmentId;
            if (closed) {
                id_segment = 0;
                UI.currentSegment = undefined;
            } else {
                setTimeout(function() {
                    window.location.hash = UI.currentSegmentId;
                }, 300);
            }

            if (this.readonly) return;
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
            var fid = segment.data("fid");
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
                SegmentActions.addClassToSegment(UI.getSegmentId( el ), 'modified');
                el.data('modified', true);
                el.trigger('modified');
            } else {
                SegmentActions.removeClassToSegment(UI.getSegmentId( el ), 'modified');
                el.data('modified', false);
                el.trigger('modified');
            }
        },

        focusSegment: function(segment) {
            var clickableEditArea = segment.find('.editarea:not(.opened)');
            if ( clickableEditArea.length == 0 || ( Review.enabled() && !isTranslated( segment ) ) ) {
                UI.openSegment( segment );
            }
            else {
                clickableEditArea.trigger('click');
            }
            $(document).trigger('ui:segment:focus', UI.getSegmentId( segment ) );
        },

        getSegmentById: function(id) {
            return $('#segment-' + id);
        },

        getEditAreaBySegmentId: function(id) {
            return $('#segment-' + id + ' .targetarea');
        },

        segmentIsLoaded: function(segmentId) {
            return UI.getSegmentById(segmentId).length > 0 ;
        },
        getContextBefore: function(segmentId) {
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
            var isSplitted = segmentAfterId.split('-').length > 1;
            if (isSplitted) {
                return this.collectSplittedTranslations(segmentAfterId, ".source");
            } else if (config.brPlaceholdEnabled)  {
                return this.postProcessEditarea(segmentAfter, '.source');
            } else {
                return $('.source', segmentAfter ).text();
            }
        },
        /**
         * findNextSegment
         *
         * Finds next segment or returns null if next segment does not exist.
         */
        findNextSegment : function(segmentId) {
            var selector = UI.selectorForNextSegment() ;
            var currentElem = (_.isUndefined(segmentId)) ? $('.editor') : $('#segment-' + segmentId);
            var next = currentElem.nextAll( selector ).first();

            if ( next.is('section') ) {
                return next ;
            } else if ( UI.currentFile ) {
                next = UI.currentFile.next().find( selector ).first();
                if ( next.length ) {
                    return next ;
                }
            }
            return false ;
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
            return API.SEGMENT.approveSegments(segmentsArray).done(function ( response ) {
                if (response.data && response.unchangeble_segments.length === 0) {
                    segmentsArray.forEach(function ( item ) {
                        let fileId = UI.getSegmentFileId(UI.getSegmentById(item));
                        SegmentActions.setStatus(item, fileId, "APPROVED");
                        let $segment = UI.getSegmentById(item);
                        if ( $segment ) {
                            UI.setSegmentModified( $segment, false ) ;
                            UI.disableTPOnSegment( $segment )
                        }

                    })
                } else if (response.unchangeble_segments.length > 0) {
                    let arrayMapped = _.map(segmentsArray, function ( item ) {
                        return parseInt(item);
                    });
                    let array = _.difference(arrayMapped, response.unchangeble_segments);
                    array.forEach(function ( item ) {
                        let fileId = UI.getSegmentFileId(UI.getSegmentById(item));
                        SegmentActions.setStatus(item, fileId, "APPROVED");
                        let $segment = UI.getSegmentById(item);
                        if ( $segment ) {
                            UI.setSegmentModified( $segment, false ) ;
                            UI.disableTPOnSegment( $segment )
                        }
                    });
                    UI.showApproveAllModalWarnirng();
                }
            });
        },
        translateFilteredSegments: function(segmentsArray) {
            return API.SEGMENT.translateSegments(segmentsArray).done(function ( response ) {
                if (response.data && response.unchangeble_segments.length === 0) {
                    segmentsArray.forEach(function ( item ) {
                        let fileId = UI.getSegmentFileId(UI.getSegmentById(item));
                        SegmentActions.setStatus(item, fileId, "TRANSLATED");
                        let $segment = UI.getSegmentById(item);
                        if ( $segment ) {
                            UI.setSegmentModified( $segment, false ) ;
                            UI.disableTPOnSegment( $segment )
                        }
                    })
                } else if (response.unchangeble_segments.length > 0) {
                    let arrayMapped = _.map(segmentsArray, function ( item ) {
                        return parseInt(item);
                    });
                    let array = _.difference(arrayMapped, response.unchangeble_segments);
                    array.forEach(function ( item ) {
                        let fileId = UI.getSegmentFileId(UI.getSegmentById(item));
                        SegmentActions.setStatus(item, fileId, "TRANSLATED");
                        let $segment = UI.getSegmentById(item);
                        if ( $segment ) {
                            UI.setSegmentModified( $segment, false ) ;
                            UI.disableTPOnSegment( $segment )
                        }
                    });
                    UI.showTranslateAllModalWarnirng();
                }
            });
        }
    });
})(jQuery); 
