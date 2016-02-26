export default React.createClass({

    getInitialState: function() {
        var segment = MateCat.db.segments.by('sid', this.props.sid);
        var original_target = this.getOriginalTarget( segment );
        var versions =  this.getVersions() ;

        return {
            segment         : segment,
            original_target : original_target,
            versions        : versions
        }
    },

    getVersions : function() {
        return MateCat.db.segment_versions.findObjects({
            id_segment : '' + this.props.sid
        });
    },

    getOriginalTarget : function( segment ) {
        var version_number = segment.version_number ;
        if ( version_number == "0" ) {
            return segment.translation ;
        }
        else {
            // query versions to find original target
            var root_version = MateCat.db.segment_versions.findObject({
                id_segment : '' + this.props.sid,
                version_number : "0"
            });

            if (! root_version ) {
                throw 'Unable to find root version';
            }
            return root_version.translation ;
        }
    },
    componentDidMount: function() {
    },

    componentWillUnmount: function() {
    },

    render: function() {

        var versionsComponents = this.state.versions.map(function() {
            console.log(this);
        });

        return <div className="review-issues-overview-panel"> 
            <strong>Original target</strong>

            <div className="muted-text-box">
            {this.state.original_target}
            </div>
        </div>
        ;
    }
});
