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
	}
});




