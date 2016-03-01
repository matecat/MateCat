export default React.createClass({
    getInitialState : function() {
        var segment =  MateCat.db.segments.by('sid', this.props.sid); 

        return {
            segment : segment, 
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

    currentTranslation : function () {
        return { __html : UI.decodePlaceholdersToText( this.state.segment.translation ) };
    },

    render : function() {

        var cs = classnames({
            'review-current-version-container' : true,
        }); 


        return <div className={cs} > 
            <strong>Current version</strong>

            <div className="muted-text-box"
                dangerouslySetInnerHTML={this.currentTranslation()} />

            <ReviewIssuesContainer sid={this.props.sid} 
                issueMouseEnter={this.issueMouseEnter}
                issueMouseLeave={this.issueMouseLeave}
                versionNumber={this.state.segment.version_number} />

        </div>;
        
    }
});
