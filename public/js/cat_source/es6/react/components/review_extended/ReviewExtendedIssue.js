class ReviewExtendedIssue extends React.Component{

    constructor(props) {
        super(props);
        this.state = {

        };

    }

    categoryLabel() {
        let id_category = this.props.issue.id_category ;
        config.lqa_flat_categories = config.lqa_flat_categories.replace(/\"\[/g, "[").replace(/\]"/g, "]").replace(/\"\{/g, "{").replace(/\}"/g, "}")
        return _( JSON.parse( config.lqa_flat_categories ))
            .select(function(e) {
                return parseInt(e.id) == id_category ;
            }).first().label
    }

    deleteIssue(event) {
        event.preventDefault();
        event.stopPropagation();
        ReviewImproved.deleteIssue(this.props.issue);
    }
    render() {
        let category_label = this.categoryLabel();
        let formatted_date = moment( this.props.issue.created_at ).format('lll');

        let commentLine = null;
        if ( this.props.issue.comment ) {
            commentLine = <div className="review-issue-thread-entry">
                <strong>Comment:</strong> { comment }</div>;
        }

        let deleteIssue ;

        if ( config.isReview ) {
            deleteIssue = <a href="#" className="cancel-project"
                             onClick={this.deleteIssue.bind(this)}>Delete issue</a>;
        }

        return <div className="review-issue-detail"
                    onMouseEnter={this.props.issueMouseEnter.bind(null, this.props.issue) }
                    onMouseLeave={this.props.issueMouseLeave}
                    onClick={this.props.issueMouseEnter.bind(null, this.props.issue)} >
            <h4>Issue # {this.props.progressiveNumber} </h4> <span className="review-issue-date">{formatted_date} </span>
            <br />
            <span className="review-issue-severity">{this.props.issue.severity} - </span><span className="review-issue-label">{category_label} </span>
            <br />
            <div className="review-issue-comment">
                {commentLine}
            </div>

            {/*<ReviewTranslationIssueCommentsContainer*/}
                {/*sid={this.props.sid}*/}
                {/*issueId={this.props.issueId} />*/}

            {deleteIssue}
        </div>;
    }
}

export default ReviewExtendedIssue ;