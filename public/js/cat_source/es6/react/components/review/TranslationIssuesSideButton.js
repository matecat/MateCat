let SegmentStore = require('../../stores/SegmentStore');
let SegmentConstants = require('../../constants/SegmentConstants');

class TranslationIssuesSideButton extends React.Component{

    constructor(props) {
        super(props);

        if (this.props.reviewType === "improved") {
            this.state = this.readDatabaseAndReturnState();
        } else {
            this.state = {
                issues_count : 0
            }
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
        if ( this.props.sid == segment.sid ) {
            this.setState( this.readDatabaseAndReturnState() );
        }
    }

    setStateOnIssueChange( issue ) {
        if ( this.props.sid == issue.id_segment ) {
            this.setState( this.readDatabaseAndReturnState() );
        }
    }

    setSegmentVersions(sid, segment) {
        if (this.props.sid === sid && segment.versions.length > 0) {
            this.setState({
                issues_count : segment.versions[0].issues.length
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
        }

    }

    componentWillUnmount() {
        if (this.props.reviewType === "improved") {
            MateCat.db.removeListener('segments', ['update'], this.setStateOnSegmentsChange);
            MateCat.db.removeListener('segment_translation_issues', ['insert', 'update', 'delete'],
                this.setStateOnIssueChange);
        } else if (this.props.reviewType === "extended") {
            SegmentStore.removeListener(SegmentConstants.ADD_SEGMENT_VERSIONS_ISSUES, this.setSegmentVersions);
        }
    }

    handleClick (e) {
        SegmentActions.openIssuesPanel({sid: this.props.sid});
        // ReviewImproved.openPanel({sid: this.props.sid});
    }

    shouldComponentUpdate (nextProps, nextState) {
        return this.state.issues_count != nextState.issues_count  ;
    }

    render() {
        var plus = config.isReview ? <span className="revise-button-counter">+</span> : null;
        if ( this.state.issues_count > 0 ) {
            return (<div onClick={this.handleClick.bind(this)}><a className="revise-button has-object" href="javascript:void(0);"><span className="icon-error_outline" /><span className="revise-button-counter">{this.state.issues_count}</span></a></div>);
        } else  {
            return (<div onClick={this.handleClick.bind(this)}><a className="revise-button" href="javascript:void(0);"><span className="icon-error_outline" />{plus}</a></div>);
        }

    }
}

export default TranslationIssuesSideButton;
