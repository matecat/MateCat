export default React.createClass({
    getIssuesFromDb : function( sid, versionNumber ) {
        return db.segment_translation_issues.findObjects({
            'id_segment' : '' + sid,
            'translation_version' : '' + versionNumber
        });
    },

    getInitialState : function() {
        return {
            issues : this.getIssuesFromDb(this.props.sid, 
                                          this.props.versionNumber)
        }
    },

    componentWillReceiveProps : function( nextProps ) {
        var issues = this.getIssuesFromDb( nextProps.sid, nextProps.versionNumber) ;
        this.setState({ issues: issues }); 
    },

    componentDidMount : function() {
        MateCat.db.addListener('segment_translation_issues', 
                               ['delete'], this.issueDeleted ); 
        

    },

    componentWillUnmount : function() {
        MateCat.db.removeListener('segment_translation_issues', 
                               ['delete'], this.issueDeleted );
    },

    issueDeleted : function (issue) {
        var issues = this.getIssuesFromDb( this.props.sid, this.props.versionNumber) ;
        this.setState({ issues: issues });
    },

    render : function() {

        var cs = classnames({
            'review-issues-container' : true,
        }); 
        var issues; 

        if (this.state.issues.length > 0 ) {
            var sorted_issues = this.state.issues.sort(function(a,b) {
                return parseInt( a.id ) < parseInt( b.id ); 
            });

            issues = sorted_issues.map(function( item, index ) {
                index = index + 1 ; 
                return <ReviewTranslationIssue 
                    issueMouseEnter={this.props.issueMouseEnter} 
                    issueMouseLeave={this.props.issueMouseLeave}
                    sid={this.props.sid} index={index} 
                    issueId={item.id} key={item.id} />

            }.bind(this) );

        }
        else {
            issues = <div class="review-no-issues">No issues on this version</div>;
        }

        return <div className={cs} > 
            {issues} 
        </div>;
        
    }
});
