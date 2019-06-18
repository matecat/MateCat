let SegmentStore = require('../../stores/SegmentStore');
let SegmentConstants = require('../../constants/SegmentConstants');

class ReviewExtendedTranslationIssuesSideButton extends React.Component{

    constructor(props) {
        super(props);
        this.state = {
            issues_count : this.setIssueCount()
        };
        this.setSegmentPreloadedIssues = this.setSegmentPreloadedIssues.bind(this);
        this.setSegmentVersions = this.setSegmentVersions.bind(this);
    }

    setIssueCount() {
        let issue_count = 0;
        if (this.props.segment.versions && this.props.segment.versions.length > 0) {
            this.props.segment.versions.forEach( (version) => {
                issue_count = issue_count + version.issues.length;
            })
            return issue_count;
        } else {
            return 0;
        }
    }

    setSegmentVersions(sid, segment) {
        if (parseInt(this.props.sid) === parseInt(sid) && segment.versions.length > 0) {
            let issue_count = 0;
            segment.versions.forEach( (version) => {
                issue_count = issue_count + version.issues.length;
            })
            this.setState({
                issues_count : issue_count
            });
        }
    }
    setSegmentPreloadedIssues(sid, issues){
        if (parseInt(this.props.sid) === parseInt(sid)) {

            this.setState({
                issues_count : issues.length
            });
        }
    }

    componentDidMount() {
        SegmentStore.addListener(SegmentConstants.ADD_SEGMENT_VERSIONS_ISSUES, this.setSegmentVersions);
        SegmentStore.addListener(SegmentConstants.ADD_SEGMENT_PRELOADED_ISSUES, this.setSegmentPreloadedIssues);
    }

    componentWillUnmount() {
        SegmentStore.removeListener(SegmentConstants.ADD_SEGMENT_VERSIONS_ISSUES, this.setSegmentVersions);
        SegmentStore.removeListener(SegmentConstants.ADD_SEGMENT_PRELOADED_ISSUES, this.setSegmentPreloadedIssues);
    }

    handleClick (e) {
        e.preventDefault();
        e.stopPropagation();
        $(this.button).addClass('open');
        SegmentActions.closeIssuesPanel();
        if (!this.props.open) {
            SegmentActions.openIssuesPanel({sid: this.props.sid}, true);
        } else {
            UI.closeIssuesPanel();
        }

    }

    shouldComponentUpdate (nextProps, nextState) {
        return this.state.issues_count != nextState.issues_count || this.props.segment.unlocked !== nextProps.segment.unlocked;
    }

    componentDidUpdate() {
        console.log("Update Segment translation button" + this.props.segment.sid);
    }

    render() {
        let openClass = this.props.open ? "open-issues" : "";
        let plus = config.isReview ? <span className="revise-button-counter">+</span> : null;
        if ( this.state.issues_count > 0 ) {
            return (<div title="Add Issues" onClick={this.handleClick.bind(this)}>
                <a ref={(button)=> this.button=button} className={"revise-button has-object " + openClass} href="javascript:void(0);">
                    <span className="icon-error_outline" />
                    <span className="revise-button-counter">{this.state.issues_count}</span>
                </a>
            </div>);
        } else  if (config.isReview && !(this.props.segment.ice_locked == 1 &&  !this.props.segment.unlocked) ){
            return (<div title="Show Issues" onClick={this.handleClick.bind(this)}>
                <a ref={(button)=> this.button=button} className={"revise-button " + openClass} href="javascript:void(0);">
                    <span className="icon-error_outline" />
                    {plus}
                </a>
            </div>);
        } else {
            return "";
        }

    }
}

export default ReviewExtendedTranslationIssuesSideButton;
