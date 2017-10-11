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
        UI.shortcuts =  {
            "translate": {
                "label" : "Confirm translation",
                "equivalent": "click on Translated",
                "keystrokes" : {
                    "standard": "ctrl+return",
                    "mac": "meta+return",
                }
            },
            "translate_nextUntranslated": {
                "label" : "Confirm translation and go to Next untranslated segment",
                "equivalent": "click on [T+>>]",
                "keystrokes" : {
                    "standard": "ctrl+shift+return",
                    "mac": "meta+shift+return",
                }
            },
            "openNext": {
                "label" : "Go to next segment",
                "equivalent": "",
                "keystrokes" : {
                    "standard": "ctrl+down",
                    "mac": "meta+down",
                }
            },
            "openPrevious": {
                "label" : "Go to previous segment",
                "equivalent": "",
                "keystrokes" : {
                    "standard": "ctrl+up",
                    "mac": "meta+up",
                }
            },
            "gotoCurrent": {
                "label" : "Go to current segment",
                "equivalent": "",
                "keystrokes" : {
                    "standard": "ctrl+home",
                    "mac": "meta+shift+up",
                }
            },
            "copySource": {
                "label" : "Copy source to target",
                "equivalent": "click on > between source and target",
                "keystrokes" : {
                    "standard": "ctrl+i",
                    "mac": "alt+ctrl+i"
                }
            },
            "undoInSegment": {
                "label" : "Undo in segment",
                "equivalent": "",
                "keystrokes" : {
                    "standard": "ctrl+z",
                    "mac": "meta+z",
                }
            },
            "redoInSegment": {
                "label" : "Undo in segment",
                "equivalent": "",
                "keystrokes" : {
                    "standard": "ctrl+y",
                    "mac": "meta+shift+z",
                }
            },
            "openSearch": {
                "label" : "Open/Close search panel",
                "equivalent": "",
                "keystrokes" : {
                    "standard": "ctrl+f",
                    "mac": "meta+f",
                }
            },
            "searchInConcordance": {
                "label" : "Perform Concordance search on word(s) selected in the source or target segment",
                "equivalent": "",
                "keystrokes" : {
                    "standard": "alt+k",
                    "mac": "alt+k",
                }
            }
        };
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




