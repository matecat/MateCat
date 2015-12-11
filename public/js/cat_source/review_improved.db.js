
if ( Review.enabled() && Review.type == 'improved' )
(function($, root, undefined) {
    var db = new loki('loki.json');

    var segments = db.addCollection('segments', { indices: ['sid']} ) ;
    segments.ensureUniqueIndex('sid');

    var versions = db.addCollection('segment_versions', {indices: ['id','id_segment']});
    versions.ensureUniqueIndex('id');

    var issues = db.addCollection('segment_translation_issues', {indices: ['id', 'id_segment']});
    issues.ensureUniqueIndex('id');


    $(document).on('segments:load', function(e, data) {
        $.each(data.files, function() {
            $.each( this.segments, function() {
                var seg = segments.findOne( {sid : this.sid} );
                if ( seg ) {
                    var update = segments.update( this );
                }
                else {
                    var insert = segments.insert( this );
                }
            });
        });
    });

    db.upsert = function(collection, record) {
        var c = this.getCollection(collection);
        if ( !c.insert( record ) ) {
            c.update( record );
        }
    }

    root.MateCat = root.MateCat || {};
    root.MateCat.db = db ;

})(jQuery, window);
