UI = null;
UI = {
    init: function () {
        this.Search = {};
        this.Search.filter = {};
        this.performingSearchRequest = false;
        this.filterProjectsFromName = this.filterProjectsFromName.bind(this);
        this.renderMoreProjects = this.renderMoreProjects.bind(this);
        this.closeSearchCallback = this.closeSearchCallback.bind(this);
        this.filterProjectsFromStatus = this.filterProjectsFromStatus.bind(this);
        this.openJobSettings = this.openJobSettings.bind(this);
        this.changeJobsOrProjectStatus = this.changeJobsOrProjectStatus.bind(this);
        this.changeJobPassword = this.changeJobPassword.bind(this);
        this.createTeam = this.createTeam.bind(this);
        this.changeTeam = this.changeTeam.bind(this);
        this.changeProjectAssignee = this.changeProjectAssignee.bind(this);
        this.changeProjectTeam = this.changeProjectTeam.bind(this);

        ProjectsStore.addListener(ManageConstants.OPEN_JOB_SETTINGS, this.openJobSettings);
        ProjectsStore.addListener(ManageConstants.OPEN_JOB_TM_PANEL, this.openJobTMPanel);

        ProjectsStore.addListener(ManageConstants.OPEN_CREATE_TEAM_MODAL, this.openCreateTeamModal);
        ProjectsStore.addListener(ManageConstants.OPEN_MODIFY_TEAM_MODAL, this.openModifyTeamModal);
        ProjectsStore.addListener(ManageConstants.OPEN_CHANGE_TEAM_MODAL, this.openChangeProjectTeam);

        ProjectsStore.addListener(ManageConstants.CHANGE_PROJECT_ASSIGNEE, this.changeProjectAssignee);
        ProjectsStore.addListener(ManageConstants.CHANGE_PROJECT_TEAM, this.changeProjectTeam);

        TeamsStore.addListener(ManageConstants.CREATE_TEAM, this.createTeam);
        TeamsStore.addListener(ManageConstants.CHANGE_TEAM, this.changeTeam);


        //Remove this
        this.ebayProjects = EbayProjects;
        this.mscProjects = MSCProjects;
        this.translatedProjects = TranslatedProjects;


    },

    render: function () {
        let self = this;
        let headerMountPoint = $("header")[0];
        this.Search.currentPage = 1;
        this.pageLeft = false;
        ReactDOM.render(React.createElement(Header, {
            searchFn: _.debounce(function(name) {
                            self.filterProjectsFromName(name);
                        }, 300),
            filterFunction: this.filterProjectsFromStatus,
            closeSearchCallback: this.closeSearchCallback
        }), headerMountPoint);




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
        this.getAllTeams().done(function (data) {
            ManageActions.renderTeams(data.teams);
            self.teams = data.teams;
            self.selectedTeam = data.teams[0];
            self.getProjects().done(function (response) {
                let projects = response.data;
                //Remove this
                self.myProjects = projects;
                self.currentProjects = projects;
                self.renderProjects(projects);
            });
        });

    },

    reloadProjects: function (team) {
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
                ManageActions.renderProjects(total_projects, true);
            });

        }
    },

    renderProjects: function (projects) {
        if ( !this.ProjectsContainer ) {
            let mountPoint = $("#main-container")[0];
            this.ProjectsContainer = ReactDOM.render(React.createElement(ProjectsContainer, {
                getLastActivity: this.getLastProjectActivityLogAction,
                changeStatus: this.changeJobsOrProjectStatus,
                changeJobPasswordFn: this.changeJobPassword,
                downloadTranslationFn : this.downloadTranslation,
            }), mountPoint);
        }
        ManageActions.renderProjects(projects, this.selectedTeam);

    },

    renderMoreProjects: function () {
        if (this.selectedTeam.name !== 'My Workspace') {
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

    filterProjectsFromName: function(name) {
        console.log("Search " + name);
        if (!this.performingSearchRequest) {
            let self = this;
            this.performingSearchRequest = true;
            let filter = {
                pn: name
            };
            this.Search.filter = $.extend( this.Search.filter, filter );
            UI.Search.currentPage = 1;
            this.getProjects().done(function (response) {
                let projects = response.data;
                ManageActions.renderProjects(projects);
                self.performingSearchRequest = false;
            });

        }
    },

    filterProjectsFromStatus: function(status) {
        let self = this;
        let filter = {
            status: status
        };
        this.Search.filter = $.extend( this.Search.filter, filter );
        UI.Search.currentPage = 1;
        this.getProjects().done(function (response) {
            let projects = response.data;
            ManageActions.renderProjects(projects);
        });


    },

    closeSearchCallback: function () {
        UI.Search.currentPage = 1;
        if ( this.Search.filter.pn ) {
            delete this.Search.filter.pn;
        }
        this.getProjects().done(function (response) {
            let projects = response.data;
            ManageActions.renderProjects(projects);
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
            undo:		0               // ??
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
    openCreateTeamModal: function () {
        APP.ModalWindow.showModalComponent(CreateTeamModal, {}, "Create new team");
    },

    openModifyTeamModal: function (team) {
        let props = {
            team: team
        };
        APP.ModalWindow.showModalComponent(ModifyTeamModal, props, "Modify "+ team.name + " Team");
    },

    openChangeProjectTeam: function (team, project, teams) {

        let props = {
            currentTeam: team,
            project: project,
            teams: teams
        };
        APP.ModalWindow.showModalComponent(ChangeProjectTeamModal, props, "Change team");
    },

    getAllTeams: function () {
        let teams = [
            {
                id: 0,
                name: 'My Workspace',
                userWorkspace: true
            },
            {
                id: 1,
                name: 'Ebay',
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

                }]
            },
            {
                id: 2,
                name: 'MSC',
                users: [{
                    id: 0,
                    userMail: config.userMail,
                    userFullName: config.userFullName,
                    userShortName: config.userShortName

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

                }]
            },
            {
                id: 3,
                name: 'Translated',
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

                }]
            }
        ];
        let data = {
            teams: teams
        };
        let deferred = $.Deferred().resolve(data);
        return deferred.promise();

    },

    createTeam: function (teamName) {
        let self = this;
        let team = {
            id: 300,
            name: teamName,
            users: []
        };
        setTimeout(function () {
            ManageActions.addTeam(team);
        });

    },

    changeTeam: function (team) {
        let self = this;
        this.selectedTeam = team;
        if (team.name === "My Workspace") {
            this.currentProjects = this.myProjects;
        } else if (team.name === "Ebay") {
            this.currentProjects = this.ebayProjects;
        }else if (team.name === "MSC") {
            this.currentProjects = this.mscProjects;
        }else if (team.name === "Translated") {
            this.currentProjects = this.translatedProjects;
        } else {
            this.currentProjects = [];
        }
        setTimeout(function () {
            ManageActions.renderProjects(self.currentProjects, self.selectedTeam);
        });

    },

    changeProjectAssignee: function (idProject, user, teamName) {
        let projectsArray = [];
        if (teamName === "My Workspace") {
            projectsArray = this.myProjects;
        } else if (teamName === "Ebay") {
            projectsArray = this.ebayProjects;
        }else if (teamName === "MSC") {
            projectsArray = this.mscProjects;
        }else if (teamName === "Translated") {
            projectsArray = this.translatedProjects;
        }

        $.each(projectsArray, function() {
            if (this.id == idProject) {
                this.user = user;
            }
        });

        setTimeout(function () {
            ManageActions.updateProjects(projectsArray);
        });
    },

    changeProjectTeam: function (oldTeamName, team, projectId) {
        let self = this;
        $.each(this.currentProjects, function(index) {
            if (this.id == projectId) {
                indexToRemove = index;
                project = this;
            }
        });
        let removedProject = this.currentProjects.splice(indexToRemove, 1)[0];
        setTimeout(function () {
            ManageActions.updateProjects(self.currentProjects);
        });

        let projectsArray = [];
        if (team.name === "My Workspace") {
            projectsArray = this.myProjects;
        } else if (team.name === "Ebay") {
            projectsArray = this.ebayProjects;
        }else if (team.name === "MSC") {
            projectsArray = this.mscProjects;
        }else if (team.name === "Translated") {
            projectsArray = this.translatedProjects;
        }
        removedProject.team = team.name;
        projectsArray.unshift(removedProject);

    },

    getUsers: function () {
        let users = [
            {
                userMail: 'chloe.king@translated.net',
                userFullName: 'Chloe King',
                userShortName: 'CK'

            },{
                userMail: 'owen.james@translated.net',
                userFullName: 'Owen	James',
                userShortName: 'OJ'

            },{
                userMail: 'stephen.powell@translated.net',
                userFullName: 'Stephen Powell',
                userShortName: 'SP'

            },{
                userMail: 'lillian.lambert@translated.net',
                userFullName: 'Lillian	Lambert',
                userShortName: 'LL'

            },{
                userMail: 'joe.watson@translated.net',
                userFullName: 'Joe	Watson',
                userShortName: 'JW'

            },{
                userMail: 'rachel.sharp@translated.net',
                userFullName: 'Rachel	Sharp',
                userShortName: 'RS'

            },{
                userMail: 'dan.marshall@translated.net',
                userFullName: 'Dan	Marshall',
                userShortName: 'DM'

            },{
                userMail: 'vanessa.simpson@translated.net',
                userFullName: 'Vanessa	Simpson',
                userShortName: 'VS'

            },{
                userMail: 'dan.howard@translated.net',
                userFullName: 'Dan	Howard',
                userShortName: 'DH'

            },{
                userMail: 'keith.kelly@translated.net',
                userFullName: 'Keith	Kelly',
                userShortName: 'KC'

            }
        ];
        return Promise.resolve(users);
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

let EbayProjects = [
    {
        "team": "Ebay",
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
        "team": "Ebay",
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
        "team": "Ebay",
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
        "team": "Ebay",
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

let MSCProjects = [
    {
        "team": "MSC",
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
        "team": "MSC",
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
        "team": "MSC",
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
        "team": "MSC",
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

let TranslatedProjects = [
    {
        "team": "Translated",
        "tm_analysis":"241",
        "has_archived":false,
        "name":"Translated 1",
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
            id: 9,
            userMail: 'vanessa.simpson@translated.net',
            userFullName: 'Vanessa	Simpson',
            userShortName: 'VS'

        }
    },
    {
        "team": "Translated",
        "tm_analysis":"241",
        "has_archived":false,
        "name":"Translated 2",
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
            id: 9,
            userMail: 'vanessa.simpson@translated.net',
            userFullName: 'Vanessa	Simpson',
            userShortName: 'VS'

        }
    },
    {
        "team": "Translated",
        "tm_analysis":"241",
        "has_archived":false,
        "name":"Translated 2",
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
            id: 11,
            userMail: 'keith.kelly@translated.net',
            userFullName: 'Keith	Kelly',
            userShortName: 'KC'

        }
    }

];

$(document).ready(function(){
    UI.init();
    UI.render();
});