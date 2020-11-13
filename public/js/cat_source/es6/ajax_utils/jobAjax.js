if (!API) {
    var API = {}
}


API.JOB = {
    /**
     * Change the password for the job
     * @param job
     * @param undo
     * @param old_pass
     */
    changeJobPassword: function(job, undo, old_pass) {
        var id = job.id;
        var password = job.password;

        return APP.doRequest({
            data: {
                action:		    "changePassword",
                res: 		    'obj',
                id: 		    id,
                password: 	    password,
                old_password: 	old_pass,
                undo:           undo
            },
            success: function(d){}
        });
    },
    checkSplitRequest: function (job, project, numsplit, arrayValues) {
        return APP.doRequest({
            data: {
                action: "splitJob",
                exec: "check",
                project_id: project.id,
                project_pass: project.password,
                job_id: job.id,
                job_pass: job.password,
                num_split: numsplit,
                split_values: arrayValues
            },
            success: function(d) {}
        });
    },
    confirmSplitRequest: function(job, project, numsplit, arrayValues) {

        return APP.doRequest({
            data: {
                action: "splitJob",
                exec: "apply",
                project_id: project.id,
                project_pass: project.password,
                job_id: job.id,
                job_pass: job.password,
                num_split: numsplit,
                split_values: arrayValues
            }
        });
    },
    confirmMerge: function(project, job) {

        return APP.doRequest({
            data: {
                action: "splitJob",
                exec: "merge",
                project_id: project.id,
                project_pass: project.password,
                job_id: job.id
            }

        });
    },
    sendTranslatorRequest: function (email, date, timezone, job) {
        var data = {
            email: email,
            delivery_date: Math.round(date/1000),
            timezone: timezone
        };
        return $.ajax({
            async: true,
            data: data,
            type: "POST",
            xhrFields: { withCredentials: true },
            url : APP.getRandomUrl() + "api/v2/jobs/" + job.id +"/" + job.password + "/translator"
        });
    },
    getJobFilesInfo: function (idJob, password) {
        return $.ajax({
            async: true,
            type: "GET",
            xhrFields: { withCredentials: true },
            url : APP.getRandomUrl() + "api/v3/jobs/" + idJob +"/" + password + "/files"
        });
    },
    retrieveStatistics: function (idJob, password) {
        return $.ajax({
            async: true,
            type: "GET",
            xhrFields: { withCredentials: true },
            url : APP.getRandomUrl() + "api/app/jobs/" + idJob +"/" + password + "/stats"
        });
    },
    sendRevisionFeedback: function (idJob, revisionNumber, password, text) {
        let data = {
            id_job: idJob,
            revision_number: revisionNumber,
            password: password,
            feedback: text
        };
        return $.ajax({
            async: true,
            data: data,
            type: "POST",
            xhrFields: { withCredentials: true },
            url : APP.getRandomUrl() + "api/v3/feedback"
        });
    },

    getJobMetadata: function (idJob, password) {
        return $.ajax({
            async: true,
            type: "GET",
            xhrFields: { withCredentials: true },
            url : APP.getRandomUrl() + "api/v3/jobs/" + idJob + '/' + password + "/metadata"
        });
    }
};