let ReviewVersionDiff =  require("./ReviewVersionsDiff").default;
class ReviewExtendedIssue extends React.Component {

	constructor(props) {
		super(props);
		this.state = {
			extendDiffView: false
		};

	}

	categoryLabel() {
		let id_category = this.props.issue.id_category;
		config.lqa_flat_categories = config.lqa_flat_categories.replace(/\"\[/g, "[")
			.replace(/\]"/g, "]")
			.replace(/\"\{/g, "{")
			.replace(/\}"/g, "}");
		return _(JSON.parse(config.lqa_flat_categories))
			.select(function (e) {
				return parseInt(e.id) == id_category;
			}).first().label
	}

	deleteIssue(event) {
		event.preventDefault();
		event.stopPropagation();
		SegmentActions.deleteIssue(this.props.issue)
	}
	setExtendedDiffView(event){
		event.preventDefault();
		event.stopPropagation();
		this.setState({extendDiffView : !this.state.extendDiffView})
	}

	render() {
		let category_label = this.categoryLabel();
		let formatted_date = moment(this.props.issue.created_at).format('lll');

		let commentLine = null;
		if (this.props.issue.comment) {
			commentLine = <div className="review-issue-thread-entry">
				<strong>Comment:</strong> {comment}</div>;
		}
		let extendedViewButtonClass = (this.state.extendDiffView ? "re-active" : "");

		return <div className="issue-item">
			<div className="issue">
				<div className="issue-head">
					<p><b>{category_label}</b>: {this.props.issue.severity}</p>
				</div>
				<div className="issue-activity-icon">
					<div className="icon-buttons">
						<button className={extendedViewButtonClass} onClick={this.setExtendedDiffView.bind(this)}><i className="icon-eye icon"/></button>
						<button><i className="icon-uniE96E icon"/></button>
						{this.props.isReview ? (<button onClick={this.deleteIssue.bind(this)}><i className="icon-trash-o icon"/></button>): (null)}
					</div>
				</div>
				{this.props.issue.target_text ?
					(<div className="selected-text">
						<p><b>Selected text</b>: <span className="selected">{this.props.issue.target_text}</span></p>
					</div>):(null)}

			</div>
			{this.state.extendDiffView ?
				<ReviewVersionDiff
					diffPatch={this.props.issue.diff}
					segment={this.props.segment}
					decodeTextFn={UI.decodeText}
					selectable={false}
				/> : null}

			<div className="issue-date">
				<i>({formatted_date})</i>
			</div>
		</div>
		/*return <div className="review-issue-detail"
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

			{/!*<ReviewTranslationIssueCommentsContainer*!/}
				{/!*sid={this.props.segment.sid}*!/}
				{/!*issueId={this.props.issueId} />*!/}

			{deleteIssue}
		</div>;*/
	}
}

export default ReviewExtendedIssue;