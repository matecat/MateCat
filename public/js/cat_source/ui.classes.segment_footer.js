
UI.SegmentFooter = function( element ) {
    var self = this;

    this.el = $( element );
    this.segment = new UI.Segment( element );

    this.html = function() {
        var labels = $( UI.SegmentFooter.renderLabels( self ) );
        var bodies = $( UI.SegmentFooter.renderBodies( self ) );
        this.bindLabelEvents(labels);
        return [ labels, bodies ];
    }

    this.bindLabelEvents = function( el ) {
        var self = this ;

        el.on('click', '.tab-switcher', function(e) {
            e.preventDefault();

            var section = el.closest('section');
            var tab_class = $(e.target).closest('li').data('tab-class');
            var code = $(e.target).closest('li').data('code');
            var li = $(e.target).closest('li');

            $('.sub-editor', section).removeClass('open');
			$('.' + tab_class, section).addClass('open');

            $('.tab-switcher', section).removeClass('active');
            li.addClass('active');

            var item = _
                .chain(UI.SegmentFooter.registry)
                .select(function(item) { return item.code == code })
                .first()
                .value();

            if ( typeof item.on_activation  == 'function' ) {
                item.on_activation( self ) ;
            }
        });
    }

    this.activeItem = function() {
        // find a list of all enabled ones
        // call a function to determine if they want to be active
        // for those who want to be active
        // sort by activation priority and pick the first

        var active_candidates = _.select(UI.SegmentFooter.registry, function(item) {
            if ( !item.is_enabled( self ) ) return false;
            if ( item.is_hidden ( self ) ) return false;
            if ( typeof item.is_active == 'function' ) {
                return item.is_active( self );
            }
            return true; // every visible tabs wants to be active by default
        });

        var sorted = _
            .sortBy(active_candidates, 'activation_priority')
            .reverse();

        return _.first( sorted );
    }

}

UI.SegmentFooter.registry = [];
UI.SegmentFooter.registerTab = function( params ) {
    // Ensure no duplicates
    var found = _.select(this.registry, function(item) {
        return item.code == params.code ;
    });

    if ( found.length ) {
        throw new Error("Trying to register a tab twice", params);
    }

    this.registry.push( params );
}

UI.SegmentFooter.renderLabels = function( segmentFooter ) {
    var enabled = _.select(UI.SegmentFooter.registry, function(item) {
        return item.is_enabled( segmentFooter );
    });
    var tabs = _.sortBy( enabled, 'tab_position');

    var active = segmentFooter.activeItem();

    var labels = _.map( tabs , function(item) {
        var active_class = active.code == item.code ?  'active' : null ;
        var hidden_class = item.is_hidden( segmentFooter ) ? 'hide' : null ;

        return {
            hidden_class : hidden_class,
            active_class : active_class,
            id_segment : segmentFooter.segment.id ,
            tab_markup : item.tab_markup( segmentFooter ),
            code : item.code,
            tab_class : item.tab_class
        }
    });

    var data = { labels : labels };
    return MateCat.Templates['segment_footer/labels']( data );
}

UI.SegmentFooter.renderBodies = function( segmentFooter ) {
    var enabled = _.select(UI.SegmentFooter.registry, function(item) {
        return item.is_enabled( segmentFooter );
    });
    var tabs = _.sortBy( enabled, 'tab_position');

    var active = segmentFooter.activeItem();
    var bodies = _.map( tabs, function( item ) {
        var active_class =  active.code == item.code ? 'open' : null ;

        return {
            active_class : active_class,
            id_segment : segmentFooter.segment.id ,
            rendered_body : item.content_markup( segmentFooter ),
            tab_class : item.tab_class
        };
    });

    var data = { bodies : bodies };
    return MateCat.Templates['segment_footer/bodies']( data );
}
