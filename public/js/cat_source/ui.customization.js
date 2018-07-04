/*
	Component: ui.customization
 */
$.extend(UI, {
	loadCustomization: function() {
		if (Cookies.get('user_customization')) {
			this.custom = $.parseJSON(Cookies.get('user_customization'));
		} else {
			this.custom = {
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
        Cookies.set('user_customization', JSON.stringify(this.custom), { expires: 3650 });
	},
	setShortcuts: function() {
        UI.shortcuts =  {
            cattol: {
                label: "Translate/Revise",
                events: {
                    "translate": {
                        "label": "Confirm translation",
                        "equivalent": "click on Translated",
                        "keystrokes": {
                            "standard": "ctrl+return",
                            "mac": "meta+return",
                        }
                    },
                    "translate_nextUntranslated": {
                        "label": "Confirm translation and go to Next untranslated segment",
                        "equivalent": "click on [T+>>]",
                        "keystrokes": {
                            "standard": "ctrl+shift+return",
                            "mac": "meta+shift+return",
                        }
                    },
                    "openNext": {
                        "label": "Go to next segment",
                        "equivalent": "",
                        "keystrokes": {
                            "standard": "ctrl+down",
                            "mac": "meta+down",
                        }
                    },
                    "openPrevious": {
                        "label": "Go to previous segment",
                        "equivalent": "",
                        "keystrokes": {
                            "standard": "ctrl+up",
                            "mac": "meta+up",
                        }
                    },
                    "gotoCurrent": {
                        "label": "Go to current segment",
                        "equivalent": "",
                        "keystrokes": {
                            "standard": "ctrl+home",
                            "mac": "meta+shift+up",
                        }
                    },
                    "copySource": {
                        "label": "Copy source to target",
                        "equivalent": "click on > between source and target",
                        "keystrokes": {
                            "standard": "ctrl+i",
                            "mac": "alt+ctrl+i"
                        }
                    },
                    "undoInSegment": {
                        "label": "Undo in segment",
                        "equivalent": "",
                        "keystrokes": {
                            "standard": "ctrl+z",
                            "mac": "meta+z",
                        }
                    },
                    "redoInSegment": {
                        "label": "Undo in segment",
                        "equivalent": "",
                        "keystrokes": {
                            "standard": "ctrl+y",
                            "mac": "meta+shift+z",
                        }
                    },
                    "openSearch": {
                        "label": "Open/Close search panel",
                        "equivalent": "",
                        "keystrokes": {
                            "standard": "ctrl+f",
                            "mac": "meta+f",
                        }
                    },
                    "searchInConcordance": {
                        "label": "Perform TM Search search on word(s) selected in the source or target segment",
                        "equivalent": "",
                        "keystrokes": {
                            "standard": "alt+k",
                            "mac": "alt+k",
                        }
                    },
                    "openSettings": {
                        "label": "Open Settings panel",
                        "equivalent": "",
                        "keystrokes": {
                            "standard": "alt+shift+l",
                            "mac": "Meta+shift+l",
                        }
                    },
                    "toggleTagDisplayMode": {
                        "label": "Switch Tag Display Mode",
                        "equivalent": "",
                        "keystrokes": {
                            "standard": "alt+shift+s",
                            "mac": "Meta+shift+s",
                        }
                    },
                    "copyContribution1": {
                        "label": "Copy first translation match in Target",
                        "equivalent": "",
                        "keystrokes": {
                            "standard": "ctrl+1",
                            "mac": "ctrl+1",
                        }
                    }, "copyContribution2": {
                        "label": "Copy second translation match in Target",
                        "equivalent": "",
                        "keystrokes": {
                            "standard": "ctrl+2",
                            "mac": "ctrl+2",
                        }
                    }, "copyContribution3": {
                        "label": "Copy third translation match in Target",
                        "equivalent": "",
                        "keystrokes": {
                            "standard": "ctrl+3",
                            "mac": "ctrl+3",
                        }
                    }, "splitSegment": {
                        "label": "Split Segment",
                        "equivalent": "",
                        "keystrokes": {
                            "standard": "ctrl+s",
                            "mac": "ctrl+s",
                        }
                    }
                }
            },
        };
	}
});




