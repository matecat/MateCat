UI = null;

UI = {
    render: function(firstLoad, segment_id) {
        seg = (segment_id || false);
        this.segmentToScrollAtRender = (seg)? seg : false;        
        this.isWebkit = $.browser.webkit;
        this.isChrome = $.browser.webkit && !!window.chrome;
        this.isFirefox = $.browser.mozilla;
        this.isSafari = $.browser.webkit && !window.chrome;
        this.isMac = (navigator.platform == 'MacIntel') ? true : false;
        this.body = $('body');
        this.firstLoad = firstLoad; 
//        if (firstLoad)
//            this.startRender = true;
        this.initSegNum = 200; // number of segments initially loaded
        this.moreSegNum = 50;
        this.loadingMore = false;
        this.infiniteScroll = true;
        this.noMoreSegmentsAfter = false;
        this.noMoreSegmentsBefore = false;
        this.blockButtons = false;
        this.blockOpenSegment = false;
        this.dmp = new diff_match_patch();
        this.beforeDropEditareaHTML = '';
        this.beforeDropSearchSourceHTML = '';
        this.currentConcordanceField = null;
        this.droppingInEditarea = false;
        this.draggingInsideEditarea = false;
        this.undoStack = [];
        this.undoStackPosition = 0;
        this.ccSourceUndoStack = [];
        this.ccSourceUndoStackPosition = 0;
        this.ccTargetUndoStack = [];
        this.ccTargetUndoStackPosition = 0;
        this.tagSelection = false;
        this.nextSegmentIdByServer = 0;
        this.cursorPlaceholder = '[[placeholder]]';
        this.tempViewPoint = '';

        /**
         * Global Warnings array definition.
         */
        this.globalWarnings = [];

        this.downOpts = { offset: '130%' };
        this.upOpts = { offset: '-40%' };
        this.readonly = (this.body.hasClass('archived')) ? true : false;
        this.suggestionShortcutLabel = 'ALT+' + ((UI.isMac) ? "CMD" : "CTRL") + '+';

        this.taglockEnabled = true;
        this.debug = Loader.detect('debug');
        this.checkTutorialNeed();

        UI.detectStartSegment();
        var openCurrentSegmentAfter = ((!seg)&&(!this.firstLoad))? true : false;
//        if((!seg)&&(!this.firstLoad)) this.gotoSegment(this.currentSegmentId);
        UI.getSegments(openCurrentSegmentAfter);
    },
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
        var bb = $.cookie('noAlertConfirmTranslation');
        this.alertConfirmTranslationEnabled = (typeof bb == 'undefined') ? true : false;
        this.customSpellcheck = false;
        setTimeout(function() {
            UI.blockGetMoreSegments = false;
        }, 1000);
//        this.heavy = ($('section').length > 200)? true : false;
        this.detectFirstLast();
        this.reinitMMShortcuts();
        this.initSegmentNavBar();
        rangy.init();
        this.savedSel = null;
        this.savedSelActiveElement = null;
        this.firstOpenedSegment = false;
        this.autoscrollCorrectionEnabled = true;
        this.viewConcordanceInContextMenu = true;
        if(!this.viewConcordanceInContextMenu) $('#searchConcordance').hide();
        this.viewSpellCheckInContextMenu = true;
        if(!this.viewSpellCheckInContextMenu) $('#spellCheck').hide();
        setTimeout(function() {
            UI.autoscrollCorrectionEnabled = false;
        }, 2000);
        this.checkSegmentsArray = {};
        this.markTags();
        this.setContextMenu();
        this.createJobMenu();
        $('#alertConfirmTranslation p').text('To confirm your translation, please press on Translated or use the shortcut ' + ((UI.isMac) ? 'CMD' : 'CTRL') + '+Enter.');

        // SET EVENTS

        $("body").bind('keydown', 'Ctrl+return', function(e) {
            e.preventDefault();
            $('.editor .translated').click();
        }).bind('keydown', 'Meta+return', function(e) {
            e.preventDefault();
            $('.editor .translated').click();
        }).bind('keydown', 'Ctrl+pageup', function(e) {
            e.preventDefault();
//            alert('pageup');
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
        }).bind('keydown', 'Meta+right', function(e) {
            e.preventDefault();
            UI.copySource();
        }).bind('keydown', 'Ctrl+z', function(e) {
            e.preventDefault();
            UI.undoInSegment(segment);
        }).bind('keydown', 'Meta+z', function(e) {
            e.preventDefault();
            UI.undoInSegment(segment);
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
        }).bind('keydown', 'Backspace', function(e) {
        }).bind('keydown','Meta+f', function(e){ 
            e.preventDefault();
            $('body').addClass('filterOpen');
            $('#search-source').focus();
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
        })

        $(window).on('scroll', function(e) {
            UI.browserScrollPositionRestoreCorrection();
//            UI.setSegmentPointer();
        })
// no more used:
        $("header .filter").click(function(e) {
            e.preventDefault();
            UI.body.toggleClass('filtering');
        })
        $("#filterSwitch").bind('click', function(e){ 
            e.preventDefault();
            if($('body').hasClass('filterOpen')) {
                $('body').removeClass('filterOpen');
            } else {
                $('body').addClass('filterOpen');
                $('#search-source').focus();
            }
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

        /*
         $(document).mouseup(function(e) {
         if($(e.target).parent("a.m-notification").length==0) {
         $(".m-notification").removeClass("menu-open");
         $("fieldset#signin_menu").hide();
         }
         });	
         */
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

        $("#outer").on('click', 'a.number', function(e) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }).on('click', 'a.status', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var segment = $(this).parents("section");
            var statusMenu = $("ul.statusmenu", segment);

            UI.createStatusMenu(statusMenu);
            statusMenu.show();
            var autoCloseStatusMenu = $('html').bind("click.vediamo", function(event) {
                $("ul.statusmenu").hide();
                $('html').unbind('click.vediamo');
                UI.removeStatusMenu(statusMenu);
            });
        }).on('blur', '.graysmall .translation', function(e) {
            e.preventDefault();
            UI.closeInplaceEditor($(this));
        }).on('click', '.graysmall .switch-editing', function(e) {
            e.preventDefault();
            ed = $(this).parent().find('.translation');
            if(ed.hasClass('editing')) {
                UI.closeInplaceEditor(ed);
            } else {
                UI.openInplaceEditor(ed);
            }
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

        $("form#fileDownload").submit(function() {
            if ($("#notifbox").hasClass("warningbox")) {
                var a = APP.confirm({cancelTxt:'Fix errors', onCancel: 'goToFirstError', okTxt: 'Continue', msg: "Potential errors (missing tags, numbers etc.) found in the text. <br>If you continue, part of the content could be untranslated - look for the string \"UNTRANSLATED_CONTENT\" in the downloaded file(s).<br><br>Continue downloading or fix the error in MateCat:"});
//                if (!a) {
//                    return false;
//                }
            }
//            if (UI.isChrome) {
//                $('.download-chrome').addClass('d-open');
//                setTimeout(function() {
//                    $('.download-chrome').removeClass('d-open');
//                }, 7000);
//
//            }
//            return true;
        })

        $('html').click(function() {
            $(".menucolor").hide();
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
            if($('#contextMenu').attr('data-sid') == UI.currentSegmentId) {
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
                if(action == 'openConcordance') UI.openConcordance();

                if (operation != 'moving')
                    UI.scrollSegment($('#segment-' + $(this).data('sid')));
            }
            if (UI.debug)
                console.log('Total onclick Editarea: ' + ((new Date()) - this.onclickEditarea));
        }).on('keydown', '.editor .source, .editor .editarea', 'alt+meta+c', function(e) {
            e.preventDefault();
            var selection = window.getSelection();
            if(selection.type == 'Range') { // something is selected
                var str = selection.toString().trim();
                if(str.length) { // the trimmed string is not empty
                    UI.currentSelectedText = str;
                    UI.currentSearchInTarget = ($(this).hasClass('source'))? 0 : 1;
                    UI.openConcordance();
                };
            }; 
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
//				e.preventDefault();
                if ($('.selected', $(this)).length) {
                    e.preventDefault();
                    $('.selected', $(this)).remove();
                    UI.saveInUndoStack('cancel');
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
                    }  catch( e ) { 
                        //Error: Cannot read property 'length' of null 
                        //when we are on first character position in edit area and try to BACKSPACE
                        //console.log(e.message); 
                    }
                }
            }

            if (e.which == 37) { // left arrow
                var selection = window.getSelection();
                var range = selection.getRangeAt(0);
                if (range.startOffset != range.endOffset) { // if something is selected when the left button is pressed...
                    var r = range.startContainer.data;
//                    if ((range.startOffset == 0) && ($(range.startContainer.previousSibling).hasClass('locked'))) 
                    if ((r[0] == '<') && (r[r.length - 1] == '>')) { // if a tag is selected
                        saveSelection();
                        var rr = document.createRange();
                        var referenceNode = $('.rangySelectionBoundary', UI.editarea).first().get(0);
                        rr.setStartBefore(referenceNode);
                        rr.setEndBefore(referenceNode);
                        $('.rangySelectionBoundary', UI.editarea).remove();
                    }
                }
            }

            if (e.which == 38) { // top arrow
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
            }

            if (e.which == 40) { // down arrow
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

            if (e.which == 32) { // space
                setTimeout(function() {
                    UI.saveInUndoStack('space');
                }, 100);
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
            UI.currentSegment.addClass('modified').removeClass('waiting_for_check_result');
            if (UI.draggingInsideEditarea) {
                $(UI.tagToDelete).remove();
                UI.draggingInsideEditarea = false;
                UI.tagToDelete = null;
            }
            if (UI.droppingInEditarea) {
                UI.cleanDroppedTag(UI.editarea, UI.beforeDropEditareaHTML);
            }
            setTimeout(function() {
                UI.lockTags(UI.editarea);
            }, 10);
            UI.registerQACheck();        
        }).on('input', '.editor .cc-search .input', function(e) {
            UI.markTagsInSearch($(this));
        }).on('dblclick', '.source', function(e) {
//            console.log(window.getSelection());
//            console.log(window.getSelection().extentOffset);
//            var selection = window.getSelection();
//            range = selection.getRangeAt(0);
//            selection.removeAllRanges();
//            selection.addRange(range);
//            selection.modify("extend", "left", "character");
        }).on('click', '.editor .source .locked,.editor .editarea .locked', function(e) {
            e.preventDefault();
            e.stopPropagation();
            selectText(this);
            $(this).toggleClass('selected');
        }).on('contextmenu', '.source,.editarea', function(e) {
            if(UI.viewConcordanceInContextMenu||UI.viewSpellCheckInContextMenu) e.preventDefault();
        }).on('mousedown', '.source, .editarea', function(e) {
            if(e.button == 2) { // right click
                if($('#contextMenu').css('display') == 'block') return true;

                var selection = window.getSelection();
                    if(selection.type == 'Range') { // something is selected
                        var str = selection.toString().trim();
                        if(str.length) { // the trimmed string is not empty
                            UI.currentSelectedText = str;
                            
                            UI.currentSearchInTarget = ($(this).hasClass('source'))? 0 : 1;
                            $('#contextMenu').attr('data-sid', $(this).parents('section').attr('id').split('-')[1]);
                            
                            if(UI.customSpellcheck) {
                                var range = selection.getRangeAt(0);
                                var tag = range.startContainer.parentElement;
                                if(($(tag).hasClass('misspelled'))&&(tag === range.endContainer.parentElement)) { // the selected element is in a misspelled element
                                    UI.selectedMisspelledElement = $(tag);
                                    var replacements = '';
                                    var words = $(tag).attr('data-replacements').split(',');
                                    $.each(words, function(item) {
                                        replacements += '<a class="words" href="#">' + this + '</a>';
                                    });
                                    if((words.length == 1)&&(words[0] == '')) {
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
                        };
                    }; 
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
        }).on('drop paste', '.editor .cc-search .input', function(e) {
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
        }).on('click', 'a.translated', function(e) {
            e.preventDefault();
            UI.checkHeaviness();
            if (UI.blockButtons) {
                if (UI.segmentIsLoaded(UI.nextSegmentId) || UI.nextSegmentId == '') {
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
            $(".editarea", UI.nextSegment).trigger("click", "translated");
            UI.changeStatus(this, 'translated', 0);

            UI.markTags();
            UI.lockTags(UI.editarea);
            UI.changeStatusStop = new Date();
            UI.changeStatusOperations = UI.changeStatusStop - UI.buttonClickStop;
//            if(UI.segmentIsLoaded(UI.nextSegmentId)) console.log('UI.segmentIsLoaded(UI.nextSegmentId): ', UI.segmentIsLoaded(UI.nextSegmentId));
//            if(UI.nextSegmentId) console.log('UI.nextSegmentId: ', UI.nextSegmentId);
            
            if (UI.segmentIsLoaded(UI.nextSegmentId) || UI.nextSegmentId == '') {
                if (UI.debug)
                    console.log('next segment is loaded');
            } else {
                if (!UI.noMoreSegmentsAfter) {
                    if (UI.debug)
                        console.log('next segment is not loaded');
                    if (typeof UI.nextSegmentId == 'undefined')
                        return false;
                    UI.reloadWarning();
                } else {
                    return false;
                }
            }
        }).on('click', 'a.approved', function(e) {
            UI.setStatusButtons(this);
            $(".editarea", UI.nextSegment).click();

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
        }).on('click', 'a.copysource', function(e) {
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
        }).on('keydown', '.sub-editor .cc-search .input', 'return', function(e) {
            //if($(this).text().length > 2) UI.getConcordance($(this).text(), 0);
        }).on('keydown', '.sub-editor .cc-search .search-source', function(e) {
            if(e.which == 13) { // enter
                e.preventDefault();
                var txt = $(this).text();
                if(txt.length > 2) UI.getConcordance(txt, 0);
            } else if(e.which == 9) {
            } else {
                if($('.editor .sub-editor .cc-search .search-target').text().length > 0) {
                    $('.editor .sub-editor .cc-search .search-target').text('');
                    $('.editor .sub-editor.concordances .results').empty();
                };
//                $('.editor .sub-editor .cc-search .search-target').val('');
                clearTimeout(UI.liveConcordanceSearchReq);
                UI.liveConcordanceSearchReq = setTimeout(function() {
                    var txt = $('.editor .sub-editor .cc-search .search-source').text();
                    if(txt.length > 2) UI.getConcordance(txt, 0);
                }, 1000);                
//                $('.editor .sub-editor.concordances .results').empty();
            };
        }).on('keydown', '.sub-editor .cc-search .search-target', function(e) {
            if(e.which == 13) {
                e.preventDefault();
                var txt = $(this).text();
                if(txt.length > 2) UI.getConcordance(txt, 1);
            } else {
                if($('.editor .sub-editor .cc-search .search-source').text().length > 0) {
                    $('.editor .sub-editor .cc-search .search-source').text('');
                    $('.editor .sub-editor.concordances .results').empty();
                };
                clearTimeout(UI.liveConcordanceSearchReq);
                UI.liveConcordanceSearchReq = setTimeout(function() {
                    var txt = $('.editor .sub-editor .cc-search .search-target').text();
                    if(txt.length > 2) UI.getConcordance(txt, 1);
                }, 1000);
//                $('.editor .sub-editor.concordances .results').empty();
            };
        }).on('paste', '.editarea', function(e) {
            UI.saveInUndoStack('paste');
            $('#placeHolder').remove();
            var node = document.createElement("div");
            node.setAttribute('id', 'placeHolder');
//            if(window.getSelection().type == 'Caret')) removeSelectedText($(this));
            removeSelectedText($(this));
            insertNodeAtCursor(node);
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
        }).on('click', 'a.close', function(e,param) {
            e.preventDefault();
            var save = (typeof param == 'undefined')? 'noSave' : param;
            UI.closeSegment(UI.currentSegment, 1, save);
        });
/*
        $('#hideAlertConfirmTranslation').bind('change', function(e) {
            if ($('#hideAlertConfirmTranslation').attr('checked')) {
                UI.alertConfirmTranslationEnabled = false;
                $.cookie('noAlertConfirmTranslation', true, {expires: 1000});
            } else {
                UI.alertConfirmTranslationEnabled = true;
                $.removeCookie('noAlertConfirmTranslation');
            }
        })
*/
        UI.toSegment = true;
        if(!this.segmentToScrollAtRender) UI.gotoSegment(this.startSegmentId);

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
/*
            if($('#jobNav').hasClass('open')) {
                $('#jobNav').animate({bottom: "-=300px"}, 500).removeClass('open');
            } else {
                $('#jobNav').animate({bottom: "+=300px"}, 300).addClass('open');
            }
*/
        })
        $("#pname").on('click', function(e) {
            e.preventDefault();
            UI.toggleFileMenu();
        })
        $("#jobNav .jobstart").on('click', function(e) {
            e.preventDefault();
            UI.scrollSegment($('#segment-'+config.firstSegmentOfFiles[0].first_segment));           
        })
        $("#jobMenu").on('click', 'li', function(e) {
            e.preventDefault();
            UI.scrollSegment($('#segment-' + $(this).attr('data-segment')));
        })
        $("#jobNav .prevfile").on('click', function(e) {
            e.preventDefault();
            currArtId = $(UI.currentFile).attr('id').split('-')[1];
            $.each(config.firstSegmentOfFiles, function() {
                if(currArtId == this.id_file) firstSegmentOfCurrentFile = this.first_segment;
            });
            UI.scrollSegment($('#segment-' + firstSegmentOfCurrentFile));           
        })
        $("#jobNav .currseg").on('click', function(e) {
            e.preventDefault();
            
            if(!($('#segment-'+UI.currentSegmentId).length)) {
                $('#outer').empty();
                UI.render(false);
            } else {
                UI.scrollSegment(UI.currentSegment);
            }
        })
        $("#jobNav .nextfile").on('click', function(e) {
            e.preventDefault();
            if(UI.tempViewPoint == '') { // the user have not used yet the Job Nav
                // go to current file first segment
                currFileFirstSegmentId = $(UI.currentFile).attr('id').split('-')[1]
                $.each(config.firstSegmentOfFiles, function() {
                    if(this.id_file == currFileFirstSegmentId) firstSegId = this.first_segment;
                });        
                UI.scrollSegment($('#segment-'+firstSegId));
                UI.tempViewPoint = $(UI.currentFile).attr('id').split('-')[1];
            }
            $.each(config.firstSegmentOfFiles, function() {
                console.log(this.id_file);
            });
        })
        this.initEnd = new Date();
        this.initTime = this.initEnd - this.initStart;
        if (this.debug)
            console.log('Init time: ' + this.initTime);

    },
    toggleFileMenu: function(){

        if( $('#jobMenu').is(':animated') ) {
            return false;
        }
        if(this.body.hasClass('editing')) {
            $('#jobMenu .currSegment').show();
        } else {
            $('#jobMenu .currSegment').hide();            
        }
        var menuHeight = $('#jobMenu').height();
        var startTop = 47 - menuHeight;
        $('#jobMenu').css('top', (47 - menuHeight) + "px");

        if( $('#jobMenu').hasClass('open') ){
                $('#jobMenu').animate({top: "-=" + menuHeight + "px"}, 500).removeClass('open');
        } else {
            $('#jobMenu').animate({top: "+=" + menuHeight + "px"}, 300, function(){
                $('body').on('click', function(e){
                    if( $('#jobMenu').hasClass('open') ){
                        UI.toggleFileMenu();
                    }
                });
            }).addClass('open');
        }
    },
    activateSegment: function() {
        this.createFooter(this.currentSegment);
        this.createButtons();
        this.createHeader();
    },
    cacheObjects: function(editarea) {
        this.editarea = $(editarea);
        // current and last opened object reference caching
        this.lastOpenedSegment = this.currentSegment;
        this.lastOpenedEditarea = $('.editarea', this.currentSegment);
        this.currentSegmentId = this.lastOpenedSegmentId = this.editarea.data('sid');
        this.currentSegment = segment = $('#segment-' + this.currentSegmentId);
        this.currentFile = segment.parent();
        this.currentFileId = this.currentFile.attr('id').split('-')[1];
    },
    changeStatus: function(ob, status, byStatus) {
        var segment = (byStatus) ? $(ob).parents("section") : $('#' + $(ob).data('segmentid'));
        $('.percentuage', segment).removeClass('visible');
        this.setContribution(segment, status, byStatus);
        this.setContributionMT(segment, status, byStatus);
        this.setTranslation(segment, status);
        $(window).trigger({
            type: "statusChanged",
            segment: segment,
            status: status
        });
    },
    checkHeaviness: function() {
        if ($('section').length > 500) {
            UI.reloadToSegment(UI.nextSegmentId);
        }
    },
    checkIfFinished: function(closing) {
        if (((this.progress_perc != this.done_percentage) && (this.progress_perc == '100')) || ((closing) && (this.progress_perc == '100'))) {
            this.body.addClass('justdone');
        } else {
            this.body.removeClass('justdone');
        }
    },
    checkIfFinishedFirst: function() {
        if ($('section').length == $('section.status-translated, section.status-approved').length) {
            this.body.addClass('justdone');
        }
    },
    checkTutorialNeed: function() {
        if (!Loader.detect('tutorial'))
            return false;
        if (!$.cookie('noTutorial')) {
            $('#dialog').dialog({
            });
            $('#hideTutorial').bind('change', function(e) {
                if ($('#hideTutorial').attr('checked')) {
                    $.cookie('noTutorial', true);
                } else {
                    $.removeCookie('noTutorial');
                }
            })

        } else {

        }
    },
    chooseSuggestion: function(w) {
        this.copySuggestionInEditarea(this.currentSegment, $('.editor ul[data-item=' + w + '] li.b .translation').text(), $('.editor .editarea'), $('.editor ul[data-item=' + w + '] ul.graysmall-details .percent').text(), false, false, w);
        this.lockTags(this.editarea);
        this.setChosenSuggestion(w);

        this.editarea.focus().effect("highlight", {}, 1000);
//		this.placeCaretAtEnd(document.getElementById($(this.editarea).attr('id')));

    },
    cleanDroppedTag: function(area,beforeDropHTML) {

        if(area == this.editarea) this.droppingInEditarea = false;

        var diff = this.dmp.diff_main(beforeDropHTML, $(area).html());
        var draggedText = '';
        $(diff).each(function() {
            if (this[0] == 1) {
                draggedText += this[1];
            }
        })

        draggedText = draggedText.replace(/^(\&nbsp;)(.*?)(\&nbsp;)$/gi, "$2");
        var div = document.createElement("div");
        div.innerHTML = draggedText;
        saveSelection();
        var phcode = $('.rangySelectionBoundary')[0].outerHTML;
        $('.rangySelectionBoundary').text(this.cursorPlaceholder);

        closeTag = '</' + $(div).text().trim().replace(/\<(.*?)\s.*?\>/gi, "$1") + '>';
        newTag = $(div).text();

        var newText = area.text().replace(draggedText, newTag);
        area.text(newText);

        area.html(area.html().replace(this.cursorPlaceholder, phcode))
        restoreSelection();
    },
    closeSegment: function(segment, byButton, operation) {
        if ((typeof segment == 'undefined') || (typeof UI.toSegment != 'undefined')) {
            this.toSegment = undefined;
            return true;
        }

        var closeStart = new Date();
        this.autoSave = false;

        $(window).trigger({
            type: "segmentClosed",
            segment: segment
        });

        clearTimeout(this.liveConcordanceSearchReq);

        var saveBrevior = true;
        if (operation != 'noSave') {
//        if (typeof operation != 'undefined') {
            if ((operation == 'translated')||(operation == 'Save'))
                saveBrevior = false;
        }
        if ((segment.hasClass('modified')) && (saveBrevior)) {
//            if(operation != 'noSave')
                this.saveSegment(segment);
//            if (UI.alertConfirmTranslationEnabled) {
//                APP.alert('To confirm your translation, please press on Translated or use the shortcut CMD+Enter.<form><input id="hideAlertConfirmTranslation" type="checkbox"><span>Do not display again</span></form>');
//            }
        }
        this.currentSegment.removeClass('modified');
        this.deActivateSegment(byButton);

        this.lastOpenedEditarea.attr('contenteditable', 'false');
        this.body.removeClass('editing');
        $(segment).removeClass("editor");
        //		$('#downloadProject').focus();
        if (!this.opening) {
            this.checkIfFinished(1);
        }
    },
    copySource: function() {
        //var source_val = $.trim($(".source",this.currentSegment).data('original'));

        var source_val = $.trim($(".source", this.currentSegment).text());
        // Test
        //source_val = source_val.replace(/&quot;/g,'"');

        // Attention I use .text to obtain a entity conversion, by I ignore the quote conversion done before adding to the data-original
        // I hope it still works.

        this.saveInUndoStack('copysource');
        $(".editarea", this.currentSegment).text(source_val).keyup().focus();
        this.saveInUndoStack('copysource');
        $(".editarea", this.currentSegment).effect("highlight", {}, 1000);
        $(window).trigger({
            type: "sourceCopied",
            segment: segment
        });
        this.currentSegment.addClass('modified');
        this.currentSegmentQA();
        this.setChosenSuggestion(0);
        this.lockTags(this.editarea);
    },
    copySuggestionInEditarea: function(segment, translation, editarea, match, decode, auto, which) {

        if (typeof(decode) == "undefined") {
            decode = false;
        }
        percentageClass = this.getPercentuageClass(match);

        if ($.trim(translation) != '') {

            //ANTONIO 20121205    	editarea.text(translation).addClass('fromSuggestion');

            if (decode) {
                translation = htmlDecode(translation);
            }
            this.saveInUndoStack('copysuggestion');
            $(editarea).text(translation).addClass('fromSuggestion');
            this.saveInUndoStack('copysuggestion');
            $('.percentuage', segment).text(match).removeClass('per-orange per-green per-blue per-yellow').addClass(percentageClass).addClass('visible');
            if (which)
                this.currentSegment.addClass('modified');
        }

        // a value of 0 for 'which' means the choice has been made by the
        // program and not by the user

        $(window).trigger({
            type: "suggestionChosen",
            segment: UI.currentSegment,
            element: UI.editarea,
            which: which,
            translation: translation
        });


//		this.placeCaretAtEnd(document.getElementById($(editarea).attr('id')));
    },
    confirmDownload: function(res) {
        if (res) {
            if (UI.isChrome) {
                $('.download-chrome').addClass('d-open');
                setTimeout(function() {
                    $('.download-chrome').removeClass('d-open');
                }, 7000);

            }
        }
    },
    copyToNextIfSame: function(nextSegment) {
        if ($('.source', this.currentSegment).data('original') == $('.source', nextSegment).data('original')) {
            if ($('.editarea', nextSegment).hasClass('fromSuggestion')) {
                $('.editarea', nextSegment).text(this.editarea.text());
            }
        }
    },
    createButtons: function() {
        var disabled = (this.currentSegment.hasClass('loaded')) ? '' : ' disabled="disabled"';
        var buttons = '<li><a id="segment-' + this.currentSegmentId + '-copysource" href="#" class="btn copysource" data-segmentid="segment-' + this.currentSegmentId + '" title="Copy source to target"></a><p>' + ((UI.isMac) ? 'CMD' : 'CTRL') + '+RIGHT</p></li><li style="margin-right:-20px"><a id="segment-' + this.currentSegmentId + '-button-translated" data-segmentid="segment-' + this.currentSegmentId + '" href="#" class="translated"' + disabled + ' >TRANSLATED</a><p>' + ((UI.isMac) ? 'CMD' : 'CTRL') + '+ENTER</p></li>';
//        var buttons = '<li class="tag-mismatch" title="Tag Mismatch">Tag Mismatch</li><li><a id="segment-'+this.currentSegmentId+'-copysource" href="#" class="btn copysource" data-segmentid="segment-'+this.currentSegmentId+'" title="Copy source to target"></a><p>CTRL+RIGHT</p></li><li style="margin-right:-20px"><a id="segment-'+this.currentSegmentId+'-button-translated" data-segmentid="segment-'+this.currentSegmentId+'" href="#" class="translated"'+disabled+' >TRANSLATED</a><p>CTRL+ENTER</p></li>';
        $('#segment-' + this.currentSegmentId + '-buttons').append(buttons);
        $('#segment-' + this.currentSegmentId + '-buttons').before('<p class="warnings"></p>');
//      $('#segment-'+this.currentSegmentId+'-buttons').append(buttons);

    },
    createFooter: function(segment) {
        if ($('.footer', segment).text() != '')
            return false;
        var footer = '<ul class="submenu"><li class="active tab-switcher-tm" id="segment-' + this.currentSegmentId + '-tm"><a tabindex="-1" href="#">Translation matches</a></li><li class="tab-switcher-cc" id="segment-' + this.currentSegmentId + '-cc"><a tabindex="-1" href="#">Concordance</a></li></ul><div class="tab sub-editor matches" id="segment-' + this.currentSegmentId + '-matches"><div class="overflow"></div></div><div class="tab sub-editor concordances" id="segment-' + this.currentSegmentId + '-concordances"><div class="overflow"><div class="cc-search"><div class="input search-source" contenteditable="true" /><div class="input search-target" contenteditable="true" /></div><div class="results"></div></div></div>';
        $('.footer', segment).html(footer);
    },
    createHeader: function() {
        if ($('h2.percentuage', this.currentSegment).length) {
            return;
        }
        var header = '<h2 title="" class="percentuage"><span></span></h2><a href="#" id="segment-' + this.currentSegmentId + '-close" class="close" title="Close this segment"></a>';
        $('#' + this.currentSegment.attr('id') + '-header').html(header);
    },
    createJobMenu: function() {
        var menu = '<nav id="jobMenu" class="topMenu">' +
                    '    <ul>';
        $.each(config.firstSegmentOfFiles, function(index) {
            menu += '<li data-file="' + this.id_file + '" data-segment="' + this.first_segment + '"><a href="#" title="' + this.file_name + '">' + this.file_name + '</a></li>';
        });
        menu += '<li class="currSegment" data-segment="' + UI.currentSegmentId + '"><a href="#">Go to current segment</a></li>';
        
        menu +=    '    </ul>' +
                    '</nav>';
        this.body.append(menu);
        $('#jobMenu li').each(function() {
            APP.fitText($(this), $('a',$(this)), 20);
        });
    },
    createStatusMenu: function(statusMenu) {
        $("ul.statusmenu").empty().hide();
        var menu = '<li class="arrow"><span class="arrow-mcolor"></span></li><li><a href="#" class="f" data-sid="segment-' + this.currentSegmentId + '" title="set draft as status">DRAFT</a></li><li><a href="#" class="d" data-sid="segment-' + this.currentSegmentId + '" title="set translated as status">TRANSLATED</a></li><li><a href="#" class="a" data-sid="segment-' + this.currentSegmentId + '" title="set approved as status">APPROVED</a></li><li><a href="#" class="r" data-sid="segment-' + this.currentSegmentId + '" title="set rejected as status">REJECTED</a></li>';
        statusMenu.html(menu).show();
    },
    deActivateSegment: function(byButton) {
        this.removeButtons(byButton);
        this.removeHeader(byButton);
        this.removeFooter(byButton);
    },
    detectAdjacentSegment: function(segment, direction, times) { // currently unused
        if (!times)
            times = 1;
        if (direction == 'down') {
            var adjacent = segment.next();
            if (!adjacent.is('section'))
                adjacent = this.currentFile.next().find('section:first');
        } else {
            var adjacent = segment.prev();
            if (!adjacent.is('section'))
                adjacent = $('.editor').parents('article').prev().find('section:last');
        }

        if (adjacent.length) {
            if (times == 1) {
                return adjacent;
            } else {
                this.detectAdjacentSegment(adjacent, direction, times - 1);
            }
        } else {
        }
    },
    detectFirstLast: function() {
        var s = $('section');
        this.firstSegment = s.first();
        this.lastSegment = s.last();
    },
    setSegmentPointer: function() {
        if($('.editor').length) {
            if ($('.editor').isOnScreen()) {
                $('#segmentPointer').hide();
            } else {
                if ($(window).scrollTop() > $('.editor').offset().top) {
                    $('#segmentPointer').removeClass('down').css('margin-top', '-10px').addClass('up').show();
                } else {
                    $('#segmentPointer').removeClass('up').addClass('down').css('margin-top', ($(window).height() - 140) + 'px').show();
                }
            };
        }
    },
    detectRefSegId: function(where) {
        var step = this.moreSegNum;
        var seg = (where == 'after') ? $('section').last() : (where == 'before') ? $('section').first() : '';
        var segId = (seg.length) ? seg.attr('id').split('-')[1] : 0;
        return segId;
    },
    detectStartSegment: function() {
        if(this.segmentToScrollAtRender) {
            this.startSegmentId = this.segmentToScrollAtRender;
        } else {
            var hash = window.location.hash.substr(1);
            this.startSegmentId = (hash) ? hash : config.last_opened_segment;            
        }
    },
    getConcordance: function(txt, in_target) {
        $('.cc-search', UI.currentSegment).addClass('loading');
        $('.sub-editor.concordances .overflow .results', this.currentSegment).empty();
        txt = view2rawxliff(txt);
        APP.doRequest({
            data: {
                action: 'getContribution',
                is_concordance: 1,
                from_target: in_target,
                id_segment: UI.currentSegmentId,
                text: txt,
                id_job: config.job_id,
                num_results: UI.numMatchesResults,
                id_translator: config.id_translator
            },
//            context: $('#' + id),
            success: function(d) {
                UI.renderConcordances(d, in_target);
            }
        });
    },  
    getContribution: function(segment, next) {
        var n = (next) ? $('#segment-' + this.nextSegmentId) : $(segment);
        if ($(n).hasClass('loaded')) {
            this.spellCheck();
            if (next) {
                this.nextIsLoaded = true;
            } else {
                this.currentIsLoaded = true;
            }
            if (this.currentIsLoaded)
                this.blockButtons = false;
            if (this.currentSegmentId == this.nextSegmentId)
                this.blockButtons = false;
            if(!next) this.currentSegmentQA();
            return false;
        }
        if ((!n.length) && (next)) {
            return false;
        }
        var id = n.attr('id');
        var id_segment = id.split('-')[1];

        var txt = $('.source', n).text();
        txt = view2rawxliff(txt);
        // Attention: As for copysource, what is the correct file format in attributes? I am assuming html encoded and "=>&quot;
        //txt = txt.replace(/&quot;/g,'"');

        if (!next) {
            $(".loader", n).addClass('loader_on')
        }

        APP.doRequest({
            data: {
                action: 'getContribution',
                is_concordance: 0,
                id_segment: id_segment,
                text: txt,
                id_job: config.job_id,
                num_results: this.numContributionMatchesResults,
                id_translator: config.id_translator
            },
            context: $('#' + id),
            success: function(d) {
                UI.getContribution_success(d, this);
            },
            complete: function(d) {
                UI.getContribution_complete(n);
            }
        });
    },
    getContribution_complete: function(n) {
        $(".loader", n).removeClass('loader_on');
    },
    getContribution_success: function(d, segment) {
        this.renderContributions(d, segment);
        if($(segment).attr('id').split('-')[1] == UI.currentSegmentId) this.currentSegmentQA();
        this.lockTags(this.editarea);
        this.spellCheck();

        this.saveInUndoStack();

        this.blockButtons = false;
        if (d.data.matches.length > 0) {
            $('.submenu li.matches a span', segment).text('(' + d.data.matches.length + ')');
        } else {
            $(".sbm > .matches", segment).hide();
        }
    },
    getMoreSegments: function(where) {
        if ((where == 'after') && (this.noMoreSegmentsAfter))
            return;
        if ((where == 'before') && (this.noMoreSegmentsBefore))
            return;
        if (this.loadingMore) {
            return;
        }
        this.loadingMore = true;

        var segId = this.detectRefSegId(where);

        if (where == 'before') {
            $("section").each(function() {
                if ($(this).offset().top > $(window).scrollTop()) {
                    UI.segMoving = $(this).attr('id').split('-')[1];
                    return false;
                }
            })
        }

        if (where == 'before') {
            $('#outer').addClass('loadingBefore');
        } else if (where == 'after') {
            $('#outer').addClass('loading');
        }

        APP.doRequest({
            data: {
                action: 'getSegments',
                jid: config.job_id,
                password: config.password,
                step: 50,
                segment: segId,
                where: where
            },
            success: function(d) {
                UI.getMoreSegments_success(d);
            }
        });
    },
    getMoreSegments_success: function(d) {
        if(d.error.length) 
            this.processErrors(d.error, 'getMoreSegments');
        where = d.data['where'];
        if (typeof d.data['files'] != 'undefined') {
            var numsegToAdd = 0;
            $.each(d.data['files'], function() {
                numsegToAdd = numsegToAdd + this.segments.length;
            });
            this.renderSegments(d.data['files'], where, false);

            // if getting segments before, UI points to the segment triggering the event 
            if ((where == 'before') && (numsegToAdd)) {
                this.scrollSegment($('#segment-' + this.segMoving));
            }

            this.markTags();
        }
        if (where == 'after') {
        }
        if (d.data['files'].length == 0) {
            if (where == 'after')
                this.noMoreSegmentsAfter = true;
            if (where == 'before')
                this.noMoreSegmentsBefore = true;
        }
        $('#outer').removeClass('loading loadingBefore');
        this.loadingMore = false;
        this.setWaypoints();
    },
    getNextSegment: function(segment, status) {
        var seg = this.currentSegment;
        var rules = (status == 'untranslated') ? 'section.status-draft, section.status-rejected, section.status-new' : 'section.status-' + status;
        var n = $(seg).nextAll(rules).first();

        if (!n.length) {
            n = $(seg).parents('article').next().find(rules).first();
        }
        if (n.length) {
            this.nextSegmentId = $(n).attr('id').split('-')[1];
        } else if ((UI.nextSegmentIdByServer) && (!UI.noMoreSegmentsAfter)) {
            this.nextSegmentId = UI.nextSegmentIdByServer;
        } else {
            this.nextSegmentId = 0;
        }
    },
    getPercentuageClass: function(match) {
        var percentageClass = "";
        m_parse = parseInt(match);
        if (!isNaN(m_parse)) {
            match = m_parse;
        }

        switch (true) {
            case (match == 100):
                percentageClass = "per-green";
                break;
            case (match == 101):
                percentageClass = "per-blue";
                break;
            case(match > 0 && match <= 99):
                percentageClass = "per-orange";
                break;
            case (match == "MT"):
                percentageClass = "per-yellow";
                break;
            default :
                percentageClass = "";
        }
        return percentageClass;
    },
    getSegments: function(openCurrentSegmentAfter) {
        where = (this.startSegmentId) ? 'center' : 'after';
        var step = this.initSegNum;
        $('#outer').addClass('loading');

        APP.doRequest({
            data: {
                action: 'getSegments',
                jid: config.job_id,
                password: config.password,
                step: step,
                segment: this.startSegmentId,
                where: where
            },
            success: function(d) {
                UI.getSegments_success(d,openCurrentSegmentAfter);
            }
        });
    },
    getSegments_success: function(d,openCurrentSegmentAfter) {
        if(d.error.length) 
            this.processErrors(d.error, 'getSegments');
        where = d.data['where'];
        $.each(d.data['files'], function() {
            startSegmentId = this['segments'][0]['sid'];
        })
        if (typeof this.startSegmentId == 'undefined')
            this.startSegmentId = startSegmentId;
        this.body.addClass('loaded');
        if (typeof d.data['files'] != 'undefined') {
            this.renderSegments(d.data['files'], where, this.firstLoad);
            if(openCurrentSegmentAfter) {
                seg = (UI.firstLoad)? this.currentSegmentId : UI.startSegmentId;
                this.gotoSegment(seg);
            }
        }
        $('#outer').removeClass('loading loadingBefore');
        this.loadingMore = false;
        this.setWaypoints();
    },
    getSegmentSource: function(seg) {
        segment = (typeof seg == 'undefined')? this.currentSegment : seg;
        return $('.source', segment).text();
    },
    getSegmentTarget: function(seg) {
        editarea = (typeof seg == 'undefined')? this.editarea : $('.editarea', seg);
        return editarea.text();
    },
    test: function(params) {
        console.log('params: ', params);
        console.log('giusto');
    },
    gotoNextSegment: function() {
        var next = $('.editor').next();
        if (next.is('section')) {
            this.scrollSegment(next);
            $('.editarea', next).trigger("click", "moving");
        } else {
            next = this.currentFile.next().find('section:first');
            if (next.length) {
                this.scrollSegment(next);
                $('.editarea', next).trigger("click", "moving");
            }
        };
    },
    gotoOpenSegment: function() {
        this.scrollSegment(this.currentSegment);
        $(window).trigger({
            type: "scrolledToOpenSegment",
            segment: segment
        });
    },
    gotoPreviousSegment: function() {
        var prev = $('.editor').prev();
        if (prev.is('section')) {
            $('.editarea', prev).click();
        } else {
            prev = $('.editor').parents('article').prev().find('section:last');
            if (prev.length) {
                $('.editarea', prev).click();
            } else {
                this.topReached();
            }
        };
        this.scrollSegment(prev);
    },
    gotoSegment: function(id) {
        var el = $("#segment-" + id + "-target").find(".editarea");
        $(el).click();
    },
    initSegmentNavBar: function() {
        if(config.firstSegmentOfFiles.length == 1) {
            $('#segmentNavBar .prevfile, #segmentNavBar .nextfile').addClass('disabled');
        }
    },
    justSelecting: function() {
        if (window.getSelection().isCollapsed)
            return false;
        var selContainer = $(window.getSelection().getRangeAt(0).startContainer.parentNode);
        return ((selContainer.hasClass('editarea')) && (!selContainer.is(UI.editarea)));
    },
    noTagsInSegment: function(starting) {
        if ((!this.editarea) && (typeof starting == 'undefined'))
            return true;
        if (typeof starting != 'undefined')
            return false;

        var a = $('.source', this.currentSegment).html().match(/\&lt;.*?\&gt;/gi);
        var b = this.editarea.html().match(/\&lt;.*?\&gt;/gi);
        if (a || b) {
            return false;
        } else {
            return true;
        }
    },
    lockTags: function(el) {
        editarea = (typeof el == 'undefined')? UI.editarea : el;
        if (!this.taglockEnabled)
            return false;
        if (this.noTagsInSegment())
            return false;
        $(editarea).each(function(index) {
            saveSelection();

            var tx = $(this).html();

            tx = tx.replace(/\<span/gi, "<pl")
                    .replace(/\<\/span/gi, "</pl")
                    .replace(/&lt;/gi, "<")
                    .replace(/(\<(g|x|bx|ex|bpt|ept|ph|it|mrk)\sid[^<]*?&gt;)/gi, "<pl contenteditable=\"false\" class=\"locked\">$1</pl>")
                    .replace(/\</gi, "&lt;")
                    .replace(/\&lt;pl/gi, "<span")
                    .replace(/\&lt;\/pl/gi, "</span")
                    .replace(/\&lt;div\>/gi, "<div>")
                    .replace(/\&lt;\/div\>/gi, "</div>")
                    .replace(/\&lt;br\>/gi, "<br>")

                    // encapsulate tags of closing
                    .replace(/(&lt;\s*\/\s*(g|x|bx|ex|bpt|ept|ph|it|mrk)\s*&gt;)/gi, "<span contenteditable=\"false\" class=\"locked\">$1</span>");


            if (UI.isFirefox) {
                tx = tx.replace(/(\<span class=\"(.*?locked.*?)\" contenteditable=\"false\"\>)(\<span class=\"(.*?locked.*?)\" contenteditable=\"false\"\>)(.*?)(\<\/span\>){2,}/gi, "$1$5</span>");
                tx = tx.replace(/(\<span class=\"(.*?locked.*?)\" contenteditable=\"false\"\>){2,}(.*?)(\<\/span\>){2,}/gi, "<span contenteditable=\"false\" class=\"$2\">$3</span>");
            } else {
                // fix nested encapsulation
                tx = tx.replace(/(\<span contenteditable=\"false\" class=\"(.*?locked.*?)\"\>)(\<span contenteditable=\"false\" class=\"(.*?locked.*?)\"\>)(.*?)(\<\/span\>){2,}/gi, "$1$5</span>");
                tx = tx.replace(/(\<span contenteditable=\"false\" class=\"(.*?locked.*?)\"\>){2,}(.*?)(\<\/span\>){2,}/gi, "<span contenteditable=\"false\" class=\"$2\">$3</span>");
            }

            tx = tx.replace(/(\<\/span\>)$(\s){0,}/gi, "</span> ");
            $(this).html(tx);
            restoreSelection();
        })        

    },
    markSuggestionTags: function(segment) {
        if (!this.taglockEnabled)
            return false;
        $('.footer .suggestion_source', segment).each(function() {
            $(this).html($(this).html().replace(/(&lt;(g|x|bx|ex|bpt|ept|ph|it|mrk)\sid.*?&gt;)/gi, "<span contenteditable=\"false\" class=\"locked\">$1</span>"));
            if (UI.isFirefox) {
                $(this).html($(this).html().replace(/(\<span class=\"(.*?locked.*?)\" contenteditable=\"false\"\>)(\<span class=\"(.*?locked.*?)\" contenteditable=\"false\"\>)(.*?)(\<\/span\>){2,}/gi, "$1$5</span>"));
            } else {
                $(this).html($(this).html().replace(/(\<span contenteditable=\"false\" class=\"(.*?locked.*?)\"\>)(\<span contenteditable=\"false\" class=\"(.*?locked.*?)\"\>)(.*?)(\<\/span\>){2,}/gi, "$1$5</span>"));
            }
        });
        $('.footer .translation').each(function() {
            $(this).html($(this).html().replace(/(&lt;(g|x|bx|ex|bpt|ept|ph|it|mrk)\sid.*?&gt;)/gi, "<span contenteditable=\"false\" class=\"locked\">$1</span>"));
            if (UI.isFirefox) {
                $(this).html($(this).html().replace(/(\<span class=\"(.*?locked.*?)\" contenteditable=\"false\"\>)(\<span class=\"(.*?locked.*?)\" contenteditable=\"false\"\>)(.*?)(\<\/span\>){2,}/gi, "$1$5</span>"));
            } else {
                $(this).html($(this).html().replace(/(\<span contenteditable=\"false\" class=\"(.*?locked.*?)\"\>)(\<span contenteditable=\"false\" class=\"(.*?locked.*?)\"\>)(.*?)(\<\/span\>){2,}/gi, "$1$5</span>"));
            }
        });
    },
    markTags: function() {
        if (!this.taglockEnabled)
            return false;
        if (this.noTagsInSegment(1))
            return false;

        $('.source').each(function() {
            UI.detectTags(this);
        });

        $('.editarea').each(function() {
            if ($('#segment-' + $(this).data('sid')).hasClass('mismatch'))
                return false;
            UI.detectTags(this);
        });
    },
    closeInplaceEditor: function(ed) {
        $(ed).removeClass('editing');
        $(ed).attr('contenteditable', false);
        $('.graysmall .edit-buttons').remove();
    },
    openInplaceEditor: function(ed) {
        $('.graysmall .translation.editing').each(function() {
            UI.closeInplaceEditor($(this));
        });
        $(ed).addClass('editing').attr('contenteditable', true).after('<span class="edit-buttons"><button class="cancel">Cancel</button><button class="save">Save</button></span>');
        $(ed).focus();
    },
    detectTags: function(area) {
            $(area).html($(area).html().replace(/(&lt;(g|x|bx|ex|bpt|ept|ph|it|mrk)\sid.*?&gt;)/gi, "<span contenteditable=\"false\" class=\"locked\">$1</span>"));
            $(area).html($(area).html().replace(/(&lt;\s*\/\s*(g|x|bx|ex|bpt|ept|ph|it|mrk)\s*&gt;)/gi, "<span contenteditable=\"false\" class=\"locked\">$1</span>"));
            $(area).html($(area).html().replace(/(\<span contenteditable=\"false\" class=\".*?locked.*?\"\>){2,}(.*?)(\<\/span\>){2,}/gi, "<span contenteditable=\"false\" class=\"locked\">$2</span>"));
    },
    markTagsInSearch: function(el) {
        if (!this.taglockEnabled)
            return false;
        var elements = (typeof el == 'undefined')? $('.editor .cc-search .input') : el;
        elements.each(function() {
            UI.detectTags(this);
        });
    },
    millisecondsToTime: function(milli) {
        var milliseconds = milli % 1000;
        var seconds = Math.round((milli / 1000) % 60);
        var minutes = Math.floor((milli / (60 * 1000)) % 60);
        return [minutes, seconds];
    },
    closeContextMenu: function() {
        $('#contextMenu').hide();
        $('#spellCheck .words').remove();
    },
    openConcordance: function() {
        this.closeContextMenu();
        $('.editor .submenu .tab-switcher-cc a').click();
        $('.editor .cc-search .input').text('');
        $('.editor .concordances .results').empty();
        var searchField = (this.currentSearchInTarget)? $('.editor .cc-search .search-target') : $('.editor .cc-search .search-source');
        $(searchField).text(this.currentSelectedText);
        this.markTagsInSearch();

        this.getConcordance(this.currentSelectedText, this.currentSearchInTarget);
    },
    openSegment: function(editarea, operation) {
        this.openSegmentStart = new Date();
        if (!this.byButton) {
            if (this.justSelecting())
                return;
        }
        this.firstOpenedSegment = (this.firstOpenedSegment == 0)? 1 : 2;
        this.byButton = false;
        this.cacheObjects(editarea);
        this.updateJobMenu();
        $(window).trigger({
            type: "segmentOpened",
            segment: segment
        });

        this.clearUndoStack();
        this.saveInUndoStack('open');
        this.autoSave = true;
        this.activateSegment();

        this.getNextSegment(this.currentSegment, 'untranslated');
        this.setCurrentSegment(segment);
        this.currentSegment.addClass('opened');

        this.fillCurrentSegmentWarnings(this.globalWarnings);
        this.setNextWarnedSegment();
        
        this.focusEditarea = setTimeout(function() {
            UI.editarea.focus();
            clearTimeout(UI.focusEditarea);
        }, 100);
        this.currentIsLoaded = false;
        this.nextIsLoaded = false;
        if (!this.readonly)
            this.getContribution(segment, 0);
        this.opening = true;
        if (!(this.currentSegment.is(this.lastOpenedSegment))) {
            var lastOpened = $(this.lastOpenedSegment).attr('id');
            if(lastOpened != 'segment-'+this.currentSegmentId)
                this.closeSegment(this.lastOpenedSegment, 0, operation);
        }
        this.opening = false;
        this.body.addClass('editing');

        segment.addClass("editor");
        if (!this.readonly)
            this.editarea.attr('contenteditable', 'true');
        this.editStart = new Date();
        $(editarea).removeClass("indent");

        this.lockTags();
        if (!this.readonly)
            this.getContribution(segment, 1);
        if (this.debug)
            console.log('close/open time: ' + ((new Date()) - this.openSegmentStart));
    },
    placeCaretAtEnd: function(el) {
        el.focus();
        if (typeof window.getSelection != "undefined"
                && typeof document.createRange != "undefined") {
            var range = document.createRange();
            range.selectNodeContents(el);
            range.collapse(false);
            var sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(range);
        } else if (typeof document.body.createTextRange != "undefined") {
            var textRange = document.body.createTextRange();
            textRange.moveToElementText(el);
            textRange.collapse(false);
            textRange.select();
        }
    },
    registerQACheck: function() {
        clearTimeout(UI.pendingQACheck);
        UI.pendingQACheck = setTimeout(function() {
            UI.currentSegmentQA();
        }, config.segmentQACheckInterval);    
    },
    reinitMMShortcuts: function(a) {
        var keys = (this.isMac) ? 'alt+meta' : 'alt+ctrl';
        $('body').unbind('keydown.alt1').unbind('keydown.alt2').unbind('keydown.alt3').unbind('keydown.alt4').unbind('keydown.alt5');
        $("body, .editarea").bind('keydown.alt1', keys + '+1', function(e) {
            e.preventDefault();
            e.stopPropagation();
//            if (e.which != 97) {
                UI.chooseSuggestion('1');
//            }
        }).bind('keydown.alt2', keys + '+2', function(e) {
            e.preventDefault();
            e.stopPropagation();
//            if (e.which != 98) {
                UI.chooseSuggestion('2');
//            }
        }).bind('keydown.alt3', keys + '+3', function(e) {
            e.preventDefault();
            e.stopPropagation();
//            if (e.which != 99) {
                UI.chooseSuggestion('3');
//            }
        }).bind('keydown.alt4', keys + '+4', function(e) {
            e.preventDefault();
            e.stopPropagation();
//            if (e.which != 100) {
                UI.chooseSuggestion('4');
//            }
        }).bind('keydown.alt5', keys + '+5', function(e) {
            e.preventDefault();
            e.stopPropagation();
//            if (e.which != 101) {
                UI.chooseSuggestion('5');
//            }
        })
    },
    reloadToSegment: function(segmentId) {
        this.infiniteScroll = false;
        config.last_opened_segment = segmentId;
        window.location.hash = segmentId;
        $('#outer').empty();
        this.render(false);
    },
    reloadWarning: function() {
        var m = APP.confirm({msg: 'The next untranslated segment is outside the current view.'});
        if (m) {
            this.infiniteScroll = false;
            config.last_opened_segment = this.nextSegmentId;
            window.location.hash = this.nextSegmentId;
            $('#outer').empty();
            this.render(false);
        }
    },
    pointBackToSegment: function(segmentId) {
        if (!this.infiniteScroll)
            return;
        if (segmentId == '') {
            this.startSegmentId = config.last_opened_segment;
            $('#outer').empty();
            this.render(false);
        } else {
            $('#outer').empty();
            this.render(false);
        }
    },
    pointToOpenSegment: function() {
        this.gotoOpenSegment();
    },
    removeButtons: function(byButton) {
        var segment = (byButton) ? this.currentSegment : this.lastOpenedSegment;
        $('#' + segment.attr('id') + '-buttons').empty();
        $('p.warnings', segment).remove();
    },
    removeFooter: function(byButton) {
    },
    removeHeader: function(byButton) {
        var segment = (byButton) ? this.currentSegment : this.lastOpenedSegment;
        $('#' + segment.attr('id') + '-header').empty();
    },
    removeStatusMenu: function(statusMenu) {
        statusMenu.empty().hide();
    },
    renderConcordances: function(d, in_target) {
        segment = this.currentSegment;
        segment_id = this.currentSegmentId;
        $('.sub-editor.concordances .overflow .results', segment).empty();        
        $.each(d.data.matches, function(index) {
            if ((this.segment == '') || (this.translation == ''))
                return;
            var disabled = (this.id == '0') ? true : false;
            cb = this['created_by'];
            cl_suggestion = UI.getPercentuageClass(this['match']);
            var leftTxt = (in_target)? this.translation : this.segment;
            leftTxt = leftTxt.replace(/\#\{/gi, "<mark>");
            leftTxt = leftTxt.replace(/\}\#/gi, "</mark>");
            var rightTxt = (in_target)? this.segment : this.translation;
            rightTxt = rightTxt.replace(/\#\{/gi, "<mark>");
            rightTxt = rightTxt.replace(/\}\#/gi, "</mark>");
            $('.sub-editor.concordances .overflow .results', segment).append('<ul class="graysmall" data-item="' + (index + 1) + '" data-id="' + this.id + '"><li class="sugg-source">' + ((disabled) ? '' : ' <a id="' + segment_id + '-tm-' + this.id + '-delete" href="#" class="trash" title="delete this row"></a>') + '<span id="' + segment_id + '-tm-' + this.id + '-source" class="suggestion_source">' + leftTxt + '</span></li><li class="b sugg-target"><span class="switch-editing">Edit</span><span id="' + segment_id + '-tm-' + this.id + '-translation" class="translation">' + rightTxt + '</span></li><ul class="graysmall-details"><li class="percent ' + cl_suggestion + '">' + (this.match) + '</li><li>' + this['last_update_date'] + '</li><li class="graydesc">Source: <span class="bold">' + cb + '</span></li></ul></ul>');
        });
        $('.cc-search', this.currentSegment).removeClass('loading');
        this.setDeleteSuggestion(segment);
    },
    renderContributions: function(d, segment) {
        var isActiveSegment = $(segment).hasClass('editor');
        var editarea = $('.editarea', segment);
        if (d.data.matches.length) {
            var editareaLength = editarea.text().trim().length;
            if (isActiveSegment) {
                editarea.removeClass("indent");
            } else {
                if (editareaLength == 0)
                    editarea.addClass("indent");
            }
            var translation = d.data.matches[0].translation;
            var perc_t = $(".percentuage", segment).attr("title");

            $(".percentuage", segment).attr("title", '' + perc_t + "Created by " + d.data.matches[0].created_by);
            var match = d.data.matches[0].match;

            var copySuggestionDone = false;
            if (editareaLength == 0) {
                UI.copySuggestionInEditarea(segment, translation, editarea, match, true, true, 0);
                UI.setChosenSuggestion(1);
                copySuggestionDone = true;
            } else {
            }
            var segment_id = segment.attr('id');
            $(segment).addClass('loaded');
            $('.sub-editor.matches .overflow', segment).empty();

            $.each(d.data.matches, function(index) {
                if ((this.segment == '') || (this.translation == ''))
                    return;
                var disabled = (this.id == '0') ? true : false;
                cb = this['created_by'];
                cl_suggestion = UI.getPercentuageClass(this['match']);

                if (!$('.sub-editor.matches', segment).length) {
                    UI.createFooter(segment);
                }
                // Attention Bug: We are mixing the view mode and the raw data mode.
                // before doing a enanched view you will need to add a data-original tag
                $('.sub-editor.matches .overflow', segment).append('<ul class="graysmall" data-item="' + (index + 1) + '" data-id="' + this.id + '"><li class="sugg-source">' + ((disabled) ? '' : ' <a id="' + segment_id + '-tm-' + this.id + '-delete" href="#" class="trash" title="delete this row"></a>') + '<span id="' + segment_id + '-tm-' + this.id + '-source" class="suggestion_source">' + this.segment + '</span></li><li class="b sugg-target"><span class="switch-editing">Edit</span><span class="graysmall-message">' + UI.suggestionShortcutLabel + (index + 1) + '</span><span id="' + segment_id + '-tm-' + this.id + '-translation" class="translation">' + this.translation + '</span></li><ul class="graysmall-details"><li class="percent ' + cl_suggestion + '">' + (this.match) + '</li><li>' + this['last_update_date'] + '</li><li class="graydesc">Source: <span class="bold">' + cb + '</span></li></ul></ul>');
            });
            UI.markSuggestionTags(segment);
            UI.setDeleteSuggestion(segment);
            UI.lockTags();
            if (copySuggestionDone) {
                if (isActiveSegment) {
                }
            }

            $('.translated', segment).removeAttr('disabled');
            $('.draft', segment).removeAttr('disabled');
        } else {
            if (UI.debug)
                console.log('no matches');
            $(segment).addClass('loaded');
            $('.sub-editor.matches .overflow', segment).append('<ul class="graysmall message"><li>Sorry. Can\'t help you this time. Check the language pair if you feel this is weird.</li></ul>');
        }
    },
    renderSegments: function(files, where, starting) {
        $.each(files, function() {
            var newFile = '';
            var fs = this['file_stats'];
            var fid = fs['ID_FILE'];
            var articleToAdd = ((where == 'center') || (!$('#file-' + fid).length)) ? true : false;

            if (articleToAdd) {
                filenametoshow = truncate_filename(this.filename, 40);
                newFile += '<article id="file-' + fid + '" class="loading">' +
                        '	<ul class="projectbar" data-job="job-' + this.jid + '">' +
                        '		<li class="filename">' +
                        '			<form class="download" action="/" method="post">' +
                        '				<input type=hidden name="action" value="downloadFile">' +
                        '				<input type=hidden name="id_job" value="' + this.jid + '">' +
                        '				<input type=hidden name="id_file" value="' + fid + '">' +
                        '				<input type=hidden name="filename" value="' + this.filename + '">' +
                        '				<input type=hidden name="password" value="' + config.password + '">' +
                        '				<!--input title="Download file" name="submit" type="submit" value="" class="downloadfile" id="file-' + fid + '-download" -->' +
                        '			</form>' +
                        '			<h2 title="' + this.filename + '">' + filenametoshow + '</div>' +
                        '		</li>' +
                        '		<li style="text-align:center;text-indent:-20px">' +
                        '			<strong>' + this.source + '</strong> [<span class="source-lang">' + this.source_code + '</span>]&nbsp;>&nbsp;<strong>' + this.target + '</strong> [<span class="target-lang">' + this.target_code + '</span>]' +
                        '		</li>' +
                        '		<li class="wordcounter">' +
                        '			Payable Words: <strong>' + fs['TOTAL_FORMATTED'] + '</strong>' +
//                '			To-do: <strong>' + fs['DRAFT_FORMATTED'] + '</strong>'+
                        '			<span id="rejected" class="hidden">Rejected: <strong>' + fs['REJECTED_FORMATTED'] + '</strong></span>' +
                        '		</li>' +
                        '	</ul>';
            }

            var t = config.time_to_edit_enabled;
            $.each(this.segments, function(index) {
                var escapedSegment = htmlEncode(this.segment.replace(/\"/g, "&quot;"));
                newFile += '<section id="segment-' + this.sid + '" class="status-' + ((!this.status) ? 'new' : this.status.toLowerCase()) + '">' +
                        '	<a tabindex="-1" href="#' + this.sid + '"></a>' +
                        '	<span class="number">' + this.sid + '</span>' +
                        '	<div class="body">' +
                        '		<div class="header toggle" id="segment-' + this.sid + '-header">' +
                        '                 <h2 title="" class="percentuage"><span></span></h2><a href="#" id="segment-' + this.sid + '-close" class="close" title="Close this segment"></a>' +
                        '               </div>' +
                        '		<div class="text">' +
                        '			<div class="wrap">' +
                        '				<div class="outersource"><div class="source item" tabindex="0" id="segment-' + this.sid + '-source" data-original="' + escapedSegment + '">' + this.segment + '</div>' +
                        '				<div class="target item" id="segment-' + this.sid + '-target">' +
                        '					<span class="hide toggle"> ' +
                        '						<a href="#" class="warning normalTip exampleTip" title="Warning: as">!</a>' +
                        '					</span>' +
                        '					<div class="textarea-container">' +
                        '						<span class="loader"></span>' +
                        '						<div class="editarea invisible" contenteditable="false" spellcheck="true" lang="' + config.target_lang.toLowerCase() + '" id="segment-' + this.sid + '-editarea" data-sid="' + this.sid + '">' + ((!this.translation) ? '' : this.translation) + '</div>' +
                        '					</div> <!-- .textarea-container -->' +
                        '				</div> <!-- .target -->' +
                        '			</div></div> <!-- .wrap -->' +
                        '						<ul class="buttons toggle provissima" id="segment-' + this.sid + '-buttons"></ul>' +
                        '			<div class="status-container">' +
                        '				<a href=# title="' + ((!this.status) ? 'Change segment status' : this.status.toLowerCase() + ', click to change it') + '" class="status" id="segment-' + this.sid + '-changestatus"></a>' +
                        '			</div> <!-- .status-container -->' +
                        '		</div> <!-- .text -->' +
                        '		<div class="timetoedit" data-raw_time_to_edit="' + this.time_to_edit + '">' +
                        ((t) ? '			<span class=edit-min>' + this.parsed_time_to_edit[1] + '</span>m:' : '') +
                        ((t) ? '			<span class=edit-sec>' + this.parsed_time_to_edit[2] + '</span>s' : '') +
                        '		</div>' +
                        '		<div class="footer toggle"></div> <!-- .footer -->     ' +
                        '	</div> <!-- .body -->' +
                        '	<ul class="statusmenu"></ul>' +
                        '</section> ';
            })

            if (articleToAdd) {
                newFile += '</article>';
            }

            if (articleToAdd) {
                if (where == 'before') {
                    if (typeof lastArticleAdded != 'undefined') {
                        $('#file-' + fid).after(newFile);
                    } else {
                        $('article').first().before(newFile);
                    }
                    lastArticleAdded = fid;
                } else if (where == 'after') {
                    $('article').last().after(newFile);
                } else if (where == 'center') {
                    $('#outer').append(newFile);
                }
            } else {
                if (where == 'before') {
                    $('#file-' + fid).prepend(newFile);
                } else if (where == 'after') {
                    $('#file-' + fid).append(newFile);
                }
            }
        })
        if (starting) {
            this.init();
        }
    },
    saveSegment: function(segment) {
        var status = (segment.hasClass('status-translated')) ? 'translated' : (segment.hasClass('status-approved')) ? 'approved' : (segment.hasClass('status-rejected')) ? 'rejected' : (segment.hasClass('status-new')) ? 'new' : 'draft';
        if (status == 'new') {
            status = 'draft';
        }
        this.setTranslation(segment, status);
    },
    scrollSegment: function(segment) {
        if(!segment.length) {
            $('#outer').empty();
            this.render(false, segment.selector.split('-')[1]);
        };
        var spread = 23;
        var current = this.currentSegment;
        var previousSegment = $(segment).prev('section');

        if (!previousSegment.length) {
            previousSegment = $(segment);
            spread = 103;
        }
        var destination = "#" + previousSegment.attr('id');
        var destinationTop = $(destination).offset().top;
        if (this.firstScroll) {
            destinationTop = destinationTop + 100;
            this.firstScroll = false;
        }

        if ($(current).length) { // if there is an open segment
            if ($(segment).offset().top > $(current).offset().top) { // if segment to open is below the current segment
                if (!current.is($(segment).prev())) { // if segment to open is not the immediate follower of the current segment
                    var diff = (this.firstLoad) ? ($(current).height() - 200 + 120) : 20;
                    destinationTop = destinationTop - diff;
                } else { // if segment to open is the immediate follower of the current segment
                    destinationTop = destinationTop - spread;
                }
            } else { // if segment to open is above the current segment
                destinationTop = destinationTop - spread;
            }
        } else { // if no segment is opened
            destinationTop = destinationTop - spread;
        }

        $("html,body").stop();
        $("html,body").animate({
            scrollTop: destinationTop - 20
        }, 500);
    },
    segmentIsLoaded: function(segmentId) {
        if ($('#segment-' + segmentId).length) {
            return true;
        } else {
            return false;
        }
    },
    setChosenSuggestion: function(w) {
        this.editarea.data('lastChosenSuggestion', w);
    },
    setContribution: function(segment, status, byStatus) {
        if ((status == 'draft') || (status == 'rejected'))
            return false;
        var source = $('.source', segment).text();
        // Attention: to be modified when we will be able to lock tags.
        var target = $('.editarea', segment).text();
        if ((target == '') && (byStatus)) {
            APP.alert('Cannot change status on an empty segment. Add a translation first!');
        }
        if (target == '') {
            return false;
        }            
        this.updateContribution(source, target);
    },
    setContribution: function(segment, status, byStatus) {
        if ((status == 'draft') || (status == 'rejected'))
            return false;
        var source = $('.source', segment).text();
        // Attention: to be modified when we will be able to lock tags.
        var target = $('.editarea', segment).text();
        if ((target == '') && (byStatus)) {
            APP.alert('Cannot change status on an empty segment. Add a translation first!');
        }
        if (target == '') {
            return false;
        }            
        this.updateContribution(source, target);
    },
    spellCheck: function(ed) {
        if(!UI.customSpellcheck) return false;
        editarea = (typeof ed == 'undefined')? UI.editarea : $(ed);
        if($('#contextMenu').css('display') == 'block') return true;

        APP.doRequest({
            data: {
                action: 'getSpellcheck',
                lang: config.target_rfc,
                sentence: UI.editarea.text()
            },
            context: editarea,
            success: function(data) {
                ed = this;
                $.each(data.result, function(key, value) { //key --> 0: { 'word': { 'offset':20, 'misses':['word1','word2'] } }

                    var word = Object.keys( value )[0];
                    replacements = value[word]['misses'].join(",");

                    var Position = [
                        parseInt( value[word]['offset'] ),
                        parseInt( value[word]['offset'] ) + parseInt( word.length )
                    ];

                    var sentTextInPosition = ed.text().substring( Position[0], Position[1] );
                    //console.log(sentTextInPosition);

                        var re = new RegExp("(\\b" + word + "\\b)","gi");
                        $(ed).html($(ed).html().replace(re, '<span class="misspelled" data-replacements="' + replacements + '">$1</span>' ));
                        // fix nested encapsulation
                        $(ed).html($(ed).html().replace(/(\<span class=\"misspelled\" data-replacements=\"(.*?)\"\>)(\<span class=\"misspelled\" data-replacements=\"(.*?)\"\>)(.*?)(\<\/span\>){2,}/gi, "$1$5</span>"));
//
//                    });
                });
            }
        });
    },
    addWord: function(word) {
        APP.doRequest({
            data: {
                action: 'setSpellcheck',
                slang: config.target_rfc,
                word: word
            },
            success: function(data) {

            }
        });
    },
    updateContribution: function(source, target) {
        source = view2rawxliff(source);
        target = view2rawxliff(target);
        APP.doRequest({
            data: {
                action: 'setContribution',
                id_job: config.job_id,
                source: source,
                target: target,
                source_lang: config.source_lang,
                target_lang: config.target_lang,
                password: config.password,
                id_translator: config.id_translator,
                private_translator: config.private_translator,
                id_customer: config.id_customer,
                private_customer: config.private_customer
            },
            success: function(d) {
                if(d.error.length) 
                    UI.processErrors(d.error, 'setContribution');
            }
        });
    },
    setContributionMT: function(segment, status, byStatus) {
        if ((status == 'draft') || (status == 'rejected'))
            return false;
        var source = $('.source', segment).text();
        source = view2rawxliff(source);
        // Attention: to be modified when we will be able to lock tags.
        var target = $('.editarea', segment).text();
        if ((target == '') && (byStatus)) {
            APP.alert('Cannot change status on an empty segment. Add a translation first!');
        }
        if (target == '') {
            return false;
        }
        target = view2rawxliff(target);
        var languages = $(segment).parents('article').find('.languages');
        var source_lang = $('.source-lang', languages).text();
        var target_lang = $('.target-lang', languages).text();
        var id_translator = config.id_translator;
        var private_translator = config.private_translator;
        var id_customer = config.id_customer;
        var private_customer = config.private_customer;

        var info = $(segment).attr('id').split('-');
        var id_segment = info[1];
        var time_to_edit = UI.editTime;
        var chosen_suggestion = $('.editarea', segment).data('lastChosenSuggestion');

        APP.doRequest({
            data: {
                action: 'setContributionMT',
                id_segment: id_segment,
                source: source,
                target: target,
                source_lang: config.source_lang,
                target_lang: config.target_lang,
                password: config.password,
                time_to_edit: time_to_edit,
                id_job: config.job_id,
                chosen_suggestion_index: chosen_suggestion
            },
            success: function(d) {
                if(d.error.length) 
                    UI.processErrors(d.error, 'setContributionMT');
            }
        });
    },
    setCurrentSegment: function(segment, closed) {
        var id_segment = this.currentSegmentId;
        if (closed) {
            id_segment = 0;
            UI.currentSegment = undefined;
        } else {
            setTimeout(function() {
                var hash_value = window.location.hash;
                window.location.hash = UI.currentSegmentId;
            }, 300);
        }
        var file = this.currentFile;
        if (this.readonly)
            return;
        APP.doRequest({
            data: {
                action: 'setCurrentSegment',
                password: config.password,
                id_segment: id_segment,
                id_job: config.job_id
            },
            success: function(d) {
                UI.setCurrentSegment_success(d);
            }
        });
    },
    setCurrentSegment_success: function(d) {
        if(d.error.length) 
            this.processErrors(d.error, 'setCurrentSegment');
        this.nextSegmentIdByServer = d.nextSegmentId;
        this.getNextSegment(this.currentSegment, 'untranslated');
    },
    setDeleteSuggestion: function(segment) {
        $('.sub-editor .overflow a.trash', segment).click(function(e) {
            e.preventDefault();
            var ul = $(this).parents('.graysmall');
console.log('a');
            source = $('.suggestion_source', ul).text();
            source = view2rawxliff(source);
            target = $('.translation', ul).text();
            target = view2rawxliff(target);
            ul.remove();

            APP.doRequest({
                data: {
                    action: 'deleteContribution',
                    source_lang: config.source_lang,
                    target_lang: config.target_lang,
                    id_job: config.job_id,
                    password: config.password,
                    seg: source,
                    tra: target,
                    id_translator: config.id_translator
                },
                success: function(d) {
                    UI.setDeleteSuggestion_success(d);
                }
            });
        });
    },
    setDeleteSuggestion_success: function(d) {
        if(d.error.length) 
            this.processErrors(d.error, 'setDeleteSuggestion');
        if (this.debug)
            console.log('match deleted');

        $(".editor .matches .graysmall").each(function(index) {
            $(this).find('.graysmall-message').text(UI.suggestionShortcutLabel + (index + 1));
            $(this).attr('data-item', index + 1);
            UI.reinitMMShortcuts();
        })
    },
    setDownloadStatus: function(stats) {
        var t = 'approved';
        if (parseFloat(stats.TRANSLATED))
            t = 'translated';
        if (parseFloat(stats.DRAFT))
            t = 'draft';
        if (parseFloat(stats.REJECTED))
            t = 'draft';
        $('.downloadtr-button').removeClass("draft translated approved").addClass(t);
        var label = (t == 'translated') ? 'DOWNLOAD TRANSLATION' : 'PREVIEW';
        $('#downloadProject').attr('value', label);
    },
    setProgress: function(stats) { 
        var s = stats;
        m = $('footer .meter');
        var status = 'approved';
        var total = s.TOTAL;
        var t_perc = s.TRANSLATED_PERC;
        var a_perc = s.APPROVED_PERC;
        var d_perc = s.DRAFT_PERC;
        var r_perc = s.REJECTED_PERC;

        var t_perc_formatted = s.TRANSLATED_PERC_FORMATTED;
        var a_perc_formatted = s.APPROVED_PERC_FORMATTED;
        var d_perc_formatted = s.DRAFT_PERC_FORMATTED;
        var r_perc_formatted = s.REJECTED_PERC_FORMATTED;

        var d_formatted = s.DRAFT_FORMATTED;
        var r_formatted = s.REJECTED_FORMATTED;
        var t_formatted = s.TODO_FORMATTED;

        var wph = s.WORDS_PER_HOUR;
        var completion = s.ESTIMATED_COMPLETION;
        if (typeof wph == 'undefined') {
            $('#stat-wph').hide();
        } else {
            $('#stat-wph').show();
        }
        if (typeof completion == 'undefined') {
            $('#stat-completion').hide();
        } else {
            $('#stat-completion').show();
        }

        this.progress_perc = s.PROGRESS_PERC_FORMATTED; 
        this.checkIfFinished();

        this.done_percentage = this.progress_perc;

        $('.approved-bar', m).css('width', a_perc + '%').attr('title', 'Approved ' + a_perc_formatted + '%');
        $('.translated-bar', m).css('width', t_perc + '%').attr('title', 'Translated ' + t_perc_formatted + '%');
        $('.draft-bar', m).css('width', d_perc + '%').attr('title', 'Draft ' + d_perc_formatted + '%');
        $('.rejected-bar', m).css('width', r_perc + '%').attr('title', 'Rejected ' + r_perc_formatted + '%');

        $('#stat-progress').html(this.progress_perc);

        $('#stat-todo strong').html(t_formatted);
        $('#stat-wph strong').html(wph);
        $('#stat-completion strong').html(completion);
    },
    setStatus: function(segment, status) {
        segment.removeClass("status-draft status-translated status-approved status-rejected status-new").addClass("status-" + status);
    },
    setStatusButtons: function(button) {
        this.editStop = new Date();
        var segment = this.currentSegment;
        tte = $('.timetoedit', segment);
        this.editTime = this.editStop - this.editStart;
        this.totalTime = this.editTime + tte.data('raw_time_to_edit');
        var editedTime = this.millisecondsToTime(this.totalTime);
        if (config.time_to_edit_enabled) {
            var editSec = $('.timetoedit .edit-sec', segment);
            var editMin = $('.timetoedit .edit-min', segment);
            editMin.text(this.zerofill(editedTime[0], 2));
            editSec.text(this.zerofill(editedTime[1], 2));
        }
        tte.data('raw_time_to_edit', this.totalTime);
        var statusSwitcher = $(".status", segment);
        statusSwitcher.removeClass("col-approved col-rejected col-done col-draft");
        var statusToGo = ($(button).hasClass('translated')) ? 'untranslated' : '';
        var nextSegment = $('#segment-' + this.nextSegmentId);
        this.nextSegment = nextSegment;
        if (!nextSegment.length) {
            $(".editor:visible").find(".close").trigger('click','Save');
            $('.downloadtr-button').focus();
            return false;
        };
        this.buttonClickStop = new Date();
        this.copyToNextIfSame(nextSegment);
        this.byButton = true;
    },
    collectSegmentErrors: function(segment) {
        var errors = '';
        // tag mismatch
        if (segment.hasClass('mismatch'))
            errors += '01|';
        return errors.substring(0, errors.length - 1);
    },
    goToFirstError: function() {
        location.href = $('#point2seg').attr('href');
    },            
    /**
     * fill segments with relative errors from polling
     * 
     * @param {type} segment
     * @param {type} warnings
     * @returns {undefined}
     */
    setNextWarnedSegment: function(segment_id) {
        segment_id = segment_id || UI.currentSegmentId;
//        UI.globalWarnings = (Loader.detect('test'))? fakeArr : UI.globalWarnings;
        idList = UI.globalWarnings;
        $.each(idList, function(index) {
            if(this > segment_id) {
                $('#point2seg').attr('href', '#'+this);
                return false;
            }
            if(this == idList[idList.length - 1]) {
                $('#point2seg').attr('href', '#'+idList[0]);                
            };
        });        
/*
        counter = 0;
        $.each(UI.globalWarnings, function(key, value) {
            counter++;
            if(counter== 1) UI.firstWarnedSegment = key;
            if(key > segment_id) {
                $('#point2seg').attr('href', '#'+key);
                return false;
            }
            if(counter == Object.keys(UI.globalWarnings).length) {
                $('#point2seg').attr('href', '#'+UI.firstWarnedSegment);                
            };
        });
*/        
    },     
    fillWarnings: function(segment, warnings) {
        //console.log( 'fillWarnings' );
        //console.log( warnings);

        //add Warnings to current Segment
        var parentTag = segment.find('p.warnings').parent();
        var actualWarnings = segment.find('p.warnings');

        $.each(warnings, function(key, value) {
            //console.log(warnings[key]);
            parentTag.before(actualWarnings).append('<p class="warnings">' + value.debug + '</p>');
        });
        actualWarnings.remove();

    },
    /**
     * Walk Warnings to fill right segment
     * 
     * @returns {undefined}
     */
    fillCurrentSegmentWarnings: function(warningDetails) {
        //console.log( 'fillCurrentSegmentWarnings' );
//        console.log('warningDetails: ',warningDetails );
        //scan array    
        try {
            $.each(warningDetails, function(key, value) {
//                console.log('value: ',value );
                if ('segment-' + value.id_segment === UI.currentSegment[0].id) {
                    UI.fillWarnings(UI.currentSegment, $.parseJSON(value.warnings));
                }
            });
        } catch (e) {
            //try to read index 0 of undefined currentSegment when document start
            //console.log( e.toString() );
        }
    },
    //check for segments in warning in the project
    checkWarnings: function(openingSegment) {
        var dd = new Date();
        ts = dd.getTime();
        var seg = (typeof this.currentSegmentId == 'undefined')? this.startSegmentId : this.currentSegmentId;
        var token = seg + '-' + ts.toString();

        APP.doRequest({
            data: {
                action: 'getWarning',
                id_job: config.job_id,
                token: token
            },
            success: function(data) {
                var warningPosition = '';
//                console.log('data.total: '+data.total);
                UI.globalWarnings = data.details;

                //check for errors
                if (UI.globalWarnings.length > 0) {

                    //for now, put only last in the pointer to segment id
                    warningPosition = '#' + data.details[ Object.keys(data.details).sort().shift() ].id_segment;
//                    console.log('warningPosition: ' + warningPosition);

                    if(openingSegment) UI.fillCurrentSegmentWarnings(data.details);

                    //switch to css for warning
                    $('#notifbox').attr('class', 'warningbox').attr("title", "Some translations seems to have TAGS and/or other untraslatables that do not match the source").find('.numbererror').text(UI.globalWarnings.length);

                } else {
                    //if everything is ok, switch css to ok
                    $('#notifbox').attr('class', 'notific').attr("title", "Well done, no errors found!").find('.numbererror').text('');
                    //reset the pointer to offending segment
                    $('#point2seg').attr('href', '#');
                }

                UI.setNextWarnedSegment();
//                $('#point2seg').attr('href', warningPosition);
            }
        });
    },
    currentSegmentQA: function() {
        this.currentSegment.addClass('waiting_for_check_result');
        var dd = new Date();
        ts = dd.getTime();
        var token = this.currentSegmentId + '-' + ts.toString();
        //var src_content = $('.source', this.currentSegment).attr('data-original');  
        var src_content = this.getSegmentSource();  
        var trg_content = this.getSegmentTarget();        
        this.checkSegmentsArray[token] = trg_content;
        APP.doRequest({
            data: {
                action: 'getWarning',
                id: this.currentSegmentId,
                token: token,
                src_content: src_content,
                trg_content: trg_content
            },
            success: function(d) {
                if(UI.currentSegment.hasClass('waiting_for_check_result')) {
                    if(d.details) {
                        $.each(d.details, function(key, value) {
                            id_seg = key;
                            item = value;
                        });                        
                    }

                    // check conditions for results discard 
                    if(!d.total) {
                        $('p.warnings', UI.currentSegment).empty();
                        return;
                    }
                    if(id_seg != UI.currentSegmentId) return;
                    if(UI.editarea.text().trim() != UI.checkSegmentsArray[d.token].trim()) return;
                    
                    
                    UI.fillCurrentSegmentWarnings(d.details); // update warnings
                    delete UI.checkSegmentsArray[d.token]; // delete the token from the tail
                    UI.currentSegment.removeClass('waiting_for_check_result');
                }
            }
        }, 'local');
    },
    setTranslation: function(segment, status) {
        var info = $(segment).attr('id').split('-');
        var id_segment = info[1];
        var file = $(segment).parents('article');
        var status = status;
        // Attention, to be modified when we will lock tags
        var translation = $('.editarea', segment).text();

        if (translation == '')
            return false;
        var time_to_edit = UI.editTime;
        var id_translator = config.id_translator;
        var errors = '';
        errors = this.collectSegmentErrors(segment);
        var chosen_suggestion = $('.editarea', segment).data('lastChosenSuggestion');

        APP.doRequest({
            data: {
                action: 'setTranslation',
                id_segment: id_segment,
                id_job: config.job_id,
                id_first_file: file.attr('id').split('-')[1],
                password: config.password,
                status: status,
                translation: translation,
                time_to_edit: time_to_edit,
                id_translator: id_translator,
                errors: errors,
                chosen_suggestion_index: chosen_suggestion
            },
            success: function(d) {
                UI.setTranslation_success(d, segment, status);
            }
        });
    },
    processErrors: function(err, operation) {
        $.each(err, function() {
            if((operation == 'setTranslation')||(operation == 'setContribution')) {
                if(this['code'] != '-10') {
                    APP.alert("Error in saving the translation. Try the following: \n1) Refresh the page (Ctrl+F5 twice) \n2) Clear the cache in the browser \nIf the solutions above does not resolve the issue, please stop the translation and report the problem to alessandro@translated.net");
                }
            }
            if(this['code'] == '-10') {
                APP.alert("Job canceled or assigned to another translator");
                location.reload();
            }

        });
    },
    setContextMenu: function() {
        var alt = (this.isMac)? '&#x2325;' : 'Alt ';
        var cmd = (this.isMac)? '&#8984;' : 'Ctrl ';
        $('#contextMenu .shortcut .alt').html(alt);
        $('#contextMenu .shortcut .cmd').html(cmd);
    },
    setTranslation_success: function(d, segment, status) {
        if(d.error.length) 
            this.processErrors(d.error);
        if (d.data == 'OK') {
            this.setStatus(segment, status);
            this.setDownloadStatus(d.stats);
            this.setProgress(d.stats);
            //check status of global warnings
            this.checkWarnings(false);
        };
    },
    setWaypoints: function() {
        this.firstSegment.waypoint('remove');
        this.lastSegment.waypoint('remove');
        this.detectFirstLast();
        this.lastSegment.waypoint(function(event, direction) {
            if (direction === 'down') {
                UI.lastSegment.waypoint('remove');
                if (UI.infiniteScroll) {
                    if (!UI.blockGetMoreSegments) {
                        UI.blockGetMoreSegments = true;
                        UI.getMoreSegments('after');
                        setTimeout(function() {
                            UI.blockGetMoreSegments = false;
                        }, 1000);
                    }
                }
            }
        }, UI.downOpts);

        this.firstSegment.waypoint(function(event, direction) {
            if (direction === 'up') {
                UI.firstSegment.waypoint('remove');
                UI.getMoreSegments('before');
            }
        }, UI.upOpts);
    },
    showContextMenu: function(str, ypos, xpos) {
        if(($('#contextMenu').width() + xpos) > $(window).width()) xpos = $(window).width() - $('#contextMenu').width() - 30;
        $('#contextMenu').css({
            "top" : (ypos+13)+"px",
            "left" : xpos+"px"
        }).show();
    },
    tagCompare: function(sourceTags, targetTags, prova) {

// removed, to be verified
//		if(!UI.currentSegment.hasClass('loaded')) return false;

        var mismatch = false;
        for (var i = 0; i < sourceTags.length; i++) {
            for (var index = 0; index < targetTags.length; index++) {
                if (sourceTags[i] == targetTags[index]) {
                    sourceTags.splice(i, 1);
                    targetTags.splice(index, 1);
                    UI.tagCompare(sourceTags, targetTags, prova++);
                }
            }
        }
        if ((!sourceTags.length) && (!targetTags.length)) {
            mismatch = false;
        } else {
            mismatch = true;
        }
        ;
        return(mismatch);
    },
    /*
     // for future implementation
     
     getSegmentComments: function(segment) {
     var id_segment = $(segment).attr('id').split('-')[1];
     var id_translator = config.id_translator;
     $.ajax({
     url: config.basepath + '?action=getSegmentComment',
     data: {
     action: 'getSegmentComment',
     id_segment: id_segment,
     id_translator: id_translator
     },
     type: 'POST',
     dataType: 'json',
     context: segment,
     success: function(d){
     $('.numcomments',this).text(d.data.length);
     $.each(d.data, function() {
     $('.comment-area ul .newcomment',segment).before('<li><p><strong>'+this.author+'</strong><span class="date">'+this.date+'</span><br />'+this.text+'</p></li>');
     });
     }
     });
     },
     
     addSegmentComment: function(segment) {
     var id_segment = $(segment).attr('id').split('-')[1];
     var id_translator = config.id_translator;
     var text = $('.newcomment textarea',segment).val();
     $.ajax({
     url: config.basepath + '?action=addSegmentComment',
     data: {
     action: 'addSegmentComment',
     id_segment: id_segment,
     id_translator: id_translator,
     text: text
     },
     type: 'POST',
     dataType: 'json',
     success: function(d){
     }
     });
     },
     */

    topReached: function() {
        var jumpto = $(this.currentSegment).offset().top;
        $("html,body").animate({
            scrollTop: 0
        }, 200).animate({
            scrollTop: jumpto - 50
        }, 200);
    },
    browserScrollPositionRestoreCorrection: function() {
        // detect if the scroll is a browser generated scroll position restore, and if this is the case rescroll to the segment 
        if(this.firstOpenedSegment == 1) { // if the current segment is the first opened in the current UI  
            if(!$('.editor').isOnScreen()) { // if the current segment is out of the current viewport 
                if(this.autoscrollCorrectionEnabled) { // if this is the first correction and we are in the initial 2 seconds since page init 
                    this.scrollSegment(this.currentSegment);
                    this.autoscrollCorrectionEnabled = false;
                }
            }
        }
    },
    undoInSegment: function() {
        if (this.undoStackPosition == 0)
            this.saveInUndoStack('undo');
        var ind = 0;
        if (this.undoStack[this.undoStack.length - 1 - this.undoStackPosition - 1])
            ind = this.undoStack.length - 1 - this.undoStackPosition - 1;

        this.editarea.html(this.undoStack[ind]);
        if (!ind)
            this.lockTags();

        if (this.undoStackPosition < (this.undoStack.length - 1))
            this.undoStackPosition++;
        this.currentSegment.removeClass('waiting_for_check_result');
        this.registerQACheck();        
    },
    redoInSegment: function() {
        this.editarea.html(this.undoStack[this.undoStack.length - 1 - this.undoStackPosition - 1 + 2]);
        if (this.undoStackPosition > 0)
            this.undoStackPosition--;
        this.currentSegment.removeClass('waiting_for_check_result');
        this.registerQACheck();        
    },
    saveInUndoStack: function(action) {
        currentItem = this.undoStack[this.undoStack.length - 1 - this.undoStackPosition];

        if (typeof currentItem != 'undefined') {
            if (currentItem.trim() == this.editarea.html().trim())
                return;
        }

        if (this.editarea.html() == '')
            return;

        var ss = this.editarea.html().match(/\<span.*?contenteditable\="false".*?\>/gi);
        var tt = this.editarea.html().match(/&lt;/gi);
        if (tt) {
            if ((tt.length) && (!ss))
                return;
        }

        var diff = (typeof currentItem != 'undefined') ? this.dmp.diff_main(currentItem, this.editarea.html())[1][1] : 'null';
        if (diff == ' selected')
            return;

        var pos = this.undoStackPosition;
        if (pos > 0) {
            this.undoStack.splice(this.undoStack.length - pos, pos);
            this.undoStackPosition = 0;
        }
        this.undoStack.push(this.editarea.html().replace(/(\<.*?)\s?selected\s?(.*?\>)/gi, '$1$2'));
    },
    clearUndoStack: function() {
        this.undoStack = [];
    },
    unlockTags: function() {
        if (!this.taglockEnabled)
            return false;
        this.editarea.html(this.editarea.html().replace(/\<span contenteditable=\"false\" class=\"locked\"\>(.*?)\<\/span\>/gi, "$1"));
    },
    updateJobMenu: function() {
        $('#jobMenu li.current').removeClass('current');
        $('#jobMenu li:not(.currSegment)').each(function(index) {
            if($(this).attr('data-file') == UI.currentFileId) $(this).addClass('current');
        });
        $('#jobMenu li.currSegment').attr('data-segment',UI.currentSegmentId);
    },
    zerofill: function(i, l, s) {
        var o = i.toString();
        if (!s) {
            s = '0';
        }
        while (o.length < l) {
            o = s + o;
        }
        return o;
    }
}

function htmlEncode(value) {
    if (value) {
        a = jQuery('<div />').text(value).html();
        return a;
    } else {
        return '';
    }
}

function htmlDecode(value) {
    if (value) {
        return $('<div />').html(value).text();
    } else {
        return '';
    }
}

function utf8_to_b64(str) { // currently unused
    return window.btoa(unescape(encodeURIComponent(str)));
}

function b64_to_utf8(str) { // currently unused
    return decodeURIComponent(escape(window.atob(str)));
}


// START Get clipboard data at paste event (SEE http://stackoverflow.com/a/6804718)
function handlepaste(elem, e) {
    var savedcontent = elem.innerHTML;

    if (e && e.clipboardData && e.clipboardData.getData) {// Webkit - get data from clipboard, put into editdiv, cleanup, then cancel event
        if (/text\/html/.test(e.clipboardData.types)) {
            var txt = (UI.tagSelection) ? UI.tagSelection : htmlEncode(e.clipboardData.getData('text/plain'));
            elem.innerHTML = txt;
        }
        else if (/text\/plain/.test(e.clipboardData.types)) {
            var txt = (UI.tagSelection) ? UI.tagSelection : htmlEncode(e.clipboardData.getData('text/plain'));
            elem.innerHTML = txt;
        }
        else {
            elem.innerHTML = "";
        }

        waitforpastedata(elem, savedcontent);
        if (e.preventDefault) {
            e.stopPropagation();
            e.preventDefault();
        }
        return false;
    }
    else {// Everything else - empty editdiv and allow browser to paste content into it, then cleanup
        elem.innerHTML = "";
        waitforpastedata(elem, savedcontent);
        return true;
    }
}

function waitforpastedata(elem, savedcontent) {

    if (elem.childNodes && elem.childNodes.length > 0) {
        processpaste(elem, savedcontent);
    }
    else {
        that = {
            e: elem,
            s: savedcontent
        }
        that.callself = function() {
            waitforpastedata(that.e, that.s)
        }
        setTimeout(that.callself, 20);
    }
}

function processpaste(elem, savedcontent) {
    pasteddata = elem.innerHTML;

    if (UI.isFirefox) {
        var div = document.createElement("div");
        div.innerHTML = pasteddata;
        savedcontent = htmlEncode($(div).text());
    }

    //^^Alternatively loop through dom (elem.childNodes or elem.getElementsByTagName) here
    elem.innerHTML = savedcontent;
    if (UI.isFirefox)
        UI.lockTags();
    // Do whatever with gathered data;
    $('#placeHolder').before(pasteddata);
    focusOnPlaceholder();
    $('#placeHolder').remove();
}
// END Get clipboard data at paste event

function focusOnPlaceholder() {
    var placeholder = document.getElementById('placeHolder');
    if (!placeholder)
        return;
    var sel, range;

    if (window.getSelection && document.createRange) {
        range = document.createRange();
        range.selectNodeContents(placeholder);
        range.collapse(true);
        sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
    } else if (document.body.createTextRange) {
        range = document.body.createTextRange();
        range.moveToElementText(placeholder);
        range.select();
    }
}

function truncate_filename(n, len) {
    var ext = n.substring(n.lastIndexOf(".") + 1, n.length).toLowerCase();
    var filename = n.replace('.' + ext, '');
    if (filename.length <= len) {
        return n;
    }
    filename = filename.substr(0, len) + (n.length > len ? '[...]' : '');
    return filename + '.' + ext;
}

function insertNodeAtCursor(node) {
    var range, html;
    if (window.getSelection && window.getSelection().getRangeAt) {
        if (window.getSelection().type == 'Caret') {
            range = window.getSelection().getRangeAt(0);
            range.insertNode(node);
        } else {
        }

    } else if (document.selection && document.selection.createRange) {
        range = document.selection.createRange();
        html = (node.nodeType == 3) ? node.data : node.outerHTML;
        range.pasteHTML(html);
    }
}

function removeSelectedText(editarea) {
    if (window.getSelection || document.getSelection) {
        var oSelection = (window.getSelection ? window : document).getSelection();
        if (oSelection.type == 'Caret') {
            if (oSelection.extentOffset != oSelection.baseOffset)
                oSelection.deleteFromDocument();
        } else if (oSelection.type == 'Range') {
            var ss = $(oSelection.baseNode).parent()[0];
            if ($(ss).hasClass('selected')) {
                $(ss).remove();
            } else {
                oSelection.deleteFromDocument();
            }
        }
        ;
    } else {
        document.selection.clear();
    }
}




/* FORMATTING FUNCTION  TO TEST */

var LTPLACEHOLDER = "##LESSTHAN##";
var GTPLACEHOLDER = "##GREATERTHAN##";
var re_lt = new RegExp(LTPLACEHOLDER, "g");
var re_gt = new RegExp(GTPLACEHOLDER, "g");
// test jsfiddle http://jsfiddle.net/YgKDu/

function placehold_xliff_tags(segment) {
    segment = segment.replace(/<(g\s*.*?)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER);
    segment = segment.replace(/<(\/g)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER);
    segment = segment.replace(/<(x.*?\/?)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER);
    segment = segment.replace(/<(bx.*?\/?])>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER, segment);
    segment = segment.replace(/<(ex.*?\/?)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER, segment);
    segment = segment.replace(/<(bpt\s*.*?)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER, segment);
    segment = segment.replace(/<(\/bpt)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER, segment);
    segment = segment.replace(/<(ept\s*.*?)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER, segment);
    segment = segment.replace(/<(\/ept)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER, segment);
    segment = segment.replace(/<(ph\s*.*?)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER, segment);
    segment = segment.replace(/<(\/ph)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER, segment);
    segment = segment.replace(/<(it\s*.*?)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER, segment);
    segment = segment.replace(/<(\/ph)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER, segment);
    segment = segment.replace(/<(it\s*.*?)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER, segment);
    segment = segment.replace(/<(\/it)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER, segment);
    segment = segment.replace(/<(mrk\s*.*?)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER, segment);
    segment = segment.replace(/<(\/mrk)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER, segment);
    return segment;
}

function restore_xliff_tags(segment) {
    segment = segment.replace(re_lt, "<");
    segment = segment.replace(re_gt, ">");
    return segment;
}

function restore_xliff_tags_for_view(segment) {
    segment = segment.replace(re_lt, "&lt;");
    segment = segment.replace(re_gt, "&gt;");
    return segment;
}

function view2rawxliff(segment) {
    // return segment+"____";
    // input : <g id="43">bang & olufsen < 3 </g> <x id="33"/>; --> valore della funzione .text() in cat.js su source, target, source suggestion,target suggestion
    // output : <g id="43"> bang &amp; olufsen are &gt; 555 </g> <x/>

    // caso controverso <g id="4" x="&lt; dfsd &gt;"> 
    //segment=htmlDecode(segment);
    segment = placehold_xliff_tags(segment);
    segment = htmlEncode(segment);

    segment = restore_xliff_tags(segment);

    return segment;
}

function rawxliff2view(segment) { // currently unused
    // input : <g id="43">bang &amp; &lt; 3 olufsen </g>; <x id="33"/>
    // output : &lt;g id="43"&gt;bang & < 3 olufsen &lt;/g&gt;;  &lt;x id="33"/&gt;
    segment = placehold_xliff_tags(segment);
    segment = htmlDecode(segment);
    segment = segment.replace(/<(.*?)>/i, "&lt;$1&gt;");
    segment = restore_xliff_tags_for_view(segment);		// li rendering avviene via concat o via funzione html()
    return segment;
}

function rawxliff2rawview(segment) { // currently unused
    // input : <g id="43">bang &amp; &lt; 3 olufsen </g>; <x id="33"/>
    segment = placehold_xliff_tags(segment);
    segment = htmlDecode(segment);
    segment = restore_xliff_tags_for_view(segment);
    return segment;
}
/*
function checkLockability(html) {

    if (UI.editarea.text() == '')
        return false;
    if (!html.match(/\</gi))
        return false;
    var lockable = (html.match(/\</gi).length == html.match(/\>/gi).length) ? true : false;
    if (lockable) {
        if (UI.currentSegment.hasClass('unlockable')) {
            setTimeout(function() {
                UI.saveInUndoStack('checklockability');
            }, 100);
//			UI.saveInUndoStack();
        }
        UI.currentSegment.removeClass('unlockable');
    } else {
        UI.currentSegment.addClass('unlockable');
    }

    return lockable;

}
*/
function saveSelection(el) {
//    console.log('UI.savedSel 1: ', UI.savedSel);
    var editarea = (typeof editarea == 'undefined')? UI.editarea : el;
    if (UI.savedSel) {
        rangy.removeMarkers(UI.savedSel);
    }
    UI.savedSel = rangy.saveSelection();
    // this is just to prevent the addiction of a couple of placeholders who may sometimes occur for a Rangy bug
    editarea.html(editarea.html().replace(UI.cursorPlaceholder, ''));

    UI.savedSelActiveElement = document.activeElement;
}

function restoreSelection() {
//    console.log('UI.savedSel 2: ', UI.savedSel);
    if (UI.savedSel) {
        rangy.restoreSelection(UI.savedSel, true);
        UI.savedSel = null;
        window.setTimeout(function() {
            if (UI.savedSelActiveElement && typeof UI.savedSelActiveElement.focus != "undefined") {
                UI.savedSelActiveElement.focus();
            }
        }, 1);
    }
}

function selectText(element) {
    var doc = document
            , text = element
            , range, selection
            ;
    if (doc.body.createTextRange) {
        range = document.body.createTextRange();
        range.moveToElementText(text);
        range.select();
    } else if (window.getSelection) {
        selection = window.getSelection();
        range = document.createRange();
        range.selectNodeContents(text);
        selection.removeAllRanges();
        selection.addRange(range);
    }
}

function getSelectionHtml() {
    var html = "";
    if (typeof window.getSelection != "undefined") {
        var sel = window.getSelection();
        if (sel.rangeCount) {
            var container = document.createElement("div");
            for (var i = 0, len = sel.rangeCount; i < len; ++i) {
                container.appendChild(sel.getRangeAt(i).cloneContents());
            }
            html = container.innerHTML;
        }
    } else if (typeof document.selection != "undefined") {
        if (document.selection.type == "Text") {
            html = document.selection.createRange().htmlText;
        }
    }
    return html;
}

function setBrowserHistoryBehavior() {

    window.onpopstate = function(event) {
        segmentId = location.hash.substr(1);
        if (UI.segmentIsLoaded(segmentId)) {
            $(".editarea", $('#segment-' + segmentId)).click();
        } else {
            if ($('section').length)
                UI.pointBackToSegment(segmentId);
        }
    };

}

$.fn.isOnScreen = function() {

    var win = $(window);

    var viewport = {
        top: win.scrollTop(),
        left: win.scrollLeft()
    };
//    console.log('viewport: ', viewport);
    viewport.right = viewport.left + win.width();
    viewport.bottom = viewport.top + win.height();

    var bounds = this.offset();
//    console.log('bounds: ', bounds);
    bounds.right = bounds.left + this.outerWidth();
    bounds.bottom = bounds.top + this.outerHeight();


    return (!(viewport.right < bounds.left || viewport.left > bounds.right || viewport.bottom < bounds.top || viewport.top > bounds.bottom));

};


$(document).ready(function() {

    APP.init();
    APP.fitText($('.breadcrumbs'), $('#pname'), 30);
    setBrowserHistoryBehavior();
    $("article").each(function() {
        APP.fitText($('.filename h2', $(this)), $('.filename h2', $(this)), 30);
    });
    UI.render(true);

    //launch segments check on opening
    UI.checkWarnings(true);
    //and on every polling interval
    setInterval(function() {
        UI.checkWarnings(false);
    }, config.warningPollingInterval);
});

$(window).resize(function() {
});
