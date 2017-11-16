/*
 Component: ui.review2
 */

ReviewExtended = {
    enabled : function() {
        return Review.type === 'extended' ;
    },
    type : config.reviewType
};

if ( ReviewExtended.enabled() ) {

    // config.lqa_flat_categories = '[{"id":"336","severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}],"id_model":"61","id_parent":null,"label":"Accuracy","options":{"dqf_id":null}},{"id":"341","severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}],"id_model":"61","id_parent":null,"label":"Terminology","options":{"dqf_id":null}},{"id":"342","severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}],"id_model":"61","id_parent":null,"label":"Fluency","options":{"dqf_id":null}},{"id":"346","severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}],"id_model":"61","id_parent":null,"label":"Style","options":{"dqf_id":null}},{"id":"337","severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}],"id_model":"61","id_parent":"336","label":"Mistranslation","options":{"dqf_id":null}},{"id":"338","severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}],"id_model":"61","id_parent":"336","label":"Addition","options":{"dqf_id":null}},{"id":"339","severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}],"id_model":"61","id_parent":"336","label":"Omission","options":{"dqf_id":null}},{"id":"340","severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}],"id_model":"61","id_parent":"336","label":"Untranslated","options":{"dqf_id":null}},{"id":"343","severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}],"id_model":"61","id_parent":"342","label":"Spelling","options":{"dqf_id":null}},{"id":"344","severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}],"id_model":"61","id_parent":"342","label":"Grammar","options":{"dqf_id":null}},{"id":"345","severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}],"id_model":"61","id_parent":"342","label":"Punctuation","options":{"dqf_id":null}},{"id":"347","severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}],"id_model":"61","id_parent":"346","label":"Company Style","options":{"dqf_id":null}}]';
    // config.lqa_nested_categories = '{"categories":[{"label":"Accuracy","id":"336","severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}],"options":{"dqf_id":null},"subcategories":[{"label":"Mistranslation","id":"337","options":null,"severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}]},{"label":"Addition","id":"338","options":null,"severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}]},{"label":"Omission","id":"339","options":null,"severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}]},{"label":"Untranslated","id":"340","options":null,"severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}]}]},{"label":"Terminology","id":"341","severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}],"options":{"dqf_id":null},"subcategories":[]},{"label":"Fluency","id":"342","severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}],"options":{"dqf_id":null},"subcategories":[{"label":"Spelling","id":"343","options":null,"severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}]},{"label":"Grammar","id":"344","options":null,"severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}]},{"label":"Punctuation","id":"345","options":null,"severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}]}]},{"label":"Style","id":"346","severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}],"options":{"dqf_id":null},"subcategories":[{"label":"Company Style","id":"347","options":null,"severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}]}]}]}';

    (function (ReviewExtended, $,undefined) {

        $.extend(ReviewExtended, {
            evalOpenableSegment: function (section) {
                if (isTranslated(section)) return true;
                var sid = UI.getSegmentId(section);
                alertNotTranslatedYet(sid);
                $(document).trigger('review:unopenableSegment', section);
                return false;
            },
            submitIssue: function (sid, data_array, diff) {
                var fid = UI.getSegmentFileId(UI.getSegmentById(sid))


                var deferreds = _.map(data_array, function (data) {
                    data.diff = diff;
                    return API.SEGMENT.sendSegmentVersionIssue(sid, data)
                });

                return $.when.apply($, deferreds).done(function (response) {
                    UI.getSegmentVersionsIssues(sid, fid);
                });
            },

            submitComment : function(id_segment, id_issue, data) {
                return API.SEGMENT.sendSegmentVersionIssueComment(id_segment, id_issue, data)
                    .done( function( data ) {
                        var fid = UI.getSegmentFileId(UI.getSegmentById(id_segment));
                        UI.getSegmentVersionsIssues(id_segment, fid);
                    });
            },
        });


        $.extend(UI, {

            alertNotTranslatedMessage: "This segment is not translated yet.<br /> Only translated segments can be revised.",

            registerReviseTab: function () {
                SegmentActions.registerTab('review2', true, true);
            },

            trackChanges: function (editarea) {
                var segmentId = UI.getSegmentId($(editarea));
                var text = UI.postProcessEditarea($(editarea).closest('section'), '.editarea');
                SegmentActions.updateTranslation(segmentId, htmlEncode(text));
            },

            submitIssues: function (sid, data, diff) {
                return ReviewExtended.submitIssue(sid, data, diff);
            },

            getSegmentVersionsIssuesHandler(event) {
                let sid = event.segment.absId;
                let fid = UI.getSegmentFileId(event.segment.el);
                UI.getSegmentVersionsIssues(sid, fid);
            },

            getSegmentVersionsIssues: function (segmentId, fileId) {
                // TODO Uniform behavior of ReviewExtended and ReviewImproved
                API.SEGMENT.getSegmentVersionsIssues(segmentId)
                    .done(function (response) {
                        SegmentActions.addTranslationIssuesToSegment(fileId, segmentId, response.versions);
                    });
            },

            clickOnApprovedButton: function (e, button) {
                // the event click: 'A.APPROVED' i need to specify the tag a and not only the class
                // because of the event is triggered even on download button
                e.preventDefault();
                var goToNextNotApproved = ($(button).hasClass('approved') ) ? false : true;

                $('.sub-editor.review .error-type').removeClass('error');

                UI.changeStatus(button, 'approved', 0);  // this does < setTranslation

                if (UI.currentSegment.data('modified')) {
                    SegmentActions.openIssuesPanel({ sid: UI.getSegmentId(UI.currentSegment) });
                    SegmentActions.removeClassToSegment(UI.getSegmentId(UI.currentSegment), 'modified');
                    UI.currentSegment.data('modified', false);
                } else {
                    if (goToNextNotApproved) {
                        UI.openNextTranslated();
                    } else {
                        UI.gotoNextSegment();
                    }
                }
            },

            deleteTranslationIssue : function( context ) {
                console.debug('delete issue', context);

                var parsed = JSON.parse( context );
                var issue_path = sprintf(
                    '/api/v2/jobs/%s/%s/segments/%s/translation-issues/%s',
                    config.id_job, config.review_password,
                    parsed.id_segment,
                    parsed.id_issue
                );
                var fid = UI.getSegmentFileId(UI.getSegmentById(parsed.id_segment));
                $.ajax({
                    url: issue_path,
                    type: 'DELETE'
                }).done( function( data ) {
                    UI.getSegmentVersionsIssues(parsed.id_segment, fid);
                });
            },
            submitComment : function(id_segment, id_issue, data) {
                return ReviewExtended.submitComment(id_segment, id_issue, data)
            },


        });
    })(Review, jQuery);
}
