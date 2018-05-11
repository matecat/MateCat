class ReviewTranslationIssueCommentsContainer extends React.Component{

    constructor(props) {
        super(props);
        this.state = {
            sendLabel : 'Send',
            sendDisabled : true,
            replying : false,
            comments : MateCat.db.segment_translation_issue_comments.findObjects({
                'id_issue' : this.props.issueId
            }),
            rebutLabel : 'Send and Rebut',
            rebutDisabled : true,
            rebutVisible : true,
            undoRebutLabel : 'Undo Rebut',
            undoRebutDisabled : false,
            undoRebutVisible : false
        };
    }


    replyClick() {
        this.setState({ replying: true });
    }

    undoRebutClick() {
        this.setState({ undoRebutDisabled : true, undoRebutLabel: 'Undoing' });
        ReviewImproved.undoRebutIssue( this.props.sid, this.props.issueId )
            .fail( this.handleFail )
            .fail(function() {
                this.setState({ undoRebutDisabled : false, undoRebutLabel: this.getInitialState().undoRebutLabel });
            }.bind(this) );

    }

    commentsChanged() {
        this.setState({
            comment_text: '', 
            sendLabel : 'Send',
            replying : false,
            comments : MateCat.db.segment_translation_issue_comments.findObjects({
                'id_issue' : this.props.issueId
            }),
            rebutLabel : 'Send and Rebut'
        });
    }

    issueChanged( data ) {
        if( data.id === this.props.issueId ) {
            this.checkIssue( data );
        }
    }

    checkIssue( issue ) {
        if( issue.rebutted_at ) {
            if( this.state.rebutVisible ) {
                this.setState({
                    rebutVisible : false,
                    undoRebutVisible : true,
                    sendDisabled : true,
                    undoRebutLabel : 'Undo Rebut',
                    undoRebutDisabled : false
                });
            }
        } else {
            if( !this.state.rebutVisible ) {
                this.setState({
                    rebutVisible : true,
                    undoRebutVisible : false,
                    sendDisabled : true,
                    undoRebutLabel : 'Undo Rebut',
                    undoRebutDisabled : false
                });
            }
        }
    }

    componentDidMount() {
        MateCat.db.addListener('segment_translation_issue_comments',
            ['insert', 'delete'], this.commentsChanged.bind(this));
        ReviewImproved.loadComments(this.props.sid, this.props.issueId);

        var issue = MateCat.db.segment_translation_issues.by( 'id', parseInt(this.props.issueId) );
        this.checkIssue( issue );

        MateCat.db.addListener('segment_translation_issues',
            ['insert', 'update'], this.issueChanged.bind(this));

    }
    componentWillUnmount() {
        MateCat.db.removeListener('segment_translation_issue_comments',
            ['insert', 'delete'], this.commentsChanged);

        MateCat.db.removeListener('segment_translation_issues',
            ['insert', 'update'], this.issueChanged);

    }
    handleFail() {
        genericErrorAlertMessage() ;
        this.setState({ sendLabel : 'Send', sendDisabled : false });
    }

    sendClick() {
        // send action invokes ReviewImproved function
        if ( !this.state.comment_text || this.state.comment_text.length == 0 ) {
            return ;
        }

        var data = {
          message : this.state.comment_text,
          source_page : (config.isReview ? 2 : 1)  // TODO: move this to UI property
        };

        this.setState({ sendLabel : 'Sending', sendDisabled : true, rebutDisabled : true });
        SegmentActions
            .submitComment( this.props.sid, this.props.issueId, data )
            .fail( this.handleFail );

    }

    rebutClick() {
        // send action invokes ReviewImproved function
        if ( !this.state.comment_text || this.state.comment_text.length == 0 ) {
            return ;
        }

        var data = {
          rebutted : true,
          message : this.state.comment_text,
          source_page : (config.isReview ? 2 : 1)  // TODO: move this to UI property
        };
        
        this.setState({ rebutLabel : 'Sending', rebutDisabled : true, sendDisabled : true });

        $.when(
            ReviewImproved.submitComment( this.props.sid, this.props.issueId, data )
        ).fail( this.handleFail );

    }

    handleCommentChange(event) {
        var text = event.target.value ;
        var disabled = true;

        if ( text.length > 0 ) {
            disabled = false;
        }
        this.setState({
            comment_text : text,
            sendDisabled : disabled,
            rebutDisabled : disabled
        });
    }

    cancelClick() {
        this.setState({ replying: false });
    }

    handleRootClick(event) {
        event.stopPropagation();
    }
    render() {

        var terminal ;

        var sortedComments = _.sortBy(this.state.comments, function(comment) {
            return parseInt(comment.id); 
        }); 

        var commentLines = sortedComments.map(function(comment, index) {
            var source_page ; 
            if ( comment.source_page == '1' ) {
                source_page = <strong className="review-issue-translator">Translator:</strong> ;
            } else {
                source_page = <strong className="review-issue-revisor">Revisor:</strong>;
            }

            return <div key={comment.id} className="review-issue-comment-detail">
                {source_page} {comment.message}
            </div>;
        }); 

        if ( !this.state.replying ) {
            var undoRebutButton;

            if( !config.isReview && this.state.undoRebutVisible  && this.props.reviewType === "improved") {
                var undoRebutButtonClasses = classnames({
                    'ui' : true,
                    'red' : true,
                    'button' : true,
                    'small' : true,
                    'disabled' : this.state.undoRebutDisabled
                });

                undoRebutButton =
                    <a onClick={this.undoRebutClick.bind(this)} className={undoRebutButtonClasses}>
                        {this.state.undoRebutLabel}
                    </a>;
            }

            terminal = <div className="review-issue-comment-buttons">
                <div className="review-issue-comment-buttons-right">
                    {undoRebutButton}
                    <a onClick={this.replyClick.bind(this)} className="ui primary button small">Reply</a>
                </div>
            </div>;
        }
        else {

            var buttonClasses = classnames({
                'ui' : true,
                'primary' : true,
                'button' : true,
                'small' : true,
                'disabled' : this.state.sendDisabled
            });

            var rebutButton;

            var rebutButtonClasses = classnames({
                'ui' : true,
                'red' : true,
                'button' : true,
                'small' : true,
                'disabled' : this.state.rebutDisabled
            });
            rebutButton =
                <a onClick={this.rebutClick.bind(this)} className={rebutButtonClasses}>
                    {this.state.rebutLabel}
                </a>;

            terminal = <div className="review-issue-comment-reply">
                <div className="review-issue-comment-reply-text">

                    <textarea data-minheight="40" data-maxheight="90"
                        className=""
                        placeholder="Write a comment..."
                        value={this.state.comment_text}
                        onChange={this.handleCommentChange.bind(this)} />

                </div>

                <div className="review-issue-comment-buttons">
                    <div className="review-issue-comment-buttons-right">
                        {rebutButton}
                        <a onClick={this.sendClick.bind(this)} className={buttonClasses}>{this.state.sendLabel}</a>
                    </div>
                </div>
            </div>;
        }

        return <div onClick={this.handleRootClick} className="review-issue-comment-container" >
            <div className="review-issue-comment-entries">
                {commentLines}
            </div>
            <hr/>
            {terminal}
        </div>; 
    }

}

export default ReviewTranslationIssueCommentsContainer;
