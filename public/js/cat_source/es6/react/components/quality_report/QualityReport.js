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
            moreSegments: true,
            revisionToShow: this.getUrlParameter()
        };
        this.renderSegmentsFiles = this.renderSegmentsFiles.bind(this);
        this.renderJobInfo = this.renderJobInfo.bind(this);
        this.noMoreSegments = this.noMoreSegments.bind(this);
    }
    getUrlParameter() {
        let url = new URL(window.location.href);
        let revType = url.searchParams.get('revision_type');
        return (revType) ? revType : '1'
    }

    updateUrlParameter( revisionType ) {
        history.pushState(null, '', '?revision_type=' + revisionType);
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

    initDropDown() {
        let self = this;
        if ( this.reviewDropdown ) {
            $(this.reviewDropdown).dropdown({
                onChange: function(value, text, $selectedItem) {
                    if (value && value !== "") {
                        self.updateUrlParameter(value);
                        self.setState({
                            revisionToShow: value
                        });
                    }
                }
            });
            this.dropdownInitialized = true;
        }
    }

    componentWillMount() {
        QRActions.loadInitialAjaxData();
    }

    componentDidMount() {
        QRStore.addListener(QRConstants.RENDER_SEGMENTS, this.renderSegmentsFiles);
        QRStore.addListener(QRConstants.RENDER_REPORT, this.renderJobInfo);
        QRStore.addListener(QRConstants.NO_MORE_SEGMENTS, this.noMoreSegments);
        setTimeout(this.initDropDown.bind(this), 100);
        // console.log("Render Quality Report");
    }
    componentWillUnmount() {
        QRStore.removeListener(QRConstants.RENDER_SEGMENTS, this.renderSegmentsFiles);
        QRStore.removeListener(QRConstants.RENDER_REPORT, this.renderJobInfo);
        QRStore.removeListener(QRConstants.NO_MORE_SEGMENTS, this.noMoreSegments);
    }

    componentDidUpdate() {
        if (!this.dropdownInitialized) {
            this.initDropDown();
        }
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
        let quality_summary;
        if ( this.state.jobInfo ) {

            quality_summary = this.state.jobInfo.get('quality_summary').find((value)=>{ return value.get('revision_number') === parseInt(this.state.revisionToShow) });
        }
        return <div className="qr-container">
                <div className="qr-container-inside">
                    <div className="qr-job-summary-container">
                        <div className="qr-bg-head"/>
                        { this.state.jobInfo ? (
                            <div className="qr-job-summary">
                                <div className="qr-header">
                                    <h3>QR Job Summary</h3>
                                    { this.state.jobInfo.get('quality_summary').size > 1 ? (
                                    <div className="qr-filter-list">
                                        <div className="filter-dropdown right-10">
                                            <div className={"filter-reviewType active"}>
                                                <div className="ui top left pointing dropdown basic tiny button right-0" style={{marginBottom: '12px'}} ref={(dropdown)=>this.reviewDropdown=dropdown}>
                                                    { this.state.revisionToShow === '1' ? (
                                                        <div className="text">
                                                            <div  className={"ui revision-color empty circular label"} />
                                                            Revision
                                                        </div>
                                                    ) : (
                                                        <div className="text">
                                                            <div  className={"ui second-revision-color empty circular label"} />
                                                            2nd Revision
                                                        </div>
                                                    )}
                                                    <div className="menu">
                                                        <div className="item" data-value="1">
                                                            <div  className={"ui revision-color empty circular label"} />
                                                            Revision
                                                        </div>
                                                        <div className="item" data-value="2">
                                                            <div  className={"ui second-revision-color empty circular label"} />
                                                            2nd Revision
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div> ) : null }
                                </div>
                                <JobSummary jobInfo={this.state.jobInfo}
                                            qualitySummary={quality_summary}
                                />
                                <SegmentsDetails files={this.state.segmentFiles}
                                                 urls={this.state.jobInfo.get('urls')}
                                                 categories={quality_summary.get('categories')}
                                                 moreSegments={this.state.moreSegments}
                                                 secondPassReviewEnabled={quality_summary.size > 1}
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