/**
 * React Component for the editarea.

 */
import React from 'react';
import $ from 'jquery';
import SegmentConstants from '../../constants/SegmentConstants';
import SegmentStore from '../../stores/SegmentStore';
import Immutable from 'immutable';
import EditArea from './utils/editarea';
import TagUtils from '../../utils/tagUtils';
import Speech2Text from '../../utils/speech2text';
import EventHandlersUtils from './utils/eventsHandlersUtils';
import TextUtils from '../../utils/textUtils';

class Editarea extends React.Component {
    constructor(props) {
        super(props);
        this.state = {
            editAreaClasses: ['targetarea'],
        };
        this.editAreaIsEditing = false;

        this.hightlightEditarea = this.hightlightEditarea.bind(this);
        this.addClass = this.addClass.bind(this);
        this.onMouseUpEvent = this.onMouseUpEvent.bind(this);
        this.onInputEvent = this.onInputEvent.bind(this);
        this.onKeyDownEvent = this.onKeyDownEvent.bind(this);
        this.onKeyPressEvent = this.onKeyPressEvent.bind(this);
        this.onPasteEvent = this.onPasteEvent.bind(this);
        this.openConcordance = this.openConcordance.bind(this);
        this.redoInUndoStack = this.redoInUndoStack.bind(this);
        this.undoInUndoStack = this.undoInUndoStack.bind(this);
        this.setFocusInEditArea = this.setFocusInEditArea.bind(this);
        this.updateCursorPosition = this.updateCursorPosition.bind(this);

        this.onInputDebounced = _.debounce(this.onInputEvent, 500);
        this.saveInUndoStackDebounced = _.debounce(this.saveInUndoStack, 200);
        this.keyPressed = false;

        this.undoStack = [];
        this.undoStackPosition = 0;
    }

    allowHTML(string) {
        return { __html: string };
    }

    hightlightEditarea(sid) {
        if (this.props.segment.sid == sid) {
            let self = this;
            let editAreaClasses = this.state.editAreaClasses.slice();
            editAreaClasses.push('highlighted1');
            this.setState({
                editAreaClasses: editAreaClasses.slice(),
            });
            setTimeout(function () {
                let index = editAreaClasses.indexOf('highlighted1');
                editAreaClasses.splice(index, 1);
                self.setState({
                    editAreaClasses: editAreaClasses.slice(),
                });
            }, 2000);
        }
    }

    addClass(sid, className) {
        if (this.props.segment.sid == sid) {
            let editAreaClasses = this.state.editAreaClasses.slice();
            if (editAreaClasses.indexOf(className) < 0) {
                editAreaClasses.push(className);
                this.setState({
                    editAreaClasses: editAreaClasses,
                });
            }
        }
    }
    checkEditToolbar() {
        let self = this;
        setTimeout(function () {
            if (!$(self.editAreaRef).find('.locked.selected').length) {
                try {
                    if (window.getSelection() && !$(window.getSelection().getRangeAt(0))[0].collapsed) {
                        // there's something selected
                        //ShowEditToolbar
                        $('.editor .editToolbar').addClass('visible');
                    }
                } catch (e) {
                    console.log('Fail in checkEditToolbar', e);
                }
            }
        }, 100);
    }
    onMouseUpEvent(e) {
        this.checkEditToolbar();
    }
    onMouseDownEvent() {
        // Hide Edit Toolbar
        $('.editor .editToolbar').removeClass('visible');
    }
    onBlurEvent() {
        // Hide Edit Toolbar
        setTimeout(() => $('.editor .editToolbar').removeClass('visible'), 500);
    }
    onClickEvent(event) {
        SegmentActions.closeTagsMenu();
        if (this.props.readonly || this.props.locked) {
            UI.handleClickOnReadOnly($(event.currentTarget).closest('section'));
        } else {
            TagUtils.removeSelectedClassToTags();
        }
    }

    onInputEvent(e) {
        if (!this.keyPressed && !this.compositionsStart) {
            UI.registerQACheck();
            UI.inputEditAreaEventHandler();
        }
    }

    modifiedTranslation(translation) {
        let translationToSend = translation ? translation : this.editAreaRef.innerHTML;
        SegmentActions.modifiedTranslation(this.props.segment.sid, this.props.segment.id_file, true, translationToSend);
    }

