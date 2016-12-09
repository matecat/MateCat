UI = null;

UI = {
    init: function () {
        this.Search = {};
        this.performingSearchRequest = false;
        this.filterProjectsFromName = this.filterProjectsFromName.bind(this);
        this.renderMoreProjects = this.renderMoreProjects.bind(this);
        this.closeSearchCallback = this.closeSearchCallback.bind(this);
        this.filterProjectsFromStatus = this.filterProjectsFromStatus.bind(this);
    },
    render: function () {
        var self = this;
        var headerMountPoint = $("header")[0];
        this.Search.currentPage = 1;
        ReactDOM.render(React.createElement(Header, {
            searchFn: _.debounce(function(name) {
                            self.filterProjectsFromName(name);
                        }, 300),
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
            var mountPoint = $("#contentBox")[0];
            this.ProjectsContainer = ReactDOM.render(React.createElement(ProjectsContainer, {
                filterFunction: this.filterProjectsFromStatus
            }), mountPoint);
            ManageActions.renderProjects(projects);
        }

    },

    renderMoreProjects: function () {
        UI.Search.currentPage = UI.Search.currentPage + 1;
        this.getProjects().done(function (response) {
            var projects = $.parseJSON(response.data);
            ManageActions.renderMoreProjects(projects);
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
     *
     * @param res Job or Project: obj, prj
     * @param ob
     * @param status
     * @param only_if
     */
    changeJobsOrProjectStatus: function(res,ob,status,only_if) {
        if(typeof only_if == 'undefined') only_if = 0;

        if ( res == 'job' ) {
            UI.lastJobStatus = ob.data( 'status' );
            id = ob.data( 'jid' );
            password = ob.data( 'password' );
            console.log( 'password: ', password );

        } else {
            var arJobs = '';
            $( "tr.row", ob ).each( function () {
                arJobs += $( this ).data( 'jid' ) + "-" + $( this ).data( 'password' ) + ':' + $( this ).data( 'status' ) + ',';
            } );
            arJobs = arJobs.substring( 0, arJobs.length - 1 );
            UI.lastJobStatus = arJobs;
            id = ob.data( 'pid' );
            password = ob.data('password');
        }

        var d = {
            action:		"changeJobsStatus",
            new_status: status,
            res: 		res,            //Project or Job:
            id:			id,             // Job Id
            password:   password,
            page:		UI.Search.currentPage,        //Tha pagination ??
            step:		UI.pageStep,    //??
            only_if:	only_if,        // ??
            undo:		0               // ??
        };
        // Vengono passati anche i filtri
        // EX status: cancelled
        UI.filters = {};
        ar = $.extend(d,UI.filters);

        APP.doRequest({
            data: ar,
            context: ob,
            success: function(d){},
            error: function(d){
                // ????
                // document.location = '/';
            }
        });
    },
    /**
     * To undo an action on a Project of change status
     */
    applyUndoStatusChange: function() {
        var undo = $('.message a.undo');

        $('.message').hide();
        var new_status = $(undo).data('status');
        var res = $(undo).data('res');
        var id = $(undo).data('id');
        var password = $(undo).data('password');
        var ob = (res=='job')? $('tr.row[data-jid=' + id + ']') : $('.article[data-pid=' + id + ']');
        var d = {
            action:		"changeJobsStatus",
            new_status: new_status,
            res: 		res,
            id:			id,
            password:   password,
            page:		UI.Search.currentPage,
            step:		UI.pageStep,
            undo:		1
        };
        ar = $.extend(d,{});

        APP.doRequest({
            data: ar,
            context: ob,
            success: function(d){}
        });
    },
    /**
     * Change the password for the job
     * @param ob
     * @param pwd The job password
     * @param undo ??
     */
    changeJobPassword: function(ob,pwd,undo) {
        var res = 'job'
        if(typeof pwd == 'undefined') pwd = false;
        if(res=='job') {
            id = ob.data('jid');
            password = (pwd)? pwd : ob.data('password');
        }

        if( undo ){
            old_password = $(undo).data('old_password');
        } else {
            old_password = null;
        }

        APP.doRequest({
            data: {
                action:		    "changePassword",
                res: 		    res,
                id: 		    id,
                password: 	    password,
                old_password: 	old_password,
                undo:           ( typeof undo == 'object' )
            },
            context: ob,
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
    }
};

$(document).ready(function(){
    UI.init();
    UI.render();
});