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

    render : function() {

        var category_label = this.categoryLabel();
        var formatted_date = moment( this.state.issue.created_at ).format('lll'); 

        var commentLine = null; 
        var comment = this.state.issue.comment ; 

        if ( comment ) {
            commentLine = <div className="review-issue-thread-entry">
            <strong>Comment:</strong> { comment }</div>; 
        }

        return <div className="review-issue-detail" >
        <strong>Issue # {this.props.index} </strong> 

        <div>{this.state.issue.severity} - {category_label} - {formatted_date} </div>
        {commentLine}
        <ReviewTranslationIssueCommentsContainer sid={this.props.sid} issueId={this.props.issueId} />
        

        </div>; 
        
    }
});