    onKeyDownEvent(e) {
        this.keyPressed = true;
        if (!this.compositionsStart) {
            EditArea.keydownEditAreaEventHandler.call(this.editAreaRef, e, (textAdded) => {
                this.keyPressed = false;
                if (textAdded) {
                    this.moveCursor = textAdded;
                }
                if (!this.props.segment.modified) {
                    SegmentActions.modifiedTranslation(this.props.segment.sid, this.props.segment.id_file, true);
                }
                this.props.sendTranslationWithoutUpdate();
                this.saveInUndoStackDebounced();
                this.onInputDebounced();
            });
        }
        this.openConcordance(e);
    }
    onKeyPressEvent(e) {
        EditArea.keyPressEditAreaEventHandler.call(this.editAreaRef, e, this.props.segment.sid);
    }
    onKeyUpEvent(e) {
        this.keyPressed = false;
        this.checkEditToolbar();
    }
    onCompositionStartEvent() {
        this.compositionsStart = true;
        EditArea.setEditAreaEditing(true);
    }
    onCompositionEndEvent() {
        this.compositionsStart = false;
        EditArea.setEditAreaEditing(false);
    }
    onCopyText(e) {
        EventHandlersUtils.handleCopyEvent(e);
    }
    onCutText(e) {
        var elem = $(e.target);
        if (elem.hasClass('locked') || elem.parent().hasClass('locked')) {
            EventHandlersUtils.handleCopyEvent(e);
            TextUtils.removeSelectedText();
        }
        setTimeout(() => {
            let textToSend = this.editAreaRef.innerHTML;
            if (textToSend === '') {
                SegmentActions.replaceEditAreaTextContent(this.props.segment.sid, null, textToSend);
            }
        });
    }
    onPasteEvent(e) {
        EditArea.pasteEditAreaEventHandler(e.nativeEvent);
        if (e && e.clipboardData && e.clipboardData.getData) {
            let txt;
            if (/text\/html/.test(e.clipboardData.types)) {
                txt = TextUtils.htmlEncode(e.clipboardData.getData('text/plain'));
            } else if (/text\/plain/.test(e.clipboardData.types)) {
                txt = TextUtils.htmlEncode(e.clipboardData.getData('text/plain'));
            }
            this.pastedAction = {
                length: txt.length,
            };
            setTimeout(() => {
                let textToSend = this.editAreaRef.innerHTML;
                if (textToSend === '') {
                    SegmentActions.replaceEditAreaTextContent(this.props.segment.sid, null, textToSend);
                }
            });
        }
    }
    onDragEvent(e) {
        EventHandlersUtils.handleDragEvent(e);
        this.draggingFromEditArea = true;
        EditArea.setEditAreaEditing(true);
    }
    onDragOver(e) {}
    onDragEnd() {
        this.draggingFromEditArea = false;
        EditArea.setEditAreaEditing(false);
    }
    onDropEvent(e) {
        if (this.draggingFromEditArea) {
            TextUtils.removeSelectedText();
        }
        this.draggingFromEditArea = false;
        this.draggedText = true;
        setTimeout(() => {
            // Fix to remove br at the end of dropped tags and duplicate span generated by Chrome
            $(this.editAreaRef)
                .find(
                    'br:not([class]), span[style]:not([class~=locked]), span[class*=selectionBoundary], span.locked:empty, a[style]'
                )
                .remove();
            if (UI.isSafari) {
                $(this.editAreaRef).find('span:not([contenteditable])').attr('contenteditable', false);
                $(this.editAreaRef).find('span[style][class~=locked]').removeAttr('style');
            }
        });
    }
    openConcordance(e) {
        if ((e.altKey && e.key === 'k') || (e.metaKey && e.key === 'k')) {
            e.preventDefault();
            var selection = window.getSelection();
            if (selection.type === 'Range') {
                // something is selected
                var str = selection.toString().trim();
                if (str.length) {
                    // the trimmed string is not empty
                    SegmentActions.openConcordance(this.props.segment.sid, str, true);
                }
            }
        }
    }
    setEditAreaEditing(editAreaIsEditing) {
        this.editAreaIsEditing = editAreaIsEditing;
        EditArea.setEditAreaEditing(editAreaIsEditing);
    }

