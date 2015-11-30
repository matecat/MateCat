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
                var status_change_title = segment.status.toLowerCase() +
                    ', click to change it'; 
            } else {
                var status_change_title = 'Change segment status' ; 
            }

            if ( t ) {
                var segment_edit_min = segment.parsed_time_to_edit[1]; 
                var segment_edit_sec = segment.parsed_time_to_edit[2]; 
            }

            var decoded_translation = UI.decodePlaceholdersToText(
                segment.translation || '', 
                true, segment.sid, 'translation'); 

            var decoded_source = UI.decodePlaceholdersToText(
                segment.segment || '', 
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
                tagLockCustomizable     : $('#tpl-taglock-customize').html(),
                tagModesEnabled         : UI.tagModesEnabled,
                decoded_translation     : decoded_translation  ,
                status_change_title     : status_change_title ,
                segment_edit_sec        : segment_edit_sec,
                segment_edit_min        : segment_edit_min
            }

            return templateData ;
        },

        getSegmentMarkup: function (
            segment, t, readonly, autoPropagated, autoPropagable,
            escapedSegment, splitAr, splitGroup, originalId
        ) {
            var data = UI.getSegmentTemplateData.apply( this, arguments )
            return UI.getSegmentTemplate()( data );
        }
    }); 
})(jQuery); 
