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

    config.lqa_flat_categories = '[{"id":"336","severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}],"id_model":"61","id_parent":null,"label":"Accuracy","options":{"dqf_id":null}},{"id":"341","severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}],"id_model":"61","id_parent":null,"label":"Terminology","options":{"dqf_id":null}},{"id":"342","severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}],"id_model":"61","id_parent":null,"label":"Fluency","options":{"dqf_id":null}},{"id":"346","severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}],"id_model":"61","id_parent":null,"label":"Style","options":{"dqf_id":null}},{"id":"337","severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}],"id_model":"61","id_parent":"336","label":"Mistranslation","options":{"dqf_id":null}},{"id":"338","severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}],"id_model":"61","id_parent":"336","label":"Addition","options":{"dqf_id":null}},{"id":"339","severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}],"id_model":"61","id_parent":"336","label":"Omission","options":{"dqf_id":null}},{"id":"340","severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}],"id_model":"61","id_parent":"336","label":"Untranslated","options":{"dqf_id":null}},{"id":"343","severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}],"id_model":"61","id_parent":"342","label":"Spelling","options":{"dqf_id":null}},{"id":"344","severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}],"id_model":"61","id_parent":"342","label":"Grammar","options":{"dqf_id":null}},{"id":"345","severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}],"id_model":"61","id_parent":"342","label":"Punctuation","options":{"dqf_id":null}},{"id":"347","severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}],"id_model":"61","id_parent":"346","label":"Company Style","options":{"dqf_id":null}}]';
    config.lqa_nested_categories = '{"categories":[{"label":"Accuracy","id":"336","severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}],"options":{"dqf_id":null},"subcategories":[{"label":"Mistranslation","id":"337","options":null,"severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}]},{"label":"Addition","id":"338","options":null,"severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}]},{"label":"Omission","id":"339","options":null,"severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}]},{"label":"Untranslated","id":"340","options":null,"severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}]}]},{"label":"Terminology","id":"341","severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}],"options":{"dqf_id":null},"subcategories":[]},{"label":"Fluency","id":"342","severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}],"options":{"dqf_id":null},"subcategories":[{"label":"Spelling","id":"343","options":null,"severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}]},{"label":"Grammar","id":"344","options":null,"severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}]},{"label":"Punctuation","id":"345","options":null,"severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}]}]},{"label":"Style","id":"346","severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}],"options":{"dqf_id":null},"subcategories":[{"label":"Company Style","id":"347","options":null,"severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":3},{"label":"Critical","penalty":10}]}]}]}';

    (function (ReviewExtended, $, undefined) {

        $.extend(ReviewExtended, {
            evalOpenableSegment: function (section) {
                if (isTranslated(section)) return true;
                var sid = UI.getSegmentId(section);
                alertNotTranslatedYet(sid);
                $(document).trigger('review:unopenableSegment', section);
                return false;
            },
            submitIssue: function (sid, data_array, diff) {
                var path = sprintf('/api/v2/jobs/%s/%s/segments/%s/translation-issues',
                    config.id_job, config.password, sid);

                var deferreds = _.map(data_array, function (data) {
                    return $.post(path, data)
                        .done(function (data) {
                            MateCat.db.segment_translation_issues.insert(data.issue);
                        })
                });

                return $.when.apply($, deferreds).done(function () {
                    // ReviewImproved.reloadQualityReport();
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

            submitIssues: function (sid, data) {
                return ReviewExtended.submitIssue(sid, data);
            },

            getSegmentVersionsIssues: function (event) {
                // TODO Uniform behavior of ReviewExtended and ReviewImproved
                let sid = event.segment.absId;
                let fid = UI.getSegmentFileId(event.segment.el);
                let versions = [
                    {
                        "id": 389,
                        "id_segment": 673489,
                        "id_job": 499,
                        "translation": "||| ||| Prova UNTRANSLATED_CONTENT_START&lt;g id=\"1\"&gt;ci sono innumerevoli&lt;/g&gt;&lt;g id=\"2\"&gt; variazioni &lt;g id=\"3\"&gt;passaggi&lt;/g&gt; il &lt;g id=\"4\"&gt;Lorem Ipsum&lt;/g&gt;, &lt;g id=\"5\"&gt;ma la maggior parte &lt;/g&gt;&lt;/g&gt;||| ||| UNTRANSLATED_CONTENT_END",
                        "version_number": 1,
                        "propagated_from": 0,
                        "created_at": "2017-10-24 13:52:37",
                        "issues": [
                            {
                                "comment": "",
                                "created_at": "2017-10-23T15:39:08+02:00",
                                "id": 27,
                                "id_category": 336,
                                "is_full_segment": "0",
                                "severity": "Neutral",
                                "start_node": "0",
                                "start_offset": "0",
                                "end_node": "0",
                                "end_offset": "9",
                                "translation_version": "0",
                                "target_text": "No Previe",
                                "penalty_points": "0",
                                "rebutted_at": null
                            },
                            {
                                "comment": "",
                                "created_at": "2017-10-24T11:12:01+02:00",
                                "id": 28,
                                "id_category": 337,
                                "is_full_segment": "0",
                                "severity": "Critical",
                                "start_node": "0",
                                "start_offset": "1",
                                "end_node": "0",
                                "end_offset": "9",
                                "translation_version": "0",
                                "target_text": "o Previe",
                                "penalty_points": "10",
                                "rebutted_at": null
                            }
                        ],
                        "diff": [
                            [0,"||| |||"],
                            [1," Prova"],
                            [0," UNTRANSLATED_CONTENT_START&lt;g id=\"1\"&gt;ci sono innumerevoli&lt;/g&gt;&lt;g id=\"2\"&gt; variazioni &lt;g id=\"3\"&gt;passaggi&lt;/g&gt; il &lt;g id=\"4\"&gt;Lorem Ipsum&lt;/g&gt;, &lt;g id=\"5\"&gt;ma la maggior parte &lt;/g&gt;&lt;/g&gt;||| ||| UNTRANSLATED_CONTENT_END"]
                        ]
                    },
                    {
                        "id": 388,
                        "id_segment": 673489,
                        "id_job": 499,
                        "translation": "||| ||| UNTRANSLATED_CONTENT_START&lt;g id=\"1\"&gt;ci sono innumerevoli&lt;/g&gt;&lt;g id=\"2\"&gt; variazioni &lt;g id=\"3\"&gt;passaggi&lt;/g&gt; il &lt;g id=\"4\"&gt;Lorem Ipsum&lt;/g&gt;, &lt;g id=\"5\"&gt;ma la maggior parte &lt;/g&gt;&lt;/g&gt;||| ||| UNTRANSLATED_CONTENT_END",
                        "version_number": 0,
                        "propagated_from": 0,
                        "created_at": "2017-10-24 13:52:37",
                        "issues": [
                            {
                                "comment": "",
                                "created_at": "2017-10-23T15:39:08+02:00",
                                "id": 27,
                                "id_category": 336,
                                "is_full_segment": "0",
                                "severity": "Neutral",
                                "start_node": "0",
                                "start_offset": "0",
                                "end_node": "0",
                                "end_offset": "9",
                                "translation_version": "0",
                                "target_text": "No Previe",
                                "penalty_points": "0",
                                "rebutted_at": null
                            },
                            {
                                "comment": "",
                                "created_at": "2017-10-24T11:12:01+02:00",
                                "id": 28,
                                "id_category": 337,
                                "is_full_segment": "0",
                                "severity": "Critical",
                                "start_node": "0",
                                "start_offset": "1",
                                "end_node": "0",
                                "end_offset": "9",
                                "translation_version": "0",
                                "target_text": "o Previe",
                                "penalty_points": "10",
                                "rebutted_at": null
                            }
                        ],
                        "diff": [
                            [0,"||| ||| UNTRANSLATED_CONTENT_START&lt;g id=\"1\"&gt;ci sono innumerevoli&lt;/g&gt;&lt;g id=\"2\"&gt; variazioni &lt;g id=\"3\"&gt;passaggi&lt;/g&gt; il &lt;g id=\"4\"&gt;Lorem Ipsum&lt;/g&gt;, &lt;g id=\"5\"&gt;ma la maggior parte &lt;/g&gt;&lt;/g&gt;||| ||| UNTRANSLATED_CONTENT_END"]
                        ]
                    }
                ];

                SegmentActions.addTranslationIssuesToSegment(fid, sid, versions);
            }

        });
    })(Review, jQuery);
}