    undoInUndoStack() {
        if (!this.props.segment.opened) return;

        if (this.undoStackPosition === 0) {
            return;
        }
        this.undoStackPosition = this.undoStackPosition - 1;
        let translation = this.undoStack[this.undoStackPosition === 0 ? 0 : this.undoStackPosition - 1];

        this.undoRedoAction = true;
        this.cursorPosition = translation.position;
        setTimeout(() => {
            this.modifiedTranslation(translation.text);
            this.onInputDebounced();
        });

        // console.log("UNDO IN SEGMENT", translation);
        // console.log("UNDOSTACK = ", this.undoStack);
        // console.log("UNDOPOSITION = ", this.undoStackPosition);
    }

    redoInUndoStack() {
        if (!this.props.segment.opened) return;

        if (this.undoStackPosition === this.undoStack.length) {
            return;
        }

        this.undoStackPosition++;
        let translation = this.undoStack[this.undoStackPosition - 1];

        this.undoRedoAction = true;
        this.cursorPosition = translation.position;
        setTimeout(() => {
            this.modifiedTranslation(translation.text);
            this.onInputDebounced();
        });

        // console.log("REDO IN SEGMENT", translation);
        // console.log("UNDOSTACK = ", this.undoStack);
        // console.log("UNDOPOSITION = ", this.undoStackPosition);
    }

    updateCursorPosition(sid, length) {
        if (this.props.segment.sid == sid) {
            this.pastedAction = {
                length: length,
            };
        }
    }
    saveInUndoStack() {
        if (!this.props.segment.opened || this.editAreaRef.innerHTML === '') return;

        let currentItem = this.undoStack[this.undoStackPosition - 1];

        let $editAreaClone = $(this.editAreaRef).clone();

        $editAreaClone.find('.tag-autocomplete-endcursor, .rangySelectionBoundary').remove();
        $editAreaClone.find('a[style], span[style]').remove(); //Generated by the browser when dropping a tag
        $editAreaClone
            .find('.locked.selected, .locked.mismatch, .locked.selfClosingTag')
            .removeClass('startTag endTag selected highlight order-error selfClosingTag mismatch');
        $editAreaClone.find('lxqwarning').replaceWith(function () {
            return $(this).contents();
        });
        $editAreaClone.find('br.end').remove();

        let textToSave = $editAreaClone.html();

        if (
            currentItem &&
            TagUtils.cleanTextFromPlaceholdersSpan(currentItem.text).replace(/\uFEFF/g, '') ===
                TagUtils.cleanTextFromPlaceholdersSpan(textToSave).replace(/\uFEFF/g, '')
        ) {
            return;
        }

        var pos = this.undoStackPosition;
        if (pos > 0) {
            this.undoStack.splice(pos);
        }
        let position = this.saveCursorPosition(this.editAreaRef);
        this.undoStackPosition++;
        this.undoStack.push({
            text: textToSave,
            position: position ? position : { start: 0, end: 0 },
        });

        // console.log("SAVE IN STACK IN SEGMENT", textToSave);
        // console.log("UNDOSTACK = ", this.undoStack);
        // console.log("UNDOPOSITION = ", this.undoStackPosition);
    }

    setFocusInEditArea() {
        if (this.props.segment.opened) {
            this.editAreaRef.focus();
        }
    }

