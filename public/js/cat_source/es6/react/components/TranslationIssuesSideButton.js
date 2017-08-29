class TranslationIssuesSideButton extends React.Component{

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
        if ( this.props.sid == segment.sid ) {
            this.setState( this.readDatabaseAndReturnState() );
        }
    }

    setStateOnIssueChange( issue ) {
        if ( this.props.sid == issue.id_segment ) {
            this.setState( this.readDatabaseAndReturnState() );
        }
    }

    componentDidMount() {
        MateCat.db.addListener('segments', ['update'], this.setStateOnSegmentsChange.bind(this) );
        MateCat.db.addListener('segment_translation_issues', ['insert', 'update', 'delete'],
            this.setStateOnIssueChange.bind(this) );

    }

    componentWillUnmount() {
        MateCat.db.removeListener('segments', ['update'], this.setStateOnSegmentsChange );
        MateCat.db.removeListener('segment_translation_issues', ['insert', 'update', 'delete'],
            this.setStateOnIssueChange );
    }

    handleClick (e) {
        ReviewImproved.openPanel({sid: this.props.sid});
    }

    shouldComponentUpdate (nextProps, nextState) {
        return this.state.issues_count != nextState.issues_count  ;
    }

    render() {
        var plus = config.isReview ? <span className="revise-button-counter">+</span> : null;
        if ( this.state.issues_count > 0 ) {
            return (<div onClick={this.handleClick.bind(this)}><div className="review-triangle"></div><a className="revise-button has-object" href="javascript:void(0);"><span className="icon-error_outline" /><span className="revise-button-counter">{this.state.issues_count}</span></a></div>);
        } else  {
            return (<div onClick={this.handleClick.bind(this)}><div className="review-triangle"></div><a className="revise-button" href="javascript:void(0);"><span className="icon-error_outline" />{plus}</a></div>);
        }

    }
}

export default TranslationIssuesSideButton;
