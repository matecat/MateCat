export default React.createClass({
    getInitialState : function() {
        var segment =  MateCat.db.segments.by('sid', this.props.sid); 

        return {
            segment : segment, 
            trackChanges : false
        }
    },

    issueMouseEnter : function( issue, event, reactid ) {
        var node = $('.muted-text-box', ReactDOM.findDOMNode( this ) ) ; 
        ReviewImproved.highlightIssue( issue, node ); 
    }, 
    issueMouseLeave : function() {
        var selection = document.getSelection();
        selection.removeAllRanges();
    },

    componentWillReceiveProps : function( nextProps ) {
        var sid = nextProps.sid; 
        var segment =  MateCat.db.segments.by('sid', nextProps.sid);
        this.setState({ segment : segment });
    },

    toggleTrackChanges : function(e) {
        e.preventDefault(); 
        e.stopPropagation(); 
        this.setState({trackChanges : !this.state.trackChanges });
    },

    currentTranslation : function () {
        return { __html : UI.decodePlaceholdersToText( this.state.segment.translation ) };
    },

    render : function() {

        var cs = classnames({
            'review-current-version-container' : true,
        }); 

        var styleForVersionText = { 
            display: this.state.trackChanges ? 'none' : 'block' 
        }; 
        var styleForTrackChanges = {
            display: this.state.trackChanges ? 'block' : 'none' 
        }; 

        var labelForToggle = this.state.trackChanges ? 'Issues' : 'Track changes' ; 

        return <div className={cs} > 
            <strong>Current version</strong>

            <div className="muted-text-box" style={styleForVersionText}
                dangerouslySetInnerHTML={this.currentTranslation()} />

            <div style={styleForTrackChanges}
                className="muted-text-box review-track-changes-box">Track changes go here</div> 

            <a href="#" onClick={this.toggleTrackChanges} 
                className="review-track-changes-toggle">{labelForToggle}</a>

            <ReviewIssuesContainer sid={this.props.sid} 
                issueMouseEnter={this.issueMouseEnter} 
                issueMouseLeave={this.issueMouseLeave}
                versionNumber={this.state.segment.version_number} />

        </div>;
        
    }
});
