
let AnalyzeConstants = require('../../constants/AnalyzeConstants');
let AnalyzeHeader = require('./AnalyzeHeader').default;
let ProjectAnalyze = require('./ProjectAnalyze').default;
let AnalyzeStore = require('../../stores/AnalyzeStore');


class AnalyzeMain extends React.Component {

    constructor(props) {
        super(props);
        this.state = {
            volumeAnalysis: null,
            project: null
        };
        this.updateAll = this.updateAll.bind(this);
        this.updateAnalysis = this.updateAnalysis.bind(this);
        this.updateProject = this.updateProject.bind(this);
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

    componentDidUpdate() {
    }

    componentDidMount() {
        AnalyzeStore.addListener(AnalyzeConstants.RENDER_ANALYSIS, this.updateAll);
        AnalyzeStore.addListener(AnalyzeConstants.UPDATE_ANALYSIS, this.updateAnalysis);
        AnalyzeStore.addListener(AnalyzeConstants.UPDATE_PROJECT, this.updateProject);
    }

    componentWillUnmount() {
        AnalyzeStore.removeListener(AnalyzeConstants.RENDER_ANALYSIS, this.updateAll);
        AnalyzeStore.removeListener(AnalyzeConstants.UPDATE_ANALYSIS, this.updateAnalysis);
        AnalyzeStore.removeListener(AnalyzeConstants.UPDATE_PROJECT, this.updateProject);
    }

    shouldComponentUpdate(nextProps, nextState){
        return ( !nextState.project.equals(this.state.project) ||
        !nextState.volumeAnalysis.equals(this.state.volumeAnalysis) )
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
                <div className="project ui grid shadow-1">
                    <div className="sixteen wide column">
                        <div className="analyze-header">
                            <AnalyzeHeader data={this.state.volumeAnalysis.get('summary')} project={this.state.project}/>
                        </div>

                        <div className="project-body ui grid">
                            <ProjectAnalyze/>
                        </div>
                    </div>
                </div>
            ) : (spinner)}

            </div>;


    }
}

export default AnalyzeMain;
