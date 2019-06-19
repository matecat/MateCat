let ReviewExtendedIssuesContainer = require('./ReviewExtendedIssuesContainer').default;
let ReviewExtendedIssuePanel = require('./ReviewExtendedIssuePanel').default;
let SegmentConstants = require('../../constants/SegmentConstants');

class ReviewExtendedPanel extends React.Component {

	constructor(props) {
		super(props);
		this.addIssueToApproveMessageType = 1;
		this.addIssueToSelectedTextMessageType = 2;
		this.state = {
			versionNumber: this.props.segment.versions[0].version_number,
			diffPatch: null,
			newtranslation: this.props.segment.translation,
			issueInCreation: false,
            showAddIssueMessage: false,
            showAddIssueToSelectedTextMessage: false
		};
	}

	removeSelection() {
        this.setCreationIssueLoader(false);
		this.props.removeSelection();
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

    showIssuesMessage(sid, type) {
		switch ( type ) {
			case this.addIssueToApproveMessageType:
                if (this.props.issueRequiredOnSegmentChange) {
                    this.setState({
                        showAddIssueMessage: true
                    });
                    setTimeout(()=>{
                        SegmentActions.openIssuesPanel({ sid: sid }, false);
                    });
                }
                break;
			case this.addIssueToSelectedTextMessageType:
                this.setState({
                    showAddIssueToSelectedTextMessage: true
                });
				break;
        }
    }

    closePanel() {
	    UI.closeIssuesPanel();
    }

	componentWillReceiveProps(nextProps) {
		this.setState({
			versionNumber: nextProps.segment.versions[0].version_number,
            showAddIssueMessage: false,
            showAddIssueToSelectedTextMessage: false
		});
	}

    componentDidMount() {
		// this.props.setParentLoader(false);
        SegmentStore.addListener(SegmentConstants.SHOW_ISSUE_MESSAGE, this.showIssuesMessage.bind(this));

    }

    componentWillUnmount() {
        SegmentStore.removeListener(SegmentConstants.SHOW_ISSUE_MESSAGE, this.showIssuesMessage);
    }

	render() {
		let issues = this.getAllIssues();
		let thereAreIssuesClass = (issues.length > 0 ) ? "thereAreIssues" : "";
        return <div className={"re-wrapper shadow-1 " + thereAreIssuesClass}>
			<div className="re-open-view re-issues"/>
			<a className="re-close-balloon re-close-err shadow-1" onClick={this.closePanel.bind(this)}><i className="icon-cancel3 icon" /></a>
			<ReviewExtendedIssuesContainer
				reviewType={this.props.reviewType}
				loader={this.state.issueInCreation}
				issues={issues}
				isReview={this.props.isReview}
				segment={this.props.segment}
			/>
            {this.state.showAddIssueMessage ? (
				<div className="re-warning-not-added-issue">
					<p>In order to Approve the segment you need to add an Issue from the Error list.</p>
				</div>
            ) : (null)}

            {this.state.showAddIssueToSelectedTextMessage ? (
				<div className="re-warning-selected-text-issue">
					<p>Select an issue from the list below to associate it to the selected text.</p>
				</div>
            ) : (null)}

            { (this.props.isReview && !(this.props.segment.ice_locked == 1 &&  !this.props.segment.unlocked)) ? (<ReviewExtendedIssuePanel
				sid={this.props.segment.sid}
				selection={this.props.selectionObj}
				segmentVersion={this.state.versionNumber}
				submitIssueCallback={this.removeSelection.bind(this)}
				reviewType={this.props.reviewType}
				newtranslation={this.state.newtranslation}
				segment={this.props.segment}
				setCreationIssueLoader={this.setCreationIssueLoader.bind(this)}
			/>): (null)}
		</div>;
	}
}
ReviewExtendedPanel.defaultProps = {
    issueRequiredOnSegmentChange: false
};


export default ReviewExtendedPanel;
