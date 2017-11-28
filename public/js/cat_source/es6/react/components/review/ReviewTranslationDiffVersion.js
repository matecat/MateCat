let ReviewIssuesContainer = require('./ReviewIssuesContainer').default;
let ReviewVersionDiff = require('./ReviewVersionsDiff').default;
let ReviewIssueSelectionPanel = require('./ReviewIssueSelectionPanel').default;
let SegmentConstants = require('../../constants/SegmentConstants');
let SegmentStore = require('../../stores/SegmentStore');
class ReviewTranslationDiffVersion extends React.Component {


    constructor(props) {
        super(props);
        this.originalTranslation = this.props.segment.translation;
        this.state = {
            translation: this.props.segment.translation,
            addIssue: false,
            selectionObj: null,
            diffPatch: null
        };

    }

    trackChanges(sid, editareaText) {
        if (this.props.segment.sid === sid) {
            this.setState({
                translation: editareaText,
            });
        }
    }

    openAddIssue() {
        this.setState({
            addIssue: true,
            selectionObj: null,
            diffPatch: null
        });
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

    issueMouseEnter ( issue, event, reactid ) {
        SegmentActions.showSelection(this.props.sid, issue);
    }

    issueMouseLeave () {
        this.removeSelection();
    }

    getAllIssues () {
        let issues = [];
        this.props.versions.forEach(function (version) {
            if ( !_.isEmpty(version.issues) ) {
                issues = issues.concat(version.issues);
            }
        });
        return issues;
    }

    componentDidMount() {
        SegmentStore.addListener(SegmentConstants.UPDATE_TRANSLATION, this.trackChanges.bind(this));
    }

    componentWillUnmount() {
        SegmentStore.removeListener(SegmentConstants.UPDATE_TRANSLATION, this.trackChanges);
    }

    render () {
        let issues = this.getAllIssues();
        return <div className="review-version-wrapper">
            <div className="review-translation-version" >
                <div className="review-version-header">
                    <h3>Live changes</h3>
                </div>
                <div className="collapsable">

                    <ReviewVersionDiff
                        textSelectedFn={this.textSelected.bind(this)}
                        removeSelection={this.removeSelection.bind(this)}
                        previousVersion={this.originalTranslation}
                        translation={this.state.translation}
                        segment={this.props.segment}
                        decodeTextFn={UI.decodeText}
                        selectable={true}
                    />

                    <div className="review-add-issues-button-container">
                        <a className="ui primary button small"
                           onClick={this.openAddIssue.bind(this)}
                        >Add Issue</a>
                    </div>

                    {this.state.addIssue  ? (
                        <div className="error-type">
                            <ReviewIssueSelectionPanel
                                sid={this.props.sid}
                                selection={this.state.selectionObj}
                                segmentVersion={this.props.versionNumber}
                                diffPatch={this.state.diffPatch}
                                closeSelectionPanel={this.removeSelection.bind(this)}
                                submitIssueCallback={this.removeSelection.bind(this)}
                            />
                        </div>
                    ) : (
                        <ReviewIssuesContainer
                            issueMouseEnter={this.issueMouseEnter.bind(this)}
                            issueMouseLeave={this.issueMouseLeave.bind(this)}
                            reviewType={this.props.reviewType}
                            issues={issues}
                            sid={this.props.segment.sid}
                        />
                    )}


                </div>
            </div>
        </div>;

    }
}

export default ReviewTranslationDiffVersion;
