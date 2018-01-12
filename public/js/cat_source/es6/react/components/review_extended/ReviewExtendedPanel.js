let ReviewExtendedIssuesContainer = require('./ReviewExtendedIssuesContainer').default;
let ReviewVersionDiffContainer = require('./ReviewVersionsDiffContainer').default;
let ReviewExtendedIssuePanel = require('./ReviewExtendedIssuePanel').default;
let SegmentConstants = require('../../constants/SegmentConstants');

class ReviewExtendedPanel extends React.Component {

	constructor(props) {
		super(props);
		this.state = {
			versionNumber: this.props.segment.versions[0].version_number,
			selectionObj: null,
			diffPatch: null,
			isDiffChanged: false,
			newtranslation: this.props.segment.translation,
			issueInCreation: false,
            showAddIssueMessage: false
		};
	}

	textSelected(data) {
		this.setState({
			selectionObj: data
		});
	}
	updateDiffData(diffPatch, newTranslation){
		//detect if diff is changed.
		let isDiffChanged = false;
		if(this.props.segment.translation !== newTranslation){
			isDiffChanged = true;
		}
		this.setState({
			diffPatch: diffPatch,
			newtranslation: newTranslation,
			isDiffChanged: isDiffChanged
		});
	}

	setDiffStatus(status){
		this.setState({
			diffStatus: status
		});
	}
	removeSelection() {
        this.setCreationIssueLoader(false);
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

	setCreationIssueLoader(inCreation){
		this.setState({
			issueInCreation: inCreation
		})
	}

    showIssuesMessage() {
        this.setState({
            showAddIssueMessage: true
        });
    }

	componentWillReceiveProps(nextProps) {
		this.setState({
			versionNumber: nextProps.segment.versions[0].version_number,
            showAddIssueMessage: false
		});
	}

    componentDidMount() {
		this.props.setParentLoader(false);
        SegmentStore.addListener(SegmentConstants.SHOW_ISSUE_MESSAGE, this.showIssuesMessage.bind(this));
    }

    componentWillUnmount() {
        SegmentStore.removeListener(SegmentConstants.SHOW_ISSUE_MESSAGE, this.showIssuesMessage);
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
			<ReviewExtendedIssuesContainer
				reviewType={this.props.reviewType}
				loader={this.state.issueInCreation}
				issues={issues}
				isReview={this.props.isReview}
				segment={this.props.segment}
			/>
            {this.state.showAddIssueMessage ? (
              <div className="re-warning-not-added-issue">
				  <p>In order to Approve the segment you need to add an Issue from the Error list</p>
              </div>
            ) : (null)}

			{this.props.isReview? (<ReviewExtendedIssuePanel
				sid={this.props.segment.sid}
				selection={this.state.selectionObj}
				segmentVersion={this.state.versionNumber}
				diffPatch={this.state.diffPatch}
				isDiffChanged={this.state.isDiffChanged}
				submitIssueCallback={this.removeSelection.bind(this)}
				reviewType={this.props.reviewType}
				newtranslation={this.state.newtranslation}
				segment={this.props.segment}
                setCreationIssueLoader={this.setCreationIssueLoader.bind(this)}
			/>): (null)}

		</div>;
	}
}

export default ReviewExtendedPanel;
