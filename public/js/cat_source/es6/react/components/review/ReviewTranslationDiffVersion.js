let ReviewIssuesContainer = require('./ReviewIssuesContainer').default;
let ReviewVersionDiff = require('./ReviewVersionsDiff').default;

class ReviewTranslationDiffVersion extends React.Component {


    constructor(props) {
        super(props);
        this.state = {

        };

    }

    getMarkupForTrackChanges() {
        return { __html : trackChangesHTMLFromDiffArray(this.props.diff) };
    }

    translationMarkup () {
        return { __html : UI.decodePlaceholdersToText( this.props.translation ) };
    }

    textSelected(data) {
        this.setState({
            selectedText: data.selected_string,
            selectionObj: data
        });
    }

    issueMouseEnter ( issue, event, reactid ) {
        SegmentActions.showSelection(this.props.sid, issue);
    }

    issueMouseLeave () {

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
                    <h4> Target </h4>
                    <div ref={(elem)=>this.highlightArea=elem} className="muted-text-box issueHighlightArea"
                         dangerouslySetInnerHTML={this.translationMarkup()} />
                    <h4> Diff </h4>
                    <ReviewVersionDiff
                        // translation={this.props.translation}
                        sid={this.props.sid}
                        textSelectedFn={this.textSelected.bind(this)}
                        decodeTextFn={UI.decodeText}
                        diff={this.props.diff}
                        versionNumber={this.props.versionNumber}
                    />

                    <ReviewIssuesContainer
                        issueMouseEnter={this.issueMouseEnter.bind(this)}
                        issueMouseLeave={this.issueMouseLeave.bind(this)}
                        reviewType={this.props.reviewType}
                        issues={this.props.issues}
                        sid={this.props.sid}
                        versionNumber={this.props.versionNumber} />
                </div>
            </div>
        </div>;

    }
}

export default ReviewTranslationDiffVersion;
