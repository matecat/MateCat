/*
	Component: ui.render 
 */
$.extend(UI, {
	render: function(options) {
        options = options || {};
		var seg = (options.segmentToOpen || false);
		this.segmentToScrollAtRender = (seg) ? seg : false;

		this.isSafari = (navigator.userAgent.search("Safari") >= 0 && navigator.userAgent.search("Chrome") < 0);
		this.isChrome = (typeof window.chrome != 'undefined');
		this.isFirefox = (typeof navigator.mozApps != 'undefined');

		this.isMac = (navigator.platform == 'MacIntel') ? true : false;
		this.body = $('body');
		// this.firstLoad = (options.firstLoad || false);
		this.initSegNum = 100; // number of segments initially loaded
		this.moreSegNum = 25;
		this.numOpenedSegments = 0;
		this.maxMinutesBeforeRerendering = 60;
		this.loadingMore = false;
		this.infiniteScroll = true;
		this.noMoreSegmentsAfter = false;
		this.noMoreSegmentsBefore = false;
		this.blockButtons = false;
		this.dmp = new diff_match_patch();
		this.undoStack = [];
		this.undoStackPosition = 0;
		this.nextUntranslatedSegmentIdByServer = 0;
		this.checkUpdatesEvery = 180000;
		this.goingToNext = false;
		this.preCloseTagAutocomplete = false;
        this.hiddenTextEnabled = true;
		this.setGlobalTagProjection();
		this.tagModesEnabled = (typeof options.tagModesEnabled != 'undefined')? options.tagModesEnabled : true;
		if(this.tagModesEnabled && !this.enableTagProjection) {
			UI.body.addClass('tagModes');
		} else {
			UI.body.removeClass('tagModes');
		}

        /**
         * Global Translation mismatches array definition.
         */
        this.translationMismatches = [];
        /**
         * Global Warnings array definition.
         */
        this.globalWarnings = [];

        // this.downOpts = {offset: '130%'};
		// this.upOpts = {offset: '-40%'};
        this.downOpts = {
            offset: '100%',
            context: $('#outer')
        };
        this.upOpts = {
            offset: '-100%',
            context: $('#outer')
        };
		this.readonly = (this.body.hasClass('archived')) ? true : false;


        this.setTagLockCustomizeCookie(true);
        this.debug = false;
		UI.detectStartSegment();
		options.openCurrentSegmentAfter = !!((!seg) && (!this.firstLoad));


		if ( UI.firstLoad ) {

			this.lastUpdateRequested = new Date();

			setTimeout(function() {
				UI.getUpdates();
			}, UI.checkUpdatesEvery);

		}

		ReactDOM.render(
            React.createElement(
                SubHeaderContainer, {
                	filtersEnabled: SegmentFilter.enabled()
				}),
            $('#header-bars-wrapper')[0]
        );

        return UI.getSegments(options);

	},
});

