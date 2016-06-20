export default React.createClass({
    getInitialState : function() {
        return {
            sendLabel : 'Send',
            sendDisabled : true,
            replying : false,
            comments : MateCat.db.segment_translation_issue_comments.findObjects({
                'id_issue' : this.props.issueId
            })
        }
    },

    replyClick : function() {
        this.setState({ replying: true });
    },

    commentsChanged : function() {
        this.setState({
            sendLabel : 'Send',
            replying : false,
            comments : MateCat.db.segment_translation_issue_comments.findObjects({
                'id_issue' : this.props.issueId
            })
        });
    },

    componentDidMount : function() {
        MateCat.db.addListener('segment_translation_issue_comments', 
                               ['insert', 'delete'], this.commentsChanged);
        ReviewImproved.loadComments(this.props.sid, this.props.issueId);
    },
    componentWillUnmount: function() {
        MateCat.db.removeListener('segment_translation_issue_comments', 
                                  ['insert', 'delete'], this.commentsChanged);
    },
    handleFail: function() {
        genericErrorAlertMessage() ;
        this.setState({ sendLabel : 'Send', sendDisabled : false });
    },
    sendClick : function() {
        // send action invokes ReviewImproved function
        if ( this.state.comment_text.length == 0 ) {
            return ;
        }

        var data = {
          message : this.state.comment_text,
          source_page : (config.isReview ? 2 : 1)  // TODO: move this to UI property
        };

        this.setState({ sendLabel : 'Sending', sendDisabled : true });
        ReviewImproved
            .submitComment( this.props.sid, this.props.issueId, data )
            .fail( this.handleFail );

    },

    handleCommentChange : function(event) {
        var text = event.target.value ;
        var disabled = true;

        if ( text.length > 0 ) {
            disabled = false;
        }
        this.setState({
            comment_text : text,
            sendDisabled : disabled
        });
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
            var source_page ; 
            if ( comment.source_page == '1' ) {
                source_page = 'Translator' ; 
            } else {
                source_page = 'Revisor'; 
            }

            return <div key={comment.id} className="review-issue-comment-detail">
                <strong>{source_page}:</strong> {comment.message}
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

            var buttonClasses = classnames({
                'mc-button' : true,
                'blue-button' : true,
                'disabled' : this.state.sendDisabled
            });

            terminal = <div className="review-issue-comment-reply">
                <div className="review-issue-comment-reply-text">

            <textarea data-minheight="40" data-maxheight="90"
                className="mc-textinput mc-textarea mc-resizable-textarea"
                placeholder="Write a comment..."
                onChange={this.handleCommentChange} />

            </div>
            <div className="review-issue-comment-buttons">
            <div className="review-issue-comment-buttons-right">
            <a onClick={this.sendClick} className={buttonClasses}>{this.state.sendLabel}</a>
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
