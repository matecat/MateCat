UI = null;
UI = {
    init: function () {
        this.Search = {};
        this.Search.filter = {};
        this.performingSearchRequest = false;
        this.renderMoreProjects = this.renderMoreProjects.bind(this);
        this.openJobSettings = this.openJobSettings.bind(this);
        this.changeJobsOrProjectStatus = this.changeJobsOrProjectStatus.bind(this);
        this.changeJobPassword = this.changeJobPassword.bind(this);
        this.createOrganization = this.createOrganization.bind(this);
        this.changeOrganization = this.changeOrganization.bind(this);
        this.changeProjectAssignee = this.changeProjectAssignee.bind(this);
        this.changeProjectWorkspace = this.changeProjectWorkspace.bind(this);

        //Job Actions
        ProjectsStore.addListener(ManageConstants.OPEN_JOB_SETTINGS, this.openJobSettings);
        ProjectsStore.addListener(ManageConstants.OPEN_JOB_TM_PANEL, this.openJobTMPanel);

        //Projects actions
        ProjectsStore.addListener(ManageConstants.FILTER_PROJECTS, this.filterProjects.bind(this));

        //Organizations actions
        OrganizationsStore.addListener(ManageConstants.CREATE_ORGANIZATION, this.createOrganization);
        OrganizationsStore.addListener(ManageConstants.CHANGE_ORGANIZATION, this.changeOrganization);

        //Workspaces Actions
        // OrganizationsStore.addListener(ManageConstants.CREATE_WORKSPACE, this.createWorkspace);
        // OrganizationsStore.addListener(ManageConstants.CHANGE_WORKSPACE, this.changeWorkspace);

        //Modals
        ProjectsStore.addListener(ManageConstants.OPEN_CREATE_ORGANIZATION_MODAL, this.openCreateOrganizationModal);
        ProjectsStore.addListener(ManageConstants.OPEN_MODIFY_ORGANIZATION_MODAL, this.openModifyOrganizationModal);
        ProjectsStore.addListener(ManageConstants.OPEN_CHANGE_ORGANIZATION_MODAL, this.openChangeProjectWorkspace);
        ProjectsStore.addListener(ManageConstants.OPEN_ASSIGN_TO_TRANSLATOR_MODAL, this.openAssignToTranslator);
        ProjectsStore.addListener(ManageConstants.OPEN_CREATE_WORKSPACE_MODAL, this.openCreateWorkspace);
        ProjectsStore.addListener(ManageConstants.OPEN_MODIFY_WORKSPACE_MODAL, this.openModifyWorkspace);

        //Remove this
        this.ebayProjects = EbayProjects;
        this.mscProjects = MSCProjects;
        this.adWordsProjects = AdWordsProjects;
        this.youtubeProjects = YoutubeProjects;
        this.personalProject = PersonalProjects;
        this.otherWorkspace = WorkspaceProjects

        $(".dropdown").dropdown();


    },

    render: function () {
        let self = this;
        let headerMountPoint = $("header")[0];
        this.Search.currentPage = 1;
        this.pageLeft = false;
        ReactDOM.render(React.createElement(Header), headerMountPoint);




        window.addEventListener('scroll', this.scrollDebounceFn());

        $(window).on("blur focus", function(e) {
            let prevType = $(this).data("prevType");

            if (prevType != e.type) {   //  reduce double fire issues
                switch (e.type) {
                    case "blur":
                        console.log("leave page");
                        self.pageLeft = true;
                        break;
                    case "focus":
                        console.log("Enter page");
                        if (self.pageLeft) {
                            // alert("Refresf");
                            console.log("Refresh projects");
                            self.reloadProjects();
                        }
                        break;
                }
            }

            $(this).data("prevType", e.type);
        });
        this.getAllOrganizations().done(function (data) {

            self.organizations = data.organizations;
            self.selectedOrganization = data.organizations[0];
            self.selectedWorkspace = {
                id: 0,
                name: 'General'
            };
            self.selectedUser = {};


            ManageActions.renderOrganizations(data.organizations, self.selectedOrganization);
            self.getProjects().done(function (response) {
                let projects = response.data;
                //Remove this
                self.myProjects = projects.concat(self.personalProject, self.otherWorkspace);
                self.currentProjects = self.myProjects;
                self.renderProjects(self.myProjects, self.selectedOrganization);
            });
        });

    },

    reloadProjects: function (organization) {
        let self = this;
        if ( UI.Search.currentPage === 1) {
            this.getProjects().done(function (response) {
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
                requests.push(this.getProjects(i));
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
        if (this.selectedOrganization.name !== 'Personal') {
            return;
        }
        UI.Search.currentPage = UI.Search.currentPage + 1;
        this.getProjects().done(function (response) {
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
            step:		UI.pageStep,    // Number of Projects that returns from getProjects
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

    /**
     * Retrieve Projects. Passing filters is possible to retrieve projects
     */
    getProjects: function(page) {
        let pageNumber = (page) ? page : UI.Search.currentPage;
        let data = {
            action: 'getProjects',
            page:	pageNumber,
            filter: (!$.isEmptyObject(UI.Search.filter)) ? 1 : 0,
        };
        // Filters
        data = $.extend(data,UI.Search.filter);

        return APP.doRequest({
            data: data,
            success: function(d){
                data = d.data;
                UI.pageStep = d.pageStep;
                if( typeof d.errors != 'undefined' && d.errors.length ){
                    window.location = '/';
                }
            },
            error: function(d){
                window.location = '/';
            }
        });
    },

    getAllOrganizations: function () {
        let data = {
            organizations: organizations
        };
        let deferred = $.Deferred().resolve(data);
        return deferred.promise();

    },

    createOrganization: function (organizationName) {
        let organization = {
            id: 300,
            name: organizationName,
            users: [],
            workspaces: []
        };
        setTimeout(function () {
            ManageActions.addOrganization(organization);
        });

    },

    changeOrganization: function (organization) {
        let self = this;
        this.selectedOrganization = organization;
        if (organization.id === 0) {
            this.currentProjects = this.myProjects;
        } else if (organization.id === 1) {
            this.currentProjects = [].concat(this.ebayProjects, this.mscProjects, this.adWordsProjects, this.youtubeProjects);
        } else if (organization.id === 2) {
            this.currentProjects = [];
        } else {
            this.currentProjects = [];
        }
        setTimeout(function () {
            ManageActions.renderProjects(self.currentProjects, self.selectedOrganization);
        });

    },

    filterProjects: function(user, workspace, name, status) {
        let self = this;
        if ((typeof workspace !== "undefined") ) {
            this.selectedWorkspace = workspace;
        }
        if (typeof user != "undefined") {
            this.selectedUser =  user;
        }
        let filter = {
            status: status,
            pn: name,
            workspace: this.selectedWorkspace.name,
            user: this.selectedUser.name,
            organization: this.selectedOrganization.id
        };
        this.Search.filter = $.extend( this.Search.filter, filter );
        UI.Search.currentPage = 1;
        this.getProjects().done(function (response) {
            let projects = response.data;
            $.each(projects, function() {
                if (self.selectedUser.id) {
                    this.user = self.selectedUser;
                }
                if (self.selectedWorkspace.id  >= 0) {
                    this.workspace = self.selectedWorkspace;
                }
            });
            ManageActions.renderProjects(projects);
        });
    },

    changeProjectAssignee: function (project, user) {
        // let projectsArray = [];
        // if (organizationName === "My Workspace") {
        //     projectsArray = this.myProjects;
        // } else if (organizationName === "Ebay") {
        //     projectsArray = this.ebayProjects;
        // }else if (organizationName === "MSC") {
        //     projectsArray = this.mscProjects;
        // }else if (organizationName === "Translated") {
        //     projectsArray = this.translatedProjects;
        // }
        //
        // $.each(projectsArray, function() {
        //     if (this.id == idProject) {
        //         this.user = user;
        //     }
        // });
        //
        // setTimeout(function () {
        //     ManageActions.updateProjects(projectsArray);
        // });

        let data = {};
        let deferred = $.Deferred().resolve(data);
        return deferred.promise();
    },

    changeProjectWorkspace: function (project, workspace) {
        // let self = this;
        // $.each(this.currentProjects, function(index) {
        //     if (this.id == projectId) {
        //         indexToRemove = index;
        //         project = this;
        //     }
        // });
        // let removedProject = this.currentProjects.splice(indexToRemove, 1)[0];
        // setTimeout(function () {
        //     ManageActions.updateProjects(self.currentProjects);
        // });
        //
        // let projectsArray = [];
        // if (workspace.name === "My Workspace") {
        //     projectsArray = this.myProjects;
        // } else if (workspace.name === "Ebay") {
        //     projectsArray = this.ebayProjects;
        // }else if (workspace.name === "MSC") {
        //     projectsArray = this.mscProjects;
        // }else if (workspace.name === "Translated") {
        //     projectsArray = this.translatedProjects;
        // }
        // removedProject.organization = workspace.name;
        // projectsArray.unshift(removedProject);
        let data = {};
        let deferred = $.Deferred().resolve(data);
        return deferred.promise();
    },

    getLastProjectActivityLogAction: function (id, pass) {
        return $.ajax({
            async: true,
            type: "get",
            url : "/api/v2/activity/project/" + id + "/" + pass + "/last",
        });
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

    downloadTranslation: function(project, job) {
        let url = '/translate/'+project.name +'/'+ job.source +'-'+job.target+'/'+ job.id +'-'+ job.password + "?action=download" ;
        window.open(url, '_blank');

    },
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

    changeProjectName: function (idProject, password, newName) {
        let data = {
            name: newName
        };
        return $.ajax({
            data: data,
            type: "post",
            url : "/api/v2/projects/" + idProject + "/" + password + "/rename",
        });
    },

    openCreateOrganizationModal: function () {
        APP.ModalWindow.showModalComponent(CreateOrganizationModal, {}, "Create new organization");
    },

    openModifyOrganizationModal: function (organization) {
        let props = {
            organization: organization
        };
        APP.ModalWindow.showModalComponent(ModifyOrganizationModal, props, "Modify "+ organization.name + " Organization");
    },

    openChangeProjectWorkspace: function (workspace, project, workspaces) {

        let props = {
            currentWorkspace: workspace,
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

    openCreateWorkspace: function () {
        APP.ModalWindow.showModalComponent(CreateWorkspaceModal, {}, "Create new workspace");
    },

    openModifyWorkspace: function () {
        let props = {
            workspace: this.selectedWorkspace
        };
        APP.ModalWindow.showModalComponent(ModifyWorkspaceModal, props, "Modify "+ this.selectedWorkspace.name + " Workspace");
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
    window.organizations = [
        {
            id: 0,
            name: 'Personal',
            workspaces: [
                {
                    id: 0,
                    name: "General"
                },
                {
                    id: 1,
                    name: "Personali"
                },
                {
                    id: 2,
                    name: "Tradotti"
                }

            ]
        },
        {
            id: 1,
            name: 'Translated',
            users: [{
                id: 0,
                userMail: config.userMail,
                userFullName: config.userFullName,
                userShortName: config.userShortName

            },{
                id: 2,
                userMail: 'chloe.king@translated.net',
                userFullName: 'Chloe King',
                userShortName: 'CK'

            },{
                id: 3,
                userMail: 'owen.james@translated.net',
                userFullName: 'Owen	James',
                userShortName: 'OJ'

            },{
                id: 4,
                userMail: 'stephen.powell@translated.net',
                userFullName: 'Stephen Powell',
                userShortName: 'SP'

            },{
                id: 5,
                userMail: 'lillian.lambert@translated.net',
                userFullName: 'LillianLambert',
                userShortName: 'LL'

            },{
                id: 6,
                userMail: 'joe.watson@translated.net',
                userFullName: 'Joe Watson',
                userShortName: 'JW'

            },{
                id: 7,
                userMail: 'rachel.sharp@translated.net',
                userFullName: 'Rachel Sharp',
                userShortName: 'RS'

            },{
                id: 8,
                userMail: 'dan.marshall@translated.net',
                userFullName: 'Dan Marshall',
                userShortName: 'DM'

            }],
            workspaces: [
                {
                    id: 0,
                    name: "General"
                },
                {
                    id: 1,
                    name: "Ebay"
                },
                {
                    id: 2,
                    name: "AdWords"
                },
                {
                    id: 3,
                    name: "MSC"
                },
                {
                    id: 4,
                    name: "YouTube"
                }

            ]

        },
        {
            id: 3,
            name: 'Other Organization',
            users: [{
                id: 0,
                userMail: config.userMail,
                userFullName: config.userFullName,
                userShortName: config.userShortName

            },{
                id: 9,
                userMail: 'vanessa.simpson@translated.net',
                userFullName: 'Vanessa Simpson',
                userShortName: 'VS'

            },{
                id: 10,
                userMail: 'dan.howard@translated.net',
                userFullName: 'Dan Howard',
                userShortName: 'DH'

            },{
                id: 11,
                userMail: 'keith.kelly@translated.net',
                userFullName: 'Keith Kelly',
                userShortName: 'KC'

            }],
            workspaces: []
        }
    ];

    window.EbayProjects = [
        {
            "tm_analysis":"241",
            "has_archived":false,
            "name":"Ebay 1",
            "has_cancelled":false,
            "mt_engine_name":"MyMemory (All Pairs)",
            "no_active_jobs":"",
            "jobs":
                [
                    {"show_download_xliff":true,"job_first_segment":"326830","open_threads_count":0,"warnings_count":0,"pid":"147","subject":"general","job_last_segment":"326846","formatted_create_date":"Jan 19, 12:18","mt_engine_name":"MyMemory (All Pairs)","create_date":"2017-01-19 12:18:08","target":"ja-JP","status":"active","sourceTxt":"Italian","private_tm_key":"[]","id_tms":"1","source":"it-IT","id":"194","password":"5126c64fee83","disabled":"","stats":{"DRAFT_PERC_FORMATTED":100,"TOTAL_FORMATTED":"241","DRAFT":241.4,"TODO_FORMATTED":"241","REJECTED_PERC_FORMATTED":0,"DRAFT_PERC":100,"TOTAL":241.4,"REJECTED_PERC":0,"DOWNLOAD_STATUS":"draft","PROGRESS_FORMATTED":"0","APPROVED_PERC_FORMATTED":0,"TRANSLATED_PERC_FORMATTED":0,"PROGRESS":0,"APPROVED_PERC":0,"TRANSLATED_PERC":0,"TRANSLATED_FORMATTED":"0","APPROVED_FORMATTED":"0","PROGRESS_PERC_FORMATTED":0,"TRANSLATED":0,"APPROVED":0,"PROGRESS_PERC":0,"id":null,"REJECTED_FORMATTED":"0","REJECTED":0,"DRAFT_FORMATTED":"241"},"targetTxt":"Japanese"}
                ],
            "id_tms":"1",
            "id":"1010",
            "password":"263d689044f8",
            "user": {
                id: 2,
                userMail: 'chloe.king@translated.net',
                userFullName: 'Chloe King',
                userShortName: 'CK'

            }
        },
        {
            "tm_analysis":"241",
            "has_archived":false,
            "name":"Ebay2",
            "has_cancelled":false,
            "mt_engine_name":"MyMemory (All Pairs)",
            "no_active_jobs":"",
            "jobs":
                [
                    {"show_download_xliff":true,"job_first_segment":"326830","open_threads_count":0,"warnings_count":0,"pid":"147","subject":"general","job_last_segment":"326846","formatted_create_date":"Jan 19, 12:18","mt_engine_name":"MyMemory (All Pairs)","create_date":"2017-01-19 12:18:08","target":"ja-JP","status":"active","sourceTxt":"Italian","private_tm_key":"[]","id_tms":"1","source":"it-IT","id":"194","password":"5126c64fee83","disabled":"","stats":{"DRAFT_PERC_FORMATTED":100,"TOTAL_FORMATTED":"241","DRAFT":241.4,"TODO_FORMATTED":"241","REJECTED_PERC_FORMATTED":0,"DRAFT_PERC":100,"TOTAL":241.4,"REJECTED_PERC":0,"DOWNLOAD_STATUS":"draft","PROGRESS_FORMATTED":"0","APPROVED_PERC_FORMATTED":0,"TRANSLATED_PERC_FORMATTED":0,"PROGRESS":0,"APPROVED_PERC":0,"TRANSLATED_PERC":0,"TRANSLATED_FORMATTED":"0","APPROVED_FORMATTED":"0","PROGRESS_PERC_FORMATTED":0,"TRANSLATED":0,"APPROVED":0,"PROGRESS_PERC":0,"id":null,"REJECTED_FORMATTED":"0","REJECTED":0,"DRAFT_FORMATTED":"241"},"targetTxt":"Japanese"}
                ],
            "id_tms":"1",
            "id":"1011",
            "password":"263d689044f8",
            "user": {
                id: 2,
                userMail: 'chloe.king@translated.net',
                userFullName: 'Chloe King',
                userShortName: 'CK'

            }
        },
        {
            "tm_analysis":"241",
            "has_archived":false,
            "name":"Ebay 3",
            "has_cancelled":false,
            "mt_engine_name":"MyMemory (All Pairs)",
            "no_active_jobs":"",
            "jobs":
                [
                    {"show_download_xliff":true,"job_first_segment":"326830","open_threads_count":0,"warnings_count":0,"pid":"147","subject":"general","job_last_segment":"326846","formatted_create_date":"Jan 19, 12:18","mt_engine_name":"MyMemory (All Pairs)","create_date":"2017-01-19 12:18:08","target":"ja-JP","status":"active","sourceTxt":"Italian","private_tm_key":"[]","id_tms":"1","source":"it-IT","id":"194","password":"5126c64fee83","disabled":"","stats":{"DRAFT_PERC_FORMATTED":100,"TOTAL_FORMATTED":"241","DRAFT":241.4,"TODO_FORMATTED":"241","REJECTED_PERC_FORMATTED":0,"DRAFT_PERC":100,"TOTAL":241.4,"REJECTED_PERC":0,"DOWNLOAD_STATUS":"draft","PROGRESS_FORMATTED":"0","APPROVED_PERC_FORMATTED":0,"TRANSLATED_PERC_FORMATTED":0,"PROGRESS":0,"APPROVED_PERC":0,"TRANSLATED_PERC":0,"TRANSLATED_FORMATTED":"0","APPROVED_FORMATTED":"0","PROGRESS_PERC_FORMATTED":0,"TRANSLATED":0,"APPROVED":0,"PROGRESS_PERC":0,"id":null,"REJECTED_FORMATTED":"0","REJECTED":0,"DRAFT_FORMATTED":"241"},"targetTxt":"Japanese"}
                ],
            "id_tms":"1",
            "id":"1012",
            "password":"263d689044f8",
            "user": {
                id: 3,
                userMail: 'owen.james@translated.net',
                userFullName: 'Owen	James',
                userShortName: 'OJ'

            }
        },
        {
            "tm_analysis":"241",
            "has_archived":false,
            "name":"Ebay 4",
            "has_cancelled":false,
            "mt_engine_name":"MyMemory (All Pairs)",
            "no_active_jobs":"",
            "jobs":
                [
                    {"show_download_xliff":true,"job_first_segment":"326830","open_threads_count":0,"warnings_count":0,"pid":"147","subject":"general","job_last_segment":"326846","formatted_create_date":"Jan 19, 12:18","mt_engine_name":"MyMemory (All Pairs)","create_date":"2017-01-19 12:18:08","target":"ja-JP","status":"active","sourceTxt":"Italian","private_tm_key":"[]","id_tms":"1","source":"it-IT","id":"194","password":"5126c64fee83","disabled":"","stats":{"DRAFT_PERC_FORMATTED":100,"TOTAL_FORMATTED":"241","DRAFT":241.4,"TODO_FORMATTED":"241","REJECTED_PERC_FORMATTED":0,"DRAFT_PERC":100,"TOTAL":241.4,"REJECTED_PERC":0,"DOWNLOAD_STATUS":"draft","PROGRESS_FORMATTED":"0","APPROVED_PERC_FORMATTED":0,"TRANSLATED_PERC_FORMATTED":0,"PROGRESS":0,"APPROVED_PERC":0,"TRANSLATED_PERC":0,"TRANSLATED_FORMATTED":"0","APPROVED_FORMATTED":"0","PROGRESS_PERC_FORMATTED":0,"TRANSLATED":0,"APPROVED":0,"PROGRESS_PERC":0,"id":null,"REJECTED_FORMATTED":"0","REJECTED":0,"DRAFT_FORMATTED":"241"},"targetTxt":"Japanese"}
                ],
            "id_tms":"1",
            "id":"1013",
            "password":"263d689044f8",
            "user": {
                id: 4,
                userMail: 'stephen.powell@translated.net',
                userFullName: 'Stephen Powell',
                userShortName: 'SP'

            }
        }

    ];

    window.MSCProjects = [
        {
            "tm_analysis":"241",
            "has_archived":false,
            "name":"MSC 1",
            "has_cancelled":false,
            "mt_engine_name":"MyMemory (All Pairs)",
            "no_active_jobs":"",
            "jobs":
                [
                    {"show_download_xliff":true,"job_first_segment":"326830","open_threads_count":0,"warnings_count":0,"pid":"147","subject":"general","job_last_segment":"326846","formatted_create_date":"Jan 19, 12:18","mt_engine_name":"MyMemory (All Pairs)","create_date":"2017-01-19 12:18:08","target":"ja-JP","status":"active","sourceTxt":"Italian","private_tm_key":"[]","id_tms":"1","source":"it-IT","id":"194","password":"5126c64fee83","disabled":"","stats":{"DRAFT_PERC_FORMATTED":100,"TOTAL_FORMATTED":"241","DRAFT":241.4,"TODO_FORMATTED":"241","REJECTED_PERC_FORMATTED":0,"DRAFT_PERC":100,"TOTAL":241.4,"REJECTED_PERC":0,"DOWNLOAD_STATUS":"draft","PROGRESS_FORMATTED":"0","APPROVED_PERC_FORMATTED":0,"TRANSLATED_PERC_FORMATTED":0,"PROGRESS":0,"APPROVED_PERC":0,"TRANSLATED_PERC":0,"TRANSLATED_FORMATTED":"0","APPROVED_FORMATTED":"0","PROGRESS_PERC_FORMATTED":0,"TRANSLATED":0,"APPROVED":0,"PROGRESS_PERC":0,"id":null,"REJECTED_FORMATTED":"0","REJECTED":0,"DRAFT_FORMATTED":"241"},"targetTxt":"Japanese"}
                ],
            "id_tms":"1",
            "id":"2010",
            "password":"263d689044f8",
            "user": {
                id: 5,
                userMail: 'lillian.lambert@translated.net',
                userFullName: 'Lillian	Lambert',
                userShortName: 'LL'

            },
        },
        {
            "tm_analysis":"241",
            "has_archived":false,
            "name":"MSC 2",
            "has_cancelled":false,
            "mt_engine_name":"MyMemory (All Pairs)",
            "no_active_jobs":"",
            "jobs":
                [
                    {"show_download_xliff":true,"job_first_segment":"326830","open_threads_count":0,"warnings_count":0,"pid":"147","subject":"general","job_last_segment":"326846","formatted_create_date":"Jan 19, 12:18","mt_engine_name":"MyMemory (All Pairs)","create_date":"2017-01-19 12:18:08","target":"ja-JP","status":"active","sourceTxt":"Italian","private_tm_key":"[]","id_tms":"1","source":"it-IT","id":"194","password":"5126c64fee83","disabled":"","stats":{"DRAFT_PERC_FORMATTED":100,"TOTAL_FORMATTED":"241","DRAFT":241.4,"TODO_FORMATTED":"241","REJECTED_PERC_FORMATTED":0,"DRAFT_PERC":100,"TOTAL":241.4,"REJECTED_PERC":0,"DOWNLOAD_STATUS":"draft","PROGRESS_FORMATTED":"0","APPROVED_PERC_FORMATTED":0,"TRANSLATED_PERC_FORMATTED":0,"PROGRESS":0,"APPROVED_PERC":0,"TRANSLATED_PERC":0,"TRANSLATED_FORMATTED":"0","APPROVED_FORMATTED":"0","PROGRESS_PERC_FORMATTED":0,"TRANSLATED":0,"APPROVED":0,"PROGRESS_PERC":0,"id":null,"REJECTED_FORMATTED":"0","REJECTED":0,"DRAFT_FORMATTED":"241"},"targetTxt":"Japanese"}
                ],
            "id_tms":"1",
            "id":"2011",
            "password":"263d689044f8",
            "user": {
                id: 6,
                userMail: 'joe.watson@translated.net',
                userFullName: 'Joe	Watson',
                userShortName: 'JW'

            }
        },
        {
            "tm_analysis": "241",
            "has_archived": false,
            "name": "MSC 1",
            "has_cancelled": false,
            "mt_engine_name": "MyMemory (All Pairs)",
            "no_active_jobs": "",
            "jobs": [
                {
                    "show_download_xliff": true,
                    "job_first_segment": "326830",
                    "open_threads_count": 0,
                    "warnings_count": 0,
                    "pid": "147",
                    "subject": "general",
                    "job_last_segment": "326846",
                    "formatted_create_date": "Jan 19, 12:18",
                    "mt_engine_name": "MyMemory (All Pairs)",
                    "create_date": "2017-01-19 12:18:08",
                    "target": "ja-JP",
                    "status": "active",
                    "sourceTxt": "Italian",
                    "private_tm_key": "[]",
                    "id_tms": "1",
                    "source": "it-IT",
                    "id": "194",
                    "password": "5126c64fee83",
                    "disabled": "",
                    "stats": {
                        "DRAFT_PERC_FORMATTED": 100,
                        "TOTAL_FORMATTED": "241",
                        "DRAFT": 241.4,
                        "TODO_FORMATTED": "241",
                        "REJECTED_PERC_FORMATTED": 0,
                        "DRAFT_PERC": 100,
                        "TOTAL": 241.4,
                        "REJECTED_PERC": 0,
                        "DOWNLOAD_STATUS": "draft",
                        "PROGRESS_FORMATTED": "0",
                        "APPROVED_PERC_FORMATTED": 0,
                        "TRANSLATED_PERC_FORMATTED": 0,
                        "PROGRESS": 0,
                        "APPROVED_PERC": 0,
                        "TRANSLATED_PERC": 0,
                        "TRANSLATED_FORMATTED": "0",
                        "APPROVED_FORMATTED": "0",
                        "PROGRESS_PERC_FORMATTED": 0,
                        "TRANSLATED": 0,
                        "APPROVED": 0,
                        "PROGRESS_PERC": 0,
                        "id": null,
                        "REJECTED_FORMATTED": "0",
                        "REJECTED": 0,
                        "DRAFT_FORMATTED": "241"
                    },
                    "targetTxt": "Japanese"
                }
            ],
            "id_tms": "1",
            "id": "2012",
            "password": "263d689044f8",
            "user": {
                id: 7,
                userMail: 'rachel.sharp@translated.net',
                userFullName: 'Rachel	Sharp',
                userShortName: 'RS'

            }
        },
        {
            "tm_analysis":"241",
            "has_archived":false,
            "name":"MSC 2",
            "has_cancelled":false,
            "mt_engine_name":"MyMemory (All Pairs)",
            "no_active_jobs":"",
            "jobs":
                [
                    {"show_download_xliff":true,"job_first_segment":"326830","open_threads_count":0,"warnings_count":0,"pid":"147","subject":"general","job_last_segment":"326846","formatted_create_date":"Jan 19, 12:18","mt_engine_name":"MyMemory (All Pairs)","create_date":"2017-01-19 12:18:08","target":"ja-JP","status":"active","sourceTxt":"Italian","private_tm_key":"[]","id_tms":"1","source":"it-IT","id":"194","password":"5126c64fee83","disabled":"","stats":{"DRAFT_PERC_FORMATTED":100,"TOTAL_FORMATTED":"241","DRAFT":241.4,"TODO_FORMATTED":"241","REJECTED_PERC_FORMATTED":0,"DRAFT_PERC":100,"TOTAL":241.4,"REJECTED_PERC":0,"DOWNLOAD_STATUS":"draft","PROGRESS_FORMATTED":"0","APPROVED_PERC_FORMATTED":0,"TRANSLATED_PERC_FORMATTED":0,"PROGRESS":0,"APPROVED_PERC":0,"TRANSLATED_PERC":0,"TRANSLATED_FORMATTED":"0","APPROVED_FORMATTED":"0","PROGRESS_PERC_FORMATTED":0,"TRANSLATED":0,"APPROVED":0,"PROGRESS_PERC":0,"id":null,"REJECTED_FORMATTED":"0","REJECTED":0,"DRAFT_FORMATTED":"241"},"targetTxt":"Japanese"}
                ],
            "id_tms":"1",
            "id":"2013",
            "password":"263d689044f8",
            "user": {
                id: 7,
                userMail: 'rachel.sharp@translated.net',
                userFullName: 'Rachel	Sharp',
                userShortName: 'RS'

            }
        }

    ];

    window.AdWordsProjects = [
        {
            "tm_analysis":"241",
            "has_archived":false,
            "name":"AdWords 1",
            "has_cancelled":false,
            "mt_engine_name":"MyMemory (All Pairs)",
            "no_active_jobs":"",
            "jobs":
                [
                    {"show_download_xliff":true,"job_first_segment":"326830","open_threads_count":0,"warnings_count":0,"pid":"147","subject":"general","job_last_segment":"326846","formatted_create_date":"Jan 19, 12:18","mt_engine_name":"MyMemory (All Pairs)","create_date":"2017-01-19 12:18:08","target":"ja-JP","status":"active","sourceTxt":"Italian","private_tm_key":"[]","id_tms":"1","source":"it-IT","id":"194","password":"5126c64fee83","disabled":"","stats":{"DRAFT_PERC_FORMATTED":100,"TOTAL_FORMATTED":"241","DRAFT":241.4,"TODO_FORMATTED":"241","REJECTED_PERC_FORMATTED":0,"DRAFT_PERC":100,"TOTAL":241.4,"REJECTED_PERC":0,"DOWNLOAD_STATUS":"draft","PROGRESS_FORMATTED":"0","APPROVED_PERC_FORMATTED":0,"TRANSLATED_PERC_FORMATTED":0,"PROGRESS":0,"APPROVED_PERC":0,"TRANSLATED_PERC":0,"TRANSLATED_FORMATTED":"0","APPROVED_FORMATTED":"0","PROGRESS_PERC_FORMATTED":0,"TRANSLATED":0,"APPROVED":0,"PROGRESS_PERC":0,"id":null,"REJECTED_FORMATTED":"0","REJECTED":0,"DRAFT_FORMATTED":"241"},"targetTxt":"Japanese"}
                ],
            "id_tms":"1",
            "id":"3010",
            "password":"263d689044f8",
            "user": {
                id: 8,
                userMail: 'dan.marshall@translated.net',
                userFullName: 'Dan Marshall',
                userShortName: 'DM'

            }
        },
        {
            "tm_analysis":"241",
            "has_archived":false,
            "name":"AdWords 2",
            "has_cancelled":false,
            "mt_engine_name":"MyMemory (All Pairs)",
            "no_active_jobs":"",
            "jobs":
                [
                    {"show_download_xliff":true,"job_first_segment":"326830","open_threads_count":0,"warnings_count":0,"pid":"147","subject":"general","job_last_segment":"326846","formatted_create_date":"Jan 19, 12:18","mt_engine_name":"MyMemory (All Pairs)","create_date":"2017-01-19 12:18:08","target":"ja-JP","status":"active","sourceTxt":"Italian","private_tm_key":"[]","id_tms":"1","source":"it-IT","id":"194","password":"5126c64fee83","disabled":"","stats":{"DRAFT_PERC_FORMATTED":100,"TOTAL_FORMATTED":"241","DRAFT":241.4,"TODO_FORMATTED":"241","REJECTED_PERC_FORMATTED":0,"DRAFT_PERC":100,"TOTAL":241.4,"REJECTED_PERC":0,"DOWNLOAD_STATUS":"draft","PROGRESS_FORMATTED":"0","APPROVED_PERC_FORMATTED":0,"TRANSLATED_PERC_FORMATTED":0,"PROGRESS":0,"APPROVED_PERC":0,"TRANSLATED_PERC":0,"TRANSLATED_FORMATTED":"0","APPROVED_FORMATTED":"0","PROGRESS_PERC_FORMATTED":0,"TRANSLATED":0,"APPROVED":0,"PROGRESS_PERC":0,"id":null,"REJECTED_FORMATTED":"0","REJECTED":0,"DRAFT_FORMATTED":"241"},"targetTxt":"Japanese"}
                ],
            "id_tms":"1",
            "id":"3011",
            "password":"263d689044f8",
            "user": {
                id: 8,
                userMail: 'dan.marshall@translated.net',
                userFullName: 'Dan Marshall',
                userShortName: 'DM'

            }
        },
        {
            "tm_analysis":"241",
            "has_archived":false,
            "name":"AdWords 2",
            "has_cancelled":false,
            "mt_engine_name":"MyMemory (All Pairs)",
            "no_active_jobs":"",
            "jobs":
                [
                    {"show_download_xliff":true,"job_first_segment":"326830","open_threads_count":0,"warnings_count":0,"pid":"147","subject":"general","job_last_segment":"326846","formatted_create_date":"Jan 19, 12:18","mt_engine_name":"MyMemory (All Pairs)","create_date":"2017-01-19 12:18:08","target":"ja-JP","status":"active","sourceTxt":"Italian","private_tm_key":"[]","id_tms":"1","source":"it-IT","id":"194","password":"5126c64fee83","disabled":"","stats":{"DRAFT_PERC_FORMATTED":100,"TOTAL_FORMATTED":"241","DRAFT":241.4,"TODO_FORMATTED":"241","REJECTED_PERC_FORMATTED":0,"DRAFT_PERC":100,"TOTAL":241.4,"REJECTED_PERC":0,"DOWNLOAD_STATUS":"draft","PROGRESS_FORMATTED":"0","APPROVED_PERC_FORMATTED":0,"TRANSLATED_PERC_FORMATTED":0,"PROGRESS":0,"APPROVED_PERC":0,"TRANSLATED_PERC":0,"TRANSLATED_FORMATTED":"0","APPROVED_FORMATTED":"0","PROGRESS_PERC_FORMATTED":0,"TRANSLATED":0,"APPROVED":0,"PROGRESS_PERC":0,"id":null,"REJECTED_FORMATTED":"0","REJECTED":0,"DRAFT_FORMATTED":"241"},"targetTxt":"Japanese"}
                ],
            "id_tms":"1",
            "id":"3012",
            "password":"263d689044f8",
            "user": {
                id: 6,
                userMail: 'joe.watson@translated.net',
                userFullName: 'Joe Watson',
                userShortName: 'JW'

            }
        }

    ];

    window.YoutubeProjects = [
        {
            "tm_analysis":"241",
            "has_archived":false,
            "name":"Youtube 1",
            "has_cancelled":false,
            "mt_engine_name":"MyMemory (All Pairs)",
            "no_active_jobs":"",
            "jobs":
                [
                    {"show_download_xliff":true,"job_first_segment":"326830","open_threads_count":0,"warnings_count":0,"pid":"147","subject":"general","job_last_segment":"326846","formatted_create_date":"Jan 19, 12:18","mt_engine_name":"MyMemory (All Pairs)","create_date":"2017-01-19 12:18:08","target":"ja-JP","status":"active","sourceTxt":"Italian","private_tm_key":"[]","id_tms":"1","source":"it-IT","id":"194","password":"5126c64fee83","disabled":"","stats":{"DRAFT_PERC_FORMATTED":100,"TOTAL_FORMATTED":"241","DRAFT":241.4,"TODO_FORMATTED":"241","REJECTED_PERC_FORMATTED":0,"DRAFT_PERC":100,"TOTAL":241.4,"REJECTED_PERC":0,"DOWNLOAD_STATUS":"draft","PROGRESS_FORMATTED":"0","APPROVED_PERC_FORMATTED":0,"TRANSLATED_PERC_FORMATTED":0,"PROGRESS":0,"APPROVED_PERC":0,"TRANSLATED_PERC":0,"TRANSLATED_FORMATTED":"0","APPROVED_FORMATTED":"0","PROGRESS_PERC_FORMATTED":0,"TRANSLATED":0,"APPROVED":0,"PROGRESS_PERC":0,"id":null,"REJECTED_FORMATTED":"0","REJECTED":0,"DRAFT_FORMATTED":"241"},"targetTxt":"Japanese"}
                ],
            "id_tms":"1",
            "id":"4010",
            "password":"263d689044f8",
            "user": {
                id: 8,
                userMail: 'dan.marshall@translated.net',
                userFullName: 'Dan Marshall',
                userShortName: 'DM'

            }
        },
        {
            "tm_analysis":"241",
            "has_archived":false,
            "name":"Youtube 2",
            "has_cancelled":false,
            "mt_engine_name":"MyMemory (All Pairs)",
            "no_active_jobs":"",
            "jobs":
                [
                    {"show_download_xliff":true,"job_first_segment":"326830","open_threads_count":0,"warnings_count":0,"pid":"147","subject":"general","job_last_segment":"326846","formatted_create_date":"Jan 19, 12:18","mt_engine_name":"MyMemory (All Pairs)","create_date":"2017-01-19 12:18:08","target":"ja-JP","status":"active","sourceTxt":"Italian","private_tm_key":"[]","id_tms":"1","source":"it-IT","id":"194","password":"5126c64fee83","disabled":"","stats":{"DRAFT_PERC_FORMATTED":100,"TOTAL_FORMATTED":"241","DRAFT":241.4,"TODO_FORMATTED":"241","REJECTED_PERC_FORMATTED":0,"DRAFT_PERC":100,"TOTAL":241.4,"REJECTED_PERC":0,"DOWNLOAD_STATUS":"draft","PROGRESS_FORMATTED":"0","APPROVED_PERC_FORMATTED":0,"TRANSLATED_PERC_FORMATTED":0,"PROGRESS":0,"APPROVED_PERC":0,"TRANSLATED_PERC":0,"TRANSLATED_FORMATTED":"0","APPROVED_FORMATTED":"0","PROGRESS_PERC_FORMATTED":0,"TRANSLATED":0,"APPROVED":0,"PROGRESS_PERC":0,"id":null,"REJECTED_FORMATTED":"0","REJECTED":0,"DRAFT_FORMATTED":"241"},"targetTxt":"Japanese"}
                ],
            "id_tms":"1",
            "id":"4011",
            "password":"263d689044f8",
            "user": {
                id: 8,
                userMail: 'dan.marshall@translated.net',
                userFullName: 'Dan Marshall',
                userShortName: 'DM'

            }
        },
        {
            "tm_analysis":"241",
            "has_archived":false,
            "name":"Youtube 2",
            "has_cancelled":false,
            "mt_engine_name":"MyMemory (All Pairs)",
            "no_active_jobs":"",
            "jobs":
                [
                    {"show_download_xliff":true,"job_first_segment":"326830","open_threads_count":0,"warnings_count":0,"pid":"147","subject":"general","job_last_segment":"326846","formatted_create_date":"Jan 19, 12:18","mt_engine_name":"MyMemory (All Pairs)","create_date":"2017-01-19 12:18:08","target":"ja-JP","status":"active","sourceTxt":"Italian","private_tm_key":"[]","id_tms":"1","source":"it-IT","id":"194","password":"5126c64fee83","disabled":"","stats":{"DRAFT_PERC_FORMATTED":100,"TOTAL_FORMATTED":"241","DRAFT":241.4,"TODO_FORMATTED":"241","REJECTED_PERC_FORMATTED":0,"DRAFT_PERC":100,"TOTAL":241.4,"REJECTED_PERC":0,"DOWNLOAD_STATUS":"draft","PROGRESS_FORMATTED":"0","APPROVED_PERC_FORMATTED":0,"TRANSLATED_PERC_FORMATTED":0,"PROGRESS":0,"APPROVED_PERC":0,"TRANSLATED_PERC":0,"TRANSLATED_FORMATTED":"0","APPROVED_FORMATTED":"0","PROGRESS_PERC_FORMATTED":0,"TRANSLATED":0,"APPROVED":0,"PROGRESS_PERC":0,"id":null,"REJECTED_FORMATTED":"0","REJECTED":0,"DRAFT_FORMATTED":"241"},"targetTxt":"Japanese"}
                ],
            "id_tms":"1",
            "id":"4012",
            "password":"263d689044f8",
            "user": {
                id: 11,
                userMail: 'keith.kelly@translated.net',
                userFullName: 'Keith	Kelly',
                userShortName: 'KC'

            }
        },
        {
            "tm_analysis":"241",
            "has_archived":false,
            "name":"Youtube 3",
            "has_cancelled":false,
            "mt_engine_name":"MyMemory (All Pairs)",
            "no_active_jobs":"",
            "jobs":
                [
                    {"show_download_xliff":true,"job_first_segment":"326830","open_threads_count":0,"warnings_count":0,"pid":"147","subject":"general","job_last_segment":"326846","formatted_create_date":"Jan 19, 12:18","mt_engine_name":"MyMemory (All Pairs)","create_date":"2017-01-19 12:18:08","target":"ja-JP","status":"active","sourceTxt":"Italian","private_tm_key":"[]","id_tms":"1","source":"it-IT","id":"194","password":"5126c64fee83","disabled":"","stats":{"DRAFT_PERC_FORMATTED":100,"TOTAL_FORMATTED":"241","DRAFT":241.4,"TODO_FORMATTED":"241","REJECTED_PERC_FORMATTED":0,"DRAFT_PERC":100,"TOTAL":241.4,"REJECTED_PERC":0,"DOWNLOAD_STATUS":"draft","PROGRESS_FORMATTED":"0","APPROVED_PERC_FORMATTED":0,"TRANSLATED_PERC_FORMATTED":0,"PROGRESS":0,"APPROVED_PERC":0,"TRANSLATED_PERC":0,"TRANSLATED_FORMATTED":"0","APPROVED_FORMATTED":"0","PROGRESS_PERC_FORMATTED":0,"TRANSLATED":0,"APPROVED":0,"PROGRESS_PERC":0,"id":null,"REJECTED_FORMATTED":"0","REJECTED":0,"DRAFT_FORMATTED":"241"},"targetTxt":"Japanese"}
                ],
            "id_tms":"1",
            "id":"4013",
            "password":"263d689044f8",
            "user": {
                id: 6,
                userMail: 'joe.watson@translated.net',
                userFullName: 'Joe Watson',
                userShortName: 'JW'

            }
        }

    ];

    window.PersonalProjects = [
        {
            "tm_analysis":"241",
            "has_archived":false,
            "name":"Private 1",
            "has_cancelled":false,
            "mt_engine_name":"MyMemory (All Pairs)",
            "no_active_jobs":"",
            "jobs":
                [
                    {"show_download_xliff":true,"job_first_segment":"326830","open_threads_count":0,"warnings_count":0,"pid":"147","subject":"general","job_last_segment":"326846","formatted_create_date":"Jan 19, 12:18","mt_engine_name":"MyMemory (All Pairs)","create_date":"2017-01-19 12:18:08","target":"ja-JP","status":"active","sourceTxt":"Italian","private_tm_key":"[]","id_tms":"1","source":"it-IT","id":"194","password":"5126c64fee83","disabled":"","stats":{"DRAFT_PERC_FORMATTED":100,"TOTAL_FORMATTED":"241","DRAFT":241.4,"TODO_FORMATTED":"241","REJECTED_PERC_FORMATTED":0,"DRAFT_PERC":100,"TOTAL":241.4,"REJECTED_PERC":0,"DOWNLOAD_STATUS":"draft","PROGRESS_FORMATTED":"0","APPROVED_PERC_FORMATTED":0,"TRANSLATED_PERC_FORMATTED":0,"PROGRESS":0,"APPROVED_PERC":0,"TRANSLATED_PERC":0,"TRANSLATED_FORMATTED":"0","APPROVED_FORMATTED":"0","PROGRESS_PERC_FORMATTED":0,"TRANSLATED":0,"APPROVED":0,"PROGRESS_PERC":0,"id":null,"REJECTED_FORMATTED":"0","REJECTED":0,"DRAFT_FORMATTED":"241"},"targetTxt":"Japanese"}
                ],
            "id_tms":"1",
            "id":"6010",
            "password":"263d689044f8",
            "user": {
                id: 0,
                userMail: config.userMail,
                userFullName: config.userFullName,
                userShortName: config.userShortName

            }
        },
        {
            "tm_analysis":"241",
            "has_archived":false,
            "name":"Private 2",
            "has_cancelled":false,
            "mt_engine_name":"MyMemory (All Pairs)",
            "no_active_jobs":"",
            "jobs":
                [
                    {"show_download_xliff":true,"job_first_segment":"326830","open_threads_count":0,"warnings_count":0,"pid":"147","subject":"general","job_last_segment":"326846","formatted_create_date":"Jan 19, 12:18","mt_engine_name":"MyMemory (All Pairs)","create_date":"2017-01-19 12:18:08","target":"ja-JP","status":"active","sourceTxt":"Italian","private_tm_key":"[]","id_tms":"1","source":"it-IT","id":"194","password":"5126c64fee83","disabled":"","stats":{"DRAFT_PERC_FORMATTED":100,"TOTAL_FORMATTED":"241","DRAFT":241.4,"TODO_FORMATTED":"241","REJECTED_PERC_FORMATTED":0,"DRAFT_PERC":100,"TOTAL":241.4,"REJECTED_PERC":0,"DOWNLOAD_STATUS":"draft","PROGRESS_FORMATTED":"0","APPROVED_PERC_FORMATTED":0,"TRANSLATED_PERC_FORMATTED":0,"PROGRESS":0,"APPROVED_PERC":0,"TRANSLATED_PERC":0,"TRANSLATED_FORMATTED":"0","APPROVED_FORMATTED":"0","PROGRESS_PERC_FORMATTED":0,"TRANSLATED":0,"APPROVED":0,"PROGRESS_PERC":0,"id":null,"REJECTED_FORMATTED":"0","REJECTED":0,"DRAFT_FORMATTED":"241"},"targetTxt":"Japanese"}
                ],
            "id_tms":"1",
            "id":"6011",
            "password":"263d689044f8",
            "user": {
                id: 0,
                userMail: config.userMail,
                userFullName: config.userFullName,
                userShortName: config.userShortName

            }
        },
        {
            "tm_analysis":"241",
            "has_archived":false,
            "name":"Private 2",
            "has_cancelled":false,
            "mt_engine_name":"MyMemory (All Pairs)",
            "no_active_jobs":"",
            "jobs":
                [
                    {"show_download_xliff":true,"job_first_segment":"326830","open_threads_count":0,"warnings_count":0,"pid":"147","subject":"general","job_last_segment":"326846","formatted_create_date":"Jan 19, 12:18","mt_engine_name":"MyMemory (All Pairs)","create_date":"2017-01-19 12:18:08","target":"ja-JP","status":"active","sourceTxt":"Italian","private_tm_key":"[]","id_tms":"1","source":"it-IT","id":"194","password":"5126c64fee83","disabled":"","stats":{"DRAFT_PERC_FORMATTED":100,"TOTAL_FORMATTED":"241","DRAFT":241.4,"TODO_FORMATTED":"241","REJECTED_PERC_FORMATTED":0,"DRAFT_PERC":100,"TOTAL":241.4,"REJECTED_PERC":0,"DOWNLOAD_STATUS":"draft","PROGRESS_FORMATTED":"0","APPROVED_PERC_FORMATTED":0,"TRANSLATED_PERC_FORMATTED":0,"PROGRESS":0,"APPROVED_PERC":0,"TRANSLATED_PERC":0,"TRANSLATED_FORMATTED":"0","APPROVED_FORMATTED":"0","PROGRESS_PERC_FORMATTED":0,"TRANSLATED":0,"APPROVED":0,"PROGRESS_PERC":0,"id":null,"REJECTED_FORMATTED":"0","REJECTED":0,"DRAFT_FORMATTED":"241"},"targetTxt":"Japanese"}
                ],
            "id_tms":"1",
            "id":"6012",
            "password":"263d689044f8",
            "user": {
                id: 0,
                userMail: config.userMail,
                userFullName: config.userFullName,
                userShortName: config.userShortName

            }
        },
        {
            "tm_analysis":"241",
            "has_archived":false,
            "name":"Private 3",
            "has_cancelled":false,
            "mt_engine_name":"MyMemory (All Pairs)",
            "no_active_jobs":"",
            "jobs":
                [
                    {"show_download_xliff":true,"job_first_segment":"326830","open_threads_count":0,"warnings_count":0,"pid":"147","subject":"general","job_last_segment":"326846","formatted_create_date":"Jan 19, 12:18","mt_engine_name":"MyMemory (All Pairs)","create_date":"2017-01-19 12:18:08","target":"ja-JP","status":"active","sourceTxt":"Italian","private_tm_key":"[]","id_tms":"1","source":"it-IT","id":"194","password":"5126c64fee83","disabled":"","stats":{"DRAFT_PERC_FORMATTED":100,"TOTAL_FORMATTED":"241","DRAFT":241.4,"TODO_FORMATTED":"241","REJECTED_PERC_FORMATTED":0,"DRAFT_PERC":100,"TOTAL":241.4,"REJECTED_PERC":0,"DOWNLOAD_STATUS":"draft","PROGRESS_FORMATTED":"0","APPROVED_PERC_FORMATTED":0,"TRANSLATED_PERC_FORMATTED":0,"PROGRESS":0,"APPROVED_PERC":0,"TRANSLATED_PERC":0,"TRANSLATED_FORMATTED":"0","APPROVED_FORMATTED":"0","PROGRESS_PERC_FORMATTED":0,"TRANSLATED":0,"APPROVED":0,"PROGRESS_PERC":0,"id":null,"REJECTED_FORMATTED":"0","REJECTED":0,"DRAFT_FORMATTED":"241"},"targetTxt":"Japanese"}
                ],
            "id_tms":"1",
            "id":"6013",
            "password":"263d689044f8",
            "user": {
                id: 0,
                userMail: config.userMail,
                userFullName: config.userFullName,
                userShortName: config.userShortName

            }
        }

    ];

    window.WorkspaceProjects = [
        {
            "tm_analysis":"241",
            "has_archived":false,
            "name":"Workspace 1",
            "has_cancelled":false,
            "mt_engine_name":"MyMemory (All Pairs)",
            "no_active_jobs":"",
            "jobs":
                [
                    {"show_download_xliff":true,"job_first_segment":"326830","open_threads_count":0,"warnings_count":0,"pid":"147","subject":"general","job_last_segment":"326846","formatted_create_date":"Jan 19, 12:18","mt_engine_name":"MyMemory (All Pairs)","create_date":"2017-01-19 12:18:08","target":"ja-JP","status":"active","sourceTxt":"Italian","private_tm_key":"[]","id_tms":"1","source":"it-IT","id":"194","password":"5126c64fee83","disabled":"","stats":{"DRAFT_PERC_FORMATTED":100,"TOTAL_FORMATTED":"241","DRAFT":241.4,"TODO_FORMATTED":"241","REJECTED_PERC_FORMATTED":0,"DRAFT_PERC":100,"TOTAL":241.4,"REJECTED_PERC":0,"DOWNLOAD_STATUS":"draft","PROGRESS_FORMATTED":"0","APPROVED_PERC_FORMATTED":0,"TRANSLATED_PERC_FORMATTED":0,"PROGRESS":0,"APPROVED_PERC":0,"TRANSLATED_PERC":0,"TRANSLATED_FORMATTED":"0","APPROVED_FORMATTED":"0","PROGRESS_PERC_FORMATTED":0,"TRANSLATED":0,"APPROVED":0,"PROGRESS_PERC":0,"id":null,"REJECTED_FORMATTED":"0","REJECTED":0,"DRAFT_FORMATTED":"241"},"targetTxt":"Japanese"}
                ],
            "id_tms":"1",
            "id":"7010",
            "password":"263d689044f8",
            "user": {
                id: 0,
                userMail: config.userMail,
                userFullName: config.userFullName,
                userShortName: config.userShortName

            }
        },
        {
            "tm_analysis":"241",
            "has_archived":false,
            "name":"Workspace 2",
            "has_cancelled":false,
            "mt_engine_name":"MyMemory (All Pairs)",
            "no_active_jobs":"",
            "jobs":
                [
                    {"show_download_xliff":true,"job_first_segment":"326830","open_threads_count":0,"warnings_count":0,"pid":"147","subject":"general","job_last_segment":"326846","formatted_create_date":"Jan 19, 12:18","mt_engine_name":"MyMemory (All Pairs)","create_date":"2017-01-19 12:18:08","target":"ja-JP","status":"active","sourceTxt":"Italian","private_tm_key":"[]","id_tms":"1","source":"it-IT","id":"194","password":"5126c64fee83","disabled":"","stats":{"DRAFT_PERC_FORMATTED":100,"TOTAL_FORMATTED":"241","DRAFT":241.4,"TODO_FORMATTED":"241","REJECTED_PERC_FORMATTED":0,"DRAFT_PERC":100,"TOTAL":241.4,"REJECTED_PERC":0,"DOWNLOAD_STATUS":"draft","PROGRESS_FORMATTED":"0","APPROVED_PERC_FORMATTED":0,"TRANSLATED_PERC_FORMATTED":0,"PROGRESS":0,"APPROVED_PERC":0,"TRANSLATED_PERC":0,"TRANSLATED_FORMATTED":"0","APPROVED_FORMATTED":"0","PROGRESS_PERC_FORMATTED":0,"TRANSLATED":0,"APPROVED":0,"PROGRESS_PERC":0,"id":null,"REJECTED_FORMATTED":"0","REJECTED":0,"DRAFT_FORMATTED":"241"},"targetTxt":"Japanese"}
                ],
            "id_tms":"1",
            "id":"7011",
            "password":"263d689044f8",
            "user": {
                id: 0,
                userMail: config.userMail,
                userFullName: config.userFullName,
                userShortName: config.userShortName

            }
        },
        {
            "tm_analysis":"241",
            "has_archived":false,
            "name":"Workspace 2",
            "has_cancelled":false,
            "mt_engine_name":"MyMemory (All Pairs)",
            "no_active_jobs":"",
            "jobs":
                [
                    {"show_download_xliff":true,"job_first_segment":"326830","open_threads_count":0,"warnings_count":0,"pid":"147","subject":"general","job_last_segment":"326846","formatted_create_date":"Jan 19, 12:18","mt_engine_name":"MyMemory (All Pairs)","create_date":"2017-01-19 12:18:08","target":"ja-JP","status":"active","sourceTxt":"Italian","private_tm_key":"[]","id_tms":"1","source":"it-IT","id":"194","password":"5126c64fee83","disabled":"","stats":{"DRAFT_PERC_FORMATTED":100,"TOTAL_FORMATTED":"241","DRAFT":241.4,"TODO_FORMATTED":"241","REJECTED_PERC_FORMATTED":0,"DRAFT_PERC":100,"TOTAL":241.4,"REJECTED_PERC":0,"DOWNLOAD_STATUS":"draft","PROGRESS_FORMATTED":"0","APPROVED_PERC_FORMATTED":0,"TRANSLATED_PERC_FORMATTED":0,"PROGRESS":0,"APPROVED_PERC":0,"TRANSLATED_PERC":0,"TRANSLATED_FORMATTED":"0","APPROVED_FORMATTED":"0","PROGRESS_PERC_FORMATTED":0,"TRANSLATED":0,"APPROVED":0,"PROGRESS_PERC":0,"id":null,"REJECTED_FORMATTED":"0","REJECTED":0,"DRAFT_FORMATTED":"241"},"targetTxt":"Japanese"}
                ],
            "id_tms":"1",
            "id":"7012",
            "password":"263d689044f8",
            "user": {
                id: 0,
                userMail: config.userMail,
                userFullName: config.userFullName,
                userShortName: config.userShortName

            }
        },
        {
            "tm_analysis":"241",
            "has_archived":false,
            "name":"Workspace 3",
            "has_cancelled":false,
            "mt_engine_name":"MyMemory (All Pairs)",
            "no_active_jobs":"",
            "jobs":
                [
                    {"show_download_xliff":true,"job_first_segment":"326830","open_threads_count":0,"warnings_count":0,"pid":"147","subject":"general","job_last_segment":"326846","formatted_create_date":"Jan 19, 12:18","mt_engine_name":"MyMemory (All Pairs)","create_date":"2017-01-19 12:18:08","target":"ja-JP","status":"active","sourceTxt":"Italian","private_tm_key":"[]","id_tms":"1","source":"it-IT","id":"194","password":"5126c64fee83","disabled":"","stats":{"DRAFT_PERC_FORMATTED":100,"TOTAL_FORMATTED":"241","DRAFT":241.4,"TODO_FORMATTED":"241","REJECTED_PERC_FORMATTED":0,"DRAFT_PERC":100,"TOTAL":241.4,"REJECTED_PERC":0,"DOWNLOAD_STATUS":"draft","PROGRESS_FORMATTED":"0","APPROVED_PERC_FORMATTED":0,"TRANSLATED_PERC_FORMATTED":0,"PROGRESS":0,"APPROVED_PERC":0,"TRANSLATED_PERC":0,"TRANSLATED_FORMATTED":"0","APPROVED_FORMATTED":"0","PROGRESS_PERC_FORMATTED":0,"TRANSLATED":0,"APPROVED":0,"PROGRESS_PERC":0,"id":null,"REJECTED_FORMATTED":"0","REJECTED":0,"DRAFT_FORMATTED":"241"},"targetTxt":"Japanese"}
                ],
            "id_tms":"1",
            "id":"7013",
            "password":"263d689044f8",
            "user": {
                id: 0,
                userMail: config.userMail,
                userFullName: config.userFullName,
                userShortName: config.userShortName

            }
        }

    ];

    UI.init();
    UI.render();


});