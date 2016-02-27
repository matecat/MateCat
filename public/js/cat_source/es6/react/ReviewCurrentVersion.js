export default React.createClass({
    getInitialState : function() {
        var segment =  MateCat.db.segments.by('sid', this.props.sid); 

        return {
            segment : segment, 
        }
    },

    componentWillReceiveProps : function( nextProps ) {
        var sid = nextProps.sid; 
        var segment =  MateCat.db.segments.by('sid', nextProps.sid);
        this.setState({ segment : segment });
    },

    render : function() {

        var cs = classnames({
            'review-current-version-container' : true,
        }); 


        return <div className={cs} > 
            <strong>Current version</strong>

            <div className="muted-text-box">
                {this.state.segment.translation}
            </div>

            <ReviewIssuesContainer sid={this.props.sid} 
                versionNumber={this.state.segment.version_number} />

        </div>;
        
    }
});
