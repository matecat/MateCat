/**
 * React Component for the editarea.

 */
let CSSTransitionGroup = React.addons.CSSTransitionGroup;
let ManageConstants = require('../../constants/ManageConstants');
let Job = require('./JobContainer').default;

class ProjectContainer extends React.Component {

    constructor(props) {
        super(props);
        this.state = {
            showAllJobs: true,
            visibleJobs: [],
            showAllJobsBoxes: true,
            lastAction: null,
            jobsActions: null,
            projectName: this.props.project.get('name'),
            inputSelected: false,
            inputNameChanged: false
        };
        this.getActivityLogUrl = this.getActivityLogUrl.bind(this);
        this.changeUser = this.changeUser.bind(this);
        this.hideProject = this.hideProject.bind(this);
    }

    componentDidMount() {
        let self = this;

        $(this.dropdown).dropdown({
            direction : 'downward'
        });
        this.initUsersDropdown();
        //Select the assigee
        if ( this.props.project.get('id_assignee') ) {
            $(this.dropdownUsers).dropdown('set selected', this.props.project.get('id_assignee'));
        } else {
            $(this.dropdownUsers).dropdown('set selected', -1);
        }

        this.getLastAction();

        if (this.dropdownUsers) {
            if (this.props.project.get('id_assignee') ) {
                this.dropdownUsers.classList.remove("project-not-assigned");
                this.dropdownUsers.classList.remove("shadow-1");
                this.dropdownUsers.classList.add("project-assignee");
            } else {
                this.dropdownUsers.classList.remove("project-assignee");
                this.dropdownUsers.classList.add("shadow-1");
                this.dropdownUsers.classList.add("project-not-assigned");
            }
        }
        ProjectsStore.addListener(ManageConstants.HIDE_PROJECT, this.hideProject);

    }

    componentWillUnmount() {
        ProjectsStore.removeListener(ManageConstants.HIDE_PROJECT, this.hideProject);
        $(this.project).transition('fly right');
    }

    hideProject(project) {
        if ( this.props.project.get('id') === project.get('id') ) {
            $(this.project).transition('fly right');
        }
    }

    componentDidUpdate() {
        let self = this;
        this.initUsersDropdown();
        console.log("Updated Project : " + this.props.project.get('id'));
        this.inputTimeout;
        if (this.dropdownUsers) {
            if (this.props.project.get('id_assignee') ) {
                $(this.dropdownUsers).dropdown('set selected', this.props.project.get('id_assignee'));
                this.dropdownUsers.classList.remove("project-not-assigned");
                this.dropdownUsers.classList.add("project-assignee");
                this.dropdownUsers.classList.add("shadow-1");
            } else {
                $(this.dropdownUsers).dropdown('set selected', -1);
                this.dropdownUsers.classList.remove("project-assignee");
                this.dropdownUsers.classList.remove("shadow-1");
                this.dropdownUsers.classList.add("project-not-assigned");
            }

        }
        clearTimeout(this.inputTimeout);
        if (this.state.inputNameChanged) {
            this.inputTimeout = setTimeout(function () {
               self.setState({
                   inputNameChanged: false
               })
            }, 3000);
        }
    }

    initUsersDropdown() {
        let self = this;
        if (this.props.organization.get('type') != "personal") {
            $(this.dropdownUsers).dropdown({
                fullTextSearch: 'exact',
                onChange: function(value, text, $selectedItem) {
                    self.changeUser(value);
                }
            });
        }
    }

    removeProject() {
        ManageActions.updateStatusProject(this.props.project, 'cancelled');
    }

    archiveProject() {
        ManageActions.updateStatusProject(this.props.project, 'archived');
    }

    activateProject() {
        ManageActions.updateStatusProject(this.props.project, 'active');
    }

