let SegmentStore = require('../../stores/SegmentStore');
let SegmentConstants = require('../../constants/SegmentConstants');

class TranslationIssuesSideButton extends React.Component{

    constructor(props) {
        super(props);

        if (this.props.reviewType === "improved") {
            this.state = this.readDatabaseAndReturnState();
        } else {
            this.state = {
                issues_count : this.setIssueCount()
            }
        }
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

    readDatabaseAndReturnState () {
        var segment = MateCat.db.segments.by('sid', this.props.sid);
        var issues = MateCat.db.segment_translation_issues
            .findObjects({
                id_segment :  parseInt(this.props.sid),
                translation_version : segment.version_number
            });

        return {
            issues_count : issues.length
        };
    }

    setStateOnSegmentsChange( segment ) {
        if ( parseInt(this.props.sid) == parseInt(segment.sid) ) {
            this.setState( this.readDatabaseAndReturnState() );
        }
    }

    setStateOnIssueChange( issue ) {
        if ( parseInt(this.props.sid) === parseInt(issue.id_segment) ) {
            this.setState( this.readDatabaseAndReturnState() );
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
    setSegmentPreloadedIssues(sid, issues) {
        if (parseInt(this.props.sid) === parseInt(sid)) {

            this.setState({
                issues_count : issues.length
            });
        }
    }

    componentDidMount() {
        if (this.props.reviewType === "improved") {
            MateCat.db.addListener('segments', ['update'], this.setStateOnSegmentsChange.bind(this));
            MateCat.db.addListener('segment_translation_issues', ['insert', 'update', 'delete'],
                this.setStateOnIssueChange.bind(this));
        } else if (this.props.reviewType === "extended") {
            SegmentStore.addListener(SegmentConstants.ADD_SEGMENT_VERSIONS_ISSUES, this.setSegmentVersions.bind(this));
            SegmentStore.addListener(SegmentConstants.ADD_SEGMENT_PRELOADED_ISSUES, this.setSegmentPreloadedIssues.bind(this));
        }

    }

    componentWillUnmount() {
        if (this.props.reviewType === "improved") {
            MateCat.db.removeListener('segments', ['update'], this.setStateOnSegmentsChange);
            MateCat.db.removeListener('segment_translation_issues', ['insert', 'update', 'delete'],
                this.setStateOnIssueChange);
        } else if (this.props.reviewType === "extended") {
            SegmentStore.removeListener(SegmentConstants.ADD_SEGMENT_VERSIONS_ISSUES, this.setSegmentVersions);
            SegmentStore.removeListener(SegmentConstants.ADD_SEGMENT_PRELOADED_ISSUES, this.setSegmentPreloadedIssues);

        }
    }

    handleClick (e) {
        e.preventDefault();
        e.stopPropagation();
        $(this.button).addClass('open');
        if (this.props.reviewType === "extended") {
            SegmentActions.closeIssuesPanel();
        }
        if (!this.props.open) {
            SegmentActions.openIssuesPanel({sid: this.props.sid}, true);
        } else {
            UI.closeIssuesPanel();
        }

    }

    shouldComponentUpdate (nextProps, nextState) {
        return this.state.issues_count != nextState.issues_count  ;
    }

    componentDidUpdate() {
        console.log("Update Segment translation button" + this.props.segment.sid);
    }

    render() {
        let openClass = this.props.open ? "open-issues" : "";
        let plus = config.isReview ? <span className="revise-button-counter">+</span> : null;
        if ( this.state.issues_count > 0 ) {
            return (<div title="Add Issues" onClick={this.handleClick.bind(this)}><a ref={(button)=> this.button=button} className={"revise-button has-object " + openClass} href="javascript:void(0);"><span className="icon-error_outline" /><span className="revise-button-counter">{this.state.issues_count}</span></a></div>);
        } else  if (config.isReview){
            return (<div title="Show Issues" onClick={this.handleClick.bind(this)}><a ref={(button)=> this.button=button} className={"revise-button " + openClass} href="javascript:void(0);"><span className="icon-error_outline" />{plus}</a></div>);
        } else {
            return "";
        }

    }
}

export default TranslationIssuesSideButton;
