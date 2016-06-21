/*
	Component: ui.customization
 */
$.extend(UI, {
	loadCustomization: function() {
		if ($.cookie('user_customization')) {
			this.custom = $.parseJSON($.cookie('user_customization'));
		} else {
			this.custom = {
				"extended_concordance": false,
                "extended_tagmode": false
			};
			this.saveCustomization();
		}
		//Tag Projection: the tag-mode is always extended
		if (this.enableTagProjection) {
			// Disable Tag Crunched Mode
			this.custom.extended_tagmode = true;
		}
	},
	saveCustomization: function() {
		$.cookie('user_customization', JSON.stringify(this.custom), { expires: 3650 });
	},
	setShortcuts: function() {
		if($('#settings-shortcuts .list').length) return;
		$('#settings-shortcuts #default-shortcuts').before('<table class="list"></table>');
		$.each(this.shortcuts, function() {
			$('#settings-shortcuts .list').append('<tr><td class="label">' + this.label + '</td><td class="combination"><span contenteditable="true" class="keystroke">' + ((UI.isMac) ? ((typeof this.keystrokes.mac == 'undefined')? UI.viewShortcutSymbols(this.keystrokes.standard) : UI.viewShortcutSymbols(this.keystrokes.mac)) : UI.viewShortcutSymbols(this.keystrokes.standard)) + '</span></td></tr>');
		});
	},
	viewShortcutSymbols: function(txt) {
		txt = txt.replace(/meta/gi, '&#8984').replace(/return/gi, '&#8629').replace(/alt/gi, '&#8997').replace(/shift/gi, '&#8679').replace(/up/gi, '&#8593').replace(/down/gi, '&#8595').replace(/left/gi, '&#8592').replace(/right/gi, '&#8594');
		return txt;
	},
	writeNewShortcut: function(c, s, k) {
		$(k).html(s.html().substring(0, s.html().length - 1)).removeClass('changing').addClass('modified').blur();
		$(s).remove();
		$('.msg', c).remove();
		$('#settings-shortcuts.modifying').removeClass('modifying');
		$('.popup-settings .submenu li[data-tab="settings-shortcuts"]').addClass('modified');
		$('.popup-settings').addClass('modified');
	}
});




