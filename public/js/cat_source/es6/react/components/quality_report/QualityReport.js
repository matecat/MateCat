import Header from "./Header";
import JobSummary from "./JobSummary";
import SegmentsDetails from "./SegmentsDetailsContainer";
import ReactDom from "react-dom";
import QRActions from "./../../actions/QualityReportActions";
import QRStore from "./../../stores/QualityReportStore";
import QRConstants from "./../../constants/QualityReportConstants";

class QualityReport extends React.Component {

    constructor(props) {
        super(props);
        this.state = {
            segmentFiles: null
        };
        this.renderSegmentsFiles = this.renderSegmentsFiles.bind(this);
    }

    renderSegmentsFiles(files) {
        this.setState({
            segmentFiles: files
        });
    }

    componentWillMount() {
        QRActions.loadInitialAjaxData()
    }

    componentDidMount() {
        QRStore.addListener(QRConstants.RENDER_SEGMENTS, this.renderSegmentsFiles);
    }
    componentWillUnmount() {
        QRStore.removeListener(QRConstants.RENDER_SEGMENTS, this.renderSegmentsFiles);
    }

    render () {

        return <div className="qr-container">
                <div className="qr-container-inside">
                    <div className="qr-job-summary-container">
                        <div className="qr-job-summary">
                            <h3>Job Summary</h3>
                            <JobSummary/>
                            <SegmentsDetails files={this.state.segmentFiles}/>
                        </div>
                    </div>

                </div>
            </div>
    }
}

export default QualityReport ;

ReactDom.render(React.createElement(QualityReport), document.getElementById('qr-root'));