    changeUser(value) {
        let user, idUser;
        if (value === '-1') {
            user = -1;
            idUser = -1;
        } else {
            let newUser = this.props.organization.get('members').find(function (member) {
                let user = member.get('user');
                if (user.get('uid') === parseInt(value) ) {
                    return true;
                }
            });
            user = newUser.get('user');
            idUser = user.get('uid');
        }
        if ( (!this.props.project.get('id_assignee') && idUser !== -1) || (this.props.project.get('id_assignee') && idUser != this.props.project.get('id_assignee'))) {
            ManageActions.changeProjectAssignee(this.props.organization, this.props.project, user);
        }
    }

    removeWorkspace(event) {
        event.preventDefault();
        event.stopPropagation();
        ManageActions.changeProjectWorkspace(-1,  this.props.project);
    }


    onKeyUpEvent(event) {
        if(event.key == 'Enter'){
            this.changeProjectName(event);
            this.inputName.blur();
        }
    }

    inputNameClick() {
        this.setState({
            inputSelected: true,
            inputNameChanged: false
        });
    }

    inputNameOnBlur(event) {
        this.changeProjectName(event);
    }

    changeProjectName(event) {
        if (event.target.value !== this.props.project.get('name') && event.target.value !== '') {
            ManageActions.changeProjectName(this.props.organization, this.props.project, event.target.value);
            this.setState({
                projectName: event.target.value,
                inputNameChanged: true,
                inputSelected: false
            });
        } else {
            this.inputName.value = (this.props.project.get('name'));
            this.setState({
                inputNameChanged: false,
                inputSelected: false
            });
        }
    }

    getProjectMenu(activityLogUrl) {
        let menuHtml = <div className="menu">
            <div className="scrolling menu">
                <a className="item" onClick={this.openChangeOrganizationModal.bind(this)}>
                    <i className="icon-forward icon"/>Move project
                </a>
                <a className="item" href={activityLogUrl} target="_blank"><i className="icon-download-logs icon"/>Activity Log</a>

                <a className="item" onClick={this.archiveProject.bind(this)}><span><i className="icon-drawer icon"/>Archive project</span></a>

                <a className="item" onClick={this.removeProject.bind(this)}><span><i className="icon-trash-o icon"/>Cancel project</span></a>
            </div>
                        </div>;
        if ( this.props.project.get('has_archived') ) {
            menuHtml = <div className="menu">
                <div className="scrolling menu">
                    <a className="item" href={activityLogUrl} target="_blank"><span><i className="icon-download-logs icon"/>Activity Log</span></a>

                    <a className="item" onClick={this.activateProject.bind(this)}><span><i className="icon-drawer unarchive-project icon"/>Unarchive project</span></a>

                    <a className="item" onClick={this.removeProject.bind(this)}><span><i className="icon-trash-o"/>Cancel project</span></a>
                </div>
                        </div>;
        } else if ( this.props.project.get('has_cancelled') ) {
            menuHtml = <div className="menu">

                <div className="scrolling menu">
                    <a className="item" href={activityLogUrl} target="_blank"><span><i className="icon-download-logs icon"/> Activity Log</span></a>

                    <a className="item" onClick={this.activateProject.bind(this)}><span><i className="icon-drawer unarchive-project icon"/> Resume Project</span></a>
                </div>
                        </div>;
        }
        return menuHtml;
    }

    getLastAction() {
        let self = this;
        this.props.lastActivityFn(this.props.project.get('id'), this.props.project.get('password')).done(function (data) {
            let lastAction = (data.activity[0])? data.activity[0] : [];
            self.setState({
                lastAction: lastAction,
                jobsActions: data.activity
            });
        });
    }

    getLastJobAction(idJob) {
        //Last Activity Log Action
        let lastAction;
        if ( this.state.jobsActions && this.state.jobsActions.length > 0 ) {
            lastAction = this.state.jobsActions.find(function (job) {
                return job.id_job == idJob;
            });
        }
        return lastAction;
    }

    getActivityLogUrl() {
        return '/activityLog/' +this.props.project.get('id')+ '/' + this.props.project.get('password');
    }

