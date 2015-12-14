
if ( ReviewImproved.enabled() )
(function($, root, undefined) {
    var db = new loki('matecat.json');

    var segments = db.addCollection('segments', {
        indices: ['sid']
    }) ;
    segments.ensureUniqueIndex('sid');

    var versions = db.addCollection('segment_versions', {
        indices: ['id','id_segment']
    });
    versions.ensureUniqueIndex('id');

    var issues = db.addCollection('segment_translation_issues', {
        indices: ['id', 'id_segment']
    });
    issues.ensureUniqueIndex('id');

    db.upsert = function(collection, record) {
        var c = this.getCollection(collection);
        if ( !c.insert( record ) ) {
            c.update( record );
            console.debug('upsert updated', record);
        }
    }

    root.MateCat = root.MateCat || {};
    root.MateCat.db = db ;

    // TODO: Just for debugging purposes
    root.colls = {};
    root.colls.issues   = issues ;
    root.colls.segments = segments ;
    root.colls.versions = versions ;

})(jQuery, window);
