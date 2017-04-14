export default React.createClass({

    getInitialState: function() {
        return this.getStateFromSid( this.props.sid );
    },

    componentWillReceiveProps : function( nextProps ) {
        this.setState( this.getStateFromSid( nextProps.sid ) );
    }, 

    getStateFromSid : function(sid) {
        var segment = MateCat.db.segments.by('sid', sid);
        var original_target = this.getOriginalTarget( segment );

        return {
            segment         : segment,
            original_target : original_target,
            versions        : this.getVersions( sid )
        }

    },
    getVersions : function( sid ) {
        var versions = MateCat.db.segment_versions.findObjects({
            id_segment : '' + sid
        });

        var sorted = _.sortBy(versions, function(version) {
            return parseInt(version.version_number);
        }).reverse();
        return sorted;
    },

    getOriginalTarget : function( segment ) {
        var version_number = segment.version_number ;
        if ( version_number == "0" ) {
            return segment.translation ;
        }
        else {
            // query versions to find original target
            var root_version = MateCat.db.segment_versions.findObject({
                id_segment : '' + segment.sid,
                version_number : "0"
            });

            if (! root_version ) {
                throw 'Unable to find root version';
            }
            return root_version.translation ;
        }
    },

    originalTarget : function() {
        return { __html : UI.decodePlaceholdersToText( this.state.original_target ) };
    },

    getTrackChangesForCurrentVersion : function() {
        if ( this.state.segment.version_number != '0' ) {
            // no track changes possibile for first version
            var previous = this.findPreviousVersion( this.state.segment.version_number );
            return trackChangesHTML(
                UI.clenaupTextFromPleaceholders(previous.translation),
                UI.clenaupTextFromPleaceholders(
                    window.cleanupSplitMarker( this.state.segment.translation )
                ));
        }
    },

    findPreviousVersion : function( version_number ) {
        return this.state.versions.filter(function(item) {
            return parseInt( item.version_number ) == parseInt( version_number ) -1 ;
        }.bind(this) )[0];
    },

    getTrackChangesForOldVersion : function(version) {
        if ( version.version_number != "0" ) {
            var previous = this.findPreviousVersion( version.version_number );
            return trackChangesHTML(
                UI.clenaupTextFromPleaceholders( previous.translation ),
                UI.clenaupTextFromPleaceholders( version.translation )
            );
        }
    },

    render: function() {

        var previousVersions = this.state.versions.map( function(v) {
            var key = 'version-' + v.id + '-' + this.props.sid ;

            return (
                <ReviewTranslationVersion 
                trackChangesMarkup={this.getTrackChangesForOldVersion( v )}
                sid={this.state.segment.sid}
                key={key}
                versionNumber={v.version_number}  
                isCurrent={false} 
                translation={v.translation} 
                />
            ); 
        }.bind(this) ); 

        var key = 'version-0-' + this.props.sid ;
        var currentVersion = <ReviewTranslationVersion 
            trackChangesMarkup={this.getTrackChangesForCurrentVersion()}
            sid={this.state.segment.sid}
            key={'version-0'}
            versionNumber={this.state.segment.version_number}
            isCurrent={true} 
            translation={window.cleanupSplitMarker( this.state.segment.translation ) } />

        var fullList = [currentVersion].concat(previousVersions); 

        return <div className="review-issues-overview-panel"> 

            <div className="review-original-target-wrapper sidebar-block">
                <h3>Original target</h3>
                <div className="muted-text-box" dangerouslySetInnerHTML={this.originalTarget()} />
            </div>

            {fullList}
        </div>
        ;
    }
});
