
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

    var issue_comments = db.addCollection('issue_comments', {
        indices: ['id', 'id_issue']
    });
    issue_comments.ensureUniqueIndex('id');

    db.upsert = function(collection, record) {
        var c = this.getCollection(collection);
        if ( !c.insert( record ) ) {
            c.update( record );
            console.debug('upsert updated', record);
        }
    }

    root.MateCat = root.MateCat || {};
    root.MateCat.db = db ;

    root.MateCat.colls = {};
    root.MateCat.colls.issues         = issues ;
    root.MateCat.colls.segments       = segments ;
    root.MateCat.colls.versions       = versions ;
    root.MateCat.colls.issue_comments = issue_comments ;

})(jQuery, window);
