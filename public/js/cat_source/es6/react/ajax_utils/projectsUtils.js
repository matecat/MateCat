if (!API) {
    var API = {}
}


API.PROJECTS = {
    /**
     * Retrieve Projects. Passing filters is possible to retrieve projects
     */
    getProjects: function(team, page) {
        var pageNumber = (page) ? page : UI.Search.currentPage;
        var data = {
            action: 'getProjects',
            id_team: team.id,
            page:	pageNumber,
            filter: (!$.isEmptyObject(UI.Search.filter)) ? 1 : 0,
        };

        // Filters
        data = $.extend(data,UI.Search.filter);

        return APP.doRequest({
            data: data,
            success: function(d){

                if (typeof d.errors != 'undefined' && d.errors.length && d.errors[0].code === 401   ) { //Not Logged or not in the team
                    window.location.reload();
                } else if( typeof d.errors != 'undefined' && d.errors.length && d.errors[0].code === 404){
                    UI.selectPersonalTeam();
                    // UI.reloadProjects();
                } else if( typeof d.errors != 'undefined' && d.errors.length ){
                    window.location = '/';
                }
            },
            error: function(d){
                window.location = '/';
            }
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
            action:		"changeJobsStatus",
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

        return APP.doRequest({
            data: data,
            success: function(d){},
            error: function(d){}
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


};