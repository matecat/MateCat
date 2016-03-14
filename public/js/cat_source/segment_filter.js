
SegmentFilter = window.SegmentFilter || {};

SegmentFilter.enabled = function() {
    return ReviewImproved.enabled();
}

if (SegmentFilter.enabled())
(function($, UI, SF, undefined) {

    $.extend(SF, {
        lastFilterData : null,

        filterPanelOpen : function() {
            return UI.body.hasClass('filtering');
        },
        filtering : function() {
            // TODO change this, more specific when filter is submitted.
            return UI.body.hasClass('filtering');
        },

        filterSubmit : function( data ) {
            data = { filter: data } ;

            var path = sprintf('/api/v2/jobs/%s/%s/segments-filter?%s',
                              config.id_job, config.password, $.param( data )
                              );

            $.getJSON(path).done(function( data ) {
                SF.lastFilterData = data;

                $('#outer').empty();

                UI.render({
                    firstLoad: false,
                    segmentToOpen: data['segment_ids'][0]
                });

                window.segment_filter_panel.setState({clearEnabled: true});

            });
        },

        openFilter : function() {
            UI.body.addClass('filtering');
            $(document).trigger('header-tool:open', { name: 'filter' });
        },
        closeFilter : function() {
            UI.body.removeClass('filtering');
            $('.muted').removeClass('muted');
            window.segment_filter_panel.resetState();
        }
    });

    $(document).on('ready', function() {
        // mount the hiddent react component by default so we can keep status
        window.segment_filter_panel = ReactDOM.render(
          React.createElement(
            SegmentFilter_MainPanel, {}),
            $('#segment-filter-mountpoint')[0]
          );
    });

    $(document).on('header-tool:open', function(e, data) {
        if ( data.name != 'filter' ) {
            SF.closeFilter();
        }
    });

    $(document).on('click', "header .filter", function(e) {
        e.preventDefault();

        if ( UI.body.hasClass('filtering') ) {
            SF.closeFilter();
        } else {
            SF.openFilter();
        }
    });


})(jQuery, UI, SegmentFilter);
