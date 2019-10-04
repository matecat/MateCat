
let EditArea = {

    editAreaEditing: false,
    setEditAreaEditing: function(isEditing) {
        this.editAreaEditing = isEditing;
    },
    handleSoftReturn: function(e, modifiedTranslationCallback) {
        e.preventDefault();
        var node = document.createElement("span");
        var br = document.createElement("br");
        node.setAttribute('class', 'monad marker softReturn ' + config.crPlaceholderClass);
        node.setAttribute('contenteditable', 'false');
        node.appendChild(br);
        insertNodeAtCursor(node);
        EditArea.unnestMarkers();
        setTimeout(()=>{
            modifiedTranslationCallback.call();
        });
    },
    handleReturn: function(e, modifiedTranslationCallback) {
        e.preventDefault();
        var node = document.createElement("span");
        var br = document.createElement("br");
        node.setAttribute('class', 'monad marker softReturn ' + config.lfPlaceholderClass);
        node.setAttribute('contenteditable', 'false');
        node.appendChild(br);
        insertNodeAtCursor(node);
        EditArea.unnestMarkers();
        setTimeout(()=>{
            modifiedTranslationCallback.call();
        });
    },
    keydownEditAreaEventHandler: function (e, modifiedTranslationCallback) {
        var code = e.which || e.keyCode;

        if (e.shiftKey && e.key === 'Enter' && !e.ctrlKey) {
            e.preventDefault();
            EditArea.handleSoftReturn(e, modifiedTranslationCallback);
            return;
        } else if (e.key === 'Enter' && !e.ctrlKey) {
            if( !UI.tagMenuOpen ) {
                e.preventDefault();
                EditArea.handleReturn(e, modifiedTranslationCallback);
                return;
            }
        }
        if ( e.altKey && e.key === " " || e.ctrlKey && e.shiftKey && e.key === " ") {
            e.preventDefault();
            EditArea.insertNbspAtCursor(modifiedTranslationCallback);
            return;
        }

        // ??
        if (e.ctrlKey || e.shiftKey || e.metaKey){
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
                setTimeout(()=>{
                    modifiedTranslationCallback.call();
                    UI.checkTagProximity();
                });

            } else {
                saveSelection();

                var parentTag = $( 'span.locked', UI.editarea ).has( '.rangySelectionBoundary' );
                var isInsideTag = $( 'span.locked .rangySelectionBoundary , span.monad .rangySelectionBoundary', UI.editarea ).length;

                var sbIndex = 0;
                var translation = $.parseHTML( UI.editarea.html() );
                $.each( translation, function ( index ) {
                    if ( $( this ).hasClass( 'rangySelectionBoundary' ) ) sbIndex = index;
                } );

                var undeletableMonad = (($( translation[sbIndex - 1] ).hasClass( 'monad' )) && ($( translation[sbIndex - 2] ).prop( "tagName" ) == 'BR')) ? true : false;
                var selBound = $( '.rangySelectionBoundary', UI.editarea );
                if ( undeletableMonad ) $(selBound.prev()).remove();
                if ( code == 8 ) { // backspace
                    var undeletableTag = !!(
                        ($( translation[sbIndex - 1] ).hasClass( 'locked' ) && ($( translation[sbIndex - 2] ).prop( "tagName" ) === 'BR')) ||
                        ( $( translation[sbIndex - 1] ).hasClass( "marker" ) &&  $( translation[sbIndex - 2] ).hasClass( "marker" ) && translation.length -1 === sbIndex )
                    );
                    if ( undeletableTag ) {
                        $(selBound.prev()).remove();
                        setTimeout( () => SegmentActions.modifiedTranslation( UI.currentSegmentId, null, true ) );
                        // Adding many "enter" at the end and deleting the last one mysteriously matecat adds two br at the end. Blocking the propagation doesn't happen.
                        e.preventDefault();
                        e.stopPropagation();
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
    },

    keyPressEditAreaEventHandler: function (e, sid) {
        let segmentObj = SegmentStore.getSegmentByIdToJS(sid);
        if( (e.which == 60) && (UI.tagLockEnabled) && UI.hasDataOriginalTags(segmentObj.segment) && !UI.tagMenuOpen) { // opening tag sign
            SegmentActions.showTagsMenu(sid);
        }
    },
    handleEditAreaPaste: function( e) {
        if ( !e.target.classList.contains('editarea') ) {
            return false;
        }
        var clonedElem = e.target.cloneNode(true), txt;
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
            txt = TagUtils.transformTextForLockTags(txt);
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
        $('#placeHolder').remove();
        var node = document.createElement("span");
        node.setAttribute('id', 'placeHolder');
        removeSelectedText();
        insertNodeAtCursor(node);
        this.handleEditAreaPaste(e);
        UI.registerQACheck();
    },

    insertNbspAtCursor: function ( modifiedTranslationCallback ) {
        UI.editarea.find('.lastInserted').removeClass('lastInserted');

        var node = document.createElement("span");
        node.setAttribute('class', 'marker monad nbsp-marker lastInserted ' + config.nbspPlaceholderClass);
        node.setAttribute('contenteditable', 'false');
        node.textContent = htmlDecode("&nbsp;");
        insertNodeAtCursor(node);
        EditArea.unnestMarkers();
        setTimeout(()=>{
            modifiedTranslationCallback.call();
        });
    },

    unnestMarkers: function() {
        $('.editor .editarea .marker .marker').each(function() {
            $(this).parents('.marker').after($(this));
        });
    }
};

module.exports = EditArea;