
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

                <div className="project ui grid">
                    <div className="sixteen wide column">
                        <div className="analyze-header">
                            <AnalyzeHeader data={this.state.volumeAnalysis.get('summary')} project={this.state.project}/>
                        </div>

                        <div className="project-top ui grid">
                            <div className="compare-table sixteen wide column shadow-1">
                                <div className="header-compare-table ui grid">
                                    <div className="title-job">
                                        <h5>Job</h5>
                                        <p>Job ID + Combo language</p>
                                    </div>
                                    <div className="titles-compare">
                                        <div className="title-total-words">
                                            <h5>Total Words</h5>
                                            <p>(Actual words in the files)</p>
                                        </div>
                                        <div className="title-standard-words">
                                            <h5>Standard Weighted</h5>
                                            <p>(Industry word count)</p>
                                        </div>
                                        <div className="title-matecat-words">
                                            <h5>MateCat Payable Words</h5>
                                            <p>(Improved content reuse)</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div className="compare-table jobs sixteen wide column">

                                <div className="job ui grid">
                                    <div className="chunks sixteen wide column">

                                        <div className="chunk ui grid shadow-1">
                                            <div className="title-job">
                                                {/*<div className="job-id">123456-1</div>*/}
                                                <div className="source-target">
                                                    <div className="source-box">Haitian Creole French</div>
                                                    <div className="in-to"><i className="icon-chevron-right icon"></i></div>
                                                    <div className="target-box">Spanish</div>
                                                </div>
                                            </div>
                                            <div className="titles-compare">
                                                <div className="title-total-words">

                                                </div>
                                                <div className="title-standard-words">

                                                </div>
                                                <div className="title-matecat-words">

                                                </div>
                                            </div>
                                            <div className="activity-icons">
                                                <div className="merge ui blue basic button "><i className="icon-compress icon"></i>Merge</div>
                                            </div>
                                        </div>

                                        <div className="chunk ui grid shadow-1">
                                            <div className="title-job">
                                                <div className="job-id">123456-1</div>
                                                {/*<div className="source-target">
                                                    <div className="source-box">Haitian Creole French</div>
                                                    <div className="in-to"><i className="icon-chevron-right icon"></i></div>
                                                    <div className="target-box">Haitian Creole French</div>
                                                </div>*/}
                                            </div>
                                            <div className="titles-compare">
                                                <div className="title-total-words ttw">
                                                    <div>4'034,421</div>
                                                </div>
                                                <div className="title-standard-words tsw">
                                                    <div>3'938,525</div>
                                                </div>
                                                <div className="title-matecat-words tmw">
                                                    <div>2'849,452</div>
                                                </div>
                                            </div>
                                        </div>

                                        <div className="chunk ui grid shadow-1">
                                            <div className="title-job">
                                                <div className="job-id">123456-2</div>
                                                {/*<div className="source-target">
                                                    <div className="source-box">Haitian Creole French</div>
                                                    <div className="in-to"><i className="icon-chevron-right icon"></i></div>
                                                    <div className="target-box">Haitian Creole French</div>
                                                </div>*/}
                                            </div>
                                            <div className="titles-compare">
                                                <div className="title-total-words ttw">
                                                    <div>4'034,421</div>
                                                </div>
                                                <div className="title-standard-words tsw">
                                                    <div>3'938,525</div>
                                                </div>
                                                <div className="title-matecat-words tmw">
                                                    <div>2'849,452</div>
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                </div>

                                <div className="job ui grid">
                                    <div className="chunks sixteen wide column">

                                        <div className="chunk ui grid shadow-1">
                                            <div className="title-job">
                                                <div className="job-id">123456-1</div>
                                                <div className="source-target">
                                                    <div className="source-box">Haitian Creole French</div>
                                                    <div className="in-to"><i className="icon-chevron-right icon"></i></div>
                                                    <div className="target-box">English</div>
                                                </div>
                                            </div>
                                            <div className="titles-compare">
                                                <div className="title-total-words ttw">
                                                    <div>4'034,421</div>
                                                </div>
                                                <div className="title-standard-words tsw">
                                                    <div>3'938,525</div>
                                                </div>
                                                <div className="title-matecat-words tmw">
                                                    <div>2'849,452</div>
                                                </div>
                                            </div>
                                            <div className="activity-icons">
                                                <div className="split ui blue basic button "><i className="icon-expand icon"></i>Split</div>
                                                <div className="open-translate ui primary button open">Translate</div>
                                            </div>
                                        </div>

                                    </div>
                                </div>

                            </div>
                            <div className="analyze-report">
                                <h3>Analyze report</h3>
                                <div className="rounded">
                                    <i className="icon-sort-down icon"></i>
                                </div>
                            </div>
                        </div>


                        <div className="project-body ui grid">
                            <ProjectAnalyze volumeAnalysis={this.state.volumeAnalysis.get('jobs')}
                                            project={this.state.project}
                                            jobsInfo={this.props.jobsInfo}
                                            status={this.state.volumeAnalysis.get('summary').get('STATUS')}/>
                        </div>
                    </div>
                </div>
            ) : (spinner)}

            </div>;


    }
}

export default AnalyzeMain;
