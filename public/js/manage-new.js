UI = null;

UI = {
    init: function () {

    },
    render: function () {
        var self = this;
        var headerMountPoint = $("header")[0];
        UI.page = 1;
        ReactDOM.render(React.createElement(Header, {

        }), headerMountPoint);

        this.getProjects().done(function (response) {
            var projects = $.parseJSON(response.data);
            self.renderProjects(projects);
        });

        $(document).on('getmoreprojects', function () {
            self.renderMoreProjects();
        });

    },
    renderProjects: function (projects) {
        if ( !this.ProjectsContainer ) {
            var mountPoint = $("#projects")[0];
            this.ProjectsContainer = ReactDOM.render(React.createElement(ProjectsContainer, {}), mountPoint);
            ManageActions.renderProjects(projects);
        }

    },

    renderMoreProjects: function () {
        UI.page = UI.page + 1;
        this.getProjects().done(function (response) {
            var projects = $.parseJSON(response.data);
            ManageActions.renderMoreProjects(projects);
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
            page:		UI.page,        //Tha pagination ??
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
            page:		UI.page,
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
            page:	UI.page
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
        var d = {
            action: 'getProjects',
            page:	UI.page
        };
        // Filters
        ar = $.extend(d,{});

        return APP.doRequest({
            data: ar,
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
    }
};

$(document).ready(function(){
    UI.render();
    UI.init();
});