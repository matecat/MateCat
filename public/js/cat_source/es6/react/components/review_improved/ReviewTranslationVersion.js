let ReviewIssuesContainer = require('./ReviewIssuesContainer').default;
class ReviewTranslationVersion extends React.Component {


    constructor(props) {
        super(props);
        this.state = {
            collapsed : this.props.isCurrent == false
        };

    }

    componentWillReceiveProps (nextProps) {
        this.setState({ collapsed : !nextProps.isCurrent, trackChanges : false });
    }

    issueMouseEnter ( issue, event, reactid ) {
        let node = $( this.highlightArea ) ;
        ReviewImproved.highlightIssue( issue, node );
    }

    issueMouseLeave () {
        let selection = document.getSelection();
        selection.removeAllRanges();
    }

    translationMarkup () {
        return { __html : UI.decodePlaceholdersToText( this.props.translation ) };
    }

    toggleTrackChanges (e) {
        e.preventDefault();
        e.stopPropagation();
        this.setState({trackChanges : !this.state.trackChanges });
    }

    getMarkupForTrackChanges () {
        return { __html :  this.props.trackChangesMarkup  };
    }

    render () {
        let cs = classnames({
            collapsed : this.state.collapsed,
            'review-translation-version' : true
        });
        let versionLabel;

        if ( this.props.isCurrent ) {
            versionLabel = sprintf('Version %s (current)', this.props.versionNumber );
        } else {
            versionLabel = sprintf('Version %s', this.props.versionNumber );
        }


        let styleForVersionText = {
            display: this.state.trackChanges ? 'none' : 'block'
        };
        let styleForTrackChanges = {
            display: this.state.trackChanges ? 'block' : 'none'
        };

        let labelForToggle = this.state.trackChanges ? 'Issues' : 'Track changes' ;
        let trackChangesLink
        if ( this.props.trackChangesMarkup ) {
            trackChangesLink = <a href="#" onClick={this.toggleTrackChanges.bind(this)}
                                  className="review-track-changes-toggle">{labelForToggle}</a>;
        }

        return <div className="review-version-wrapper">
            <div className={cs} >
                <div className="review-version-header">
                    <h3>{versionLabel}</h3>
                    {trackChangesLink}
                </div>

                <div className="collapsable">

                    <div ref={(elem)=>this.highlightArea=elem} className="ui ignore message muted-text-box issueHighlightArea" style={styleForVersionText}
                         dangerouslySetInnerHTML={this.translationMarkup()} />

                    <div style={styleForTrackChanges}
                         className="ui ignore message muted-text-box review-track-changes-box"
                         dangerouslySetInnerHTML={this.getMarkupForTrackChanges()} />



                    <ReviewIssuesContainer
                        issueMouseEnter={this.issueMouseEnter.bind(this)}
                        issueMouseLeave={this.issueMouseLeave.bind(this)}
                        reviewType={this.props.reviewType}
                        sid={this.props.sid}
                        versionNumber={this.props.versionNumber} />
                </div>
            </div>
        </div>
            ;

    }
}

export default ReviewTranslationVersion;