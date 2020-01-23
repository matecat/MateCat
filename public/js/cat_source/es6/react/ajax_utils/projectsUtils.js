if (!API) {
    var API = {}
}


API.PROJECTS = {
    /**
     * Retrieve Projects. Passing filters is possible to retrieve projects
     */
    getProjects: function(team, searchFilter, page) {
        var pageNumber = (page) ? page : searchFilter.currentPage;
        var data = {
            id_team: team.id,
            page:	pageNumber,
            filter: (!$.isEmptyObject(searchFilter.filter)) ? 1 : 0,
        };

        // Filters
        data = $.extend(data,searchFilter.filter);

        return $.ajax({
            data: data,
            type: "POST",
            xhrFields: { withCredentials: true },
            url : APP.getRandomUrl() + "?action=getProjects"
        });

    },
    getProject: function(id) {

        return $.ajax({
            async: true,
            type: "get",
            xhrFields: { withCredentials: true },
            url : APP.getRandomUrl() + "api/v2/projects/" + id +"/" + config.password
        });
    },
    /**
     *
     * @param type Job or Project: obj, prj
     * @param object
     * @param status
     */
    changeJobsOrProjectStatus: function(type,object,status) {
        // Se Job cancella tutti arJobs = 21-10d78b343b8e:active

        var id = object.id;
        var password = object.password;

        var data = {
            new_status: status,
            res: 		type,            //Project or Job:
            id:			id,             // Job or Project Id
            password:   password,          // Job or Project Password
        };

        // Filters
        data = $.extend(data,UI.Search.filter);

        return $.ajax({
            data: data,
            type: "POST",
            xhrFields: { withCredentials: true },
            url : APP.getRandomUrl() + "?action=changeJobsStatus"
        });
    },

    getLastProjectActivityLogAction: function (id, pass) {
        return $.ajax({
            async: true,
            type: "get",
            xhrFields: { withCredentials: true },
            url : APP.getRandomUrl() + "api/v2/activity/project/" + id + "/" + pass + "/last",
        });
    },

    changeProjectName: function (idOrg, idProject, newName) {
        var data = {
            name: newName
        };
        return $.ajax({
            data: JSON.stringify(data),
            type: "PUT",
            xhrFields: { withCredentials: true },
            url : APP.getRandomUrl() + "api/v2/teams/" + idOrg + "/projects/" + idProject,
        });
    },
    changeProjectAssignee: function (idOrg, idProject, newUserId) {
        //Pass null to unassign a Project
        var idAssignee = (newUserId == '-1') ? null : newUserId;
        var data = {
            id_assignee: idAssignee
        };
        return $.ajax({
            data: JSON.stringify(data),
            type: "put",
            xhrFields: { withCredentials: true },
            url : APP.getRandomUrl() + "api/v2/teams/" + idOrg + "/projects/" + idProject,
        });
    },

    changeProjectTeam: function (newTeamId, project) {
        var data = {
            id_team: newTeamId
        };
        return $.ajax({
            data: JSON.stringify(data),
            type: "PUT",
            xhrFields: { withCredentials: true },
            url : APP.getRandomUrl() + "api/v2/teams/" + project.id_team + "/projects/" + project.id
        });
    },
    getVolumeAnalysis: function () {
        var pid = config.id_project;
        var ppassword = config.password ;
        var data = {
            pid: pid,
            ppassword: ppassword
        };
        return $.ajax({
            data: data,
            type: "POST",
            xhrFields: { withCredentials: true },
            url : APP.getRandomUrl() + "?action=getVolumeAnalysis"
        });
    },
    getJobVolumeAnalysis: function () {
        var pid = config.id_project;
        var jpassword = config.jpassword ;
        var data = {
            pid: pid,
            jpassword: jpassword
        };
        return $.ajax({
            data: data,
            type: "POST",
            xhrFields: { withCredentials: true },
            url : APP.getRandomUrl() + "?action=getVolumeAnalysis"
        });
    },
    getCompletionStatus: function () {
        var pid = config.id_project;
        var jpassword = config.password ;
        var data = {
            pid: pid,
            jpassword: jpassword
        };
        return $.ajax({
            data: data,
            type: "GET",
            xhrFields: { withCredentials: true },
            url : APP.getRandomUrl() + "api/v2/projects/" + pid + "/" + jpassword + "/completion_status"
        });
    },
    
    getSecondPassReview: function ( idProject, passwordProject, idJob, passwordJob ) {
        var data = {
            id_job: idJob,
            password: passwordJob,
            revision_number: 2
        };
        return $.ajax({
            data: data,
            type: 'POST',
            url : '/plugins/second_pass_review/project/' + idProject + '/'+ passwordProject +'/reviews'
        });
    }
};