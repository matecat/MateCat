UI = null;

UI = {
    init: function () {
        this.Search = {};
        this.Search.filter = {};
        this.renderMoreProjects = this.renderMoreProjects.bind(this);
        this.openJobSettings = this.openJobSettings.bind(this);
        this.changeTeam = this.changeTeam.bind(this);
        this.reloadProjects = this.reloadProjects.bind(this);
        this.selectedUser = ManageConstants.ALL_MEMBERS_FILTER;

        this.popupInfoTeamsStorageName = 'infoTeamPopup-' + config.userMail;

        //Job Actions
        ProjectsStore.addListener(ManageConstants.OPEN_JOB_SETTINGS, this.openJobSettings);
        ProjectsStore.addListener(ManageConstants.OPEN_JOB_TM_PANEL, this.openJobTMPanel);

        //Modals
        TeamsStore.addListener(ManageConstants.OPEN_CREATE_TEAM_MODAL, this.openCreateTeamModal);
        TeamsStore.addListener(ManageConstants.OPEN_MODIFY_TEAM_MODAL, this.openModifyTeamModal);
        TeamsStore.addListener(ManageConstants.OPEN_CHANGE_TEAM_MODAL, this.openChangeTeamModal.bind(this));
    },

    render: function () {
        var self = this;
        var headerMountPoint = $("header")[0];
        this.Search.currentPage = 1;
        this.pageLeft = false;
        ReactDOM.render(React.createElement(Header), headerMountPoint);




        window.addEventListener('scroll', this.scrollDebounceFn());

        $(window).on("blur focus", function(e) {
            var prevType = $(this).data("prevType");

            if (prevType != e.type) {   //  reduce double fire issues
                switch (e.type) {
                    case "blur":
                        console.log("leave page");
                        self.pageLeft = true;
                        // clearInterval(UI.reloadProjectsInterval);
                        break;
                    case "focus":
                        // clearInterval(UI.reloadProjectsInterval);
                        console.log("Enter page");
                        // UI.reloadProjectsInterval = setInterval(function () {
                        //     console.log("Reload Projects");
                        //     self.reloadProjects();
                        // }, 5e3);
                        if (self.pageLeft) {
                            self.reloadProjects();
                        }
                        break;
                }
            }

            $(this).data("prevType", e.type);
        });

        API.TEAM.getAllTeams().done(function (data) {
            self.teams = data.teams;
            TeamsActions.renderTeams(self.teams);
            self.selectedTeam = APP.getLastTeamSelected(self.teams);
            self.getTeamStructure(self.selectedTeam).done(function () {
                TeamsActions.selectTeam(self.selectedTeam);
                self.checkPopupInfoTeams();
                API.PROJECTS.getProjects(self.selectedTeam, UI.Search).done(function(response){
                    if (typeof response.errors != 'undefined' && response.errors.length && response.errors[0].code === 401   ) { //Not Logged or not in the team
                        window.location.reload();
                    } else if( typeof response.errors != 'undefined' && response.errors.length && response.errors[0].code === 404){
                        UI.selectPersonalTeam();
                    } else if( typeof response.errors != 'undefined' && response.errors.length ){
                        window.location = '/';
                    } else {
                        self.renderProjects(response.data);
                    }
                });
            });
        });

    },

    reloadProjects: function () {
        var self = this;
        if ( UI.Search.currentPage === 1) {
            API.PROJECTS.getProjects(self.selectedTeam, UI.Search).done(function (response) {
                if (typeof response.errors != 'undefined' && response.errors.length && response.errors[0].code === 401   ) { //Not Logged or not in the team
                    window.location.reload();
                } else if( typeof response.errors != 'undefined' && response.errors.length && response.errors[0].code === 404){
                    UI.selectPersonalTeam();
                } else if( typeof response.errors != 'undefined' && response.errors.length ){
                    window.location = '/';
                } else {
                    var projects = response.data;
                    ManageActions.updateProjects(projects);
                }
            });
        } else {
            // ManageActions.showReloadSpinner();
            var total_projects = [];
            var requests = [];
            var onDone = function (response) {
                        var projects = response.data;
                        $.merge(total_projects, projects);
                    };
            for (var i=1; i<= UI.Search.currentPage; i++ ) {
                requests.push(API.PROJECTS.getProjects(self.selectedTeam, UI.Search, i));
            }
            $.when.apply(this, requests).done(function() {
                var results = requests.length > 1 ? arguments : [arguments];
                for( var i = 0; i < results.length; i++ ){
                    onDone(results[i][0]);
                }
                ManageActions.updateProjects(total_projects);
            });
        }
        API.TEAM.getAllTeams(true).done(function (data) {
            self.teams = data.teams;
            TeamsActions.updateTeams(self.teams);
        });

    },

    renderProjects: function (projects) {
        APP.beforeRenderProjects = new Date();
        if ( !this.ProjectsContainer ) {
            var mountPoint = $("#manage-container")[0];
            this.ProjectsContainer = ReactDOM.render(React.createElement(ProjectsContainer, {
                getLastActivity: API.PROJECTS.getLastProjectActivityLogAction,
                changeJobPasswordFn: API.JOB.changeJobPassword,
                downloadTranslationFn : this.downloadTranslation
            }), mountPoint);
        }

        ManageActions.renderProjects(projects, this.selectedTeam, this.teams);
    },

    renderMoreProjects: function () {
        UI.Search.currentPage = UI.Search.currentPage + 1;
        API.PROJECTS.getProjects(this.selectedTeam, UI.Search).done(function (response) {
            var projects = response.data;
            if (projects.length > 0) {
                ManageActions.renderMoreProjects(projects);
            } else {
                ManageActions.noMoreProjects();
            }
        });
    },

    removeUserFilter: function (uid) {
        if (UI.Search.filter.id_assignee == uid) {
            delete UI.Search.filter.id_assignee;
        }
    },

    /**
     * Open the settings for the job
     */
    openJobSettings: function (job, prName) {
        var url = '/translate/'+ prName +'/'+ job.source +'-'+ job.target +'/'+ job.id +'-'+ job.password + '&openTab=options' ;
        window.open(url, '_blank');
        setTimeout(function () {
            Cookies.set('tmpanel-open', 0, { path: '/' });
        }, 2000);
    },
    /**
     * Open the tm panel for the job
     */
    openJobTMPanel: function (job, prName) {
        var url = '/translate/'+ prName +'/'+ job.source +'-'+ job.target +'/'+ job.id +'-'+ job.password + '&openTab=tm' ;
        window.open(url, '_blank');
        setTimeout(function () {
            Cookies.set('tmpanel-open', 0, { path: '/' });
        }, 2000);
    },



    changeTeam: function (team) {

        var self = this;
        this.selectedTeam = team;
        this.selectedUser = ManageConstants.ALL_MEMBERS_FILTER;
        this.Search.filter = {};
        UI.Search.currentPage = 1;
        APP.setTeamInStorage(team.id);
        return this.getTeamStructure(team).then(function () {
                return API.PROJECTS.getProjects(self.selectedTeam, UI.Search);
            }
        );
    },

    getTeamStructure: function (team) {
        var self = this;
        return API.TEAM.getTeamMembers(team.id).then(function (data) {
            self.selectedTeam.members = data.members;
            self.selectedTeam.pending_invitations = data.pending_invitations;
        });
    },

    filterProjects: function(userUid, name, status) {
        var self = this;
        this.Search.filter = {};
        this.Search.currentPage = 1;
        var filter = {};
        if (typeof userUid != "undefined") {
             if (userUid === ManageConstants.NOT_ASSIGNED_FILTER) {
                filter.no_assignee = true;
            } else if (userUid !== ManageConstants.ALL_MEMBERS_FILTER) {
                filter.id_assignee = userUid;
            }
            this.selectedUser = userUid;
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
        return API.PROJECTS.getProjects(this.selectedTeam, UI.Search);
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

    checkPopupInfoTeams: function () {
        var openPopup = localStorage.getItem(this.popupInfoTeamsStorageName);
        if (!openPopup) {
            ManageActions.openPopupTeams();
        }
    },

    setPopupTeamsCookie: function () {
        localStorage.setItem(this.popupInfoTeamsStorageName, true);
    },

    showNotificationProjectsChanged: function () {
        var notification = {
            title: 'Ooops...',
            text: 'Something went wrong, the project has been assigned to another member or moved to another team.',
            type: 'warning',
            position: 'tc',
            allowHtml: true,
            autoDismiss: false,
        };
        var boxUndo = APP.addNotification(notification);
    },

    selectPersonalTeam: function () {
        var personalTeam = this.teams.find(function (team) {
            return team.type == 'personal';
        });
        ManageActions.changeTeam(personalTeam);
    },

    downloadTranslation: function(project, job, urlWarnings) {

        var continueDownloadFunction ;
        var callback = ManageActions.enableDownloadButton.bind(null, job.id);

        if ( project.remote_file_service == 'gdrive' ) {
            continueDownloadFunction = function() {
                APP.ModalWindow.onCloseModal();
                ManageActions.disableDownloadButton(job.id);
                APP.downloadGDriveFile(null, job.id, job.password ,callback);
            }
        }
        else  {
            continueDownloadFunction = function() {
                APP.ModalWindow.onCloseModal();
                ManageActions.disableDownloadButton(job.id);
                APP.downloadFile(job.id, job.password, callback);
            }
        }

        var openUrl = function () {
            APP.ModalWindow.onCloseModal();
            ManageActions.enableDownloadButton(job.id);
            window.open(urlWarnings, '_blank');
        };

        //the translation mismatches are not a severe Error, but only a warn, so don't display Error Popup
        if ( job.warnings_count > 0 ) {
            var props = {
                text: 'Unresolved issues may prevent downloading your translation. <br>Please fix the issues. ' +
                '<a style="color: #4183C4; font-weight: 700; text-decoration: underline;" href="https://www.matecat.com/support/advanced-features/understanding-fixing-tag-errors-tag-issues-matecat/" target="_blank">How to fix tags in MateCat </a> <br /><br />'+
                'If you continue downloading, part of the content may be untranslated - ' +
                'look for the string UNTRANSLATED_CONTENT in the downloaded files.',
                successText: "Download anyway",
                successCallback: continueDownloadFunction,
                warningText: "Fix errors",
                warningCallback: openUrl

            };
            APP.ModalWindow.showModalComponent(ConfirmMessageModal, props, "Confirmation required");
        } else {
            continueDownloadFunction();
        }

    },

    //********* Modals **************//

    openCreateTeamModal: function () {
        ModalsActions.openCreateTeamModal()
    },

    openModifyTeamModal: function (team, hideChangeName) {
        ModalsActions.openModifyTeamModal(team, hideChangeName);
    },

    openChangeTeamModal: function (teams, project) {
        ModalsActions.openChangeTeamModal(teams, project,  this.selectedTeam.id);
    }
};



$(document).ready(function(){
    UI.init();
    UI.render();
});