    getAnalyzeUrl() {
        return '/analyze/' + this.props.project.get('project_slug') + '/' +this.props.project.get('id')+ '-' + this.props.project.get('password');
    }

    getJobSplitUrl(job) {
        return '/analyze/'+ this.props.project.get('project_slug') +'/'+ this.props.project.get('id')+'-' + this.props.project.get('password') + '?open=split&jobid=' + job.get('id');
    }

    getJobMergeUrl(job) {
        return '/analyze/'+ this.props.project.get('project_slug') +'/'+this.props.project.get('id')+'-' + this.props.project.get('password') + '?open=merge&jobid=' + job.get('id');
    }

    getJobSplitOrMergeButton(isChunk, mergeUrl, splitUrl ) {

        if (isChunk) {
            return <a className="merge ui basic button" target="_blank" href={mergeUrl}>
                <i className="icon-compress icon"/> Merge
            </a>
        } else {
            return '';
        }
    }

    getLastActionDate() {
        let date = new Date(this.state.lastAction.event_date);
        return date.toDateString();
    }

    getJobsList(targetsLangs, jobsList, jobsLength) {
        let self = this;
        let chunks = [],  index;
        let tempIdsArray = [];
        let orderedJobs = this.props.project.get('jobs').reverse();
        let visibleJobsBoxes = 0;
        orderedJobs.map(function(job, i){

            let openJobClass = '';
            let next_job_id = (orderedJobs.get(i+1)) ? orderedJobs.get(i+1).get('id') : 0;
            //To check if is a chunk (jobs with same id)
            let isChunk = false;
            if (tempIdsArray.indexOf(job.get('id')) > -1 ) {
                isChunk = true;
                index ++;
            }  else if ((orderedJobs.get(i+1) && orderedJobs.get(i+1).get('id') === job.get('id') )) {  //The first of the Chunk
                isChunk = true;
                tempIdsArray.push(job.get('id'));
                index = 1;
            }  else {
                index = 0;
            }

            //Create the Jobs boxes and, if visibles, the jobs body
            if (self.state.showAllJobs || self.state.visibleJobs.indexOf(job.get('id')) > -1 || jobsLength === 1 ) {
                let lastAction = self.getLastJobAction(job.get('id'));
                let item = <Job key={job.get('id') + "-" + i}
                                job={job}
                                index={index}
                                project={self.props.project}
                                jobsLenght={jobsLength}
                                changeJobPasswordFn={self.props.changeJobPasswordFn}
                                changeStatusFn={self.props.changeStatusFn}
                                downloadTranslationFn={self.props.downloadTranslationFn}
                                isChunk={isChunk}
                                lastAction={lastAction}
                                activityLogUrl =  {self.getActivityLogUrl()}/>;
                chunks.push(item);
                if ( job.get('id') !== next_job_id) {
                    let button;
                    if ( chunks.length > 1 ) {
                        let mergeUrl = self.getJobMergeUrl(job);
                        button = self.getJobSplitOrMergeButton(true, mergeUrl);
                    } else {
                        button = '';
                    }

                    let jobList = <div className="job ui grid" key = { (i - 1) + job.get('id')}>
                            <div className="job-header sixteen wide column shadow-1">

                                <div className="ui grid">
                                    <div className="nine wide column">
                                        <div className="source-target">
                                            <div className="source-box">
                                                {job.get('sourceTxt')}
                                            </div>
                                            <div className="in-to"><i className="icon-chevron-right icon"/></div>
                                            <div className="target-box">
                                                {job.get('targetTxt')}
                                            </div>
                                        </div>
                                    </div>
                                    <div className="seven wide column right aligned">
                                        <div className="split-merge">
                                            {button}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div className="job-body sixteen wide column">
                                <div className="ui grid chunks">
                                {chunks}
                                </div>
                            </div>
                        </div>;
                    jobsList.push(jobList);
                    chunks = [];
                }

            }

        });
    }