    saveCursorPosition(containerEl) {
        let sel = window.getSelection && window.getSelection();
        let start,
            pasteLength = 0;
        if (this.pastedAction || this.moveCursor) {
            pasteLength = this.pastedAction ? this.pastedAction.length : this.moveCursor;
        }
        if (sel && sel.rangeCount > 0 && document.createRange) {
            let range = window.getSelection().getRangeAt(0);
            let preSelectionRange = range.cloneRange();
            preSelectionRange.selectNodeContents(containerEl);
            preSelectionRange.setEnd(range.startContainer, range.startOffset);
            // let tabCode = TextUtils.htmlDecode("&#8677;");
            // let regExp = new RegExp(tabCode, 'g');
            let selectionText = preSelectionRange.toString();
            start = selectionText.length;

            return {
                start: start + pasteLength,
                end: start + range.toString().length + pasteLength,
            };
        } else if (document.selection && document.body.createTextRange) {
            let selectedTextRange = document.selection.createRange();
            let preSelectionTextRange = document.body.createTextRange();
            preSelectionTextRange.moveToElementText(containerEl);
            preSelectionTextRange.setEndPoint('EndToStart', selectedTextRange);
            start = preSelectionTextRange.text.length;

            return {
                start: start + pasteLength,
                end: start + selectedTextRange.text.length + pasteLength,
            };
        }
    }
    restoreCursorPosition(containerEl, savedSel) {
        if (this.pastedAction || this.moveCursor) {
            delete this.pastedAction;
            delete this.moveCursor;
        }
        if (window.getSelection && document.createRange) {
            var charIndex = 0,
                range = document.createRange();
            range.setStart(containerEl, 0);
            range.collapse(true);
            var nodeStack = [containerEl],
                node,
                foundStart = false,
                stop = false;
            while (!stop && (node = nodeStack.pop())) {
                if (
                    node.className &&
                    node.className.indexOf('marker') !== -1 &&
                    savedSel.start - charIndex === 1 &&
                    savedSel.end - charIndex === 1
                ) {
                    charIndex++;
                } else if (node.nodeType === 3) {
                    var nextCharIndex = charIndex + node.length;
                    if (!foundStart && savedSel.start >= charIndex && savedSel.start <= nextCharIndex) {
                        range.setStart(node, savedSel.start - charIndex);
                        foundStart = true;
                    }
                    if (foundStart && savedSel.end >= charIndex && savedSel.end <= nextCharIndex) {
                        range.setEnd(node, savedSel.end - charIndex);
                        stop = true;
                    }
                    charIndex = nextCharIndex;
                } else {
                    var i = node.childNodes.length;
                    while (i--) {
                        nodeStack.push(node.childNodes[i]);
                    }
                }
            }
            if (!node) return;
            if (node.parentNode.className.indexOf('locked') !== -1) {
                node =
                    node.parentNode.parentNode.className.indexOf('locked') !== -1
                        ? node.parentNode.parentNode
                        : node.parentNode;
                range.setStartAfter(node);
                range.setEndAfter(node);
            }
            var sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(range);
        } else if (document.selection && document.body.createTextRange) {
            var textRange = document.body.createTextRange();
            textRange.moveToElementText(containerEl);
            textRange.collapse(true);
            textRange.moveEnd('character', savedSel.end);
            textRange.moveStart('character', savedSel.start);
            textRange.select();
        }
        this.undoRedoAction = false;
    }

    cleanEditarea() {
        $(this.editAreaRef).find('a[style], span[style]').remove();
    }

