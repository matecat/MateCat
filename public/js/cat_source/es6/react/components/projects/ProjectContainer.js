/**
 * React Component for the editarea.

 */
let CSSTransitionGroup = React.addons.CSSTransitionGroup;
let ProjectsStore = require('../../stores/ProjectsStore');
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
            jobsActions: null
        };
        this.getActivityLogUrl = this.getActivityLogUrl.bind(this);
        this.changeUser = this.changeUser.bind(this);
    }

    componentDidMount() {
        let self = this;
        $(this.dropdown).dropdown({
            direction : 'downward'
        });
        if (this.props.project.get('user')) {
            $(this.dropdownUsers).dropdown('set selected', this.props.project.get('user').get('id'));

        }
        $(this.dropdownUsers).dropdown({
            onChange: function(value, text, $selectedItem) {
                self.changeUser(value);
            }
        });
        this.getLastAction();
    }

    componentWillUnmount() {
    }

    componentDidUpdate() {
        console.log("Updated Project : " + this.props.project.get('id'));
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
        let newUser = this.props.organization.get('users').find(function (user) {
            if (user.get('id') === parseInt(value)) {
                return true
            }
        });
        ManageActions.changeProjectAssignee(this.props.project, newUser.toJS());
    }

    onKeyPressEvent(e) {
        if(e.which == 27) {
            this.closeSearch();
        } else if (e.which == 13 || e.keyCode == 13) {
            ManageActions.changeProjectName(this.props.project, $(this.projectNameInput).val());
            e.preventDefault();
            return false;
        }
    }


    getProjectMenu(activityLogUrl) {
        let menuHtml = <div className="menu">
            <div className="header">Project Menu</div>
            <div className="ui divider"></div>
            <div className="item">
                <a href="#"><i className="icon-forward icon"/>Move project</a>
            </div>
            <div className="item"><a href={activityLogUrl} target="_blank"><i className="icon-download-logs icon"/>Activity Log</a></div>

            <div className="item"><a onClick={this.archiveProject.bind(this)}><i className="icon-drawer icon"/>Archive project</a></div>

            <div className="item"><a onClick={this.removeProject.bind(this)}><i className="icon-trash-o icon"/>Cancel project</a></div>
                        </div>;
        if ( this.props.project.get('has_archived') ) {
            menuHtml = <div className="menu">
                <div className="header">Project Menu</div>
                <div className="ui divider"></div>
                <div className="item"><a href={activityLogUrl} target="_blank"><i className="icon-download-logs icon"/>Activity Log</a></div>

                <div className="item"><a onClick={this.activateProject.bind(this)}><i className="icon-drawer unarchive-project icon"/>Unarchive project</a></div>

                <div className="item"><a onClick={this.removeProject.bind(this)}><i className="icon-trash-o"/>Cancel project</a></div>
                        </div>;
        } else if ( this.props.project.get('has_cancelled') ) {
            menuHtml = <div className="menu">
                <div className="header">Project Menu</div>
                <div className="ui divider"></div>
                <div className="item"><a href={activityLogUrl} target="_blank"><i className="icon-download-logs icon"/> Activity Log</a></div>

                <div className="item"><a onClick={this.activateProject.bind(this)}><i className="icon-drawer unarchive-project icon"/> Resume Project</a></div>
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
        return '/analyze/' +this.props.project.get('name')+ '/' +this.props.project.get('id')+ '-' + this.props.project.get('password');
    }

    getJobSplitUrl(job) {
        return '/analyze/'+ job.get('name') +'/'+ this.props.project.get('id')+'-' + this.props.project.get('password') + '?open=split&jobid=' + job.get('id');
    }

    getJobMergeUrl(job) {
        return '/analyze/'+ this.props.project.get('name') +'/'+this.props.project.get('id')+'-' + this.props.project.get('password') + '?open=merge&jobid=' + job.get('id');
    }

    getJobSplitOrMergeButton(isChunk, mergeUrl, splitUrl ) {

        if (isChunk) {
            return <a className="ui basic button" target="_blank" href={mergeUrl}>
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

    shouldComponentUpdate(nextProps, nextState){
        return (nextProps.project !== this.props.project || nextState.lastAction !==  this.state.lastAction)
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

                    let jobList = <div className="ui one column grid job" key = { (i - 1) + job.get('id')}>
                            <div className="four column row job-header shadow-1">
                                    <div className="source-target left floated column ">
                                        <span className="source-box">
                                            {job.get('sourceTxt')}
                                        </span>
                                        <i className="icon-chevron-right icon"/>
                                        <span className="target-box">
                                            {job.get('targetTxt')}
                                        </span>
                                    </div>
                                    <div className="split-merge right floated right aligned column">
                                        {button}
                                    </div>
                            </div>
                            <div className="column job-body">
                                <div className="ui one column grid chunks">
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

    getDropDownUsers() {
        let result = '';
        if (this.props.organization.get('users')) {
            let users = this.props.organization.get('users').map((user, i) => (
                <div className="item" data-value={user.get('id')}
                     key={'organization' + user.get('userShortName') + user.get('id')}>
                    <p className=" ui avatar image initials green">{user.get('userShortName')}</p>
                    {(user.get('id') === 0)? 'To me' : user.get('userFullName')}
                </div>

            ));
            result = <a className="project-assignee ui inline dropdown"
                          ref={(dropdownUsers) => this.dropdownUsers = dropdownUsers}>
                <div className="text">
                    <p className="ui avatar image initials green">??</p>???
                </div>

                <div className="menu">
                    <div className="header">
                        Assign project to:
                    </div>
                    <div className="header">
                        <div className="ui form">
                            <div className="field">
                                <input type="text" name="ProjectName" placeholder="Name or email." />
                            </div>
                        </div>
                    </div>
                    {users}
                </div>
            </a>;
        }
        return result;
    }


    render() {
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
                lastAction = <div className="activity-log">
                    <a href={activityLogUrl} target="_blank" className="right activity-log">
                        <i> <span>Last action: {this.state.lastAction.action + ' on ' + date}</span><span> by {this.state.lastAction.first_name }</span></i>
                    </a>
                </div>;
            }
        } else {
            lastAction = <div className="activity-log">
                <a href={activityLogUrl} target="_blank" className="right activity-log">
                    <i>Loading....</i>
                </a>
            </div>;

        }

        let state = '';
        if ( this.props.project.get('has_archived') ) {
            state = <div className="col m1"><span className="new badge grey darken-1" style={{marginTop: '5px'}}>archived</span></div>
        }  else if ( this.props.project.get('has_cancelled') ) {
            state = <div className="col m1"><span className="new badge grey darken-5" style={{marginTop: '5px'}}>cancelled</span></div>
        }

        let dropDownUsers = this.getDropDownUsers();

        return <div className="ui one column shadow-1 grid project">

                    <div className="one column project-header">
                        <div className="ui three column grid">
                            <div className="one wide column">
                                <div className="project-id">
                                    {this.props.project.get('id')}
                                </div>
                            </div>
                            <div className="nine wide column">
                                <div className="ui one column grid">
                                    <div className="twelve wide column">
                                        <div className="project-name">
                                            {state}
                                            <div className="ui form">
                                                <div className="field">
                                                    <div className="ui icon input">
                                                        <input type="text" defaultValue={this.props.project.get('name')}
                                                               ref={(input) => this.projectNameInput = input}
                                                               onKeyPress={this.onKeyPressEvent.bind(this)}/>
                                                        <i className="icon-pencil icon"/>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div className="four wide column">
                                        <div className="project-payable">
                                            <a href={analyzeUrl} target="_blank">{payableWords} <span>payable words</span></a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            

                            <div className="four wide right floated right aligned column">
                                <div className="project-activity-icon">
                                    <a className="ui orange circular label"
                                    onClick={this.openChangeOrganizationModal.bind(this)}>{(typeof this.props.project.get('workspace') !== 'undefined') ? this.props.project.get('workspace').get('name') : "??" }</a>
                                    {dropDownUsers}
                                    <div className="project-menu circular ui icon top right pointing dropdown button"
                                             ref={(dropdown) => this.dropdown = dropdown}>
                                        <i className="icon-more_vert icon"/>
                                        {projectMenu}
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <div className="one column project-body">
                        <div className="jobs">
                            {jobsList}
                        </div>
                    </div>
                    <div className="one column project-footer">
                        {lastAction}
                    </div>

                </div>;


    }
}

ProjectContainer.propTypes = {
};

ProjectContainer.defaultProps = {
};

export default ProjectContainer ;
