
export default React.createClass({
    getInitialState : function() {
        var version = MateCat.db.segment_versions.by('id', this.props.versionId); 
        return {
            version : version, 
            collapsed : true
        }; 
    },
    render : function() {
        var cs = classnames({
            collapsed : this.state.collapsed,
            'review-translation-version' : true 
        });

        return <div className={cs} >
            <strong>Version {this.state.version.version_number}</strong>
            <div className="muted-text-box">
                {this.state.version.translation}
            </div>

            <ReviewIssuesContainer sid={this.state.version.id_segment} 
                versionNumber={this.state.version.version_number} />
        </div>;
        
    }
}); 