    openChangeOrganizationModal() {
        ManageActions.openChangeProjectWorkspace(this.props.organization, this.props.project);
    }

    openAddMember() {
        ManageActions.openModifyOrganizationModal(this.props.organization.toJS());
    }


    getDropDownUsers() {
       let result = '';
       var self = this;
       if (this.props.organization.get('members') && this.props.organization.get("type") !== 'personal') {
           let members = this.props.organization.get('members').map(function(member, i) {
               let user = member.get('user');
               return <div className="item " data-value={user.get('uid')}
                           key={'user' + user.get('uid')}>
                   <div className="ui circular label">{APP.getUserShortName(user.toJS())}</div>
                   {(user.get('uid') === APP.USER.STORE.user.uid) ? 'To me' : user.get('first_name') + " " + user.get('last_name')}
               </div>
           });

           result = <div className={"ui dropdown top right pointing"}
                         ref={(dropdownUsers) => this.dropdownUsers = dropdownUsers}>
                        <span className="text">
                            <div className="ui not-assigned label">
                                <i className="icon-user22"/>
                            </div>
                            Not assigned

                        </span>
                       <div className="ui cancel label"
                            onClick={self.changeUser.bind(self, '-1')}>
                           <i className="icon-cancel3"/>
                       </div>

                       <div className="menu">
                           <div className="header"
                           onClick={this.openAddMember.bind(this)}>
                               <a href="#">New Member <i className="icon-plus3 icon right"/></a>
                           </div>
                           <div className="divider"></div>
                           <div className="ui icon search input">
                               <i className="icon-search icon"/>
                               <input type="text" name="UserName" placeholder="Name or email." />
                           </div>
                           <div className="scrolling menu">
                               {members}
                               <div className="item" data-value="-1">
                                   <div className="ui not-assigned label">
                                       <i className="icon-user22"/>
                                   </div>
                                   Not assigned
                               </div>
                           </div>
                       </div>
                   </div>;
       }
       return result;
    }

    shouldComponentUpdate(nextProps, nextState){
        return (nextProps.project !== this.props.project ||
        nextState.lastAction !==  this.state.lastAction ||
        nextProps.organization !==  this.props.organization ||
        nextState.inputSelected !==  this.state.inputSelected ||
        nextState.inputNameChanged !==  this.state.inputNameChanged
        )
    }

