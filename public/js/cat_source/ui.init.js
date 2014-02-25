/*
	Component: ui.init
 */
$.extend(UI, {
	init: function() {
		this.initStart = new Date();
		if (this.debug)
			console.log('Render time: ' + (this.initStart - renderStart));
		this.numContributionMatchesResults = 3;
		this.numMatchesResults = 10;
		this.numSegments = $('section').length;
		this.editarea = '';
		this.byButton = false;
		this.notYetOpened = true;
		this.pendingScroll = 0;
		this.firstScroll = true;
		this.blockGetMoreSegments = true;
		this.searchParams = {};
		this.searchParams.search = 0;
//		var bb = $.cookie('noAlertConfirmTranslation');
//		this.alertConfirmTranslationEnabled = (typeof bb == 'undefined') ? true : false;
		this.customSpellcheck = false;
		this.noGlossary = false;
		setTimeout(function() {
			UI.blockGetMoreSegments = false;
		}, 200);
		this.detectFirstLast();
		this.reinitMMShortcuts();
		this.initSegmentNavBar();
		rangy.init();
		this.savedSel = null;
		this.savedSelActiveElement = null;
		this.firstOpenedSegment = false;
		this.autoscrollCorrectionEnabled = true;
		this.searchEnabled = true;
		if (this.searchEnabled)
			$('#filterSwitch').show();
		this.viewConcordanceInContextMenu = true;
		if (!this.viewConcordanceInContextMenu)
			$('#searchConcordance').hide();
		this.viewSpellCheckInContextMenu = true;
		if (!this.viewSpellCheckInContextMenu)
			$('#spellCheck').hide();
		setTimeout(function() {
			UI.autoscrollCorrectionEnabled = false;
		}, 2000);
		this.checkSegmentsArray = {};
		this.firstMarking = true;
//		this.markTags(true);
		this.firstMarking = false;
		this.surveyDisplayed = false;
		this.setContextMenu();
		this.createJobMenu();
		$('#alertConfirmTranslation p').text('To confirm your translation, please press on Translated or use the shortcut ' + ((UI.isMac) ? 'CMD' : 'CTRL') + '+Enter.');

		// SET EVENTS
		this.setEvents();
		if(this.surveyAlreadyDisplayed()) {
			this.surveyDisplayed = true;
		}
	},
}); 


