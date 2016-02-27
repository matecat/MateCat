export default React.createClass({
    getInitialState : function() {

        var issues = MateCat.db.segment_translation_issues.findObjects({
            'id_segment' : '' + this.props.sid, 
            'translation_version' : '' + this.props.versionNumber  
        });

        return {
            issues : issues 
        }
    },

    componentWillReceiveProps : function( nextProps ) {

        var issues = MateCat.db.segment_translation_issues.findObjects({
            'id_segment' : '' + nextProps.sid, 
            'translation_version' : '' + nextProps.versionNumber  
        });

        this.setState({ issues: issues }); 
    },


    render : function() {

        var cs = classnames({
            'review-issues-container' : true,
        }); 
        var issues; 

        console.debug('rendering list of issues'); 
        console.debug( this.state.issues ); 


        if (this.state.issues.length > 0 ) {
            var sorted_issues = this.state.issues.sort(function(a,b) {
                return parseInt( a.id ) < parseInt( b.id ); 
            });

            issues = sorted_issues.map(function( item, index ) {
                index = index + 1 ; 
                return <ReviewTranslationIssue sid={this.props.sid} index={index} issueId={item.id} key={item.id} />
            }.bind(this) );

        }
        else {
            issues = <div>No issues on this version</div>;
        }

        return <div className={cs} > 
            {issues} 
        </div>;
        
    }
});
