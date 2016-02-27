export default React.createClass({
    getInitialState : function() {
        return {
            comments : MateCat.db
                .segment_translation_issue_comments.findObjects({
                    'id_issue' : this.props.issueId 
                })
        }
    },


    render : function() {

        var commentLines = this.state.comments.map(function(i) {
            return <div className="review-issue-comment-detail"> 
            {i.comment}
            </div>
        }); 

        return <div className="review-issue-comment-container" >
        {commentLines}
        </div>; 
        
    }
});
