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
            showAllJobs: false,
            visibleJobs: [],
            lastAction: null,
            jobsActions: null
        };
        this.getProjectHeader = this.getProjectHeader.bind(this);
        this.getActivityLogUrl = this.getActivityLogUrl.bind(this);
        this.hideAllJobs = this.hideAllJobs.bind(this);

    }

    showHideAllJobs() {
        var show = this.state.showAllJobs;
        // if (!show) {
        //     ManageActions.closeAllJobs();
        // }
        this.setState({
            showAllJobs: !show,
            visibleJobs: []
        });
    }

    hideAllJobs() {
        this.setState({
            showAllJobs: false,
            visibleJobs: []
        });
    }

    showSingleJob(index, job) {
        var i = this.state.visibleJobs.indexOf(job.get('id'));
        if (i != -1) {
            this.state.visibleJobs.splice(i,1);
        } else {
            this.state.visibleJobs.push(job.get('id'));
        }
        this.setState({
            showAllJobs: false,
            visibleJobs: this.state.visibleJobs
        });
        this.forceUpdate();
    }

    componentDidMount() {
        $(this.dropdown).dropdown({
            belowOrigin: true
        });
        $('.tooltipped').tooltip({delay: 50});
        this.getLastAction();
        ProjectsStore.addListener(ManageConstants.CLOSE_ALL_JOBS, this.hideAllJobs);
    }

    componentWillUnmount() {
        ProjectsStore.removeListener(ManageConstants.CLOSE_ALL_JOBS, this.hideAllJobs);
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


    getProjectHeader(sourceLang, targetsLangs, payableWords) {
        var sourceTxt = this.props.project.get('jobs').first().get('sourceTxt');
        var jobsLength = this.props.project.get('jobs').size;
        var headerProject = '';
        var analyzeUrl = this.getAnalyzeUrl();
        var buttonLabel = ( this.state.showAllJobs ) ? "Close" : "View all";

        if  ( jobsLength > 1  ) { //&& !this.state.showAllJobs
            headerProject = <div className="card job-preview z-depth-1">
                <div className="body-job">
                    <div className="row">
                        <div className="col">
                            <div className="source-lang-container tooltiped" data-tooltip={sourceTxt}>
                                <span id="source">{sourceLang}</span> <i className="icon-play" />
                            </div>
                        </div>
                        <div className="col list-language">
                            <div className="combo-language multiple"
                                 ref={(combo) => this.combo_languages = combo}>
                                <ul>
                                    {targetsLangs}
                                </ul>
                            </div>
                        </div>
                        <div className="col">
                            <div className="payable-words">
                                <a href={analyzeUrl} target="_blank">{payableWords} payable words</a>
                            </div>
                        </div>
                        <div className="col right">

                            <div className="button-list right">
                                <a className="btn waves-effect waves-light open-all top-2" onClick={this.showHideAllJobs.bind(this)}>{buttonLabel}</a>
                            </div>

                        </div>
                    </div>
                </div>
            </div>;
        }
        return headerProject;

    }

    getProjectMenu(activityLogUrl) {
        var menuHtml = <ul id={'dropdown' + this.props.project.get('id')} className='dropdown-content'>
                            <li><a href={activityLogUrl} target="_blank"><i className="icon-download-logs"/>Activity Log</a></li>
                            <li className="divider"/>
                            <li><a onClick={this.archiveProject.bind(this)}><i className="icon-drawer"/>Archive project</a></li>
                             <li className="divider"/>
                            <li><a onClick={this.removeProject.bind(this)}><i className="icon-trash-o"/>Remove from my Dashboard</a></li>
                        </ul>;
        if ( this.props.project.get('has_archived') ) {
            menuHtml = <ul id={'dropdown' + this.props.project.get('id')} className='dropdown-content'>
                            <li><a href={activityLogUrl} target="_blank"><i className="icon-download-logs"/>Activity Log</a></li>
                            <li className="divider"/>
                            <li><a onClick={this.activateProject.bind(this)}><i className="icon-drawer unarchive-project"/>Unarchive project</a></li>
                            <li className="divider"/>
                            <li><a onClick={this.removeProject.bind(this)}><i className="icon-trash-o"/>Remove from my Dashboard</a></li>
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
            self.setState({
                lastAction: data.activity[0],
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

    getLastActionDate() {
        var date = new Date(this.state.lastAction.event_date);
        return date.toDateString();
    }

    shouldComponentUpdate(nextProps, nextState){
        return (nextProps.project !== this.props.project ||
        nextState.showAllJobs !== this.state.showAllJobs || nextState.lastAction !==  this.state.lastAction)
    }

    createChunks() {
        var chunk = '', chunks = [];
        var orderedJobs = this.props.project.get('jobs').reverse();
        orderedJobs.map(function(job, i){
            var isChunk = false;
            if (tempIdsArray.indexOf(job.get('id')) > -1 || (orderedJobs.get(i+1) && orderedJobs.get(i+1).get('id') === job.get('id') )) {
                isChunk = true;
                tempIdsArray.push(job.get('id'));
            }
        });
    }

    render() {
        var self = this;
        var sourceLang = this.props.project.get('jobs').first().get('source');
        var payableWords = this.props.project.get('tm_analysis');
        var activityLogUrl = this.getActivityLogUrl();
        var projectMenu = this.getProjectMenu(activityLogUrl);

        var jobsLength = this.props.project.get('jobs').size;
        var targetsLangs = [], jobsList = [], chunks = [],  index;
        var tempIdsArray = [];
        var openProjectClass = '';
        var orderedJobs = this.props.project.get('jobs').reverse();

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
                                lastAction={lastAction}/>;
                chunks.push(item);
                if ( job.get('id') !== next_job_id) {
                    let chunkList = <div className="chunk" key = { (i - 1) + job.get('id')}>
                        <div className="jobs" >
                            {chunks}
                        </div>
                    </div>;
                    jobsList.push(chunkList);
                    chunks = [];
                }
                openJobClass = 'open-job';
                openProjectClass = (jobsLength === 1) ? '':'open-project';
            }
            if ( (isChunk && index === 1) || !isChunk) {
                var target = <li className="target-lang" key = {i} onClick={self.showSingleJob.bind(self, i, job)}>
                    <a className={"tooltipped btn waves-effect waves-dark " + openJobClass} data-tooltip={job.get('targetTxt')}>
                        <badge>{job.get('target')}</badge>
                        <div className="progress">
                            <div className="determinate" title={'Translated '+ job.get('stats').get('TRANSLATED_PERC_FORMATTED') +'%'} style={{width:  job.get('stats').get('TRANSLATED_PERC') + '%' }}></div>
                            <div className="determinate green" title={'Approved '+ job.get('stats').get('APPROVED_PERC_FORMATTED') +'%'} style={{width:  job.get('stats').get('APPROVED_PERC')+ '%' }}></div>
                            <div className="determinate red" title={'Rejected '+ job.get('stats').get('REJECTED_PERC_FORMATTED') +'%'} style={{width: job.get('stats').get('REJECTED_PERC') + '%'}}></div>
                        </div>
                    </a>
                </li>;
                targetsLangs.push(target);
            }
        });

        //The Job Header
        var headerProject = this.getProjectHeader(sourceLang, targetsLangs, payableWords);

        //Last Activity Log Action
        var lastAction;
        if (this.state.lastAction) {
            if (this.state.lastAction.length === 0) {
                lastAction = '';
            } else {
                var date = this.getLastActionDate();
                lastAction = <i><span>{this.state.lastAction.first_name }</span> <span>{this.state.lastAction.action.toLowerCase() + ' on ' + date}</span></i>

            }
        } else {
            lastAction = <i>Loading....</i>
        }

        return <div className="card-panel project">

                    <div className={"head-project " + openProjectClass}>
                        <div className="row">
                            <div className="col">
                                <div className="project-id">
                                    <div id="id-project"><span>ID:</span>{this.props.project.get('id')}</div>
                                </div>
                            </div>
                            <div className="col m8">
                                <div className="project-name">
                                    <form>
                                        <div className="row">
                                            <div className="input-field col m12">
                                                <input id="icon_prefix" type="text" disabled="disabled" defaultValue={this.props.project.get('name')}/><i
                                                    className="material-icons prefix hide">mode_edit</i>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div className="col right">
                                <ul className="project-activity-icon right">

                                    {/*<li>*/}
                                    {/*<a href="#!" className="btn-floating btn-flat waves-effect waves-dark z-depth-0">*/}
                                    {/*<i className="icon-settings"></i>*/}
                                    {/*</a>*/}
                                    {/*</li>*/}
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
                    <section className="jobs-preview">
                        {headerProject}
                    </section>
                    <section className="chunks">
                        <CSSTransitionGroup
                            transitionName="slide"
                            transitionAppear={true}
                            transitionAppearTimeout={1000}
                            transitionEnterTimeout={300}
                            transitionLeaveTimeout={200}>
                            {jobsList}
                        </CSSTransitionGroup>
                    </section>

                    <div className="foot-project">
                        <div className="row">
                            <div className="col m12">
                                <div className="activity-log">
                                    <a href={activityLogUrl} target="_blank" className="right activity-log">
                                        {lastAction}
                                    </a>
                                </div>
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
