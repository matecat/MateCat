import JobSummary from "./JobSummary";
import SegmentsDetails from "./SegmentsDetailsContainer";
import ReactDom from "react-dom";
import QRActions from "./../../actions/QualityReportActions";
import QRStore from "./../../stores/QualityReportStore";
import QRConstants from "./../../constants/QualityReportConstants";
import Header from "./../header/Header";
import QRApi from "../../ajax_utils/quality_report/qrUtils";


class QualityReport extends React.Component {

    constructor(props) {
        super(props);
        this.state = {
            segmentFiles: null,
            jobInfo: null,
            moreSegments: true
        };
        this.renderSegmentsFiles = this.renderSegmentsFiles.bind(this);
        this.renderJobInfo = this.renderJobInfo.bind(this);
        this.noMoreSegments = this.noMoreSegments.bind(this);
    }

    renderSegmentsFiles(files) {
        this.setState({
            segmentFiles: files
        });
    }

    noMoreSegments() {
        this.setState({
            moreSegments: false
        });
    }
    renderJobInfo(jobInfo) {
        this.setState({
            jobInfo: jobInfo
        });
    }

    componentWillMount() {
        QRActions.loadInitialAjaxData();
    }

    componentDidMount() {
        QRStore.addListener(QRConstants.RENDER_SEGMENTS, this.renderSegmentsFiles);
        QRStore.addListener(QRConstants.RENDER_REPORT, this.renderJobInfo);
        QRStore.addListener(QRConstants.NO_MORE_SEGMENTS, this.noMoreSegments);
        // console.log("Render Quality Report");
    }
    componentWillUnmount() {
        QRStore.removeListener(QRConstants.RENDER_SEGMENTS, this.renderSegmentsFiles);
        QRStore.removeListener(QRConstants.RENDER_REPORT, this.renderJobInfo);
        QRStore.removeListener(QRConstants.NO_MORE_SEGMENTS, this.noMoreSegments);
    }

    render () {
        let spinnerContainer = {
            position: 'absolute',
            height : '100%',
            width : '100%',
            backgroundColor: 'rgba(76, 69, 69, 0.3)',
            top: $(window).scrollTop(),
            left: 0,
            zIndex: 3
        };
        return <div className="qr-container">
                <div className="qr-container-inside">
                    <div className="qr-job-summary-container">
                        <div className="qr-bg-head"/>
                        { this.state.jobInfo ? (
                            <div className="qr-job-summary">
                                <h3>Job Summary</h3>
                                <JobSummary jobInfo={this.state.jobInfo}/>
                                <SegmentsDetails files={this.state.segmentFiles}
                                                 urls={this.state.jobInfo.get('urls')}
                                                 categories={JSON.parse(this.state.jobInfo.get('quality_summary').get('categories'))}
                                                 moreSegments={this.state.moreSegments}
                                />
                            </div>
                        ) : (
                            <div style={spinnerContainer}>
                                <div className="ui active inverted dimmer">
                                    <div className="ui massive text loader">Loading</div>
                                </div>
                            </div>
                        ) }

                    </div>

                </div>
            </div>
    }
}

export default QualityReport ;

ReactDom.render(React.createElement(QualityReport), document.getElementById('qr-root'));

let headerMountPoint = $("header")[0];

if (config.isLoggedIn) {
    QRApi.getUserData().done(function ( data ) {
        ReactDOM.render(React.createElement(Header, {
            showJobInfo: true,
            showModals: true,
            showTeams: false,
            user: data
        }), headerMountPoint);
    });
} else {
    ReactDOM.render(React.createElement(Header, {
        showJobInfo: true,
        showModals: true,
        showTeams: false,
        loggedUser: false,
    }), headerMountPoint);
}