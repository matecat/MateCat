export default React.createClass({
    getInitialState : function() {
        return {
            collapsed : this.props.isCurrent == false
        }; 
    },

    componentWillReceiveProps : function(nextProps) {
        this.setState({ collapsed : !nextProps.isCurrent, trackChanges : false });
    },

    issueMouseEnter : function( issue, event, reactid ) {
        var node = $('.muted-text-box', ReactDOM.findDOMNode( this ) ) ; 
        ReviewImproved.highlightIssue( issue, node ); 
    }, 

    issueMouseLeave : function() {
        var selection = document.getSelection();
        selection.removeAllRanges();
    },

    translationMarkup : function() {
        return { __html : UI.decodePlaceholdersToText( this.props.translation ) };
    },

    toggleTrackChanges : function(e) {
        e.preventDefault(); 
        e.stopPropagation(); 
        this.setState({trackChanges : !this.state.trackChanges });
    },

    getMarkupForTrackChanges : function() {
        return { __html :  this.props.trackChangesMarkup  };
    },
    
    render : function() {
        var cs = classnames({
            collapsed : this.state.collapsed,
            'review-translation-version' : true 
        });

        if ( this.props.isCurrent ) {
            var versionLabel = sprintf('Version %s (current)', this.props.versionNumber );
        } else {
            var versionLabel = sprintf('Version %s', this.props.versionNumber );
        }


        var styleForVersionText = { 
            display: this.state.trackChanges ? 'none' : 'block' 
        }; 
        var styleForTrackChanges = {
            display: this.state.trackChanges ? 'block' : 'none' 
        }; 

        var labelForToggle = this.state.trackChanges ? 'Issues' : 'Track changes' ;

        if ( this.props.trackChangesMarkup ) {
            var trackChangesLink = <a href="#" onClick={this.toggleTrackChanges}
                    className="review-track-changes-toggle">{labelForToggle}</a>;
        }

        return <div className="review-version-wrapper">
            <div className={cs} >
            <div className="review-version-header">
                <h3>{versionLabel}</h3>
            </div>

            <div className="collapsable">

                <div ref="highlightArea" className="muted-text-box issueHighlightArea" style={styleForVersionText}
                dangerouslySetInnerHTML={this.translationMarkup()} />

                <div style={styleForTrackChanges}
                className="muted-text-box review-track-changes-box"
                dangerouslySetInnerHTML={this.getMarkupForTrackChanges()} />

                {trackChangesLink}

                <ReviewIssuesContainer 
                    issueMouseEnter={this.issueMouseEnter} 
                    issueMouseLeave={this.issueMouseLeave}
                    sid={this.props.sid} 
                    versionNumber={this.props.versionNumber} />
                </div>
            </div>
        </div>
            ;

    }
}); 
