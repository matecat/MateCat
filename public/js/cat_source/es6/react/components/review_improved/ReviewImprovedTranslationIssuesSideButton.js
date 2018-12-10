
class ReviewImprovedTranslationIssuesSideButton extends React.Component{

    constructor(props) {
        super(props);
        this.state = this.readDatabaseAndReturnState();
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

    componentDidMount() {
        MateCat.db.addListener('segments', ['update'], this.setStateOnSegmentsChange.bind(this));
        MateCat.db.addListener('segment_translation_issues', ['insert', 'update', 'delete'], this.setStateOnIssueChange.bind(this));


    }

    componentWillUnmount() {
        MateCat.db.removeListener('segments', ['update'], this.setStateOnSegmentsChange);
        MateCat.db.removeListener('segment_translation_issues', ['insert', 'update', 'delete'], this.setStateOnIssueChange);
    }

    handleClick (e) {
        e.preventDefault();
        e.stopPropagation();
        $(this.button).addClass('open');
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
            return (<div title="Add Issues" onClick={this.handleClick.bind(this)}>
                <a ref={(button)=> this.button=button} className={"revise-button has-object " + openClass} href="javascript:void(0);">
                    <span className="icon-error_outline" /><span className="revise-button-counter">{this.state.issues_count}</span>
                </a>
            </div>);
        } else  if (config.isReview){
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

export default ReviewImprovedTranslationIssuesSideButton;
