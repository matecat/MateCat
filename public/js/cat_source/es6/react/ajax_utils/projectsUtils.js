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
            url : "/?action=getProjects"
        });

    },
    getProject: function(id) {

        return $.ajax({
            async: true,
            type: "get",
            url : "/api/v2/projects/" + id +"/" + config.password
        });
    },
    /**
     *
     * @param type Job or Project: obj, prj
     * @param object
     * @param status
     * @param only_if
     */
    changeJobsOrProjectStatus: function(type,object,status,only_if) {
        // Se Job cancella tutti arJobs = 21-10d78b343b8e:active

        if(typeof only_if == 'undefined') only_if = 0;

        var id = object.id;
        var password = object.password;

        var data = {
            new_status: status,
            res: 		type,            //Project or Job:
            id:			id,             // Job or Project Id
            password:   password,          // Job or Project Password
            page:		UI.Search.currentPage,        //The pagination ??
            only_if:	only_if,        // State before, for example resume project change to 'active' only_if previous state is archived
            undo:		0               // ?? REMOVED in backend endpoint. If needed, this MUST be re-implemented with sanity....
        };

        // Filters
        data = $.extend(data,UI.Search.filter);

        return $.ajax({
            data: data,
            type: "POST",
            url : "/?action=changeJobsStatus"
        });
    },

    getLastProjectActivityLogAction: function (id, pass) {
        return $.ajax({
            async: true,
            type: "get",
            url : "/api/v2/activity/project/" + id + "/" + pass + "/last",
        });
    },

    changeProjectName: function (idOrg, idProject, newName) {
        var data = {
            name: newName
        };
        return $.ajax({
            data: JSON.stringify(data),
            type: "PUT",
            url : "/api/v2/teams/" + idOrg + "/projects/" + idProject,
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
            url : "/api/v2/teams/" + idOrg + "/projects/" + idProject,
        });
    },

    changeProjectTeam: function (newTeamId, project) {
        var data = {
            id_team: newTeamId
        };
        return $.ajax({
            data: JSON.stringify(data),
            type: "PUT",
            url : "/api/v2/teams/" + project.id_team + "/projects/" + project.id
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
            url : "/?action=getVolumeAnalysis"
        });
    }


};