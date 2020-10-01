import TextUtils from '../../../utils/textUtils';
import TagUtils from '../../../utils/tagUtils';
import CursorUtils from '../../../utils/cursorUtils';

let EditArea = {
    editAreaEditing: false,
    setEditAreaEditing: function (isEditing) {
        this.editAreaEditing = isEditing;
    },
    handleSoftReturn: function (e, modifiedTranslationCallback) {
        e.preventDefault();
        let node = document.createElement('span');
        let br = document.createElement('br');
        node.setAttribute('class', 'monad marker softReturn ' + config.lfPlaceholderClass);
        node.setAttribute('contenteditable', 'false');
        node.appendChild(br);
        TextUtils.insertNodeAtCursor(node);
        EditArea.unnestMarkers();
        setTimeout(() => {
            modifiedTranslationCallback.call(e, 1);
        });
    },
    handleReturn: function (e, modifiedTranslationCallback) {
        e.preventDefault();
        let node = document.createElement('span');
        let br = document.createElement('br');
        node.setAttribute('class', 'monad marker softReturn ' + config.lfPlaceholderClass);
        node.setAttribute('contenteditable', 'false');
        node.appendChild(br);
        TextUtils.insertNodeAtCursor(node);
        EditArea.unnestMarkers();
        setTimeout(() => {
            modifiedTranslationCallback.call(e, 1);
        });
    },
    insertNbspAtCursor: function (e, modifiedTranslationCallback) {
        e.preventDefault();
        let node = document.createElement('span');
        node.setAttribute('class', 'marker monad nbsp-marker ' + config.nbspPlaceholderClass);
        node.setAttribute('contenteditable', 'false');
        node.innerHTML = '&nbsp;';
        TextUtils.insertNodeAtCursor(node);
        EditArea.unnestMarkers();
        setTimeout(() => {
            modifiedTranslationCallback.call(e);
        });
    },
    insertTabCursor: function (e, modifiedTranslationCallback) {
        e.preventDefault();
        let node = document.createElement('span');
        node.setAttribute('class', 'marker monad tab-marker ' + config.tabPlaceholderClass);
        node.setAttribute('contenteditable', 'false');
        node.textContent = TextUtils.htmlDecode('&#8677;');
        TextUtils.insertNodeAtCursor(node);
        EditArea.unnestMarkers();
        setTimeout(() => {
            modifiedTranslationCallback.call(e);
        });
    },
    keydownEditAreaEventHandler: function (e, modifiedTranslationCallback) {
        let code = e.which || e.keyCode;

        if (e.shiftKey && e.key === 'Enter' && !e.ctrlKey) {
            e.preventDefault();
            EditArea.handleSoftReturn(e, modifiedTranslationCallback);
            return;
        } else if (e.key === 'Enter' && !e.ctrlKey) {
            if (!UI.tagMenuOpen) {
                e.preventDefault();
                EditArea.handleReturn(e, modifiedTranslationCallback);
                return;
            }
        }
        if ((e.altKey && e.key === ' ') || (e.ctrlKey && e.shiftKey && e.key === ' ')) {
            EditArea.insertNbspAtCursor(e, modifiedTranslationCallback);
            return;
        }

        // ??
        if (e.ctrlKey || e.shiftKey || e.metaKey) {
            return;
        }
        let selection, range, r, rr, referenceNode;

        if (code == 8 || code == 46) {
            // backspace e canc(mac)
            if ($('.selected', $(this)).length) {
                e.preventDefault();
                if ($('.selected', $(this)).hasClass('inside-attribute')) {
                    $('.selected', $(this)).parent('span.locked').remove();
                } else {
                    $('.selected', $(this)).remove();
                }
                setTimeout(() => {
                    modifiedTranslationCallback.call();
                    TagUtils.checkTagProximity();
                });
            } else {
                CursorUtils.saveSelection();

                let parentTag = $('span.locked', UI.editarea).has('.rangySelectionBoundary');
                let isInsideTag = $(
                    'span.locked .rangySelectionBoundary , span.monad .rangySelectionBoundary',
                    UI.editarea
                ).length;

                let sbIndex = 0;
                let translation = $.parseHTML(UI.editarea.html());
                $.each(translation, function (index) {
                    if ($(this).hasClass('rangySelectionBoundary')) sbIndex = index;
                });

                let undeletableMonad =
                    $(translation[sbIndex - 1]).hasClass('monad') && $(translation[sbIndex - 2]).prop('tagName') == 'BR'
                        ? true
                        : false;
                let selBound = $('.rangySelectionBoundary', UI.editarea);
                if (undeletableMonad) $(selBound.prev()).remove();
                if (code == 8) {
                    // backspace
                    let undeletableTag = !!(
                        ($(translation[sbIndex - 1]).hasClass('locked') &&
                            $(translation[sbIndex - 2]).prop('tagName') === 'BR') ||
                        ($(translation[sbIndex - 1]).hasClass('marker') &&
                            $(translation[sbIndex - 2]).hasClass('marker') &&
                            translation.length - 1 === sbIndex)
                    );
                    if (undeletableTag) {
                        $(selBound.prev()).remove();
                        setTimeout(() => SegmentActions.modifiedTranslation(UI.currentSegmentId, null, true));
                        // Adding many "enter" at the end and deleting the last one mysteriously matecat adds two br at the end. Blocking the propagation doesn't happen.
                        e.preventDefault();
                        e.stopPropagation();
                    }
                }
                // insideTag management
                if (code == 8 && isInsideTag) {
                    parentTag.remove();
                    e.preventDefault();
                }
                CursorUtils.restoreSelection();
                setTimeout(function () {
                    // detect if selection ph is inside a monad tag
                    CursorUtils.saveSelection();
                    if ($('.monad .rangySelectionBoundary', UI.editarea).length) {
                        $('.monad:has(.rangySelectionBoundary)', UI.editarea).after(
                            $('.monad .rangySelectionBoundary', UI.editarea)
                        );
                        // move selboundary after the monad
                    }
                    // detect if selection ph is inside a monad tag
                    if ($('.monad .monad', UI.editarea).length) {
                        $('.monad:has(.monad)', UI.editarea).after($('.monad .monad', UI.editarea));
                        // move selboundary after the monad
                    }
                    CursorUtils.restoreSelection();
                }, 50);
            }
        }

        if (code == 9) {
            // tab

            EditArea.insertTabCursor(e, modifiedTranslationCallback);
        }
        if (code == 37) {
            // left arrow
            selection = window.getSelection();
            range = selection.getRangeAt(0);
            setTimeout(function () {
                TagUtils.checkTagProximity();
            }, 10);

            if (range.startOffset != range.endOffset) {
                // if something is selected when the left button is pressed...
                r = range.startContainer.innerText;
                if (r && r[0] == '<' && r[r.length - 1] == '>') {
                    // if a tag is selected
                    e.preventDefault();
                    CursorUtils.saveSelection();
                    rr = document.createRange();
                    referenceNode = $('.rangySelectionBoundary', UI.editarea).first().get(0);
                    rr.setStartBefore(referenceNode);
                    rr.setEndBefore(referenceNode);
                    $('.rangySelectionBoundary', UI.editarea).remove();
                }
            }
            SegmentActions.closeTagsMenu();
        }

        if (code == 38) {
            // top arrow
            if (UI.tagMenuOpen) {
                return false;
            }
            selection = window.getSelection();
            range = selection.getRangeAt(0);
            if (range.startOffset != range.endOffset) {
                // if something is selected when the left button is pressed...
                r = range.startContainer.data;
                if (r && r[0] == '<' && r[r.length - 1] == '>') {
                    // if a tag is selected
                    CursorUtils.saveSelection();
                    rr = document.createRange();
                    referenceNode = $('.rangySelectionBoundary', UI.editarea).last().get(0);
                    rr.setStartAfter(referenceNode);
                    rr.setEndAfter(referenceNode);
                    $('.rangySelectionBoundary', UI.editarea).remove();
                }
            }
            setTimeout(function () {
                TagUtils.checkTagProximity();
            }, 10);
        }
        if (code == 39) {
            // right arrow
            selection = window.getSelection();
            range = selection.getRangeAt(0);
            setTimeout(function () {
                TagUtils.checkTagProximity();
            }, 10);

            if (range.startOffset != range.endOffset) {
                r = range.startContainer.innerText;
                if (r && r[0] == '<' && r[r.length - 1] == '>') {
                    CursorUtils.saveSelection();
                    rr = document.createRange();
                    referenceNode = $('.rangySelectionBoundary', UI.editarea).last().get(0);
                    rr.setStartAfter(referenceNode);
                    rr.setEndAfter(referenceNode);
                    $('.rangySelectionBoundary', UI.editarea).remove();
                }
            }
            SegmentActions.closeTagsMenu();
        }

        if (code == 40) {
            // down arrow
            if (UI.tagMenuOpen) {
                return false;
            }
            selection = window.getSelection();
            range = selection.getRangeAt(0);
            if (range.startOffset != range.endOffset) {
                r = range.startContainer.data;
                if (r && r[0] == '<' && r[r.length - 1] == '>') {
                    CursorUtils.saveSelection();
                    rr = document.createRange();
                    referenceNode = $('.rangySelectionBoundary', UI.editarea).last().get(0);
                    rr.setStartAfter(referenceNode);
                    rr.setEndAfter(referenceNode);
                    $('.rangySelectionBoundary', UI.editarea).remove();
                }
            }
            setTimeout(function () {
                TagUtils.checkTagProximity();
            }, 10);
        }
    },

    keyPressEditAreaEventHandler: function (e, sid) {
        let segmentObj = SegmentStore.getSegmentByIdToJS(sid);
        if (
            e.which === 60 &&
            UI.tagLockEnabled &&
            TagUtils.hasDataOriginalTags(segmentObj.segment) &&
            !UI.tagMenuOpen
        ) {
            // opening tag sign
            SegmentActions.showTagsMenu(sid);
        }
    },
    handleEditAreaPaste: function (e) {
        if (!e.target.classList.contains('editarea')) {
            return false;
        }
        let clonedElem = e.target.cloneNode(true),
            txt;
        if (e && e.clipboardData && e.clipboardData.getData) {
            if (/text\/html/.test(e.clipboardData.types)) {
                txt = TextUtils.htmlEncode(e.clipboardData.getData('text/plain'));
            } else if (/text\/plain/.test(e.clipboardData.types)) {
                txt = TextUtils.htmlEncode(e.clipboardData.getData('text/plain'));
            } else {
                txt = '';
            }
            txt = txt.replace(/\r\n/g, config.lfPlaceholder);
            txt = txt.replace(/\n/g, config.lfPlaceholder);
            txt = txt.replace(/\r/g, config.lfPlaceholder);
            txt = txt.replace(/\t/g, config.tabPlaceholder);
            txt = TagUtils.decodePlaceholdersToText(txt);
            txt = TagUtils.transformTextForLockTags(txt);
            $(clonedElem).find('#placeHolder').before(txt);
            let newHtml = $(clonedElem).html();
            SegmentActions.replaceEditAreaTextContent(SegmentStore.getCurrentSegmentId(), null, newHtml);
            if (e.preventDefault) {
                e.stopPropagation();
                e.preventDefault();
            }
            return false;
        }
    },
    pasteEditAreaEventHandler: function (e) {
        $('#placeHolder').remove();
        let node = document.createElement('span');
        node.setAttribute('id', 'placeHolder');
        TextUtils.removeSelectedText();
        TextUtils.insertNodeAtCursor(node);
        this.handleEditAreaPaste(e);
        UI.registerQACheck();
    },

    unnestMarkers: function () {
        $('.editor .editarea .marker .marker').each(function () {
            $(this).parents('.marker').after($(this));
        });
    },

    /**
     *
     * This function is used before the text is sent to the server or to transform editArea content.
     * @return Return a cloned element without tag inside
     *
     * @param context
     * @param selector
     * @returns {*|jQuery}
     */
    postProcessEditarea: function (context, selector) {
        selector = typeof selector === 'undefined' ? UI.targetContainerSelector() : selector;
        let area = $(selector, context).clone();
        area = TagUtils.transformPlaceholdersHtml(area);

        area.find('span.space-marker').replaceWith(' ');
        area.find('span.rangySelectionBoundary').remove();
        area = TagUtils.encodeTagsWithHtmlAttribute(area);
        return TextUtils.view2rawxliff(area.text());
    },
};

module.exports = EditArea;
