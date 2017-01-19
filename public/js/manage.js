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

        ProjectsStore.addListener(ManageConstants.OPEN_JOB_SETTINGS, this.openJobSettings);
        ProjectsStore.addListener(ManageConstants.OPEN_JOB_TM_PANEL, this.openJobTMPanel);

        ProjectsStore.addListener(ManageConstants.OPEN_CREATE_TEAM_MODAL, this.openCreateTeamModal);
        ProjectsStore.addListener(ManageConstants.OPEN_MODIFY_TEAM_MODAL, this.openModifyTeamModal);
        ProjectsStore.addListener(ManageConstants.OPEN_CHANGE_TEAM_MODAL, this.openChangeProjectTeam);
        ProjectsStore.addListener(ManageConstants.OPEN_CHANGE_PROJECT_ASSIGNEE, this.openChangeProjectAssignee);

        TeamsStore.addListener(ManageConstants.CREATE_TEAM, this.createTeam);




    },

    render: function () {
        var self = this;
        var headerMountPoint = $("header")[0];
        this.Search.currentPage = 1;
        this.pageLeft = false;
        ReactDOM.render(React.createElement(Header, {
            searchFn: _.debounce(function(name) {
                            self.filterProjectsFromName(name);
                        }, 300),
            filterFunction: this.filterProjectsFromStatus,
            closeSearchCallback: this.closeSearchCallback
        }), headerMountPoint);

        this.selectedTeam = "Personal";

        this.getProjects().done(function (response) {
            var projects = $.parseJSON(response.data);
            self.renderProjects(projects);
        });

        window.addEventListener('scroll', this.scrollDebounceFn());

        $(window).on("blur focus", function(e) {
            var prevType = $(this).data("prevType");

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
        this.getAllTeams().done(function (data) {
            ManageActions.renderTeams(data.teams);
        });

    },

    reloadProjects: function () {
        var self = this;
        if ( UI.Search.currentPage === 1) {
            this.getProjects().done(function (response) {
                var projects = $.parseJSON(response.data);
                ManageActions.renderProjects(projects);
            });
        } else {
            ManageActions.showReloadSpinner();
            var total_projects = [];
            var requests = [];
            var onDone = function (response) {
                        var projects = $.parseJSON(response.data);
                        $.merge(total_projects, projects);
                    };
            for (var i=1; i<= UI.Search.currentPage; i++ ) {
                requests.push(this.getProjects(i));
            }
            $.when.apply(this, requests).done(function() {
                var results = requests.length > 1 ? arguments : [arguments];
                for( var i = 0; i < results.length; i++ ){
                    onDone(results[i][0]);
                }
                ManageActions.renderProjects(total_projects, true);
            });

        }
    },





    renderProjects: function (projects) {
        if ( !this.ProjectsContainer ) {
            var mountPoint = $("#main-container")[0];
            this.ProjectsContainer = ReactDOM.render(React.createElement(ProjectsContainer, {
                getLastActivity: this.getLastProjectActivityLogAction,
                changeStatus: this.changeJobsOrProjectStatus,
                changeJobPasswordFn: this.changeJobPassword,
                downloadTranslationFn : this.downloadTranslation
            }), mountPoint);
            ManageActions.renderProjects(projects);
        }

    },

    renderMoreProjects: function () {
        UI.Search.currentPage = UI.Search.currentPage + 1;
        this.getProjects().done(function (response) {
            var projects = $.parseJSON(response.data);
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
            var self = this;
            this.performingSearchRequest = true;
            var filter = {
                pn: name
            };
            this.Search.filter = $.extend( this.Search.filter, filter );
            UI.Search.currentPage = 1;
            this.getProjects().done(function (response) {
                var projects = $.parseJSON(response.data);
                ManageActions.renderProjects(projects);
                self.performingSearchRequest = false;
            });

        }
    },

    filterProjectsFromStatus: function(status) {
        var self = this;
        var filter = {
            status: status
        };
        this.Search.filter = $.extend( this.Search.filter, filter );
        UI.Search.currentPage = 1;
        this.getProjects().done(function (response) {
            var projects = $.parseJSON(response.data);
            ManageActions.renderProjects(projects);
        });


    },

    closeSearchCallback: function () {
        UI.Search.currentPage = 1;
        if ( this.Search.filter.pn ) {
            delete this.Search.filter.pn;
        }
        this.getProjects().done(function (response) {
            var projects = $.parseJSON(response.data);
            ManageActions.renderProjects(projects);
        });
    },
    /**
     * Open the settings for the job
     */
    openJobSettings: function (job, prName) {
        var url = '/translate/'+ prName +'/'+ job.source +'-'+ job.target +'/'+ job.id +'-'+ job.password + '&openTab=options' ;
        window.open(url, '_blank');
        setTimeout(function () {
            $.cookie('tmpanel-open', 0, { path: '/' });
        }, 2000);
    },
    /**
     * Open the tm panel for the job
     */
    openJobTMPanel: function (job, prName) {
        var url = '/translate/'+ prName +'/'+ job.source +'-'+ job.target +'/'+ job.id +'-'+ job.password + '&openTab=tm' ;
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

        var id = object.id;
        var password = object.password;

        var data = {
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
    /**
     * Get Project
     * @param id
     */
    getProject: function(id) {
        var d = {
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
     * Retrieve Projects. Passing filters is possible to retrieve projects
     */
    getProjects: function(page) {
        var pageNumber = (page) ? page : UI.Search.currentPage;
        var data = {
            action: 'getProjects',
            page:	pageNumber,
            filter: (!$.isEmptyObject(UI.Search.filter)) ? 1 : 0,
        };
        // Filters
        data = $.extend(data,UI.Search.filter);

        return APP.doRequest({
            data: data,
            success: function(d){
                data = $.parseJSON(d.data);
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
        var props = {
            team: team
        };
        APP.ModalWindow.showModalComponent(ChangeProjectTeamModal, props, "Modify "+ team.name + " Team");
    },

    openChangeProjectTeam: function () {
        APP.ModalWindow.showModalComponent(ModifyTeamModal, {}, "Change team");
    },

    openChangeProjectAssignee: function () {
        APP.ModalWindow.showModalComponent(ChangeProjectAssignee, {}, "Change assignee");
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

    getAllTeams: function () {
        var teams = [
                {
                    id: 1,
                    name: 'Ebay',
                    users: [{
                        id: 2,
                        userMail: 'chloe.king@translated.net',
                        userFullName: 'Chloe King',
                        userShortName: 'CK'

                    },{
                        id: 2,
                        userMail: 'owen.james@translated.net',
                        userFullName: 'Owen	James',
                        userShortName: 'OJ'

                    },{
                        id: 3,
                        userMail: 'stephen.powell@translated.net',
                        userFullName: 'Stephen Powell',
                        userShortName: 'SP'

                    }]
                },
                {
                    id: 2,
                    name: 'MSC',
                    users: [{
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

                    }]
                },
                {
                    id: 3,
                    name: 'Translated',
                    users: [{
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

                    }]
                }
                ];
        var data = {
            teams: teams
        };
        var deferred = $.Deferred().resolve(data);
        return deferred.promise();

    },

    createTeam: function (teamName) {
        var team = {
            id: 300,
            name: teamName,
            users: [{
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

            }]
        };
        setTimeout(function () {
            ManageActions.addTeam(team);
        });
    },

    getUsers: function () {
        var users = [
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
        var self = this;
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
        var url = '/translate/'+project.name +'/'+ job.source +'-'+job.target+'/'+ job.id +'-'+ job.password + "?action=download" ;
        window.open(url, '_blank');

    },
};

$(document).ready(function(){
    UI.init();
    UI.render();
});