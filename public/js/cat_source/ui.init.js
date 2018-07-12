/*
	Component: ui.init
 */
$.extend(UI, {
	init: function() {

		this.registerFooterTabs();

		this.isMac = (navigator.platform == 'MacIntel')? true : false;
		this.shortcutLeader = (this.isMac) ? 'CMD' : 'CTRL' ;

		this.initStart = new Date();
		this.version = "x.x.x";
		this.numContributionMatchesResults = 3;
		this.numMatchesResults = 10;
		this.editarea = '';
		this.byButton = false;
		this.blockGetMoreSegments = true;
		this.noGlossary = false;
		this.displayedMessages = [];
		setTimeout(function() {
			UI.blockGetMoreSegments = false;
		}, 200);
		this.loadCustomization();
        $('html').trigger('init');
        this.setTagMode();
		this.detectFirstLast();
		rangy.init();
		this.savedSel = null;
		this.savedSelActiveElement = null;
		this.autoscrollCorrectionEnabled = true;
        this.offline = false;

		if (SearchUtils.searchEnabled)
            $('#filterSwitch').show( 100, function(){ APP.fitText( $('.breadcrumbs'), $('#pname'), 30) } );
		setTimeout(function() {
			UI.autoscrollCorrectionEnabled = false;
		}, 2000);
		this.checkSegmentsArray = {};
		this.warningStopped = false;
		this.abortedOperations = [];
        this.logEnabled = true;
        this.unsavedSegmentsToRecover = [];
        this.recoverUnsavedSegmentsTimer = false;
        this.setTranslationTail = [];
        this.executingSetTranslation = false;
        this.localStorageArray = [];
        this.isPrivateSafari = (this.isSafari) && (!this.isLocalStorageNameSupported());
        this.consecutiveCopySourceNum = [];
        this.setComingFrom();
        setInterval(function() {
            UI.consecutiveCopySourceNum = [];
        }, config.copySourceInterval*1000);

        if (!config.isLoggedIn) this.body.addClass('isAnonymous');

		this.createJobMenu();
		this.checkVersion();
        this.initTM();
		this.initAdvanceOptions();

        // SET EVENTS
		this.setEvents();
		this.checkQueryParams();

        UI.firstLoad = false;
	},
    checkQueryParams: function () {
        var action = APP.getParameterByName("action");
        if (action) {
            switch (action) {
                case 'download':
                    var interval = setTimeout(function () {
                        $('#downloadProject').trigger('click');
                        clearInterval(interval);
                    }, 300);
                    APP.removeParam('action');
                    break;
                case 'openComments':
                    if ( MBC.enabled() ) {
                        var interval = setInterval(function () {
                            if ( $( '.mbc-history-balloon-outer' ) ) {
                                $( '.mbc-history-balloon-outer' ).addClass( 'mbc-visible' );
                                clearInterval(interval);
                            }
                        }, 500);

                    }
                    APP.removeParam('action');
                    break;
                case 'warnings':
                    var interval = setInterval(function () {
                        if ( $( '#notifbox.warningbox' ) ) {
                            $("#point2seg").trigger('mousedown');
                            clearInterval(interval);
                        }
                    }, 500);
                    APP.removeParam('action');
                    break;
            }
        }

    },
	/**
	 * Register tabs in segment footer
	 */
	registerFooterTabs: function () {
        SegmentActions.registerTab('concordances', true, false);

        if ( config.translation_matches_enabled ) {
            SegmentActions.registerTab('matches', true, true);
        }

        SegmentActions.registerTab('glossary', true, false);
        SegmentActions.registerTab('alternatives', false, false);
        // SegmentActions.registerTab('messages', false, false);
        if ( Review.enabled() ) {
            UI.registerReviseTab();

        }
	}
});