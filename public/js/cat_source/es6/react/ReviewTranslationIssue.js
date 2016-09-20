export default React.createClass({
    getInitialState : function() {
        
        return {
            issue : MateCat.db
                .segment_translation_issues.by('id', this.props.issueId )
        }
    },

    categoryLabel : function() {
        var id_category = this.state.issue.id_category ; 

        return _( JSON.parse( config.lqa_flat_categories ))
            .select(function(e) { return  e.id == id_category ; })
            .first().label
    },

    deleteIssue : function(event) {
        event.preventDefault(); 
        event.stopPropagation(); 
        ReviewImproved.deleteIssue(this.state.issue); 
    },
    render : function() {
        var category_label = this.categoryLabel();
        var formatted_date = moment( this.state.issue.created_at ).format('lll'); 

        var commentLine = null; 
        var comment = this.state.issue.comment ; 

        if ( comment ) {
            commentLine = <div className="review-issue-thread-entry">
            <strong>Comment:</strong> { comment }</div>; 
        }

        var deleteIssue ; 

        if ( config.isReview ) {
            deleteIssue = <a href="#" className="cancel-project"
                onClick={this.deleteIssue}>Delete issue</a>;
        }

        return <div className="review-issue-detail"
            onMouseEnter={this.props.issueMouseEnter.bind(null, this.state.issue) }
            onMouseLeave={this.props.issueMouseLeave}
            onClick={this.props.issueMouseEnter.bind(null, this.state.issue)} >
            <h4>Issue # {this.props.progressiveNumber} </h4> <span className="review-issue-date">{formatted_date} </span>
            <br />
            <span className="review-issue-severity">{this.state.issue.severity} - </span><span className="review-issue-label">{category_label} </span>
            <br />
            <div className="review-issue-comment">
            {commentLine}
            </div>

            <ReviewTranslationIssueCommentsContainer 
                sid={this.props.sid} 
                issueId={this.props.issueId} />

            {deleteIssue}
        </div>; 
    }
});
