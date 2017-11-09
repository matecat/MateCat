let ReviewIssuesContainer = require('./ReviewIssuesContainer').default;

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

    issueMouseEnter() {

    }

    issueMouseLeave() {

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

                    <div ref={(elem)=>this.highlightArea=elem} className="muted-text-box issueHighlightArea"
                         dangerouslySetInnerHTML={this.translationMarkup()} />

                    <div className="muted-text-box review-track-changes-box"
                         dangerouslySetInnerHTML={this.getMarkupForTrackChanges()} />


                    <ReviewIssuesContainer
                        issueMouseEnter={this.issueMouseEnter.bind(this)}
                        issueMouseLeave={this.issueMouseLeave.bind(this)}
                        reviewType={this.props.reviewType}
                        issues={this.props.issues}
                        sid={this.props.sid}
                        versionNumber={this.props.versionNumber} />
                </div>
            </div>
        </div>
            ;

    }
}

export default ReviewTranslationDiffVersion;
