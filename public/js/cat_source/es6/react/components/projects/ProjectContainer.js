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
        };
        this.getProjectHeader = this.getProjectHeader.bind(this);

    }

    showHideAllJobs() {
        var show = this.state.showAllJobs;
        this.setState({
            showAllJobs: !show,
            visibleJobs: []
        });
    }

    showSingleJob(index, job) {
        var i = this.state.visibleJobs.indexOf(index);
        if (i != -1) {
            this.state.visibleJobs.splice(i,1);
        } else {
            this.state.visibleJobs.push(index);
        }
        this.setState({
            showAllJobs: false,
            visibleJobs: this.state.visibleJobs
        });
        this.forceUpdate();
    }

    componentDidMount() {
        console.log("Mounted Segment : " + this.props.project.get('id'));
    }

    componentWillUnmount() {
    }

    componentWillUpdate() {}

    componentDidUpdate() {
        console.log("Updated Segment : " + this.props.project.get('id'));
    }

    shouldComponentUpdate(nextProps, nextState){
         return (nextProps.project !== this.props.project ||
         nextState.showAllJobs !== this.state.showAllJobs )
    }

    getProjectHeader(sourceLang, targetsLangs, payableWords) {
        var jobsLength = this.props.project.get('jobs').size;
        var headerProject = '';
        if( jobsLength > 1 ) {
            headerProject = <div className="card job z-depth-1">
                <div className="body-job">
                    <div className="row">
                        <div className="col s11">
                            <div className="row">
                                <div className="col s10">
                                    <div className="combo-language multiple"
                                         ref={(combo) => this.combo_languages = combo}>
                                        <ul>
                                            <li>
                                                <span id="source">{sourceLang}</span> <i className="material-icons">play_arrow</i>
                                            </li>
                                            {targetsLangs}
                                            {/*<li>*/}
                                                {/*<span id="more-combo">+20</span>*/}
                                            {/*</li>*/}
                                            <li>
                                                <div className="payable-words">
                                                    <a href="#!">{payableWords} payable words</a>
                                                </div>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div className="col s1">
                            <div className="row">
                                <div className="col s12 right">
                                    <div className="button-list open right">
                                        <a className="btn waves-effect waves-light btn-flat"
                                           style={{display: 'none'}}
                                           onClick={this.showHideAllJobs.bind(this)}>close</a>
                                        <a className="btn waves-effect waves-light"
                                           onClick={this.showHideAllJobs.bind(this)}>Open all</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>;
        }
        return headerProject;

    }

    render() {
        var self = this;

        var jobsList = [];
        var sourceLang = this.props.project.get('jobs').first().get('source');
        var targetsLangs = [];
        var jobsLength = this.props.project.get('jobs').size;
        var openProjectClass = '';
        var payableWords = 0;
        this.props.project.get('jobs').map(function(job, i){

            var index = i+1;
            var openJobClass = '';
            payableWords = payableWords + parseInt(job.get('stats').get('TOTAL_FORMATTED'));
            if (self.state.showAllJobs || self.state.visibleJobs.indexOf(i) > -1 || jobsLength === 1 ) {
                var item = <Job key={i}
                                job={job}
                                index={index}
                                projectName={self.props.project.get('name')}
                                projectId={self.props.project.get('id')}
                                projectPassword={self.props.project.get('password')}
                                jobsLenght={jobsLength}/>;
                jobsList.push(item);
                openJobClass = 'btn-active-combo';
                openProjectClass = (jobsLength === 1) ? '':'open-project';
            }

            var target = <li key = {i} onClick={self.showSingleJob.bind(self, i, job)}>
                <a className={"btn waves-effect waves-dark " + openJobClass}>
                    <badge>{job.get('target')}</badge>
                    <div className="progress">
                        <div className="determinate" style={{width: '70%'}}></div>
                    </div>
                </a>
            </li>;
            targetsLangs.push(target);
        });

        //The Job Header
        var headerProject = this.getProjectHeader(sourceLang, targetsLangs, payableWords);

        return <div className="card-panel project">

                    <div className={"head-project " + openProjectClass}>
                        <div className="row">
                            <div className="col m2 s4">
                                <div className="project-id">
                                    <div id="id-prject"><span>ID:</span>{this.props.project.get('id')}</div>
                                </div>
                            </div>
                            <div className="col m5 s4">
                                <div className="project-name">
                                    <form>
                                        <div className="row">
                                            <div className="input-field col s12">
                                                <input id="icon_prefix" type="text" defaultValue={this.props.project.get('name')}/><i
                                                    className="material-icons prefix">mode_edit</i>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div className="col m5 s4">
                                <ul className="project-activity-icon right">

                                    <li>
                                        <a href="#!" className="btn-floating btn-flat waves-effect waves-dark z-depth-0">
                                            <i className="material-icons">settings</i>
                                        </a>
                                    </li>
                                    <li>
                                        <a className='dropdown-button btn-floating btn-flat waves-effect waves-dark z-depth-0'
                                           href='#' data-activates='dropdown1'>
                                            <i className="material-icons">more_vert</i>
                                        </a>
                                        <ul id='dropdown1' className='dropdown-content'>
                                            <li><a href="#!">one</a></li>
                                            <li><a href="#!">two</a></li>
                                            <li><a href="#!">three</a></li>
                                        </ul>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <section className="jobs">
                        {headerProject}
                        <CSSTransitionGroup
                        transitionName="slide"
                        transitionAppear={true}
                        transitionAppearTimeout={500}
                        transitionEnterTimeout={300}
                        transitionLeaveTimeout={500}>
                        {jobsList}
                        </CSSTransitionGroup>
                    </section>





                    <div className="foot-project">
                        <div className="row">
                            <div className="col s12">
                                <div className="activity-log">
                                    <a href="#" className="right">
                                        <i><span id="nome-log">Ruben</span> ha <span id="act-log">commentato</span>
                                            questo <span id="oggetto-log">job</span></i>
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
