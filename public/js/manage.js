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
    },

    render: function () {
        var self = this;
        var headerMountPoint = $("header")[0];
        this.Search.currentPage = 1;
        ReactDOM.render(React.createElement(Header, {
            searchFn: _.debounce(function(name) {
                            self.filterProjectsFromName(name);
                        }, 300),
            filterFunction: this.filterProjectsFromStatus,
            closeSearchCallback: this.closeSearchCallback
        }), headerMountPoint);

        this.getProjects().done(function (response) {
            var projects = $.parseJSON(response.data);
            self.renderProjects(projects);
        });
        window.addEventListener('scroll', this.scrollDebounceFn());
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
    getProjects: function() {
        var data = {
            action: 'getProjects',
            page:	UI.Search.currentPage,
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