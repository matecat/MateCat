UI = null;

UI = {
    init: function () {
        this.Search = {};
        this.Search.filter = {};
        this.renderMoreProjects = this.renderMoreProjects.bind(this);
        this.openJobSettings = this.openJobSettings.bind(this);
        this.changeJobsOrProjectStatus = this.changeJobsOrProjectStatus.bind(this);
        this.changeJobPassword = this.changeJobPassword.bind(this);
        this.changeOrganization = this.changeOrganization.bind(this);
        this.changeProjectWorkspace = this.changeProjectWorkspace.bind(this);
        this.selectedWorkspace = ManageConstants.ALL_WORKSPACES_FILTER;
        this.selectedUser = ManageConstants.ALL_MEMBERS_FILTER;

        //Job Actions
        ProjectsStore.addListener(ManageConstants.OPEN_JOB_SETTINGS, this.openJobSettings);
        ProjectsStore.addListener(ManageConstants.OPEN_JOB_TM_PANEL, this.openJobTMPanel);

        //Modals
        ProjectsStore.addListener(ManageConstants.OPEN_CREATE_ORGANIZATION_MODAL, this.openCreateOrganizationModal);
        ProjectsStore.addListener(ManageConstants.OPEN_MODIFY_ORGANIZATION_MODAL, this.openModifyOrganizationModal);
        ProjectsStore.addListener(ManageConstants.OPEN_CHANGE_ORGANIZATION_MODAL, this.openChangeProjectWorkspace);
        ProjectsStore.addListener(ManageConstants.OPEN_ASSIGN_TO_TRANSLATOR_MODAL, this.openAssignToTranslator);
        ProjectsStore.addListener(ManageConstants.OPEN_CREATE_WORKSPACE_MODAL, this.openCreateWorkspace);
    },

    render: function () {
        let self = this;
        let headerMountPoint = $("header")[0];
        this.Search.currentPage = 1;
        this.pageLeft = false;
        ReactDOM.render(React.createElement(Header), headerMountPoint);




        window.addEventListener('scroll', this.scrollDebounceFn());

        // $(window).on("blur focus", function(e) {
        //     let prevType = $(this).data("prevType");
        //
        //     if (prevType != e.type) {   //  reduce double fire issues
        //         switch (e.type) {
        //             case "blur":
        //                 console.log("leave page");
        //                 self.pageLeft = true;
        //                 break;
        //             case "focus":
        //                 console.log("Enter page");
        //                 if (self.pageLeft) {
        //                     // alert("Refresf");
        //                     console.log("Refresh projects");
        //                     self.reloadProjects();
        //                 }
        //                 break;
        //         }
        //     }
        //
        //     $(this).data("prevType", e.type);
        // });

        this.getAllOrganizations().done(function (data) {

            self.organizations = data.organizations;
            ManageActions.renderOrganizations(self.organizations);
            self.selectedOrganization = data.organizations[0];
            self.getWorkspaces(self.selectedOrganization).done(function (data) {
                self.selectedOrganization.workspaces = data.workspaces;
                ManageActions.selectOrganization(self.selectedOrganization);
                self.getProjects(self.selectedOrganization).done(function (response) {
                    self.renderProjects(response.data, self.selectedOrganization);
                });
            });
        });

    },

    reloadProjects: function () {
        let self = this;
        if ( UI.Search.currentPage === 1) {
            this.getProjects(self.selectedOrganization).done(function (response) {
                let projects = response.data;
                ManageActions.renderProjects(projects);
            });
        } else {
            ManageActions.showReloadSpinner();
            let total_projects = [];
            let requests = [];
            let onDone = function (response) {
                        let projects = response.data;
                        $.merge(total_projects, projects);
                    };
            for (let i=1; i<= UI.Search.currentPage; i++ ) {
                requests.push(this.getProjects(self.selectedOrganization, i));
            }
            $.when.apply(this, requests).done(function() {
                let results = requests.length > 1 ? arguments : [arguments];
                for( let i = 0; i < results.length; i++ ){
                    onDone(results[i][0]);
                }
                ManageActions.renderProjects(total_projects, self.selectedOrganization,  true);
            });

        }
    },

    renderProjects: function (projects) {
        if ( !this.ProjectsContainer ) {
            let mountPoint = $("#manage-container")[0];
            this.ProjectsContainer = ReactDOM.render(React.createElement(ProjectsContainer, {
                getLastActivity: this.getLastProjectActivityLogAction,
                changeJobPasswordFn: this.changeJobPassword,
                downloadTranslationFn : this.downloadTranslation,
            }), mountPoint);
        }
        ManageActions.renderProjects(projects, this.selectedOrganization);

    },

    renderMoreProjects: function () {
        if (this.selectedOrganization.type !== 'personal') {
            return;
        }
        UI.Search.currentPage = UI.Search.currentPage + 1;
        this.getProjects(this.selectedOrganization).done(function (response) {
            let projects = response.data;
            if (projects.length > 0) {
                ManageActions.renderMoreProjects(projects);
            } else {
                ManageActions.noMoreProjects();
            }
        });
    },

    /**
     * Open the settings for the job
     */
    openJobSettings: function (job, prName) {
        let url = '/translate/'+ prName +'/'+ job.source +'-'+ job.target +'/'+ job.id +'-'+ job.password + '&openTab=options' ;
        window.open(url, '_blank');
        setTimeout(function () {
            $.cookie('tmpanel-open', 0, { path: '/' });
        }, 2000);
    },
    /**
     * Open the tm panel for the job
     */
    openJobTMPanel: function (job, prName) {
        let url = '/translate/'+ prName +'/'+ job.source +'-'+ job.target +'/'+ job.id +'-'+ job.password + '&openTab=tm' ;
        window.open(url, '_blank');
        setTimeout(function () {
            $.cookie('tmpanel-open', 0, { path: '/' });
        }, 2000);
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

        let id = object.id;
        let password = object.password;

        let data = {
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

    getAllOrganizations: function (force) {
        if ( APP.USER.STORE.organizations && !force) {
            let data = {
                organizations: APP.USER.STORE.organizations
            };
            let deferred = $.Deferred().resolve(data);
            return deferred.promise();
        } else {
            return APP.USER.loadUserData();
        }

    },

    changeOrganization: function (organization) {

        let self = this;
        this.selectedOrganization = organization;
        this.selectedWorkspace = ManageConstants.ALL_WORKSPACES_FILTER;
        this.selectedUser = ManageConstants.ALL_MEMBERS_FILTER;
        this.Search.filter = {};
        UI.Search.currentPage = 1;
        return this.getOrganizationStructure(organization).then(function () {
                return self.getProjects(self.selectedOrganization);
            }
        );
    },

    getOrganizationStructure: function (organization) {
        let self = this;
        return this.getOrganizationMembers(organization).then(function (data) {
            self.selectedOrganization.members = data.members;
            return self.getWorkspaces(organization).then(function (data) {
                self.selectedOrganization.workspaces = data.workspaces;
            });
        });
    },

    filterProjects: function(userUid, workspaceId, name, status) {
        let self = this;
        this.Search.filter = {};
        let filter = {};
        if (typeof userUid != "undefined") {
             if (userUid === ManageConstants.NOT_ASSIGNED_FILTER) {
                filter.no_assignee = true;
            } else if (userUid !== ManageConstants.ALL_MEMBERS_FILTER) {
                filter.id_assignee = userUid;
            }
            this.selectedUser = userUid;
        }
        if ((typeof workspaceId !== "undefined") ) {
            if (workspaceId === ManageConstants.NO_WORKSPACE_FILTER) {
                filter.no_workspace = true;
            } else if (workspaceId !== ManageConstants.ALL_WORKSPACES_FILTER) {
                filter.id_workspace = workspaceId;
            }
            this.selectedWorkspace = workspaceId;
        }
        if ((typeof name !== "undefined") ) {
            filter.pn = name;
        }
        if ((typeof status !== "undefined") ) {
            filter.status = status;
        }
        this.Search.filter = $.extend( this.Search.filter, filter );
        if (!_.isEmpty(this.Search.filter)) {
            UI.Search.currentPage = 1;
        }
        return this.getProjects(this.selectedOrganization);
    },

    scrollDebounceFn: function() {
        let self = this;
        return _.debounce(function() {
            self.handleScroll();
        }, 300)
    },

    handleScroll: function() {
        if($(window).scrollTop() + $(window).height() > $(document).height() - 200) {
            console.log("Scroll end");
            this.renderMoreProjects();
        }
    },

    //********** REQUESTS *********************//

    /**
     * Retrieve Projects. Passing filters is possible to retrieve projects
     */
    getProjects: function(organization, page) {
        let pageNumber = (page) ? page : UI.Search.currentPage;
        let data = {
            action: 'getProjects',
            id_organization: organization.id,
            page:	pageNumber,
            filter: (!$.isEmptyObject(UI.Search.filter)) ? 1 : 0,
        };
        // Filters
        data = $.extend(data,UI.Search.filter);

        return APP.doRequest({
            data: data,
            success: function(d){
                // if( typeof d.errors != 'undefined' && d.errors.length ){
                //     window.location = '/';
                // }
            },
            error: function(d){
                // window.location = '/';
            }
        });
    },

    createOrganization: function (organizationName, members) {
        let data = {
            type: 'general',
            name: organizationName,
            members: members
        };
        return $.ajax({
            async: true,
            data: data,
            type: "POST",
            url : "/api/v2/orgs"
        });

    },

    createWorkspace: function (organization, wsName) {
        let data = {
            name : wsName
        };
        return $.ajax({
            async: true,
            data: data,
            type: "POST",
            url : "/api/v2/orgs/" + organization.id + "/workspaces"
        });

    },

    removeWorkspace:function (organization, ws) {
        return $.ajax({
            async: true,
            type: "DELETE",
            url : "/api/v2/orgs/" + organization.id + "/workspaces/" + ws.id
        });
    },

    renameWorkspace:function (organization, ws) {
        let data = {
            name: ws.name
        };
        return $.ajax({
            data: JSON.stringify(data),
            type: "PUT",
            url : "/api/v2/orgs/" + organization.id + "/workspaces/" + ws.id
        });
    },

    getWorkspaces: function (organization) {
        return $.ajax({
            async: true,
            type: "GET",
            url : "/api/v2/orgs/" + organization.id + "/workspaces"
        });
    },

    getOrganizationMembers: function (organization) {
        return $.ajax({
            async: true,
            type: "get",
            url : "/api/v2/orgs/" + organization.id + "/members"
        });
    },

    downloadTranslation: function(project, job) {
        let url = '/translate/'+project.name +'/'+ job.source +'-'+job.target+'/'+ job.id +'-'+ job.password + "?action=download" ;
        window.open(url, '_blank');

    },

    getLastProjectActivityLogAction: function (id, pass) {
        return $.ajax({
            async: true,
            type: "get",
            url : "/api/v2/activity/project/" + id + "/" + pass + "/last",
        });
    },

    changeProjectWorkspace: function (wsId, projectId) {
        let data = {
            id_workspace: wsId
        };
        let idOrg = UI.selectedOrganization.id;
        return $.ajax({
            data: JSON.stringify(data),
            type: "put",
            url : "/api/v2/orgs/" + idOrg + "/projects/" + projectId,
        });
    },

    changeProjectName: function (idOrg, idProject, newName) {
        let data = {
            name: newName
        };
        return $.ajax({
            data: JSON.stringify(data),
            type: "PUT",
            url : "/api/v2/orgs/" + idOrg + "/projects/" + idProject,
        });
    },

    changeProjectAssignee: function (idOrg, idProject, newUserId) {
        let data = {
            id_assignee: newUserId
        };
        return $.ajax({
            data: JSON.stringify(data),
            type: "put",
            url : "/api/v2/orgs/" + idOrg + "/projects/" + idProject,
        });
    },

    /**
     * Change the password for the job
     * @param job
     * @param undo
     * @param old_pass
     */
    changeJobPassword: function(job, undo, old_pass) {
        let id = job.id;
        let password = job.password;

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

    addUserToOrganization: function (organization, userEmail) {
        let data = {
            members: [userEmail]
        };
        return $.ajax({
            data: data,
            type: "post",
            url : "/api/v2/orgs/"+ organization.id +"/members",
        });
    },

    removeUserFromOrganization: function (organization, userId) {
        return $.ajax({
            type: "delete",
            url : "/api/v2/orgs/"+ organization.id +"/members/" + userId,
        });
    },

    changeOrganizationName: function (organization, newName) {
        let data = {
            name: newName
        };
        return $.ajax({
            data: JSON.stringify(data),
            type: "PUT",
            url : "/api/v2/orgs/" + organization.id,
        });
    },

    //*******************************//

    //********* Modals **************//

    openCreateOrganizationModal: function () {
        APP.ModalWindow.showModalComponent(CreateOrganizationModal, {}, "Create new organization");
    },

    openModifyOrganizationModal: function (organization) {
        let props = {
            organization: organization
        };
        APP.ModalWindow.showModalComponent(ModifyOrganizationModal, props, "Modify "+ organization.get('name') + " Organization");
    },

    openChangeProjectWorkspace: function (workspaces, project) {

        let props = {
            project: project,
            workspaces: workspaces
        };
        APP.ModalWindow.showModalComponent(ChangeProjectWorkspaceModal, props, "Change Workspace");
    },

    openAssignToTranslator: function (project, job) {
        let props = {
            project: project,
            job: job
        };
        APP.ModalWindow.showModalComponent(AssignToTranslator, props, "Assign to a translator");

    },

    openCreateWorkspace: function (organization) {
        let props = {
            organization: organization
        };
        APP.ModalWindow.showModalComponent(CreateWorkspaceModal, props, "Create new workspace");
    },

    //***********************//


    /**
     * Get Project
     * @param id
     */
    getProject: function(id) {
        let d = {
            action: 'getProjects',
            project: id,
            page:	UI.Search.currentPage
        };
        // Add filters ??
        ar = $.extend(d,{});

        return APP.doRequest({
            data: ar,
            success: function(d){
                data = $.parseJSON(d.data);
            },
            error: function(d){
                window.location = '/';
            }
        });
    },

    /**
     * Mistero!
     * @param pid
     * @param psw
     * @param jid
     * @param jpsw
     */
    getOutsourceQuotes: function(pid, psw, jid, jpsw) {
        $.ajax({
            async: true,
            type: "POST",
            url : "/?action=outsourceTo",
            data:
                {
                    action: 'outsourceTo',
                    pid: pid,
                    ppassword: psw,
                    jobs:
                        [{
                            jid: jid,
                            jpassword: jpsw
                        }]
                },
            success : function ( data ) {}
        });
    },
};



$(document).ready(function(){
    UI.init();
    UI.render();
});