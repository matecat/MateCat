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
    }

    componentDidMount() {
        $(this.dropdown).dropdown({
            direction : 'downward'
        });
        if (this.props.project.get('user')) {
            $(this.dropdownUsers).dropdown('set selected', this.props.project.get('user').get('id'));
        }
        // $('.tooltipped').tooltip({delay: 50});
        this.getLastAction();
    }

    componentWillUnmount() {
    }

    componentDidUpdate() {
        console.log("Updated Project : " + this.props.project.get('id'));
        // $('.tooltipped').tooltip({delay: 50});
    }

    removeProject() {
        this.props.changeStatusFn('prj', this.props.project.toJS(), 'cancelled');
        ManageActions.removeProject(this.props.project);
    }

    archiveProject() {
        this.props.changeStatusFn('prj', this.props.project.toJS(), 'archived');
        ManageActions.removeProject(this.props.project);
    }

    activateProject() {
        this.props.changeStatusFn('prj', this.props.project.toJS(), 'active');
        ManageActions.removeProject(this.props.project);
    }


    getProjectMenu(activityLogUrl) {
        let menuHtml = <div className="menu">
            <div className="item"><a href={activityLogUrl} target="_blank"><i className="icon-download-logs"/>Activity Log</a></div>

            <div className="item"><a onClick={this.archiveProject.bind(this)}><i className="icon-drawer"/>Archive project</a></div>

            <div className="item"><a onClick={this.removeProject.bind(this)}><i className="icon-trash-o"/>Cancel project</a></div>
                        </div>;
        if ( this.props.project.get('has_archived') ) {
            menuHtml = <div className="menu">
                <div className="item"><a href={activityLogUrl} target="_blank"><i className="icon-download-logs"/>Activity Log</a></div>

                <div className="item"><a onClick={this.activateProject.bind(this)}><i className="icon-drawer unarchive-project"/>Unarchive project</a></div>

                <div className="item"><a onClick={this.removeProject.bind(this)}><i className="icon-trash-o"/>Cancel project</a></div>
                        </div>;
        } else if ( this.props.project.get('has_cancelled') ) {
            menuHtml = <div className="menu">
                <div className="item"><a href={activityLogUrl} target="_blank"><i className="icon-download-logs"/> Activity Log</a></div>

                <div className="item"><a onClick={this.activateProject.bind(this)}><i className="icon-drawer unarchive-project"/> Resume Project</a></div>
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
            return <a className="btn waves-effect split waves-dark" target="_blank" href={mergeUrl}>
                <i className="large icon-compress right"/>Merge
            </a>
        } else {
            return <a className="btn waves-effect split waves-dark" target="_blank" href={splitUrl}>
                <i className="large icon-expand right"/>Split
            </a>
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

                    let chunkList = <div className="chunk" key = { (i - 1) + job.get('id')}>
                        <div className="card header-chunk">
                            <div className="row">
                                <div className="col">
                                    <div className="source-box">
                                        {job.get('sourceTxt')}
                                    </div>
                                </div>
                                <div className="col top-6 no-pad">
                                    <i className="icon-chevron-right"/>
                                </div>
                                <div className="col">

                                    <div className="target-box">
                                        {job.get('targetTxt')}
                                    </div>
                                </div>

                                <div className="col right">
                                    <div className="button-list">
                                        {button}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div className="jobs" >
                            {chunks}
                        </div>
                    </div>;
                    jobsList.push(chunkList);
                    chunks = [];
                }

            }

        });
    }

    openChangeTeamModal() {
        ManageActions.openChangeProjectTeam();
    }

    getDropDownUsers() {
        let result = '';
        if (this.props.project.get('team') ) {
            let users = this.props.team.get('users').map((user, i) => (
                <div className="item" data-value={user.get('id')}
                     key={'team' + user.get('userShortName') + user.get('id')}>
                    <a className=" ui avatar image initials green">{user.get('userShortName')}</a>
                    {/*<img className="ui avatar image" src="http://semantic-ui.com/images/avatar/small/jenny.jpg"/>*/}
                    {(user.get('id') === 0)? 'To me' : user.get('userFullName')}
                </div>

            ));
            result = <div className="ui inline dropdown users-projectS"
                          ref={(dropdownUsers) => this.dropdownUsers = dropdownUsers}>
                <div className="text">
                    <img className="ui avatar image" src="http://semantic-ui.com/images/avatar/small/jenny.jpg" />
                </div>

                <div className="menu">
                    <div className="header">
                        Assign project to:
                    </div>
                    <div className="header">
                        <div className="ui form">
                            <div className="field">
                                <input type="text" name="Project Name" placeholder="Name or email." />
                            </div>
                        </div>
                    </div>
                    {users}
                </div>
            </div>;
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

        let openProjectClass = 'open-project';

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

        return <div className="card-panel project">

                    <div className={"head-project " + openProjectClass}>
                        <div className="row">
                            <div className="col">
                                <div className="project-id">
                                    <div id="id-project">{this.props.project.get('id')}</div>
                                </div>
                            </div>
                            <div className="col m6">
                                <div className="project-name">
                                    <form>
                                        {/*<div className="row">*/}
                                            {state}
                                            {/*<div className="input-field col m10">*/}
                                            <div className="input-field">
                                                <input id="icon_prefix" type="text" disabled="disabled" defaultValue={this.props.project.get('name')}/><i
                                                    className="material-icons prefix hide">mode_edit</i>
                                            </div>

                                        {/*</div>*/}
                                    </form>
                                </div>
                            </div>
                            <div className="col">
                                <div className="payable-words top-4">
                                    <a href={analyzeUrl} target="_blank">{payableWords} payable words</a>
                                </div>
                            </div>

                            <div className="col right">
                                <ul className="project-activity-icon right">
                                    <li>
                                        <a className="chip assigned-team yellow waves-effect waves-dark"
                                        onClick={this.openChangeTeamModal}>{(this.props.project.get('team')) ? this.props.project.get('team') : "Personal" }</a>
                                    </li>
                                    <li>

                                        {dropDownUsers}

                                    </li>
                                    <li>
                                        <div className="ui icon top left pointing dropdown button menu-project"
                                                 ref={(dropdown) => this.dropdown = dropdown}>
                                            <i className="icon-more_vert"/>
                                            {projectMenu}
                                        </div>
                                    </li>
                                </ul>
                            </div>

                        </div>
                    </div>

                    <section className="chunks">
                        {jobsList}
                    </section>
                    <div className="foot-project">
                        <div className="row">
                            <div className="col m12">
                                {lastAction}
                            </div>
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
