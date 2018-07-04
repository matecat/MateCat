if ( true ) // < TODO: investigate: chrome raises weird excetpion if this is missing .
(function($, root, undefined) {
    var _db = new root.loki('matecat.json');
    var _ = root._;

    root.MateCat = root.MateCat || {};
    db = {};

    // AppState experimental
    db.appstate = _db.addCollection('appstate', {
        indices: ['key']
    }) ;
    db.appstate.ensureUniqueIndex('key');

    // Segments
    db.segments = _db.addCollection('segments', {
        indices: ['sid']
    }) ;
    db.segments.ensureUniqueIndex('sid');

    // Segment versions
    db.segment_versions = _db.addCollection('segment_versions', {
        indices: ['id','id_segment']
    });
    db.segment_versions.ensureUniqueIndex('id');

    // Segment translation Issues
    db.segment_translation_issues = _db.addCollection(
        'segment_translation_issues', {
        indices: ['id', 'id_segment']
    });
    db.segment_translation_issues.ensureUniqueIndex('id');

    // Segment translation issue comments
    db.segment_translation_issue_comments = _db.addCollection(
        'segment_translation_issue_comments', {
        indices: ['id', 'id_issue']
    });
    db.segment_translation_issue_comments.ensureUniqueIndex('id');

    root.MateCat.db = db;

    MateCat.db.addListener = function(collection, events, func) {
        _.each(events, function (event) {
            MateCat.db[collection].on( event, func );
        });
    }

    MateCat.db.removeListener = function(collection, events, func) {
        _.each(events, function (event) {
            MateCat.db[collection].removeListener( event, func );
        });
    }

    MateCat.db.upsert = function(collection, key, data) {
        var coll = MateCat.db[collection];
        var record = coll.by(key, data[key]) ;
        if ( record ) {
            return coll.update( _.extend( record, data ) );
        }
        else {
            return coll.insert( data );
        }
    }

    var putSegmentsInStore = function(data) {
        $.each(data.files, function() {
            $.each( this.segments, function() {
                MateCat.db.upsert( 'segments', 'sid', _.clone( this ) );
            });
        });
    }

    $(document).on('segments:load', function(e, data) {
        putSegmentsInStore( data );
    });

})(jQuery, window);
