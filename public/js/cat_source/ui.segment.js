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

            /**if Tag Projection enabled and there are tags in the segment, remove it and add the class that identify
             * tha Tag Projection is enabled
             */
            if (UI.enableTagProjection && (UI.getSegmentStatus(segment) === 'draft' || UI.getSegmentStatus(segment) === 'new') ) {
                decoded_translation = UI.removeAllTags(segment.translation);
                decoded_source = UI.removeAllTags(segment.segment);
                classes.push('enableTP');
            } else {
                decoded_translation = segment.translation;
                decoded_source = segment.segment;
            }
            
            decoded_translation = UI.decodePlaceholdersToText(
                decoded_translation || '',
                true, segment.sid, 'translation'); 

            decoded_source = UI.decodePlaceholdersToText(
                decoded_source || '',
                true, segment.sid, 'source');



            var templateData = {
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
                enableTagProjection  : !this.enableTagProjection
            }

            return templateData ;
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
         * Tag Projection: get the tag projection for the current segment
         * @returns translation with the Tag prjection
         */
        getSegmentTagsProjection: function () {
            var source = UI.currentSegment.find('.source').data('original');
            source = htmlDecode(source).replace(/&quot;/g, '\"');
            source = htmlDecode(source);
            var target = UI.postProcessEditarea(UI.currentSegment, ".editarea");
            var suggestion;
            //Retrieve the chosen suggestion if exist
            var chosen_suggestion = $('.editarea', UI.currentSegment).data('lastChosenSuggestion');
            if (!_.isUndefined(chosen_suggestion)) {
                var storedContributions = UI.getFromStorage('contribution-' + config.id_job + '-' + UI.getSegmentId(UI.currentSegment));
                if (!_.isUndefined(storedContributions))
                var currentContribution = JSON.parse(storedContributions).data.matches[chosen_suggestion-1];
                // Send the suggestion to Tag Prjection only if is > 89% and is not MT
                if (currentContribution.match !== "MT" && parseInt(currentContribution.match) > 89) {
                    suggestion = currentContribution.translation;
                }
            }
            //Before send process with this.postProcessEditarea
            return APP.doRequest({
                data: {
                    action: 'getTagProjection',
                    password: config.password,
                    id_job: config.id_job,
                    source: source,
                    target: target,
                    source_lang: config.source_lang,
                    target_lang: config.target_lang,
                    suggestion: suggestion
                },
                error: function() {
                    console.log('getTagProjection error');
                },
                success: function(data) {
                    if (data.errors.length) {
                        UI.processErrors(d.errors, 'getTagProjection');
                    }
                    else {
                        return data.translation;
                    }
                }
            });

        },
        /**
         * Tag Projection: set the translation with tag projection to the current segment (source and editor)
         * @param translation
         */
        copyTagProjectionInCurrentSegment: function (translation) {
            var source = UI.currentSegment.find('.source').data('original');
            source = htmlDecode(source).replace(/&quot;/g, '\"');

            var decoded_source = UI.decodePlaceholdersToText(source, true);
            var decoded_translation = UI.decodePlaceholdersToText(translation, true);

            $(this.editarea).html(decoded_translation);
            UI.currentSegment.find('.source').html(decoded_source);
        },
        /**
         * Tag Projection: set a segment after tag projection is called, remove the class enableTP and set the data-tagprojection
         * attribute to tagged (after click on Guess Tags button)
         */
        setSegmentAsTagged: function () {
            UI.currentSegment.removeClass('enableTP');
            UI.currentSegment.data('tagprojection', 'tagged');
        },
        /**
         * Check if the  the Tag Projection in the current segment is enabled and still not tagged
         * @returns {boolean}
         */
        checkCurrentSegmentTPEnabled: function () {
            if (this.enableTagProjection) {
                // If the segment has tag projection enabled (has tags and has the enableTP class)
                var tagProjectionEnabled = this.hasDataOriginalTags( this.currentSegment) && this.currentSegment.hasClass('enableTP');
                // The segment is already been tagged
                var dataAttribute = UI.currentSegment.data('tagprojection');
                // If the segment has already be tagged
                var isCurrentAlreadyTagged = ( !_.isUndefined(dataAttribute) && dataAttribute === 'tagged')? true : false;
                return ( tagProjectionEnabled && !isCurrentAlreadyTagged ) ? true : false;
            }
            return false;
        },
        /**
         * Enable another time the Tag Projection, for example after clicking on the Translation Matches
         */
        enableTPOnSegmentAndSetButton: function () {
            var tagProjectionEnabled = this.hasDataOriginalTags( this.currentSegment)  && !this.currentSegment.hasClass('enableTP');
            if (this.enableTagProjection && tagProjectionEnabled) {
                UI.currentSegment.addClass('enableTP');
                UI.currentSegment.data('tagprojection', 'nottagged');
                UI.createButtons();
            }
        },
        
        
    }); 
})(jQuery); 
