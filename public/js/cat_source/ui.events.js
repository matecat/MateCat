/*
	Component: ui.render 
 */
$.extend(UI, {
	setEvents: function() {
		$("body").bind('keydown', 'Ctrl+return', function(e) {
			e.preventDefault();
			$('.editor .translated').click();
		}).bind('keydown', 'Meta+return', function(e) {
			e.preventDefault();
			$('.editor .translated').click();
		}).bind('keydown', 'Ctrl+shift+return', function(e) {
			e.preventDefault();
			$('.editor .next-untranslated').click();
		}).bind('keydown', 'Meta+shift+return', function(e) {
			e.preventDefault();
			$('.editor .next-untranslated').click();
		}).bind('keydown', 'Ctrl+pageup', function(e) {
			e.preventDefault();
		}).bind('keydown', 'Ctrl+down', function(e) {
			e.preventDefault();
			e.stopPropagation();
			UI.gotoNextSegment();
		}).bind('keydown', 'Meta+down', function(e) {
			e.preventDefault();
			e.stopPropagation();
			UI.gotoNextSegment();
		}).bind('keydown', 'Ctrl+up', function(e) {
			e.preventDefault();
			e.stopPropagation();
			UI.gotoPreviousSegment();
		}).bind('keydown', 'Meta+up', function(e) {
			e.preventDefault();
			e.stopPropagation();
			UI.gotoPreviousSegment();
		}).bind('keydown', 'Ctrl+left', function(e) {
			e.preventDefault();
			UI.pointToOpenSegment();
		}).bind('keydown', 'Meta+left', function(e) {
			e.preventDefault();
			UI.pointToOpenSegment();
		}).bind('keydown', 'Ctrl+right', function(e) {
			e.preventDefault();
			UI.copySource();
		}).bind('keydown', 'Ctrl+shift+major', function(e) {
			e.preventDefault();
			UI.copySource();
		}).bind('keydown', 'Ctrl+z', function(e) {
			e.preventDefault();
			UI.undoInSegment(segment);
			UI.closeTagAutocompletePanel();
		}).bind('keydown', 'Meta+z', function(e) {
			e.preventDefault();
			UI.undoInSegment(segment);
			UI.closeTagAutocompletePanel();
		}).bind('keydown', 'Ctrl+y', function(e) {
			e.preventDefault();
			UI.redoInSegment(segment);
		}).bind('keydown', 'Meta+Shift+z', function(e) {
			e.preventDefault();
			UI.redoInSegment(segment);
		}).bind('keydown', 'Ctrl+c', function(e) {
			UI.tagSelection = false;
		}).bind('keydown', 'Meta+c', function(e) {
			UI.tagSelection = false;
//		}).bind('keydown', 'Backspace', function(e) {
		}).bind('keydown', 'Meta+f', function(e) {
			UI.toggleSearch(e);
		}).bind('keydown', 'Ctrl+f', function(e) {
			UI.toggleSearch(e);
		}).on('change', '#hideAlertConfirmTranslation', function(e) {
			console.log($(this).prop('checked'));
			if ($(this).prop('checked')) {
				console.log('checked');
				UI.alertConfirmTranslationEnabled = false;
				$.cookie('noAlertConfirmTranslation', true, {expires: 1000});
			} else {
				console.log('unchecked');
				UI.alertConfirmTranslationEnabled = true;
				$.removeCookie('noAlertConfirmTranslation');
			}
		}).on('click', '#spellCheck .words', function(e) {
			e.preventDefault();
			UI.selectedMisspelledElement.replaceWith($(this).text());
			UI.closeContextMenu();
		}).on('click', '#spellCheck .add', function(e) {
			e.preventDefault();
			UI.closeContextMenu();
			UI.addWord(UI.selectedMisspelledElement.text());
		}).on('click', '.tag-autocomplete li', function(e) {
			e.preventDefault();
//			UI.editarea.html(UI.editarea.html().replace(/&lt;[&;"\w\s\/=]*?(\<span class="tag-autocomplete-endcursor"\>)/gi, '$1'));
//			UI.editarea.html(UI.editarea.html().replace(/&lt;(?:[a-z]*&nbsp;*(["\w\s\/=]*?))?(\<span class="tag-autocomplete-endcursor"\>)/gi, '$2'));
			UI.editarea.html(UI.editarea.html().replace(/&lt;(?:[a-z]*(?:&nbsp;)*["\w\s\/=]*)?(\<span class="tag-autocomplete-endcursor"\>)/gi, '$1'));
			saveSelection();
			if(!$('.rangySelectionBoundary', UI.editarea).length) { // click, not keypress
				setCursorPosition(document.getElementsByClassName("tag-autocomplete-endcursor")[0]);
				saveSelection();
			}
//			console.log($('.rangySelectionBoundary', UI.editarea)[0]);
			var ph = $('.rangySelectionBoundary', UI.editarea)[0].outerHTML;
			$('.rangySelectionBoundary', UI.editarea).remove();
			$('.tag-autocomplete-endcursor', UI.editarea).after(ph);
//			setCursorPosition(document.getElementsByClassName("tag-autocomplete-endcursor")[0]);
			$('.tag-autocomplete-endcursor').before(htmlEncode($(this).text()));
			restoreSelection();
			UI.closeTagAutocompletePanel();
			UI.lockTags(UI.editarea);
			UI.currentSegmentQA();
		})
		
		$(window).on('scroll', function(e) {
			UI.browserScrollPositionRestoreCorrection();
		})
// no more used:
		$("header .filter").click(function(e) {
			e.preventDefault();
			UI.body.toggleClass('filtering');
		})
		$("#filterSwitch").bind('click', function(e) {
			UI.toggleSearch(e);
		})
		$("#segmentPointer").click(function(e) {
			e.preventDefault();
			UI.pointToOpenSegment();
		})

		$(".replace").click(function(e) {
			e.preventDefault();
			UI.body.toggleClass('replace-box');
		})

		jQuery('.editarea').trigger('update');

		$("div.notification-box").mouseup(function() {
			return false;
		});

		$(".search-icon, .search-on").click(function(e) {
			e.preventDefault();
			$("#search").toggle();
		});
		$('.download-chrome a.close').bind('click', function(e) {
			e.preventDefault();
			$('.download-chrome').removeClass('d-open');
		});

		//overlay

		$(".x-stats").click(function(e) {
			$(".stats").toggle();
		});

		$(window).on('sourceCopied', function(event) {
		});

		$("#outer").on('click', 'a.sid', function(e) {
			e.preventDefault();
			e.stopPropagation();
			return false;
		}).on('click', 'a.status', function(e) {
			e.preventDefault();
			e.stopPropagation();
		}).on('click', 'section:not(.readonly) a.status', function(e) {
			console.log('status');
			var segment = $(this).parents("section");
			var statusMenu = $("ul.statusmenu", segment);

			UI.createStatusMenu(statusMenu);
			statusMenu.show();
			var autoCloseStatusMenu = $('html').bind("click.vediamo", function(event) {
				$("ul.statusmenu").hide();
				$('html').unbind('click.vediamo');
				UI.removeStatusMenu(statusMenu);
			});
		}).on('click', 'section.readonly, section.readonly a.status', function(e) {
			e.preventDefault();
			if (UI.justSelecting('readonly'))
				return;
			if (UI.someUserSelection)
				return;

			UI.selectingReadonly = setTimeout(function() {
				APP.alert({msg: 'This part has not been assigned to you.'});
			}, 200);
		}).on('mousedown', 'section.readonly, section.readonly a.status', function(e) {
			sel = window.getSelection();
			UI.someUserSelection = (sel.type == 'Range') ? true : false;
		}).on('dblclick', 'section.readonly', function(e) {
			clearTimeout(UI.selectingReadonly);
		}).on('blur', '.graysmall .translation', function(e) {
			e.preventDefault();
			UI.closeInplaceEditor($(this));
		}).on('click', '.graysmall .edit-buttons .cancel', function(e) {
			e.preventDefault();
			UI.closeInplaceEditor($(this).parents('.graysmall').find('.translation'));
		}).on('click', '.graysmall .edit-buttons .save', function(e) {
			e.preventDefault();
			console.log('save');
			ed = $(this).parents('.graysmall').find('.translation');
			UI.editContribution(UI.currentSegment, $(this).parents('.graysmall'));
			UI.closeInplaceEditor(ed);
		});

		$(".joblink").click(function(e) {
			e.preventDefault();
			$(".joblist").toggle();
			return false;
		});

		$(".statslink").click(function(e) {
			e.preventDefault();
			e.stopPropagation();
			$(".stats").toggle();
		});

		$(".getoriginal").click(function(e) {
			e.preventDefault();
			$('#originalDownload').submit();
		});
		$("form#fileDownload").bind('submit', function(e) {
			e.preventDefault();
		});

		$('html').click(function() {
			$(".menucolor").hide();
		}).on('click', '#downloadProject', function(e) {
			e.preventDefault();
			if ($("#notifbox").hasClass("warningbox")) {
				APP.confirm({
					name: 'confirmDownload',
					cancelTxt: 'Fix errors',
					onCancel: 'goToFirstError',
					callback: 'continueDownload',
					okTxt: 'Continue',
					msg: "Potential errors (missing tags, numbers etc.) found in the text. <br>If you continue, part of the content could be untranslated - look for the string \"UNTRANSLATED_CONTENT\" in the downloaded file(s).<br><br>Continue downloading or fix the error in MateCat:"
				});
			} else {
				UI.continueDownload();
			}
		}).on('click', '.alert .close', function(e) {
			e.preventDefault();
			$('.alert').remove();
		}).on('click', '.downloadtr-button .draft', function(e) {
			if (UI.isChrome) {
				$('.download-chrome').addClass('d-open');
				setTimeout(function() {
					$('.download-chrome').removeClass('d-open');
				}, 7000);
			}
		}).on('click', '#contextMenu #searchConcordance', function(e) {
			if ($('#contextMenu').attr('data-sid') == UI.currentSegmentId) {
				UI.openConcordance();
			} else {
				$('#segment-' + $('#contextMenu').attr('data-sid') + ' .editarea').trigger('click', ['clicking', 'openConcordance']);
			}
		});

		$("#outer").on('click', 'a.percentuage', function(e) {
			e.preventDefault();
			e.stopPropagation();
		}).on('click', '.editarea', function(e, operation, action) {
			if (typeof operation == 'undefined')
				operation = 'clicking';
			this.onclickEditarea = new Date();
			UI.notYetOpened = false;
			UI.closeTagAutocompletePanel();
			if ((!$(this).is(UI.editarea)) || (UI.editarea == '') || (!UI.body.hasClass('editing'))) {
				if (operation == 'moving') {
					if ((UI.lastOperation == 'moving') && (UI.recentMoving)) {
						UI.segmentToOpen = segment;
						UI.blockOpenSegment = true;

						console.log('ctrl+down troppo vicini');
					} else {
						UI.blockOpenSegment = false;
					}

					UI.recentMoving = true;
					clearTimeout(UI.recentMovingTimeout);
					UI.recentMovingTimeout = setTimeout(function() {
						UI.recentMoving = false;
					}, 1000);

				} else {
					UI.blockOpenSegment = false;
				}
				UI.lastOperation = operation;

				UI.openSegment(this, operation);
				if (action == 'openConcordance')
					UI.openConcordance();

				if (operation != 'moving')
					UI.scrollSegment($('#segment-' + $(this).data('sid')));
			}
			if (UI.debug)
				console.log('Total onclick Editarea: ' + ((new Date()) - this.onclickEditarea));
		}).on('keydown', '.editor .source, .editor .editarea', 'alt+meta+c', function(e) {
			e.preventDefault();
			UI.preOpenConcordance();
		}).on('keydown', '.editor .source, .editor .editarea', 'alt+ctrl+c', function(e) {
			e.preventDefault();
			UI.preOpenConcordance();
		}).on('keypress', '.editor .editarea', function(e) {
			if((e.which == 60)&&(UI.taglockEnabled)) { // opening tag sign
				if($('.tag-autocomplete').length) {
					e.preventDefault();
					return false;
				};
				UI.openTagAutocompletePanel();
			};
			if((e.which == 62)&&(UI.taglockEnabled)) { // closing tag sign
				if($('.tag-autocomplete').length) {
					e.preventDefault();
					return false;
				};
			};
			setTimeout(function() {
				if($('.tag-autocomplete').length) {
//					console.log(UI.editarea.html().match(/^(<span class="tag-autocomplete-endcursor"\><\/span>&lt;)/gi) != null);
					if(UI.editarea.html().match(/^(<span class="tag-autocomplete-endcursor"\><\/span>&lt;)/gi) != null) {
						UI.editarea.html(UI.editarea.html().replace(/^(<span class="tag-autocomplete-endcursor"\><\/span>&lt;)/gi, '&lt;<span class="tag-autocomplete-endcursor"\><\/span>'));
					}
					UI.checkAutocompleteTags();
				}
			}, 50);			
		}).on('keydown', '.editor .editarea', function(e) {
			var special = event.type !== "keypress" && jQuery.hotkeys.specialKeys[ event.which ];
			if ((event.metaKey && !event.ctrlKey && special !== "meta") || (event.ctrlKey)) {
				if (event.which == 88) { // ctrl+x
					if ($('.selected', $(this)).length) {
						event.preventDefault();
						UI.tagSelection = getSelectionHtml();
						$('.selected', $(this)).remove();
					}
				}
			}

			if ((e.which == 8) || (e.which == 46)) { // backspace e canc(mac)
				if ($('.selected', $(this)).length) {
					e.preventDefault();
					$('.selected', $(this)).remove();
					UI.saveInUndoStack('cancel');
					UI.currentSegmentQA();
				} else {
					try {
						var numTagsBefore = UI.editarea.text().match(/\<.*?\>/gi).length;
						var numSpacesBefore = UI.editarea.text().match(/\s/gi).length;
						setTimeout(function() {
							var numTagsAfter = UI.editarea.text().match(/\<.*?\>/gi).length;
							var numSpacesAfter = UI.editarea.text().match(/\s/gi).length;
							if (numTagsAfter < numTagsBefore)
								UI.saveInUndoStack('cancel');
							if (numSpacesAfter < numSpacesBefore)
								UI.saveInUndoStack('cancel');
						}, 50);
					} catch (e) {
						//Error: Cannot read property 'length' of null 
						//when we are on first character position in edit area and try to BACKSPACE
						//console.log(e.message); 
					}
				}
			}
			if (e.which == 8) { // backspace
				if($('.tag-autocomplete').length) {
					UI.closeTagAutocompletePanel();
					setTimeout(function() {
						UI.openTagAutocompletePanel();
						added = UI.getPartialTagAutocomplete();
						if(added == '') UI.closeTagAutocompletePanel();
					}, 10);		
				}
			}
			if (e.which == 37) { // left arrow
				var selection = window.getSelection();
				var range = selection.getRangeAt(0);
				if (range.startOffset != range.endOffset) { // if something is selected when the left button is pressed...
					var r = range.startContainer.data;
					if ((r[0] == '<') && (r[r.length - 1] == '>')) { // if a tag is selected
						saveSelection();
						var rr = document.createRange();
						var referenceNode = $('.rangySelectionBoundary', UI.editarea).first().get(0);
						rr.setStartBefore(referenceNode);
						rr.setEndBefore(referenceNode);
						$('.rangySelectionBoundary', UI.editarea).remove();
					}
				}
				UI.closeTagAutocompletePanel();
			}

			if (e.which == 38) { // top arrow
				if($('.tag-autocomplete').length) {
					if(!$('.tag-autocomplete li.current').is($('.tag-autocomplete li:first'))) {
						$('.tag-autocomplete li.current:not(:first-child)').removeClass('current').prevAll(':not(.hidden)').first().addClass('current');
						return false;
					}	
				}
				var selection = window.getSelection();
				var range = selection.getRangeAt(0);
				if (range.startOffset != range.endOffset) {
					var r = range.startContainer.data;
					if ((r[0] == '<') && (r[r.length - 1] == '>')) {
						saveSelection();
						var rr = document.createRange();
						var referenceNode = $('.rangySelectionBoundary', UI.editarea).last().get(0);
						rr.setStartAfter(referenceNode);
						rr.setEndAfter(referenceNode);
						$('.rangySelectionBoundary', UI.editarea).remove();
					}
				}
			}
			if (e.which == 39) { // right arrow
				var selection = window.getSelection();
				var range = selection.getRangeAt(0);
				if (range.startOffset != range.endOffset) {
					var r = range.startContainer.data;
					if ((r[0] == '<') && (r[r.length - 1] == '>')) {
						saveSelection();
						var rr = document.createRange();
						var referenceNode = $('.rangySelectionBoundary', UI.editarea).last().get(0);
						rr.setStartAfter(referenceNode);
						rr.setEndAfter(referenceNode);
						$('.rangySelectionBoundary', UI.editarea).remove();
					}
				}
				UI.closeTagAutocompletePanel();
			}

			if (e.which == 40) { // down arrow
				if($('.tag-autocomplete').length) {
					$('.tag-autocomplete li.current:not(:last-child)').removeClass('current').nextAll(':not(.hidden)').first().addClass('current');	
					return false;
				}
				var selection = window.getSelection();
				var range = selection.getRangeAt(0);
				if (range.startOffset != range.endOffset) {
					var r = range.startContainer.data;
					if ((r[0] == '<') && (r[r.length - 1] == '>')) {
						saveSelection();
						var rr = document.createRange();
						var referenceNode = $('.rangySelectionBoundary', UI.editarea).last().get(0);
						rr.setStartAfter(referenceNode);
						rr.setEndAfter(referenceNode);
						$('.rangySelectionBoundary', UI.editarea).remove();
					}
				}
			}

			if (!((e.which == 37) || (e.which == 38) || (e.which == 39) || (e.which == 40))) { // arrow
				if (UI.body.hasClass('searchActive'))
					UI.resetSearch();
			}
			if (e.which == 32) { // space
				setTimeout(function() {
					UI.saveInUndoStack('space');
				}, 100);
			}

			if (e.which == 13) { // return
				if($('.tag-autocomplete').length) {
					$('.tag-autocomplete li.current').click();	
					return false;
				}
			}

			if (
					(e.which == 13) || // return
					(e.which == 32) || // space
					(e.which == 49) || // semicomma
					(e.which == 188) || // comma
					(e.which == 186) || // semicomma
					(e.which == 190) || // mark
					(e.which == 191) || // question mark
					(e.which == 222)) { // apostrophe
				UI.spellCheck();
			}

		}).on('input', '.editarea', function(e) {
			if (UI.body.hasClass('searchActive'))
				UI.resetSearch();
			UI.currentSegment.addClass('modified').removeClass('waiting_for_check_result');
			if (UI.draggingInsideEditarea) {
				$(UI.tagToDelete).remove();
				UI.draggingInsideEditarea = false;
				UI.tagToDelete = null;
			}
			if (UI.droppingInEditarea) {
				UI.cleanDroppedTag(UI.editarea, UI.beforeDropEditareaHTML);
			}
			if (!UI.body.hasClass('searchActive'))
				setTimeout(function() {
					UI.lockTags(UI.editarea);
				}, 10);
			UI.registerQACheck();
		}).on('input', '.editor .cc-search .input', function(e) {
			UI.markTagsInSearch($(this));
		}).on('click', '.editor .source .locked,.editor .editarea .locked', function(e) {
			e.preventDefault();
			e.stopPropagation();
			selectText(this);
			$(this).toggleClass('selected');
//		}).on('contextmenu', '.source', function(e) {
			// temporarily disabled
//            if(UI.viewConcordanceInContextMenu||UI.viewSpellCheckInContextMenu) e.preventDefault();
		}).on('mousedown', '.source', function(e) {
			if (e.button == 2) { // right click
				// temporarily disabled
				return true;
				if ($('#contextMenu').css('display') == 'block')
					return true;

				var selection = window.getSelection();
				if (selection.type == 'Range') { // something is selected
					var str = selection.toString().trim();
					if (str.length) { // the trimmed string is not empty
						UI.currentSelectedText = str;

						UI.currentSearchInTarget = ($(this).hasClass('source')) ? 0 : 1;
						$('#contextMenu').attr('data-sid', $(this).parents('section').attr('id').split('-')[1]);

						if (UI.customSpellcheck) {
							var range = selection.getRangeAt(0);
							var tag = range.startContainer.parentElement;
							if (($(tag).hasClass('misspelled')) && (tag === range.endContainer.parentElement)) { // the selected element is in a misspelled element
								UI.selectedMisspelledElement = $(tag);
								var replacements = '';
								var words = $(tag).attr('data-replacements').split(',');
								$.each(words, function(item) {
									replacements += '<a class="words" href="#">' + this + '</a>';
								});
								if ((words.length == 1) && (words[0] == '')) {
									$('#spellCheck .label').hide();
								} else {
									$('#spellCheck .label').show();
								}
								$('#spellCheck .words').remove();
								$('#spellCheck').show().find('.label').after(replacements);
							} else {
								$('#spellCheck').hide();
							}
						}

						UI.showContextMenu(str, e.pageY, e.pageX);
					}
				}
				return false;
			}
			return true;
		}).on('dragstart', '.editor .editarea .locked', function(e) {
			var selection = window.getSelection();
			var range = selection.getRangeAt(0);
			if (range.startContainer.data != range.endContainer.data)
				return false;

			UI.draggingInsideEditarea = true;
			UI.tagToDelete = $(this);
		}).on('drop', '.editor .editarea', function(e) {
			if (e.stopPropagation) {
				e.stopPropagation(); // stops the browser from redirecting.
			}
			UI.beforeDropEditareaHTML = UI.editarea.html();
			UI.droppingInEditarea = true;

			$(window).trigger({
				type: "droppedInEditarea",
				segment: UI.currentSegment
			});
			UI.saveInUndoStack('drop');
			setTimeout(function() {
				UI.saveInUndoStack('drop');
			}, 100);
		}).on('drop paste', '.editor .cc-search .input, .editor .gl-search .input', function(e) {
			UI.beforeDropSearchSourceHTML = UI.editarea.html();
			UI.currentConcordanceField = $(this);
			setTimeout(function() {
				UI.cleanDroppedTag(UI.currentConcordanceField, UI.beforeDropSearchSourceHTML);
			}, 100);
		}).on('click', '.editor .editarea .locked.selected', function(e) {
		}).on('click', '.editor .editarea, .editor .source', function(e) {
			$('.selected', $(this)).removeClass('selected');
			UI.currentSelectedText = false;
			UI.currentSearchInTarget = false;
			$('#contextMenu').hide();
		}).on('click', 'a.translated, a.next-untranslated', function(e) {
			var w = ($(this).hasClass('translated')) ? 'translated' : 'next-untranslated';
			e.preventDefault();
			UI.currentSegment.removeClass('modified');

			var skipChange = false;
			if (w == 'next-untranslated') {
				console.log('entra');
				if (!UI.segmentIsLoaded(UI.nextUntranslatedSegmentId)) {
					UI.changeStatus(this, 'translated', 0);
					skipChange = true;
					if (!UI.nextUntranslatedSegmentId) {
						$('#' + $(this).attr('data-segmentid') + '-close').click();
					} else {
						UI.reloadWarning();
					}

				}
			} else {
				if (!$(UI.currentSegment).nextAll('section').length) {
					UI.changeStatus(this, 'translated', 0);
					skipChange = true;
					$('#' + $(this).attr('data-segmentid') + '-close').click();
				}
			}

			UI.checkHeaviness();
			if (UI.blockButtons) {
				if (UI.segmentIsLoaded(UI.nextUntranslatedSegmentId) || UI.nextUntranslatedSegmentId == '') {
					console.log('segment is already loaded');
				} else {
					console.log('segment is not loaded');

					if (!UI.noMoreSegmentsAfter) {
						UI.reloadWarning();
					}
				}
				return;
			}
			UI.blockButtons = true;

			UI.unlockTags();
			UI.setStatusButtons(this);

            if (!skipChange)
                UI.changeStatus(this, 'translated', 0);

			if (w == 'translated') {
				UI.gotoNextSegment();
			} else {
				$(".editarea", UI.nextUntranslatedSegment).trigger("click", "translated");
			}

			UI.markTags();
			UI.lockTags(UI.editarea);
			UI.changeStatusStop = new Date();
			UI.changeStatusOperations = UI.changeStatusStop - UI.buttonClickStop;
		}).on('click', 'a.approved', function(e) {
			UI.setStatusButtons(this);
			$(".editarea", UI.nextUntranslatedSegment).click();

			UI.changeStatus(this, 'approved', 0);
			UI.changeStatusStop = new Date();
			UI.changeStatusOperations = UI.changeStatusStop - UI.buttonClickStop;

		}).on('click', 'a.d, a.a, a.r, a.f', function(e) {
			var segment = $(this).parents("section");
			$("a.status", segment).removeClass("col-approved col-rejected col-done col-draft");
			$("ul.statusmenu", segment).toggle();
			return false;
		}).on('click', 'a.d', function(e) {
			UI.changeStatus(this, 'translated', 1);
		}).on('click', 'a.a', function(e) {
			UI.changeStatus(this, 'approved', 1);
		}).on('click', 'a.r', function(e) {
			UI.changeStatus(this, 'rejected', 1);
		}).on('click', 'a.f', function(e) {
			UI.changeStatus(this, 'draft', 1);
		}).on('click', '.editor .outersource .copy', function(e) {
//        }).on('click', 'a.copysource', function(e) {
			e.preventDefault();
			UI.copySource();
		}).on('click', '.tagmenu, .warning, .viewer, .notification-box li a', function(e) {
			return false;
		}).on('click', '.tab-switcher-tm', function(e) {
			e.preventDefault();
			$('.editor .submenu .active').removeClass('active');
			$(this).addClass('active');
			$('.editor .sub-editor').hide();
			$('.editor .sub-editor.matches').show();
		}).on('click', '.tab-switcher-cc', function(e) {
			e.preventDefault();
			$('.editor .submenu .active').removeClass('active');
			$(this).addClass('active');
			$('.editor .sub-editor').hide();
			$('.editor .sub-editor.concordances').show();
			$('.cc-search .search-source').focus();
//        }).on('keydown', '.sub-editor .cc-search .search-source', 'return', function(e) {
			//if($(this).text().length > 2) UI.getConcordance($(this).text(), 0);
		}).on('click', '.tab-switcher-gl', function(e) {
			e.preventDefault();
			$('.editor .submenu .active').removeClass('active');
			$(this).addClass('active');
			$('.editor .sub-editor').hide();
			$('.editor .sub-editor.glossary').show();
			$('.gl-search .search-source').focus();
		}).on('click', '.sub-editor.glossary .overflow a.trash', function(e) {
			e.preventDefault();
			ul = $(this).parents('ul.graysmall').first();
			UI.deleteGlossaryItem($(this).parents('ul.graysmall').first());
		}).on('click', '.sub-editor.glossary .details .comment', function(e) {
			e.preventDefault();
			$(this).attr('contenteditable', true).focus();
		}).on('blur', '.sub-editor.glossary .details .comment', function(e) {
			e.preventDefault();
			$(this).attr('contenteditable', false);
			item = $(this).parents('.graysmall');
			APP.doRequest({
				data: {
					action: 'glossary',
					exec: 'update',
					segment: item.find('.suggestion_source').text(),
					translation: item.find('.translation').text(),
					comment: $(this).text(),
					id_item: item.attr('data-id'),
					id_job: config.job_id,
					password: config.password
				},
				context: [UI.currentSegment, next],
				success: function(d) {
				},
				complete: function() {
				}
			})					
		}).on('keydown', '.sub-editor .cc-search .search-source', function(e) {
			if (e.which == 13) { // enter
				e.preventDefault();
				var txt = $(this).text();
				if (txt.length > 1)
					UI.getConcordance(txt, 0);
			} else {
				if ($('.editor .sub-editor .cc-search .search-target').text().length > 0) {
					$('.editor .sub-editor .cc-search .search-target').text('');
					$('.editor .sub-editor.concordances .results').empty();
				}
			}
		}).on('keydown', '.sub-editor .cc-search .search-target', function(e) {
			if (e.which == 13) {
				e.preventDefault();
				var txt = $(this).text();
				if (txt.length > 2)
					UI.getConcordance(txt, 1);
			} else {
				if ($('.editor .sub-editor .cc-search .search-source').text().length > 0) {
					$('.editor .sub-editor .cc-search .search-source').text('');
					$('.editor .sub-editor.concordances .results').empty();
				}
			}
		}).on('click', '.sub-editor .gl-search .search-glossary', function(e) {
			e.preventDefault();
			var txt = $(this).parents('.gl-search').find('.search-source').text();
			segment = $(this).parents('section').first();
			if (txt.length > 1) {
				UI.getGlossary(segment, false);
			} else {
				APP.alert({msg: 'Please insert a string of two letters at least!'});
			}

		}).on('keydown', '.sub-editor .gl-search .search-source', function(e) {
			if (e.which == 13) {
				e.preventDefault();
				var txt = $(this).text();
				if (txt.length > 2) {
					segment = $(this).parents('section').first();
					UI.getGlossary(segment, false);
				}
			}
		}).on('input', '.sub-editor .gl-search .search-target', function(e) {
			gl = $(this).parents('.gl-search').find('.set-glossary');	
			if($(this).text() == '') {
				gl.addClass('disabled');
			} else {
				gl.removeClass('disabled');
			}
		}).on('click', '.sub-editor .gl-search .set-glossary', function(e) {
			e.preventDefault();
		}).on('click', '.sub-editor .gl-search .set-glossary:not(.disabled)', function(e) {
			e.preventDefault();
			if($(this).parents('.gl-search').find('.search-source').text() == '') {
				APP.alert({msg: 'Please insert a glossary term.'});
				return false;
			} else {
				UI.setGlossaryItem();
			}
		}).on('click', '.sub-editor .gl-search .comment a', function(e) {
			e.preventDefault();
			$(this).parents('.comment').find('.gl-comment').toggle();
		}).on('paste', '.editarea', function(e) {
			UI.saveInUndoStack('paste');
			$('#placeHolder').remove();
			var node = document.createElement("div");
			node.setAttribute('id', 'placeHolder');
			removeSelectedText($(this));
			insertNodeAtCursor(node);
			if(UI.isFirefox) pasteHtmlAtCaret('<div id="placeHolder"></div>');
			var ev = (UI.isFirefox) ? e : event;
			handlepaste(this, ev);

			$(window).trigger({
				type: "pastedInEditarea",
				segment: segment
			});

			setTimeout(function() {
				UI.saveInUndoStack('paste');
			}, 100);
			UI.lockTags(UI.editarea);
			UI.currentSegmentQA();
		}).on('click', 'a.close', function(e, param) {
			e.preventDefault();
			var save = (typeof param == 'undefined') ? 'noSave' : param;
			UI.closeSegment(UI.currentSegment, 1, save);
		}).on('keyup', '.editor .editarea', function(e) {
            if ( e.which == 13 ){
                //replace all divs with a br and remove all br without a class
//                var divs = $( this ).find( 'div' );
//                if( divs.length ){
//					divs.each(function(){
//						$(this).find( 'br:not([class])' ).remove();
//						$(this).prepend( $('<br class="' + config.crPlaceholderClass + '" />' ) ).replaceWith( $(this).html() );
//					});
//                } else {
//                    $(this).find( 'br:not([class])' ).replaceWith( $('<br class="' + config.crPlaceholderClass + '" />') );
//                }
            }
		});
		UI.toSegment = true;
		if (!this.segmentToScrollAtRender)
			UI.gotoSegment(this.startSegmentId);

		$(".end-message-box a.close").on('click', function(e) {
			e.preventDefault();
			UI.body.removeClass('justdone');
		})

		this.checkIfFinishedFirst();

		$("section .close").bind('keydown', 'Shift+tab', function(e) {
			e.preventDefault();
			$(this).parents('section').find('a.translated').focus();
		})

		$("a.translated").bind('keydown', 'tab', function(e) {
			e.preventDefault();
			$(this).parents('section').find('.close').focus();
		})

		$("#navSwitcher").on('click', function(e) {
			e.preventDefault();
		})
		$("#pname").on('click', function(e) {
			e.preventDefault();
			UI.toggleFileMenu();
		})
		$("#jobNav .jobstart").on('click', function(e) {
			e.preventDefault();
			UI.scrollSegment($('#segment-' + config.firstSegmentOfFiles[0].first_segment));
		})
		$("#jobMenu").on('click', 'li:not(.currSegment)', function(e) {
			e.preventDefault();
			UI.renderAndScrollToSegment($(this).attr('data-segment'), true);
		})
		$("#jobMenu").on('click', 'li.currSegment', function(e) {
			e.preventDefault();
			UI.pointToOpenSegment();
		})
		$("#jobNav .prevfile").on('click', function(e) {
			e.preventDefault();
			currArtId = $(UI.currentFile).attr('id').split('-')[1];
			$.each(config.firstSegmentOfFiles, function() {
				if (currArtId == this.id_file)
					firstSegmentOfCurrentFile = this.first_segment;
			});
			UI.scrollSegment($('#segment-' + firstSegmentOfCurrentFile));
		})
		$("#jobNav .currseg").on('click', function(e) {
			e.preventDefault();

			if (!($('#segment-' + UI.currentSegmentId).length)) {
				$('#outer').empty();
				UI.render({
					firstLoad: false
				})
			} else {
				UI.scrollSegment(UI.currentSegment);
			}
		})
		$("#jobNav .nextfile").on('click', function(e) {
			e.preventDefault();
			if (UI.tempViewPoint == '') { // the user have not used yet the Job Nav
				// go to current file first segment
				currFileFirstSegmentId = $(UI.currentFile).attr('id').split('-')[1]
				$.each(config.firstSegmentOfFiles, function() {
					if (this.id_file == currFileFirstSegmentId)
						firstSegId = this.first_segment;
				});
				UI.scrollSegment($('#segment-' + firstSegId));
				UI.tempViewPoint = $(UI.currentFile).attr('id').split('-')[1];
			}
			$.each(config.firstSegmentOfFiles, function() {
				console.log(this.id_file);
			});
		})

// Search and replace

		$(".searchbox input, .searchbox select").bind('keydown', 'return', function(e) {
			e.preventDefault();
			if ($("#exec-find").attr('disabled') != 'disabled')
				$("#exec-find").click();
		})

		$("#exec-find").click(function(e) {
			e.preventDefault();
			if ($(this).attr('data-func') == 'find') {
				UI.execFind();
			} else {
				if (!UI.goingToNext) {
					UI.goingToNext = true;
					UI.execNext();
				}

			}
		});
		$("#exec-cancel").click(function(e) {
			e.preventDefault();
			$("#filterSwitch").click();
			UI.body.removeClass('searchActive');
			UI.clearSearchMarkers();
			UI.clearSearchFields();
			UI.setFindFunction('find');
			$('#exec-find').removeAttr('disabled');
			$('#exec-replace, #exec-replaceall').attr('disabled', 'disabled');
			UI.enableTagMark();
			if (UI.segmentIsLoaded(UI.currentSegmentId)) {
				UI.gotoOpenSegment();
			} else {
				UI.render({
					firstLoad: false,
					segmentToOpen: UI.currentSegmentId
				})
			}

		});
		$("#exec-replaceall").click(function(e) {
			e.preventDefault();
			APP.confirm({
				name: 'confirmReplaceAll',
				cancelTxt: 'Cancel',
				callback: 'execReplaceAll',
				okTxt: 'Continue',
				msg: "Do you really want to replace this text in all search results? <br>(The page will be refreshed after confirm)"
			});
		});
		$("#exec-replace").click(function(e) {
			e.preventDefault();
			if ($('#search-target').val() == $('#replace-target').val()) {
				APP.alert({msg: 'Attention: you are replacing the same text!'});
				return false;
			}

			if (UI.searchMode == 'onlyStatus') {
				
			} else if (UI.searchMode == 'source&target') {

			} else {
				txt = $('#replace-target').val();
				// todo: rifai il marksearchresults sul target

				$("mark.currSearchItem").text(txt);
				segment = $("mark.currSearchItem").parents('section');
				UI.setTranslation(segment, UI.getStatus(segment), 'replace');
				UI.updateSearchDisplayCount(segment);
				$(segment).attr('data-searchItems', $('mark.searchMarker', segment).length);

				UI.gotoNextResultItem(true);
			}
		});
		$("#enable-replace").on('change', function(e) {
			if (($('#enable-replace').is(':checked')) && ($('#search-target').val() != '')) {
				$('#replace-target, #exec-replace, #exec-replaceall').removeAttr('disabled');
			} else {
				$('#replace-target, #exec-replace, #exec-replaceall').attr('disabled', 'disabled');
			}
		});
		$("#search-source, #search-target").on('input', function(e) {
			if (UI.checkSearchChanges()) {
				UI.setFindFunction('find');
			}
		});
		$("#search-target").on('input', function(e) {
			if ($(this).val() == '') {
				$('#replace-target, #exec-replace, #exec-replaceall').attr('disabled', 'disabled');
			} else {
				if ($('#enable-replace').is(':checked'))
					$('#replace-target, #exec-replace, #exec-replaceall').removeAttr('disabled');
			}
		});
		$("#select-status").on('change', function(e) {
			if (UI.checkSearchChanges()) {
				UI.setFindFunction('find');
			}
		});
		$("#match-case, #exact-match").on('change', function(e) {
			UI.setFindFunction('find');
		});
		this.initEnd = new Date();
		this.initTime = this.initEnd - this.initStart;
		if (this.debug)
			console.log('Init time: ' + this.initTime);
		
	}
});


