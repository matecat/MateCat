
export default React.createClass({
    getInitialState : function() {
        var version = MateCat.db.segment_versions.by('id', this.props.versionId); 
        return {
            version : version, 
            collapsed : true
        }; 
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
        return { __html : UI.decodePlaceholdersToText( this.state.version.translation ) };
    },
    render : function() {
        var cs = classnames({
            collapsed : this.state.collapsed,
            'review-translation-version' : true 
        });


        return <div className="review-version-wrapper">
            <div className={cs} >
            <strong>Version {this.state.version.version_number}</strong>
            <div className="muted-text-box"
            dangerouslySetInnerHTML={this.translationMarkup()} />

            <ReviewIssuesContainer
                issueMouseEnter={this.issueMouseEnter}
                issueMouseLeave={this.issueMouseLeave}
                sid={this.state.version.id_segment}
                versionNumber={this.state.version.version_number} />
            </div>
        </div>
            ;

    }
}); 