    render() {
        let self = this;
        let activityLogUrl = this.getActivityLogUrl();
        let projectMenu = this.getProjectMenu(activityLogUrl);
        // let tMIcon = this.checkTMIcon();
        let payableWords = this.props.project.get('tm_analysis');
        let analyzeUrl = this.getAnalyzeUrl();
        let jobsLength = this.props.project.get('jobs').size;

        let targetsLangs = [], jobsList = [];
        //The list of jobs
        this.getJobsList(targetsLangs, jobsList, jobsLength);


        //Last Activity Log Action
        let lastAction;
        if (this.state.lastAction) {
            if (this.state.lastAction.length === 0) {
                lastAction = '';
            } else {
                let date = this.getLastActionDate();
                lastAction = <div className="sixteen wide right aligned column pad-top-0 pad-bottom-0">
                    <div className="activity-log">
                        <a href={activityLogUrl} target="_blank" className="right activity-log">
                            <i> <span>Last action: {this.state.lastAction.action + ' on ' + date}</span><span> by {this.state.lastAction.first_name }</span></i>
                        </a>
                    </div>
                </div>;
            }
        } else {
            lastAction = <div className="sixteen wide right aligned column">
                <div className="activity-log">
                    <a href={activityLogUrl} target="_blank" className="right activity-log">
                        <i>Loading....</i>
                    </a>
                </div>
            </div>;

        }

        // Project State (Archived or Cancelled)
        let state = '';
        if ( this.props.project.get('has_archived') ) {
            state = <span>(archived)</span>;
        }  else if ( this.props.project.get('has_cancelled') ) {
            state = <span>(cancelled)</span>;
        }

        // Workspace label
        let workspace = '';
        if (this.props.project.get('id_workspace') ) {
            let ws = this.props.organization.get('workspaces').find(function (ws) {
                return ws.get('id') == self.props.project.get('id_workspace');
            });
            if (ws) {
                workspace = <div className="ui olive circular label project-workspace shadow-1"
                               onClick={this.openChangeOrganizationModal.bind(this)}>
                    {ws.get('name') }
                    <div className="ui cancel label"
                         onClick={self.removeWorkspace.bind(self)}>
                        <i className="icon-cancel3"/>
                    </div>
                </div>
            }
        }

        // Users dropdown
        let dropDownUsers = this.getDropDownUsers();

        //Input Class
        let inputClass = (this.state.inputSelected) ? 'selected' : '';
        let inputIcon = (this.state.inputNameChanged) ? <i className="icon-checkmark green icon" /> : <i className="icon-pencil icon" />;

        return <div className="project ui column grid shadow-1" id={"project-" + this.props.project.get('id')}
                    ref={(project) => this.project = project}>

                    <div className="sixteen wide column">
                        <div className="project-header ui grid">

                            <div className="two wide computer two wide tablet three wide mobile column">
                                <div className="ui stackable grid">
                                    <div className="sixteen wide column">
                                        <div className="ui ribbon label">
                                            <span className="project-id">
                                                {"Id: " + this.props.project.get('id')}
                                            </span>
                                            {state}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div className="fourteen wide computer fourteen wide tablet thirteen wide mobile column">
                                <div className="ui mobile reversed stackable grid">
                                    <div className="five wide computer four wide tablet only reverse tablet only column">
                                        <div className="ui grid">
                                            <div className="sixteen wide column">
                                                <div className="project-name">
                                                    <div className="ui form">
                                                        <div className="field">
                                                            <div className={"ui icon input " + inputClass}>
                                                                <input type="text"
                                                                       onKeyUp={this.onKeyUpEvent.bind(this)}
                                                                       onClick={this.inputNameClick.bind(this)}
                                                                       onBlur={this.inputNameOnBlur.bind(this)}
                                                                       ref={(inputName) => this.inputName = inputName}
                                                                        defaultValue={this.state.projectName} disabled/>
                                                                {/*{inputIcon}*/}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div className="eleven wide computer twelve wide tablet right aligned column">
                                        <div className="project-payable">
                                            <a href={analyzeUrl} target="_blank">{payableWords} <span>payable words</span></a>
                                        </div>
                                        <div className="project-activity-icon">
                                            {workspace}
                                            {dropDownUsers}
                                            <div className="project-menu circular ui icon top right pointing dropdown basic button"
                                                    ref={(dropdown) => this.dropdown = dropdown}>
                                                <i className="icon-more_vert icon" />
                                                {projectMenu}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div className="sixteen wide column mobile only pad-top-0 pad-bottom-0">
                                <div className="project-name">
                                    <div className="ui form">
                                        <div className="field">
                                            <div className="ui icon input">
                                                <input type="text" defaultValue={this.state.projectName}
                                                       onKeyUp={this.onKeyUpEvent.bind(this)}
                                                       onClick={this.inputNameClick.bind(this)}
                                                       onBlur={this.inputNameOnBlur.bind(this)}
                                                />
                                                {inputIcon}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div className="sixteen wide column mobile only pad-top-0 pad-bottom-20 right aligned">
                                <div className="project-payable">
                                    <a href={analyzeUrl} target="_blank">{payableWords} <span>payable words</span></a>
                                </div>
                            </div>

                        </div>

                        <div className="project-body ui grid">
                            <div className="jobs sixteen wide column pad-bottom-0">
                                {jobsList}
                            </div>
                        </div>

                        <div className="project-footer ui grid">
                            {lastAction}
                        </div>


                    </div>
                </div>;


    }
}

ProjectContainer.propTypes = {
};

ProjectContainer.defaultProps = {
};

export default ProjectContainer ;