    componentDidMount() {
        this.$editArea = $(this.editAreaRef);
        this.saveInUndoStack();
        Speech2Text.enabled() && this.state.editAreaClasses.push('micActive');
        SegmentStore.addListener(SegmentConstants.HIGHLIGHT_EDITAREA, this.hightlightEditarea);
        SegmentStore.addListener(SegmentConstants.ADD_EDITAREA_CLASS, this.addClass);
        SegmentStore.addListener(SegmentConstants.UNDO_TEXT, this.undoInUndoStack);
        SegmentStore.addListener(SegmentConstants.REDO_TEXT, this.redoInUndoStack);
        SegmentStore.addListener(SegmentConstants.FOCUS_EDITAREA, this.setFocusInEditArea);
        SegmentStore.addListener(SegmentConstants.UPDATE_CURSOR, this.updateCursorPosition);
    }
    componentWillUnmount() {
        SegmentStore.removeListener(SegmentConstants.HIGHLIGHT_EDITAREA, this.hightlightEditarea);
        SegmentStore.removeListener(SegmentConstants.ADD_EDITAREA_CLASS, this.addClass);
        SegmentStore.removeListener(SegmentConstants.UNDO_TEXT, this.undoInUndoStack);
        SegmentStore.removeListener(SegmentConstants.REDO_TEXT, this.redoInUndoStack);
        SegmentStore.removeListener(SegmentConstants.FOCUS_EDITAREA, this.setFocusInEditArea);
        SegmentStore.removeListener(SegmentConstants.UPDATE_CURSOR, this.updateCursorPosition);

        if (this.props.segment.modified) {
            let textToSend = this.editAreaRef.innerHTML;
            let sid = this.props.segment.sid;
            setTimeout(() => SegmentActions.replaceEditAreaTextContent(sid, null, textToSend), 200);
        }
    }
    shouldComponentUpdate(nextProps, nextState) {
        return (
            (!Immutable.fromJS(nextState.editAreaClasses).equals(Immutable.fromJS(this.state.editAreaClasses)) ||
                nextProps.locked !== this.props.locked ||
                (this.props.translation !== nextProps.translation &&
                    (_.isUndefined(this.pastedAction) || _.isUndefined(this.pastedAction.textAdded))) ||
                nextProps.segment.modified !== this.props.segment.modified ||
                nextProps.segment.opened !== this.props.segment.opened ||
                nextProps.segment.muted !== this.props.segment.muted) &&
            !this.editAreaIsEditing &&
            !this.compositionsStart
        );
    }
    getSnapshotBeforeUpdate(prevProps) {
        try {
            if (this.props.segment.opened && !this.undoRedoAction) {
                this.cursorPosition = this.saveCursorPosition(this.editAreaRef);
            }
        } catch (e) {
            console.log('Error saving cursor position in EditArea component', e);
        }
        return !prevProps.segment.opened && this.props.segment.opened;
    }
    componentDidUpdate(prevProps, prevState, snapshot) {
        if (snapshot) {
            this.editAreaRef.focus();
        }
        if (this.cursorPosition) {
            try {
                if (this.props.segment.opened) {
                    this.restoreCursorPosition(this.editAreaRef, this.cursorPosition);
                }
            } catch (e) {
                console.log('Error restoring cursor position in EditArea component', e);
            }
        }
        this.saveInUndoStack();
    }
    render() {
        let lang = '';
        let readonly = false;
        if (this.props.segment) {
            lang = config.target_rfc.toLowerCase();
            readonly =
                this.props.readonly || this.props.locked || this.props.segment.muted || !this.props.segment.opened;
        }
        let classes = this.state.editAreaClasses.slice();
        if (this.props.locked || this.props.readonly) {
            classes.push('area');
        } else {
            classes.push('editarea');
        }

        return (
            <div
                className={classes.join(' ')}
                id={'segment-' + this.props.segment.sid + '-editarea'}
                lang={lang}
                data-gramm_editor="false"
                contentEditable={!readonly && this.props.segment.opened}
                spellCheck="true"
                data-sid={this.props.segment.sid}
                dangerouslySetInnerHTML={this.allowHTML(this.props.translation)}
                onMouseUp={this.onMouseUpEvent}
                onDragStart={this.onDragEvent.bind(this)}
                // onDragOver={this.onDragOver.bind(this)}
                onDrop={this.onDropEvent.bind(this)}
                onDragEnd={this.onDragEnd.bind(this)}
                onMouseDown={this.onMouseDownEvent}
                onContextMenu={this.onMouseUpEvent}
                onBlur={this.onBlurEvent}
                onClick={this.onClickEvent.bind(this)}
                onCut={this.onCutText.bind(this)}
                onKeyDown={this.onKeyDownEvent}
                onKeyPress={this.onKeyPressEvent}
                onKeyUp={this.onKeyUpEvent.bind(this)}
                onCopy={this.onCopyText.bind(this)}
                onInput={(e) => {
                    if (!this.props.segment.modified) {
                        SegmentActions.modifiedTranslation(this.props.segment.sid, this.props.segment.id_file, true);
                    }
                    if (this.draggedText) {
                        this.cleanEditarea();
                    }
                    this.props.sendTranslationWithoutUpdate(true);
                    this.saveInUndoStackDebounced();
                    // if ( !this.draggedText ) {
                    this.onInputDebounced();
                    // }
                    this.draggedText = false;
                }}
                onCompositionStart={this.onCompositionStartEvent.bind(this)}
                onCompositionEnd={this.onCompositionEndEvent.bind(this)}
                onPaste={this.onPasteEvent}
                ref={(ref) => (this.editAreaRef = ref)}
                tabIndex="-1"
            />
        );
    }
}

export default Editarea;
