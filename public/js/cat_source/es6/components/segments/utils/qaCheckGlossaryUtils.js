const QaCheckGlossary = {
    enabled() {
        return config.qa_check_glossary_enabled ;
    },
    update(glossary) {
        var mapped = {} ;

        // group by segment id
        _.each( glossary.matches, function ( item ) {
            mapped[ item.id_segment ] ? null : mapped[ item.id_segment ] = []  ;
            mapped[ item.id_segment ].push( item.data );

        });
        _.forOwn(mapped, function(value, key) {
            SegmentActions.addQaCheckMatches(key, value)
        });
    }
};

module.exports = QaCheckGlossary;
