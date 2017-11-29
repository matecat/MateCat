if (!API) {
    var API = {}
}


API.SEGMENT = {

    setTranslation: function (segment) {
        var contextBefore = UI.getContextBefore(segment.sid);
        var contextAfter = UI.getContextAfter(segment.sid);
        var trans = UI.prepareTextToSend(segment.translation);
        var time_to_edit = new Date() - UI.editStart;
        // var id_translator = config.id_translator;

        var data = {
            id_segment: segment.sid,
            id_job: config.id_job,
            password: config.password,
            status: segment.status,
            translation: trans,
            segment : segment.segment,
            propagate: false,
            context_before: contextBefore,
            context_after: contextAfter,
            time_to_edit: time_to_edit,
            // id_translator: id_translator,
        };
        return $.ajax({
            data: data,
            type: "POST",
            url : "/?action=setTranslation"
        });
    },

    getSegmentVersionsIssues: function (idSegment) {

        var path  = sprintf("/api/v2/revise/jobs/%s/%s/segments/%s/translation-versions",
            config.id_job, config.password, idSegment);
        return $.ajax({
            type: "get",
            url : path
        });
    },

    sendSegmentVersionIssue: function (idSegment, data) {

        var path = sprintf('/api/v2/jobs/%s/%s/segments/%s/translation-issues',
            config.id_job, config.password, idSegment);
        return $.ajax({
            data: data,
            type: "POST",
            url : path
        });
    },

    sendSegmentVersionIssueComment: function (idSegment, idIssue, data) {

        var replies_path = sprintf(
            '/api/v2/jobs/%s/%s/segments/%s/translation-issues/%s/comments',
            config.id_job, config.password,
            idSegment,
            idIssue
        );

        return $.ajax({
            url: replies_path,
            type: 'POST',
            data : data
        })
    },

};