let ReviewIssuesContainer = require('./ReviewIssuesContainer').default;
let ReviewVersionDiff = require('./ReviewVersionsDiff').default;
let ReviewIssueSelectionPanel = require('./ReviewIssueSelectionPanel').default;
class ReviewTranslationDiffVersion extends React.Component {


    constructor(props) {
        super(props);
        this.state = {
            selectionObj: null,
            diffPatch: null
        };

    }

    getMarkupForTrackChanges() {
        return { __html : trackChangesHTMLFromDiffArray(this.props.diff) };
    }

    translationMarkup () {
        return { __html : UI.decodePlaceholdersToText( this.props.translation ) };
    }

    textSelected(data, diffPatch) {
        this.setState({
            selectionObj: data,
            diffPatch: diffPatch
        });
    }

    removeSelection() {
        this.setState({
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

    getTrackChangesForCurrentVersion () {
        if ( this.state.segment.version_number != '0' ) {
            // no track changes possibile for first version
            let previous = this.findPreviousVersion( this.state.segment.version_number );
            return trackChangesHTML(
                UI.clenaupTextFromPleaceholders(previous.translation),
                UI.clenaupTextFromPleaceholders(
                    window.cleanupSplitMarker( this.state.segment.translation )
                ));
        }
    }

    render () {

        let versionLabel;

        if ( this.props.isCurrent ) {
            versionLabel = sprintf('Version %s (current)', this.props.versionNumber );
        } else {
            versionLabel = sprintf('Version %s', this.props.versionNumber );
        }

        return <div className="review-version-wrapper">
            <div className="review-translation-version" >
                <div className="review-version-header">
                    <h3>{versionLabel}</h3>
                </div>

                <div className="collapsable">
                    <h4> Translation </h4>
                    <div ref={(elem)=>this.highlightArea=elem} className="ui ignore message muted issueHighlightArea"
                         dangerouslySetInnerHTML={this.translationMarkup()} />
                    <h4> Changes from previous version </h4>
                    <ReviewVersionDiff
                        sid={this.props.sid}
                        textSelectedFn={this.textSelected.bind(this)}
                        removeSelection={this.removeSelection.bind(this)}
                        decodeTextFn={UI.decodeText}
                        diff={this.props.diff}
                        versionNumber={this.props.versionNumber}
                        translation={this.props.translation}
                        previousVersion={this.props.previousVersion}
                        selectable={this.props.isReview}
                    />

                    {this.state.selectionObj  ? (
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
                            issues={this.props.issues}
                            sid={this.props.sid}
                            versionNumber={this.props.versionNumber} />
                    )}


                </div>
            </div>
        </div>;

    }
}

export default ReviewTranslationDiffVersion;
