$.extend( UI, {
    editAreaEditing: false,
    setEditAreaEditing: function(isEditing) {
        this.editAreaEditing = isEditing;
    },
    setEditAreaEvents: function () {
        /**
         * Start EditArea Events Shortcuts
         */
        $("#outer").on('keydown', '.editor .source, .editor .editarea', UI.shortcuts.cattol.events.searchInConcordance.keystrokes.mac, function(e) {
            e.preventDefault();
            UI.preOpenConcordance();
        }).on('keydown', '.editor .editarea', 'shift+return', function(e) {
            e.preventDefault();
            UI.handleSoftReturn(e);
        }).on('keydown', '.editor .editarea', 'ctrl+shift+space', function(e) {
            if (!UI.hiddenTextEnabled) return;
            e.preventDefault();
            UI.insertNbspAtCursor();
        }).on('keydown', '.editor .editarea', 'alt+space', function(e) {
            if (!UI.hiddenTextEnabled) return;
            e.preventDefault();
            UI.insertNbspAtCursor();
        });
        /**
         * Finish editArea Events
         */
    },
    keydownEditAreaEventHandler: function (e) {
        var code = e.which || e.keyCode;
        if (e.ctrlKey || e.shiftKey || e.metaKey){
            if ( code === 37 || code === 39 ) { //ctrl + left/right arrows
                UI.saveInUndoStack('arrow');
            }
            return;
        }
        var selection, range, r, rr, referenceNode;
        //check if inside search
        if (UI.body.hasClass('searchActive')) {
            var el = this;
            setTimeout(function(){
                SearchUtils.rebuildSearchSegmentMarkers(el);
            },100)
        }

        if ((code == 8) || (code == 46)) { // backspace e canc(mac)
            if ($('.selected', $(this)).length) {
                e.preventDefault();
                if ( $('.selected', $(this)).hasClass('inside-attribute') ) {
                    $('.selected', $(this)).parent('span.locked').remove();
                } else {
                    $('.selected', $(this)).remove();
                }
                UI.saveInUndoStack('cancel');
                UI.segmentQA(UI.currentSegment);
                UI.checkTagProximity();
            } else {
                var numTagsBefore = (UI.editarea.text().match(/<.*?\>/gi) !== null)? UI.editarea.text().match(/<.*?\>/gi).length : 0;
                var numSpacesBefore = $('.space-marker', UI.editarea).length;
                saveSelection();

                var parentTag = $('span.locked', UI.editarea).has('.rangySelectionBoundary');
                var isInsideTag = $('span.locked .rangySelectionBoundary, span.monad .rangySelectionBoundary', UI.editarea).length;
                var isInsideMark = $('.searchMarker .rangySelectionBoundary', UI.editarea).length;

                var sbIndex = 0;
                var translation = $.parseHTML(UI.editarea.html());
                $.each(translation, function(index) {
                    if($(this).hasClass('rangySelectionBoundary')) sbIndex = index;
                });

                var undeletableMonad = (($(translation[sbIndex-1]).hasClass('monad'))&&($(translation[sbIndex-2]).prop("tagName") == 'BR'))? true : false;
                var selBound = $('.rangySelectionBoundary', UI.editarea);
                if(undeletableMonad) selBound.prev().remove();
                if(code == 8) { // backspace
                    var undeletableTag = !!(
                        ($( translation[sbIndex - 1] ).hasClass( 'locked' ) && ($( translation[sbIndex - 2] ).prop( "tagName" ) === 'BR')) ||
                        (($( translation[sbIndex - 2] ).hasClass( "monad" ) || $( translation[sbIndex - 2] ).hasClass( "locked" )) && $( translation[sbIndex - 1] ).hasClass( 'undoCursorPlaceholder' ))
                    );
                    if(undeletableTag) {
                        selBound.prev().remove();
                    }
                    undeletableMonad = !!(
                        ( $( translation[sbIndex - 1] ).hasClass( "marker" ) &&  $( translation[sbIndex - 2] ).hasClass( "marker" ) && translation.length -1 === sbIndex )
                    );
                    if(undeletableMonad) {
                        selBound.prev().remove();
                        e.preventDefault();
                    }
                }
                // insideTag management
                if ((code == 8)&&(isInsideTag)) {
                    parentTag.remove();
                    e.preventDefault();
                }

                setTimeout(function() {
                    // detect if selection ph is inside a monad tag
                    if($('.monad .rangySelectionBoundary', UI.editarea).length) {
                        saveSelection();
                        $('.monad:has(.rangySelectionBoundary)', UI.editarea).after($('.monad .rangySelectionBoundary', UI.editarea));
                        restoreSelection();
                        // move selboundary after the monad
                    }
                    var numTagsAfter = (UI.editarea.text().match(/<.*?\>/gi) !== null)? UI.editarea.text().match(/<.*?\>/gi).length : 0;
                    var numSpacesAfter = $('.space-marker', UI.editarea).length;
//                        var numSpacesAfter = (UI.editarea.text())? UI.editarea.text().match(/\s/gi).length : 0;
                    if (numTagsAfter < numTagsBefore) UI.saveInUndoStack('cancel');
                    if (numSpacesAfter < numSpacesBefore) UI.saveInUndoStack('cancel');
//                        console.log('EE: ', UI.editarea.html());
//                        console.log($(':focus'));


                }, 50);

                // insideMark management
                if ((code == 8)&&(isInsideMark)) {
                    console.log('inside mark');
                }



            }
        }

        if (code == 9) { // tab
            if(!UI.hiddenTextEnabled) return;

            e.preventDefault();
            var node = document.createElement("span");
            node.setAttribute('class', 'marker monad tab-marker ' + config.tabPlaceholderClass);
            node.setAttribute('contenteditable', 'false');
            node.textContent = htmlDecode("&#8677;");
            insertNodeAtCursor(node);
            UI.unnestMarkers();
        }
        if (code == 37) { // left arrow
            selection = window.getSelection();
            range = selection.getRangeAt(0);
            setTimeout(function() {
                UI.checkTagProximity();
            }, 10);

            if (range.startOffset != range.endOffset) { // if something is selected when the left button is pressed...
                r = range.startContainer.innerText;
                if (r && (r[0] == '<') && (r[r.length - 1] == '>')) { // if a tag is selected
                    e.preventDefault();
                    saveSelection();
                    rr = document.createRange();
                    referenceNode = $('.rangySelectionBoundary', UI.editarea).first().get(0);
                    rr.setStartBefore(referenceNode);
                    rr.setEndBefore(referenceNode);
                    $('.rangySelectionBoundary', UI.editarea).remove();

                }
            }
            UI.closeTagAutocompletePanel();
        }

        if (code == 38) { // top arrow
            if(UI.tagMenuOpen) {
                return false
            }
            selection = window.getSelection();
            range = selection.getRangeAt(0);
            if (range.startOffset != range.endOffset) { // if something is selected when the left button is pressed...
                r = range.startContainer.data;
                if (r &&(r[0] == '<') && (r[r.length - 1] == '>')) { // if a tag is selected
                    saveSelection();
                    rr = document.createRange();
                    referenceNode = $('.rangySelectionBoundary', UI.editarea).last().get(0);
                    rr.setStartAfter(referenceNode);
                    rr.setEndAfter(referenceNode);
                    $('.rangySelectionBoundary', UI.editarea).remove();
                }
            }
            setTimeout(function() {
                UI.checkTagProximity();
            }, 10);
        }
        if (code == 39) { // right arrow
            selection = window.getSelection();
            range = selection.getRangeAt(0);
            setTimeout(function() {
                UI.checkTagProximity();
            }, 10);

            if (range.startOffset != range.endOffset) {
                r = range.startContainer.innerText;
                if (r &&(r[0] == '<') && (r[r.length - 1] == '>')) {
                    saveSelection();
                    rr = document.createRange();
                    referenceNode = $('.rangySelectionBoundary', UI.editarea).last().get(0);
                    rr.setStartAfter(referenceNode);
                    rr.setEndAfter(referenceNode);
                    $('.rangySelectionBoundary', UI.editarea).remove();
                }
            }
            UI.closeTagAutocompletePanel();
        }

        if (code == 40) { // down arrow
            if( UI.tagMenuOpen ) {
                return false;
            }
            selection = window.getSelection();
            range = selection.getRangeAt(0);
            if (range.startOffset != range.endOffset) {
                r = range.startContainer.data;
                if (r &&(r[0] == '<') && (r[r.length - 1] == '>')) {
                    saveSelection();
                    rr = document.createRange();
                    referenceNode = $('.rangySelectionBoundary', UI.editarea).last().get(0);
                    rr.setStartAfter(referenceNode);
                    rr.setEndAfter(referenceNode);
                    $('.rangySelectionBoundary', UI.editarea).remove();
                }
            }
            setTimeout(function() {
                UI.checkTagProximity();
            }, 10);
        }

        if (((code == 37) || (code == 38) || (code == 39) || (code == 40) || (code == 8))) { // not arrows, backspace, canc
            UI.saveInUndoStack('arrow');
        }

        if (code == 32) { // space
            setTimeout(function() {
                UI.saveInUndoStack('space');
            }, 100);
        }

        if (code == 13) { // return
            if( UI.tagMenuOpen ) {
                return false;
            } else {
                UI.handleReturn(e);
            }
        }
    },
    /**
     * Edit area click
     *
     * This function can be extended in order for other modules
     * to change the behaviour of segment activation.
     *
     * TODO: .editarea class is bound to presentation and logic
     * and should be decoupled in future refactorings.
     *
     */
    editAreaClick : function(target, operation) {
        if (typeof operation == 'undefined') {
            operation = 'clicking';
        }

        UI.closeTagAutocompletePanel();
        UI.removeHighlightCorrespondingTags($(target).closest('section'));

        var segmentNotYetOpened = ($(target).is(UI.editarea) && !$(target).closest('section').hasClass("opened"));

        if ( !$(target).is(UI.editarea) || !UI.body.hasClass('editing') || segmentNotYetOpened) {
            if (operation == 'moving') {
                UI.recentMoving = true;
                clearTimeout(UI.recentMovingTimeout);
                UI.recentMovingTimeout = setTimeout(function() {
                    UI.recentMoving = false;
                }, 1000);
            }

            UI.lastOperation = operation;
            UI.openSegment(target, operation);

            if (operation != 'moving') {
                var segment = $(target).closest('section');
                if(!(config.isReview && (segment.hasClass('status-new') || segment.hasClass('status-draft')))) {
                    setTimeout(function () {
                        UI.scrollSegment(segment);
                    }, 50);
                }
            }
        }

        setTimeout(function() {
            UI.saveInUndoStack();
            UI.checkTagProximity();
        }, 100);
    },
    keyPressEditAreaEventHandler: function (e, sid) {
        if( (e.which == 60) && (UI.tagLockEnabled) && UI.hasDataOriginalTags(UI.currentSegment) && !UI.tagMenuOpen) { // opening tag sign
            SegmentActions.showTagsMenu(sid);
        }
    },
    inputEditAreaEventHandler: function (e) {
        if ( !UI.currentSegment.data('modified') ) {
            SegmentActions.addClassToSegment(UI.getSegmentId(UI.currentSegment), 'modified');
            UI.currentSegment.data('modified', true);
        }
        UI.currentSegment.trigger('modified');
        //UI.updateSegmentTranslation();
        UI.registerQACheck();
    },
    updateSegmentTranslationFn: function() {
        saveSelection();
        let editareaClone = UI.editarea.clone();
        SegmentActions.replaceEditAreaTextContent(UI.getSegmentId(UI.currentSegment), UI.getSegmentFileId(UI.currentSegment), editareaClone.html());
        setTimeout(function () {
            restoreSelection();
        });
    },
    pasteEditAreaEventHandler: function (e) {
        UI.saveInUndoStack('paste');
        $('#placeHolder').remove();
        var node = document.createElement("span");
        node.setAttribute('id', 'placeHolder');
        removeSelectedText();
        insertNodeAtCursor(node);
        UI.handleEditAreaPaste(this, e);
        UI.registerQACheck();
    },
    handleEditAreaPaste(elem, e) {
        var clonedElem = elem.cloneNode(true);
        if (e && e.clipboardData && e.clipboardData.getData) {
            if (/text\/html/.test(e.clipboardData.types)) {
                txt = htmlEncode(e.clipboardData.getData('text/plain'));
            }
            else if (/text\/plain/.test(e.clipboardData.types)) {
                txt = htmlEncode(e.clipboardData.getData('text/plain'));
            }
            else {
                txt = "";
            }
            txt = UI.transformTextForLockTags(txt);
            $(clonedElem).find('#placeHolder').before(txt);
            var newHtml = $(clonedElem).html();
            SegmentActions.replaceEditAreaTextContent(UI.getSegmentId(UI.editarea), UI.getSegmentFileId(UI.editarea), newHtml);
            if (e.preventDefault) {
                e.stopPropagation();
                e.preventDefault();
            }
            return false;
        }
    },
    insertNbspAtCursor: function (  ) {
        UI.editarea.find('.lastInserted').removeClass('lastInserted');

        var node = document.createElement("span");
        node.setAttribute('class', 'marker monad nbsp-marker lastInserted ' + config.nbspPlaceholderClass);
        node.setAttribute('contenteditable', 'false');
        node.textContent = htmlDecode("&nbsp;");
        insertNodeAtCursor(node);
        UI.unnestMarkers();
    }


});