let ReviewVersionDiff =  require("./ReviewVersionsDiff").default;
let SegmentConstants = require('../../constants/SegmentConstants');
class ReviewExtendedIssue extends React.Component {

	constructor(props) {
		super(props);
		this.state = {
			extendDiffView: false,
			commentView: false,
            sendDisabled : true,
            rebutDisabled: true
		};

	}

	categoryLabel() {
		let id_category = this.props.issue.id_category;
		config.lqa_flat_categories = config.lqa_flat_categories.replace(/\"\[/g, "[")
			.replace(/\]"/g, "]")
			.replace(/\"\{/g, "{")
			.replace(/\}"/g, "}");
		return _(JSON.parse(config.lqa_flat_categories))
			.filter(function (e) {
				return parseInt(e.id) == id_category;
			}).first().label
	}

	deleteIssue(event) {
		event.preventDefault();
		event.stopPropagation();
		SegmentActions.deleteIssue(this.props.issue);
	}
	confirmDeletedIssue(sid,data){
		let issue_id = data;
		if(sid === this.props.issue.id_segment && issue_id === this.props.issue.id){
			$(this.el).transition('fade left');
		}
	}
	setExtendedDiffView(event){
		event.preventDefault();
		event.stopPropagation();
		this.setState({
			extendDiffView : !this.state.extendDiffView,
            commentView : false
		})
	}
    setCommentView(event){
        event.preventDefault();
        event.stopPropagation();
        let self = this;
        if(!this.state.commentView){
        	setTimeout(function (  ) {
                $(self.el).find('.re-comment-input')[0].focus();
            }, 100);
        }
        this.setState({
            extendDiffView : false,
			commentView : !this.state.commentView
        });
	}

    handleCommentChange(event) {
        var text = event.target.value,
        	disabled = true;

        if ( text.length > 0 ) {
            disabled = false;
        }
        this.setState({
            comment_text : text,
            sendDisabled : disabled,
        });
    }

	addComment(e){
		e.preventDefault();
		let self = this;
        // send action invokes ReviewImproved function
        if ( !this.state.comment_text || this.state.comment_text.length === 0 ) {
            return ;
        }

        var data = {
            rebutted : true,
            message : this.state.comment_text,
            source_page : (config.isReview ? 2 : 1)  // TODO: move this to UI property
        };

        this.setState({sendDisabled : true});

        SegmentActions
            .submitComment( this.props.issue.id_segment, this.props.issue.id, data )
			.done(function (  ) {
				self.setState({
					comment_text: ''
				})
            })
            .fail( this.handleFail );
	}

    handleFail() {
        genericErrorAlertMessage() ;
        this.setState({ sendDisabled : false });
    }
    generateHtmlCommentLines(){
		let array = [];
        let comments = this.props.issue.comments,
			comment_date;
        for(let n in comments){
            let comment = comments[n]
			comment_date = moment(comment.create_date).format('lll');

            if(comment.source_page == 1){
				array.push(<p key={comment.id} className="re-comment"><span className="re-translator">Translator </span><span className="re-comment-date"><i>({comment_date}): </i></span>{comment.comment}</p>)
            }else if(comment.source_page == 2){
                array.push(<p key={comment.id} className="re-comment"><span className="re-revisor">Revisor </span><span className="re-comment-date"><i>({comment_date}): </i></span>{comment.comment}</p>)
            }
        }
        if(array.length > 0 ){
            array = array.reverse();
        }
        return array;
	}
    componentDidMount() {
        SegmentStore.addListener(SegmentConstants.ISSUE_DELETED, this.confirmDeletedIssue.bind(this));
    }

    componentWillUnmount() {
        SegmentStore.removeListener(SegmentConstants.ISSUE_DELETED, this.confirmDeletedIssue);
    }
    componentDidUpdate(){

    }
	render() {
		let category_label = this.categoryLabel();
		let formatted_date = moment(this.props.issue.created_at).format('lll');

		let extendedViewButtonClass = (this.state.extendDiffView ? "re-active" : "");
        let commentViewButtonClass = (this.state.commentView ? "re-active" :  '');
        commentViewButtonClass = (this.props.issue.comments.length > 0) ? commentViewButtonClass + " re-message" : commentViewButtonClass;
        let iconCommentClass = ( this.props.issue.comments.length > 0 ) ? "icon-uniE96B icon" : 'icon-uniE96E icon';
        //START comments html section
		let htmlCommentLines = this.generateHtmlCommentLines();
		let renderHtmlCommentLines = '';
		if(htmlCommentLines.length> 0){
			renderHtmlCommentLines = <div className="re-comment-list">
                {htmlCommentLines}
            </div>;
		}

		let commentSection = <div className="comments-view">
				<div className="re-add-comment">
					<form className="ui form" onSubmit={this.addComment.bind(this)}>
						<div className="field">
							<input className="re-comment-input" value={this.state.comment_text} type="text" name="first-name" placeholder="Add a comment + press Enter" onChange={this.handleCommentChange.bind(this)} />
						</div>
					</form>
				</div>
				{renderHtmlCommentLines}
			</div>;
        //END comments html section

		return <div className="issue-item" ref={(node)=>this.el=node}>
			<div className="issue">
				<div className="issue-head">
					<p><b title="Type of issue">{category_label}</b>: <span title="Type of severity">{this.props.issue.severity}</span></p>
				</div>
				<div className="issue-activity-icon">
					<div className="icon-buttons">
						{/*<button className={extendedViewButtonClass} onClick={this.setExtendedDiffView.bind(this)} title="View track changes"><i className="icon-eye icon"/></button>*/}
						<button className={commentViewButtonClass} onClick={this.setCommentView.bind(this)} title="Comments"><i className={iconCommentClass}/></button>
						{this.props.isReview ? (<button onClick={this.deleteIssue.bind(this)} title="Delete issue card"><i className="icon-trash-o icon"/></button>): (null)}
					</div>
				</div>

			</div>
			{this.props.issue.target_text ?
				(<div className="selected-text">
					<p><b>Selected text</b>: <span className="selected">{this.props.issue.target_text}</span></p>
				</div>):(null)}

			{this.state.commentView ? commentSection: null}


			{this.state.extendDiffView ?
				<ReviewVersionDiff
					diffPatch={this.props.issue.diff}
					segment={this.props.segment}
					decodeTextFn={UI.decodeText}
					selectable={false}
				/> : null}

			<div className="issue-date">
				<i>{formatted_date}</i>
			</div>
		</div>
	}
}

export default ReviewExtendedIssue;