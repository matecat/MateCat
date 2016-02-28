export default React.createClass({
    getInitialState : function() {
        return {
            replying : false,
            comments : MateCat.db.segment_translation_issue_comments.findObjects({
                'id_issue' : this.props.issueId
            })
        }
    },

    replyClick : function() {
        this.setState({ replying: true });
    },

    componentWillReceiveProps : function() {
        ReviewImproved.loadComments(this.props.sid, this.props.issueId);
    },

    commentsChanged : function() {
        this.setState({
            replying : false,
            comments : MateCat.db.segment_translation_issue_comments.findObjects({
                'id_issue' : this.props.issueId
            })
        });
    },

    componentDidMount : function() {
        MateCat.db.addListener('segment_translation_issue_comments', ['insert', 'delete'], this.commentsChanged);
        ReviewImproved.loadComments(this.props.sid, this.props.issueId);
    },
    componentWillUnmount: function() {
        MateCat.db.removeListener('segment_translation_issue_comments', ['insert', 'delete'], this.commentsChanged);
    },
    sendClick : function() {
        // send action invokes ReviewImproved function
        var data = {
          message : this.state.comment_text,
          source_page : config.isReview
        };
        ReviewImproved.submitComment( this.props.sid, this.props.issueId, data ) ;
    },

    handleCommentChange : function(event) {
        this.setState({ comment_text : event.target.value });
    },

    cancelClick : function() {
        this.setState({ replying: false });
    },

    render : function() {

        var terminal ;

        var sortedComments = _.sortBy(this.state.comments, function(comment) {
            return parseInt(comment.id); 
        }); 

        var commentLines = sortedComments.map(function(comment, index) {
            console.debug( comment.id ); 

            return <div key={comment.id} className="review-issue-comment-detail">
            {comment.id} - {comment.message}
            </div>;
        }); 

        if ( !this.state.replying ) {
            terminal = <div className="review-issue-comment-buttons">
                <div className="review-issue-comment-buttons-right">
                    <a onClick={this.replyClick} className="mc-button blue-button">Reply</a>
                </div>
            </div>;
        }
        else {
            terminal = <div className="review-issue-comment-reply">
                <div className="review-issue-comment-reply-text">

            <textarea data-minheight="40" data-maxheight="90"
                className="mc-textinput mc-textarea mc-resizable-textarea"
                placeholder="Write a comment..."
                onChange={this.handleCommentChange} />

            </div>
            <div className="review-issue-comment-buttons">
            <div className="review-issue-comment-buttons-right">
            <a onClick={this.sendClick} className="mc-button blue-button">Send</a>
            </div>
            </div>
            </div>;
        }

        return <div className="review-issue-comment-container" >
            <div className="review-issue-comment-entries">
            {commentLines}
            </div>
            {terminal}
        </div>; 
    }

});
