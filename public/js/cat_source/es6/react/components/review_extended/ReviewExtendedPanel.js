let ReviewExtendedIssuesContainer = require('./ReviewExtendedIssuesContainer').default;
let ReviewVersionDiffContainer = require('./ReviewVersionsDiffContainer').default;
let ReviewExtendedIssuePanel = require('./ReviewExtendedIssuePanel').default;

class ReviewExtendedPanel extends React.Component {

	constructor(props) {
		super(props);
		this.state = {
			versionNumber: this.props.segment.versions[0].version_number,
			selectionObj: null,
			diffPatch: null,
		};

	}

	textSelected(data) {
		this.setState({
			selectionObj: data
		});
	}

	updateDiffData(diffPatch){
		this.setState({
			diffPatch: diffPatch
		});
	}

	removeSelection() {
		this.setState({
			selectionObj: null
		});
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
			versionNumber: nextProps.segment.versions[0].version_number
		});
	}

	render() {
		let issues = this.getAllIssues();

		return <div className="re-wrapper">
			<ReviewVersionDiffContainer
				textSelectedFn={this.textSelected.bind(this)}
				updateDiffDataFn={this.updateDiffData.bind(this)}
				removeSelection={this.removeSelection.bind(this)}
				segment={this.props.segment}
				selectable={this.props.isReview}
			/>

			{this.props.isReview? (<ReviewExtendedIssuePanel
				sid={this.props.segment.sid}
				selection={this.state.selectionObj}
				segmentVersion={this.state.versionNumber}
				diffPatch={this.state.diffPatch}
				submitIssueCallback={this.removeSelection.bind(this)}
				reviewType={this.props.reviewType}
				segment={this.props.segment}
			/>): (null)}
			<ReviewExtendedIssuesContainer
				reviewType={this.props.reviewType}
				issues={issues}
				isReview={this.props.isReview}
				segment={this.props.segment}
			/>

		</div>;
	}
}

export default ReviewExtendedPanel;
