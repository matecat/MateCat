if (!API) {
    var API = {}
}


API.SEGMENT = {
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

};