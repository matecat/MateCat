/*
 Component: ui.header
 */

$.extend(UI, {
	initHeader: function() {

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

		if ( config.isLoggedIn ) {
			setTimeout( function (  ) {
				CatToolActions.showHeaderTooltip();
			}, 2000);
		}

	},
	logoutAction: function() {
		$.post('/api/app/user/logout', function(data) {
			if ($('body').hasClass('manage')) {
				location.href = config.hostpath + config.basepath;
			} else {
				window.location.reload();
			}
		});
	},
	showProfilePopUp: function ( openProfileTooltip ) {
		if ( openProfileTooltip ) {
			var self = this;
			var tooltipTex = "<h4 class='header'>Manage your projects</h4>" +
				"<div class='content'>" +
				"<p>Click here, then \"My projects\" to retrieve and manage all the projects you have created in MateCat.</p>" +
				"<a class='close-popup-teams'>Next</a>" +
				"</div>";
			$('header .user-menu-container').popup({
				on: 'click',
				onHidden: function (  ) {
					$('header .user-menu-container').popup('destroy');
					CatToolActions.setPopupUserMenuCookie();
					return true;
				},
				html: tooltipTex,
				closable: false,
				onCreate: function() {
					$('.close-popup-teams').on('click', function () {
						$('header .user-menu-container').popup('hide');
						self.openPopupThreePoints()
					})
				},
				className: {
					popup: 'ui popup user-menu-tooltip'
				}
			}).popup("show");
		} else {
			this.openPopupThreePoints()
		}
	},
	openPopupThreePoints: function (  ) {
		var closedPopup = localStorage.getItem('infoThreeDotsMenu-'+config.userMail);
		if ( !closedPopup ) {
			var self = this;
			var tooltipTex = "<h4 class='header'>Easier tool navigation</h4>" +
				"<div class='content'>" +
				"<p>Click here to navigate to:</br>" +
                "- Translate/Revise mode</br>" +
                "- Volume analysis</br>" +
                "- XLIFF-to-target converter</br>" +
                "- Shortcut guide</p>" +
				"<a class='close-popup-teams'>Got it!</a>" +
				"</div>";
			$( '#action-three-dots' ).popup( {
				on: 'click',
				onHidden: function () {
					$( '#action-three-dots' ).popup('destroy');
					CommonUtils.addInStorage('infoThreeDotsMenu-'+config.userMail, true, 'infoThreeDotsMenu');
					return true;
				},
				html: tooltipTex,
				closable: false,
				onCreate: function () {
					$( '.close-popup-teams' ).on( 'click', function () {
						$( '#action-three-dots' ).popup( 'hide' );
					} );
				},
				className: {
					popup: 'ui popup three-dots-menu-tooltip'
				}
			} ).popup( "show" );
		}
	},
});

var initEvents = function() {
	$("#action-search").bind('click', function(e) {
		SearchUtils.toggleSearch(e);
	});
	$("#action-settings").bind('click', function(e) {
		e.preventDefault();
		UI.openOptionsPanel();
	});
	$(".user-menu-container").on('click', '#logout-item', function(e) {
		e.preventDefault();
		UI.logoutAction();
	});
	$(".user-menu-container").on('click', '#manage-item', function(e) {
		e.preventDefault();
		document.location.href = '/manage';
	});
	$('#profile-item').on('click', function (e) {
		e.preventDefault();
		e.stopPropagation();
		$('#modal').trigger('openpreferences');
		return false;
	});

	$('#action-three-dots .shortcuts').on('click', function (e) {
		e.preventDefault();
		e.stopPropagation();
		APP.ModalWindow.showModalComponent(ShortCutsModal, null, 'Shortcuts');
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
