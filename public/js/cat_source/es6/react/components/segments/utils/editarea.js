
let EditArea = {

    editAreaEditing: false,
    setEditAreaEditing: function(isEditing) {
        this.editAreaEditing = isEditing;
    },
    handleSoftReturn: function(e) {
        e.preventDefault();
        var node = document.createElement("span");
        var br = document.createElement("br");
        node.setAttribute('class', 'monad marker softReturn ' + config.crPlaceholderClass);
        node.setAttribute('contenteditable', 'false');
        node.appendChild(br);
        insertNodeAtCursor(node);
        EditArea.unnestMarkers();
    },
    handleReturn: function(e) {
        e.preventDefault();
        var node = document.createElement("span");
        var br = document.createElement("br");
        node.setAttribute('class', 'monad marker softReturn ' + config.lfPlaceholderClass);
        node.setAttribute('contenteditable', 'false');
        node.appendChild(br);
        insertNodeAtCursor(node);
        EditArea.unnestMarkers();
    },
    keydownEditAreaEventHandler: function (e, modifiedTranslationCallback) {
        var code = e.which || e.keyCode;

        if (e.shiftKey && e.key === 'Enter') {
            e.preventDefault();
            EditArea.handleSoftReturn(e);
            return;
        } else if (e.key === 'Enter') {
            if( !UI.tagMenuOpen ) {
                e.preventDefault();
                EditArea.handleReturn(e);
                return;
            }
        }
        if ( e.altKey && e.key === " " || e.ctrlKey && e.shiftKey && e.key === " ") {
            e.preventDefault();
            EditArea.insertNbspAtCursor();
            return;
        }

        // ??
        if (e.ctrlKey || e.shiftKey || e.metaKey){
            // if ( code === 37 || code === 39 ) { //ctrl + left/right arrows
            //     UI.saveInUndoStack('arrow');
            // }
            return;
        }
        var selection, range, r, rr, referenceNode;

        if ((code == 8) || (code == 46)) { // backspace e canc(mac)
            if ($('.selected', $(this)).length) {
                e.preventDefault();
                if ( $('.selected', $(this)).hasClass('inside-attribute') ) {
                    $('.selected', $(this)).parent('span.locked').remove();
                } else {
                    $('.selected', $(this)).remove();
                }
                setTimeout(()=>modifiedTranslationCallback.call());

                UI.saveInUndoStack('cancel');
                UI.segmentQA(UI.currentSegment);
                UI.checkTagProximity();
            } else {
                var numTagsBefore = (UI.editarea.text().match( /<.*?\>/gi ) !== null) ? UI.editarea.text().match( /<.*?\>/gi ).length : 0;
                var numSpacesBefore = $( '.space-marker', UI.editarea ).length;
                saveSelection();

                var parentTag = $( 'span.locked', UI.editarea ).has( '.rangySelectionBoundary' );
                var isInsideTag = $( 'span.locked .rangySelectionBoundary , span.monad .rangySelectionBoundary', UI.editarea ).length;
                var isInsideMark = $( '.searchMarker .rangySelectionBoundary', UI.editarea ).length;

                var sbIndex = 0;
                var translation = $.parseHTML( UI.editarea.html() );
                $.each( translation, function ( index ) {
                    if ( $( this ).hasClass( 'rangySelectionBoundary' ) ) sbIndex = index;
                } );

                var undeletableMonad = (($( translation[sbIndex - 1] ).hasClass( 'monad' )) && ($( translation[sbIndex - 2] ).prop( "tagName" ) == 'BR')) ? true : false;
                var selBound = $( '.rangySelectionBoundary', UI.editarea );
                if ( undeletableMonad ) selBound.prev().remove();
                if ( code == 8 ) { // backspace
                    var undeletableTag = !!(
                        ($( translation[sbIndex - 1] ).hasClass( 'locked' ) && ($( translation[sbIndex - 2] ).prop( "tagName" ) === 'BR')) ||
                        (($( translation[sbIndex - 2] ).hasClass( "monad" ) || $( translation[sbIndex - 2] ).hasClass( "locked" )) && $( translation[sbIndex - 1] ).hasClass( 'undoCursorPlaceholder' )) ||
                        ( $( translation[sbIndex - 1] ).hasClass( "marker" ) &&  $( translation[sbIndex - 2] ).hasClass( "marker" ) && translation.length -1 === sbIndex )
                    );
                    if ( undeletableTag ) {
                        selBound.prev().remove();
                        setTimeout( () => SegmentActions.modifiedTranslation( UI.currentSegmentId, null, true ) );
                        // e.preventDefault();
                    }
                }
                // insideTag management
                if ( (code == 8) && (isInsideTag) ) {
                    parentTag.remove();
                    e.preventDefault();
                }

                setTimeout( function () {
                    // detect if selection ph is inside a monad tag
                    if ( $( '.monad .rangySelectionBoundary', UI.editarea ).length ) {
                        saveSelection();
                        $( '.monad:has(.rangySelectionBoundary)', UI.editarea ).after( $( '.monad .rangySelectionBoundary', UI.editarea ) );
                        restoreSelection();
                        // move selboundary after the monad
                    }
                    // detect if selection ph is inside a monad tag
                    if ( $( '.monad .monad', UI.editarea ).length ) {
                        saveSelection();
                        $( '.monad:has(.monad)', UI.editarea ).after( $( '.monad .monad', UI.editarea ) );
                        restoreSelection();
                        // move selboundary after the monad
                    }
                    var numTagsAfter = (UI.editarea.text().match( /<.*?\>/gi ) !== null) ? UI.editarea.text().match( /<.*?\>/gi ).length : 0;
                    var numSpacesAfter = $( '.space-marker', UI.editarea ).length;
                    if ( numTagsAfter < numTagsBefore ) UI.saveInUndoStack( 'cancel' );
                    if ( numSpacesAfter < numSpacesBefore ) UI.saveInUndoStack( 'cancel' );
                }, 50 );
            }
        }

        if (code == 9) { // tab

            e.preventDefault();
            var node = document.createElement("span");
            node.setAttribute('class', 'marker monad tab-marker ' + config.tabPlaceholderClass);
            node.setAttribute('contenteditable', 'false');
            node.textContent = htmlDecode("&#8677;");
            insertNodeAtCursor(node);
            EditArea.unnestMarkers();
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
    },

    keyPressEditAreaEventHandler: function (e, sid) {
        if( (e.which == 60) && (UI.tagLockEnabled) && UI.hasDataOriginalTags(UI.currentSegment) && !UI.tagMenuOpen) { // opening tag sign
            SegmentActions.showTagsMenu(sid);
        }
    },
    handleEditAreaPaste: function(elem, e) {
        var clonedElem = elem.cloneNode(true), txt;
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
    pasteEditAreaEventHandler: function (e) {
        UI.saveInUndoStack('paste');
        $('#placeHolder').remove();
        var node = document.createElement("span");
        node.setAttribute('id', 'placeHolder');
        removeSelectedText();
        insertNodeAtCursor(node);
        this.handleEditAreaPaste(this, e);
        UI.registerQACheck();
    },

    insertNbspAtCursor: function (  ) {
        UI.editarea.find('.lastInserted').removeClass('lastInserted');

        var node = document.createElement("span");
        node.setAttribute('class', 'marker monad nbsp-marker lastInserted ' + config.nbspPlaceholderClass);
        node.setAttribute('contenteditable', 'false');
        node.textContent = htmlDecode("&nbsp;");
        insertNodeAtCursor(node);
        EditArea.unnestMarkers();
    },

    unnestMarkers: function() {
        $('.editor .editarea .marker .marker').each(function() {
            $(this).parents('.marker').after($(this));
        });
    },


};

module.exports = EditArea;