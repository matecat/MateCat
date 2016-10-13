(function($, undefined) {
    $.extend(UI, {
        getSegmentTemplate : function() {
            return MateCat.Templates['translate/segment'];
        },
        getSegmentTemplateData : function(
            segment, t, readonly, autoPropagated, autoPropagable,
            escapedSegment, splitAr, splitGroup, originalId
        ) {
            var splitGroup = segment.split_group || splitGroup || '';

            var classes = new Array();
            if ( readonly ) {
                classes.push('readonly');
            }

            if ( segment.status ) {
                classes.push( 'status-' + segment.status.toLowerCase() );
            }
            else {
               classes.push('status-new');
            }

            if ( segment.has_reference == 'true') {
                classes.push('has-reference');
            }

            if ( segment.sid == splitGroup[0] ) {
                classes.push( 'splitStart' );
            }
            else if ( segment.sid == splitGroup[splitGroup.length - 1] ) {
                classes.push( 'splitEnd' );
            }
            else if ( splitGroup.length ) {
                classes.push('splitInner');
            }

            var editarea_classes = ['targetarea', 'invisible'];
            if ( readonly ) {
                editarea_classes.push( 'area' );
            } else {
                editarea_classes.push( 'editarea' );
            }

            if ( segment.status ) {
                var status_change_title = UI
                    .statusHandleTitleAttr( segment.status );
            } else {
                var status_change_title = 'Change segment status' ;
            }

            if ( t ) {
                var segment_edit_min = segment.parsed_time_to_edit[1];
                var segment_edit_sec = segment.parsed_time_to_edit[2];
            }
            var decoded_translation;
            var decoded_source;

            /**if Tag Projection enabled and there are not tags in the segment translation, remove it and add the class that identify
             * tha Tag Projection is enabled
             */
            if (UI.enableTagProjection && (UI.getSegmentStatus(segment) === 'draft' || UI.getSegmentStatus(segment) === 'new')
                && !UI.checkXliffTagsInText(segment.translation) ) {
                decoded_translation = UI.removeAllTags(segment.translation);
                decoded_source = UI.removeAllTags(segment.segment);
                classes.push('enableTP');
                dataAttrTagged = "nottagged";
            } else {
                decoded_translation = segment.translation;
                decoded_source = segment.segment;
                dataAttrTagged = "tagged";
            }

            decoded_translation = UI.decodePlaceholdersToText(
                decoded_translation || '',
                true, segment.sid, 'translation');

            decoded_source = UI.decodePlaceholdersToText(
                decoded_source || '',
                true, segment.sid, 'source');

            Speech2Text.enabled() && editarea_classes.push( 'micActive' ) ; 

            return  {
                t                       : t,
                originalId              : originalId,
                autoPropagated          : autoPropagated,
                autoPropagable          : autoPropagable,
                escapedSegment          : escapedSegment,
                segment                 : segment,
                readonly                : readonly,
                splitGroup              : splitGroup ,
                segment_classes         : classes.join(' '),
                shortened_sid           : UI.shortenId( segment.sid ),
                start_job_marker        : segment.sid == config.first_job_segment,
                end_job_marker          : segment.sid == config.last_job_segment ,
                decoded_text            : decoded_source,
                editarea_classes_string : editarea_classes.join(' '),
                lang                    : config.target_lang.toLowerCase(),
                tagLockCustomizable     : ( segment.segment.match( /\&lt;.*?\&gt;/gi ) ? $('#tpl-taglock-customize').html() : null ),
                tagModesEnabled         : UI.tagModesEnabled,
                decoded_translation     : decoded_translation  ,
                status_change_title     : status_change_title ,
                segment_edit_sec        : segment_edit_sec,
                segment_edit_min        : segment_edit_min, 
                s2t_enabled             : Speech2Text.enabled(),
                notEnableTagProjection  : !this.enableTagProjection,
                dataAttrTagged          : dataAttrTagged
            };

        },

        getSegmentMarkup: function (
            segment, t, readonly, autoPropagated, autoPropagable,
            escapedSegment, splitAr, splitGroup, originalId
        ) {
            var data = UI.getSegmentTemplateData.apply( this, arguments );
            return UI.getSegmentTemplate()( data );
        },
        getSegmentStatus: function (segment) {
            return (segment.status)? segment.status.toLowerCase() : 'new';
        },
        /**
         * Return che Suggestion, if exist, used by the current segment
         * retrun json
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
        setGlobalTagProjection: function (file) {
            UI.enableTagProjection = UI.checkTPEnabled(file);
        },
        /**
         * Tag Projection: check if is enable the Tag Projection
         * @param file
         */
        checkTPEnabled: function (file) {
            return (this.checkTpCanActivate() && !!config.tag_projection_enabled);
        },
        /**
         * Tag Projection: check if is possible to enable tag projection:
         * Condition: Languages it-IT en-GB en-US, not review
         * @param file
         */
        checkTpCanActivate: function () {
            if (_.isUndefined(this.tpCanActivate)) {
                var acceptedLanguages = config.tag_projection_languages
                var elemST = config.source_rfc.split("-")[0] + "-" + config.target_rfc.split("-")[0];
                var elemTS = config.target_rfc.split("-")[0] + "-" + config.source_rfc.split("-")[0];
                var supportedPair = (typeof acceptedLanguages[elemST] !== 'undefined' || typeof acceptedLanguages[elemTS] !== 'undefined');
                this.tpCanActivate = supportedPair > 0 &&
                    !config.isReview;
            }
            return this.tpCanActivate;
        },
        startSegmentTagProjection: function () {
            UI.getSegmentTagsProjection().success(function(response) {
                if (response.errors.length) {
                    UI.processErrors(response.errors, 'getTagProjection');
                    UI.copyTagProjectionInCurrentSegment();
                } else {
                    UI.copyTagProjectionInCurrentSegment(response.data.translation);
                }

            }).error(function () {
                UI.copyTagProjectionInCurrentSegment();
                UI.startOfflineMode();
            }).complete(function () {
                UI.setSegmentAsTagged();
                UI.lockTags(UI.editarea);
                UI.lockTags(UI.currentSegment.find('.source'));
                UI.editarea.focus();
                UI.highlightEditarea();
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
                var decoded_translation = UI.decodePlaceholdersToText(translation, true);
                $(this.editarea).html(decoded_translation);
            }

        },
        /**
         * Tag Projection: set a segment after tag projection is called, remove the class enableTP and set the data-tagprojection
         * attribute to tagged (after click on Guess Tags button)
         */
        setSegmentAsTagged: function (segment) {
            var currentSegment = (segment)? segment : UI.currentSegment;
            currentSegment.removeClass('enableTP');
            currentSegment.data('tagprojection', 'tagged');
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
                var dataAttribute = currentSegment.data('tagprojection');
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
                currentSegment.removeClass('enableTP');
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
            var source = currentSegment.find('.source').data('original');
            source = htmlDecode(source).replace(/&quot;/g, '\"');
            source = source.replace(/\n/g , config.lfPlaceholder)
                    .replace(/\r/g, config.crPlaceholder )
                    .replace(/\r\n/g, config.crlfPlaceholder )
                    .replace(/\t/g, config.tabPlaceholder )
                    .replace(String.fromCharCode( parseInt( 0xA0, 10 ) ), config.nbspPlaceholder );
            var decoded_source = UI.decodePlaceholdersToText(source, true);
            currentSegment.find('.source').html(decoded_source);
            UI.lockTags(currentSegment.find(".source"));
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
                UI.render();
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
                UI.render(false);
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
        }
    }); 
})(jQuery); 
