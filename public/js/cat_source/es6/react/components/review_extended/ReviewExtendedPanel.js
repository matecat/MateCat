import ReviewVersionsDiff from "./ReviewVersionsDiff";

let ReviewExtendedIssuesContainer = require('./ReviewExtendedIssuesContainer').default;
let ReviewVersionDiffContainer = require('./ReviewVersionsDiffContainer').default;
let ReviewVersionDiff = require('./ReviewVersionsDiff').default;
let ReviewExtendedIssuePanel = require('./ReviewExtendedIssuePanel').default;
let SegmentConstants = require('../../constants/SegmentConstants');
let SegmentStore = require('../../stores/SegmentStore');

class ReviewExtendedPanel extends React.Component {

	constructor(props) {
		super(props);
		this.state = {
			addIssue: false,
			versionNumber: this.props.segment.versions[0].version_number
		};

	}

	textSelected(data, diffPatch) {
		this.setState({
			addIssue: true,
			selectionObj: data,
			diffPatch: diffPatch
		});
	}

	removeSelection() {
		this.setState({
			addIssue: false,
			selectionObj: null,
			diffPatch: null
		});
	}

	issueMouseEnter(issue, event, reactid) {
		SegmentActions.showSelection(this.props.sid, issue);
	}

	issueMouseLeave() {
		this.removeSelection();
	}

	getAllIssues() {
		let issues = [];
		this.props.segment.versions.forEach(function (version) {
			if (!_.isEmpty(version.issues)) {
				issues = issues.concat(version.issues);
			}
		});
		return issues;
	}

	componentWillReceiveProps(nextProps) {
		this.setState({
			addIssue: false,
			versionNumber: nextProps.segment.versions[0].version_number
		});
	}

	render() {
		let issues = this.getAllIssues();

		return <div className="re-wrapper">
			<ReviewVersionDiffContainer
				textSelectedFn={this.textSelected.bind(this)}
				removeSelection={this.removeSelection.bind(this)}
				segment={this.props.segment}
				selectable={this.props.isReview}
			/>
			<ReviewExtendedIssuePanel
				sid={this.props.segment.sid}
				selection={this.state.selectionObj}
				segmentVersion={this.state.versionNumber}
				diffPatch={this.state.diffPatch}
				submitIssueCallback={this.removeSelection.bind(this)}
				reviewType={this.props.reviewType}
				segment={this.props.segment}
			/>

			<ReviewExtendedIssuesContainer
				issueMouseEnter={this.issueMouseEnter.bind(this)}
				issueMouseLeave={this.issueMouseLeave.bind(this)}
				reviewType={this.props.reviewType}
				issues={issues}
				sid={this.props.segment.sid}
			/>

		</div>;
	}
}

export default ReviewExtendedPanel;
