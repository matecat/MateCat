
let AnalyzeConstants = require('../../constants/AnalyzeConstants');
let AnalyzeHeader = require('./AnalyzeHeader').default;
let AnalyzeChunksResume = require('./AnalyzeChunksResume').default;
let ProjectAnalyze = require('./ProjectAnalyze').default;
let AnalyzeStore = require('../../stores/AnalyzeStore');
let CSSTransitionGroup = React.addons.CSSTransitionGroup;



class AnalyzeMain extends React.Component {

    constructor(props) {
        super(props);
        this.state = {
            volumeAnalysis: null,
            project: null,
            showAnalysis: true
        };
        this.updateAll = this.updateAll.bind(this);
        this.updateAnalysis = this.updateAnalysis.bind(this);
        this.updateProject = this.updateProject.bind(this);
        this.showDetails = this.showDetails.bind(this);
    }

    updateAll(volumeAnalysis, project) {
        this.setState({
            volumeAnalysis: volumeAnalysis,
            project: project
        });
    }

    updateAnalysis(volumeAnalysis) {
        this.setState({
            volumeAnalysis: volumeAnalysis,
        });
    }

    updateProject(project) {
        this.setState({
            project: project
        });
    }

    openAnalysisReport() {
        this.setState({
            showAnalysis: !this.state.showAnalysis
        });
    }

    showDetails() {
        this.setState({
            showAnalysis: true
        });
    }

    componentDidUpdate() {}

    componentDidMount() {
        AnalyzeStore.addListener(AnalyzeConstants.RENDER_ANALYSIS, this.updateAll);
        AnalyzeStore.addListener(AnalyzeConstants.UPDATE_ANALYSIS, this.updateAnalysis);
        AnalyzeStore.addListener(AnalyzeConstants.UPDATE_PROJECT, this.updateProject);
        AnalyzeStore.addListener(AnalyzeConstants.SHOW_DETAILS, this.showDetails);

    }

    componentWillUnmount() {
        AnalyzeStore.removeListener(AnalyzeConstants.RENDER_ANALYSIS, this.updateAll);
        AnalyzeStore.removeListener(AnalyzeConstants.UPDATE_ANALYSIS, this.updateAnalysis);
        AnalyzeStore.removeListener(AnalyzeConstants.UPDATE_PROJECT, this.updateProject);
        AnalyzeStore.removeListener(AnalyzeConstants.SHOW_DETAILS, this.showDetails);
    }

    shouldComponentUpdate(nextProps, nextState){
        return ( !nextState.project.equals(this.state.project) ||
        !nextState.volumeAnalysis.equals(this.state.volumeAnalysis) ||
        nextState.showAnalysis !== this.state.showAnalysis)
    }

    render() {
        var spinnerContainer = {
            position: 'absolute',
            height : '100%',
            width : '100%',
            backgroundColor: 'rgba(76, 69, 69, 0.3)',
            top: $(window).scrollTop(),
            left: 0,
            zIndex: 3
        };
        var spinner =<div style={spinnerContainer}>
            <div className="ui active inverted dimmer">
                <div className="ui massive text loader">Loading Volume Analysis</div>
            </div>
        </div>;
        return <div className="ui container">
            {this.state.volumeAnalysis ? (

                <div className="project ui grid">
                    <div className="sixteen wide column">
                        <div className="analyze-header">
                            <AnalyzeHeader data={this.state.volumeAnalysis.get('summary')} project={this.state.project}/>
                        </div>

                        <AnalyzeChunksResume jobsAnalysis={this.state.volumeAnalysis.get('jobs')}
                                             jobsInfo={this.props.jobsInfo}
                                             project={this.state.project}
                                             status={this.state.volumeAnalysis.get('summary').get('STATUS')}
                                             openAnalysisReport={this.openAnalysisReport.bind(this)}

                        />
                        <CSSTransitionGroup component="div" className="project-body ui grid"
                                            transitionName="transition"
                                            transitionEnterTimeout={1500}
                                            transitionLeaveTimeout={1500}
                        >
                        {this.state.showAnalysis ? (
                                <ProjectAnalyze volumeAnalysis={this.state.volumeAnalysis.get('jobs')}
                                                project={this.state.project}
                                                jobsInfo={this.props.jobsInfo}
                                                status={this.state.volumeAnalysis.get('summary').get('STATUS')}/>
                        ) :(null)}
                        </CSSTransitionGroup>

                    </div>
                </div>
            ) : (spinner)}

            </div>;


    }
}

export default AnalyzeMain;
