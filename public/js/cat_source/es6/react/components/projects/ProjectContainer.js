/**
 * React Component for the editarea.

 */
var CSSTransitionGroup = React.addons.CSSTransitionGroup;
var ProjectsStore = require('../../stores/ProjectsStore');
var ManageConstants = require('../../constants/ManageConstants');
var Job = require('./JobContainer').default;

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
            belowOrigin: true
        });
        $('.tooltipped').tooltip({delay: 50});
        this.getLastAction();
    }

    componentWillUnmount() {
    }

    componentDidUpdate() {
        console.log("Updated Project : " + this.props.project.get('id'));
        $('.tooltipped').tooltip({delay: 50});
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
        var menuHtml = <ul id={'dropdown' + this.props.project.get('id')} className='dropdown-content'>
                            <li><a href={activityLogUrl} target="_blank"><i className="icon-download-logs"/>Activity Log</a></li>
                            <li className="divider"/>
                            <li><a onClick={this.archiveProject.bind(this)}><i className="icon-drawer"/>Archive project</a></li>
                             <li className="divider"/>
                            <li><a onClick={this.removeProject.bind(this)}><i className="icon-trash-o"/>Cancel project</a></li>
                        </ul>;
        if ( this.props.project.get('has_archived') ) {
            menuHtml = <ul id={'dropdown' + this.props.project.get('id')} className='dropdown-content'>
                            <li><a href={activityLogUrl} target="_blank"><i className="icon-download-logs"/>Activity Log</a></li>
                            <li className="divider"/>
                            <li><a onClick={this.activateProject.bind(this)}><i className="icon-drawer unarchive-project"/>Unarchive project</a></li>
                            <li className="divider"/>
                            <li><a onClick={this.removeProject.bind(this)}><i className="icon-trash-o"/>Cancel project</a></li>
                        </ul>;
        } else if ( this.props.project.get('has_cancelled') ) {
            menuHtml = <ul id={'dropdown' + this.props.project.get('id')} className='dropdown-content'>
                            <li><a href={activityLogUrl} target="_blank"><i className="icon-download-logs"/> Activity Log</a></li>
                            <li className="divider"/>
                            <li><a onClick={this.activateProject.bind(this)}><i className="icon-drawer unarchive-project"/> Resume Project</a></li>
                        </ul>;
        }
        return menuHtml;
    }

    getLastAction() {
        var self = this;
        this.props.lastActivityFn(this.props.project.get('id'), this.props.project.get('password')).done(function (data) {
            var lastAction = (data.activity[0])? data.activity[0] : [];
            self.setState({
                lastAction: lastAction,
                jobsActions: data.activity
            });
        });
    }

    getLastJobAction(idJob) {
        //Last Activity Log Action
        var lastAction;
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
        var date = new Date(this.state.lastAction.event_date);
        return date.toDateString();
    }

    shouldComponentUpdate(nextProps, nextState){
        return (nextProps.project !== this.props.project ||
        nextState.showAllJobs !== this.state.showAllJobs || nextState.lastAction !==  this.state.lastAction)
    }

    getJobsList(targetsLangs, jobsList, jobsLength) {
        var self = this;
        var chunks = [],  index;
        var tempIdsArray = [];
        var orderedJobs = this.props.project.get('jobs').reverse();
        var visibleJobsBoxes = 0;
        orderedJobs.map(function(job, i){

            var openJobClass = '';
            var next_job_id = (orderedJobs.get(i+1)) ? orderedJobs.get(i+1).get('id') : 0;
            //To check if is a chunk (jobs with same id)
            var isChunk = false;
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
                var lastAction = self.getLastJobAction(job.get('id'));
                var item = <Job key={job.get('id') + "-" + i}
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
                    var button;
                    if ( chunks.length > 1 ) {
                        var mergeUrl = self.getJobMergeUrl(job);
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
                openJobClass = 'open-job';

            }

        });
    }

    render() {
        var activityLogUrl = this.getActivityLogUrl();
        var projectMenu = this.getProjectMenu(activityLogUrl);
        // var tMIcon = this.checkTMIcon();

        var jobsLength = this.props.project.get('jobs').size;

        var openProjectClass = (jobsLength === 1) ? '':'open-project';

        var targetsLangs = [], jobsList = [];
        //The list of jobs
        this.getJobsList(targetsLangs, jobsList, jobsLength);


        //Last Activity Log Action
        var lastAction;
        if (this.state.lastAction) {
            if (this.state.lastAction.length === 0) {
                lastAction = '';
            } else {
                var date = this.getLastActionDate();
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

        var state = '';
        if ( this.props.project.get('has_archived') ) {
            state = <div className="col m1"><span className="new badge grey darken-1" style={{marginTop: '5px'}}>archived</span></div>
        }  else if ( this.props.project.get('has_cancelled') ) {
            state = <div className="col m1"><span className="new badge grey darken-5" style={{marginTop: '5px'}}>cancelled</span></div>
        }


        return <div className="card-panel project">

                    <div className={"head-project " + openProjectClass}>
                        <div className="row">
                            <div className="col">
                                <div className="project-id">
                                    <div id="id-project">{this.props.project.get('id')}</div>
                                </div>
                            </div>
                            <div className="col m8">
                                <div className="project-name">
                                    <form>
                                        <div className="row">
                                            {state}
                                            <div className="input-field col m11">
                                                <input id="icon_prefix" type="text" disabled="disabled" defaultValue={this.props.project.get('name')}/><i
                                                    className="material-icons prefix hide">mode_edit</i>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div className="col right">
                                <ul className="project-activity-icon right">

                                    <li>
                                        <a className='dropdown-button btn-floating btn-flat waves-effect waves-dark z-depth-0'
                                           ref={(dropdown) => this.dropdown = dropdown}
                                           data-activates={'dropdown' + this.props.project.get('id')}>
                                            <i className="icon-more_vert"/>
                                        </a>
                                        {projectMenu}
                                    </li>
                                </ul>
                            </div>

                        </div>
                    </div>

                    {/*<section className="chunks">*/}
                        {/*<CSSTransitionGroup*/}
                            {/*transitionName="slide"*/}
                            {/*transitionAppear={true}*/}
                            {/*transitionAppearTimeout={1000}*/}
                            {/*transitionEnterTimeout={300}*/}
                            {/*transitionLeaveTimeout={200}>*/}
                            {/**/}
                        {/*</CSSTransitionGroup>*/}
                    {/*</section>*/}
                    {jobsList}
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
