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
        }

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

    getJobHeader() {

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
    render() {
        var self = this;

        var jobsList = [];
        var sourceLang = this.props.project.get('jobs').first().get('source');
        var targetsLangs = [];
        var jobsLength = this.props.project.get('jobs').size;
        this.props.project.get('jobs').map(function(job, i){

            var index = i+1;

            var target = <div key = {i} className="col s12 m2">
                    <p style={{cursor: 'pointer'}}
                       className=" center-align z-depth-3"
                       onClick={self.showSingleJob.bind(self, i, job)}
                    >{job.get('target')}</p>
                </div>;
            targetsLangs.push(target);

            if (self.state.showAllJobs || self.state.visibleJobs.indexOf(i) > -1 || jobsLength === 1 ) {
                var item = <Job key={i}
                                job={job}
                                index={index}
                                projectName={self.props.project.get('name')}
                                projectId={self.props.project.get('id')}
                                projectPassword={self.props.project.get('password')}
                                jobsLenght={jobsLength}/>;
                jobsList.push(item);
            }
        });

        //The jobList
        var jobListHtml = '';
        if (this.state.showAllJobs || self.state.visibleJobs.length > 0 || jobsLength === 1) {
            jobListHtml = <div className="row" key={'pippo'}>
                <div className="s12">
                    <div className="collection example-enter">
                        {jobsList}
                    </div>
                </div>
            </div>;
        }

        //The Job Header
        var headerProject = '';
        //The button to see al jobs
        var buttonShowAll = '';
        if( jobsLength > 1 ) {
            headerProject = <div className="row">
                <div className="col s12 m2">
                    <p className=" center-align z-depth-3">{sourceLang}</p>
                </div>
                <div className="col">
                    <i className="material-icons" style={{marginTop: '15px'}}>play_arrow</i>
                </div>
                {targetsLangs}
            </div>;
            buttonShowAll = <div className="col s4">
                <a className="waves-effect waves-light btn"
                   onClick={this.showHideAllJobs.bind(this)}
                >Get Jobs</a>
            </div>;
        }




        return <div className="container" style={{ overflow: 'hidden', padding: '15px'}}>
                    <div className="row" >
                        <div className=" col s4 project-container">
                            <h5>{this.props.project.get('name')}</h5>
                        </div>
                        {buttonShowAll}
                        <div className="col s4">
                            <nav>
                                <div className="nav-wrapper">
                                    <ul className="right hide-on-med-and-down">
                                        <li><a><i className="material-icons">view_module</i></a></li>
                                        <li><a ><i className="material-icons">settings</i></a></li>
                                        <li><a ><i className="material-icons">more_vert</i></a></li>
                                    </ul>
                                </div>
                            </nav>
                        </div>
                    </div>
                    {headerProject}

                    <CSSTransitionGroup
                        transitionName="slide"
                        transitionAppear={true}
                        transitionAppearTimeout={500}
                        transitionEnterTimeout={300}
                        transitionLeaveTimeout={500}>
                        {jobListHtml}
                    </CSSTransitionGroup>



                </div>;
    }
}

ProjectContainer.propTypes = {
};

ProjectContainer.defaultProps = {
};

export default ProjectContainer ;
