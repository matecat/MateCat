/*
 Component: ui.header
 */

$.extend(UI, {
	initHeader: () => {

		if (SearchUtils.searchEnabled)
			$('#action-search').show( 100, function(){
				APP.fitText($('#pname-container'), $('#pname'), 25);
			} );


		/*if ($('#action-download').length) {
			$('#action-download').dropdown();
		}*/
		if ($('#action-three-dots').length) {
			$('#action-three-dots').dropdown();
		}
		if ($('#user-menu-dropdown').length) {
			$('#user-menu-dropdown').dropdown();
		}

		initEvents();

	},
	logoutAction: () => {
		$.post('/api/app/user/logout', (data) => {
			if ($('body').hasClass('manage')) {
				location.href = config.hostpath + config.basepath;
			} else {
				window.location.reload();
			}
		});
	}
});

const initEvents = () => {
	$("#action-search").bind('click', (e) => {
		SearchUtils.toggleSearch(e);
	});
	$("#action-settings").bind('click', (e) => {
		e.preventDefault();
		UI.openOptionsPanel();
	});
	$(".user-menu-container").on('click', '#logout-item', (e) => {
		e.preventDefault();
		UI.logoutAction();
	});
	$('#profile-item').on('click', function (e) {
		e.preventDefault();
		e.stopPropagation();
		$('#modal').trigger('openpreferences');
		return false;
	});

	$(".action-menu").on('click', "#action-filter", function(e) {
		e.preventDefault();
		if (!SegmentFilter.open) {
			SegmentFilter.openFilter();
		} else {
			SegmentFilter.closeFilter();
			SegmentFilter.open = false;
		}
	});
};
