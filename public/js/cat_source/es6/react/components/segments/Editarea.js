/**
 * React Component for the editarea.
 
 */
let React = require('react');
let $ = require('jquery');
let SegmentConstants = require('../../constants/SegmentConstants');
let SegmentStore = require('../../stores/SegmentStore');
let Immutable = require('immutable');
class Editarea extends React.Component {

    constructor(props) {
        super(props);
        this.state = {
            editAreaClasses : ['targetarea']
        };
        this.editAreaIsEditing = false;
        this.hightlightEditarea = this.hightlightEditarea.bind(this);
        this.addClass = this.addClass.bind(this);
        this.onMouseUpEvent = this.onMouseUpEvent.bind(this);
        this.onInputEvent = this.onInputEvent.bind(this);
        this.onKeyDownEvent = this.onKeyDownEvent.bind(this);
        this.onKeyPressEvent = this.onKeyPressEvent.bind(this);
        this.onPasteEvent = this.onPasteEvent.bind(this);
        this.onInputDebounced = _.debounce(this.onInputEvent, 500);
        this.keyPressed = false;
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
                editAreaClasses: editAreaClasses.slice()
            });
            setTimeout(function() {
                editAreaClasses.push.apply(editAreaClasses, ['highlighted2']);
                self.setState({
                    editAreaClasses: editAreaClasses.slice()
                });
            }, 300);
            setTimeout(function() {
                let index = editAreaClasses.indexOf('highlighted1');
                editAreaClasses.splice(index, 1);
                index = editAreaClasses.indexOf('highlighted2');
                editAreaClasses.splice(index, 1);
                self.setState({
                    editAreaClasses: editAreaClasses.slice()
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
                    editAreaClasses: editAreaClasses
                });
            }

        }
    }
    checkEditToolbar() {
        let self = this;
        setTimeout(function () {
            if(!$(self.editAreaRef).find('.locked.selected').length) {
                if(window.getSelection() && !$(window.getSelection().getRangeAt(0))[0].collapsed) { // there's something selected
                    //ShowEditToolbar
                    $('.editor .editToolbar').addClass('visible');
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
        $('.editor .editToolbar').removeClass('visible');
    }
    onClickEvent(event) {
        SegmentActions.closeTagsMenu();
        if (this.props.readonly || this.props.locked) {
            UI.handleClickOnReadOnly( $(event.currentTarget).closest('section') );
        } else {
            UI.removeSelectedClassToTags()
        }
    }

    // emitTrackChanges(){
	// 	if ( Review.enabled() && ( ReviewSimple.enabled() || ReviewExtended.enabled() || ReviewExtendedFooter.enabled() ) ){
	// 		UI.trackChanges(this.editAreaRef);
	// 	}
    //     //check if the translation is changed
    //     // const   value = htmlEncode(UI.prepareTextToSend($(this.editAreaRef).html())),
    //     //     status = (value !== htmlEncode(UI.prepareTextToSend(this.props.translation)));
    //     // if( this.props.segment.modified !== status ){
    //     //     SegmentActions.modifiedTranslation(this.props.segment.sid,this.props.segment.fid,status);
    //     // }
	// }

	checkEmptyText() {
        let text = this.editAreaRef.innerText;
        if (text === "") {
            this.props.disableButtons(true);
        } else {
            this.props.disableButtons(false);
        }
    }

    onInputEvent(e) {
        if (!this.keyPressed && !this.compositionsStart) {
            this.checkEmptyText();
            // this.emitTrackChanges();
            UI.registerQACheck();
            if ( !this.props.segment.modified ) {
                SegmentActions.modifiedTranslation(UI.getSegmentId( UI.currentSegment ),UI.getSegmentFileId(UI.currentSegment),true, this.editAreaRef.innerHTML);
            }
            UI.inputEditAreaEventHandler();
        }
    }

    onKeyDownEvent(e) {
        this.keyPressed = true;
        //on textarea the event of ctrz+z have a preventDefault.
		//We added this lines for fix the bug
		//TODO:delete preventDefault on ui.events.js
		// if (e.keyCode === 90 && (e.ctrlKey || e.metaKey) ) {
		// 	this.emitTrackChanges();
		// }
        UI.keydownEditAreaEventHandler.call(this.editAreaRef, e);
    }
    onKeyPressEvent(e) {
        UI.keyPressEditAreaEventHandler.call(this.editAreaRef, e, this.props.segment.sid);
		// this.emitTrackChanges();
    }
    onKeyUpEvent(e) {
        this.keyPressed = false;
        this.checkEditToolbar();
    }
    onCompositionStartEvent() {
        this.compositionsStart = true;
        this.setEditAreaEditing(true);
        console.log('CompositionEvent START');
    }
    onCompositionEndEvent() {
        this.compositionsStart = false;
        this.setEditAreaEditing(false);
        console.log('CompositionEvent END');
    }
    onCopyText(e) {
        UI.handleCopyEvent(e);
    }
    onCutText(e) {
        var elem = $(e.target);
        if ( elem.hasClass('locked') || elem.parent().hasClass('locked') ) {
            UI.handleCopyEvent(e);
            removeSelectedText();
            UI.saveInUndoStack('cut');
            // this.emitTrackChanges();
        }
    }
    onPasteEvent(e) {
        UI.pasteEditAreaEventHandler.call(this.editAreaRef, e.nativeEvent);
		// this.emitTrackChanges();
    }
    onDragEvent(e) {
        UI.handleDragEvent(e);
        this.draggingFromEditArea = true;
        this.setEditAreaEditing(true);
    }
    onDragEnd() {
        this.draggingFromEditArea = false;
        this.setEditAreaEditing(false);

    }
    onDropEvent(e) {
        if ( this.draggingFromEditArea ) {
            removeSelectedText();
        }
        UI.saveInUndoStack('paste');
        // this.emitTrackChanges();
        this.draggingFromEditArea = false;
        let self = this;
        setTimeout(function (  ) {
            // Fix to remove br at the end of dropped tags and duplicate span generated by Chrome
            $(self.editAreaRef).find('br:not([class]), span[style]:not([class~=locked]), span[class*=selectionBoundary], span.locked:empty, a[style]').remove();
            if ( UI.isSafari ) {
                $(self.editAreaRef).find('span:not([contenteditable])').attr('contenteditable', false);
                $(self.editAreaRef).find('span[style][class~=locked]').removeAttr('style');
            }
        });
    }
    setEditAreaEditing(editAreaIsEditing){
        this.editAreaIsEditing = editAreaIsEditing;
        UI.setEditAreaEditing(editAreaIsEditing);
    }
    saveCursorPosition(containerEl) {
        var sel = window.getSelection && window.getSelection();
        if (sel && sel.rangeCount > 0 && document.createRange) {
            var range = window.getSelection().getRangeAt(0);
            var preSelectionRange = range.cloneRange();
            preSelectionRange.selectNodeContents(containerEl);
            preSelectionRange.setEnd(range.startContainer, range.startOffset);
            var start = preSelectionRange.toString().length;

            return {
                start: start,
                end: start + range.toString().length
            }
        } else if (document.selection && document.body.createTextRange) {
            var selectedTextRange = document.selection.createRange();
            var preSelectionTextRange = document.body.createTextRange();
            preSelectionTextRange.moveToElementText(containerEl);
            preSelectionTextRange.setEndPoint("EndToStart", selectedTextRange);
            var start = preSelectionTextRange.text.length;

            return {
                start: start,
                end: start + selectedTextRange.text.length
            }
        }
    }
    restoreCursorPosition(containerEl, savedSel) {
        if (window.getSelection && document.createRange) {
            var charIndex = 0, range = document.createRange();
            range.setStart(containerEl, 0);
            range.collapse(true);
            var nodeStack = [containerEl], node, foundStart = false, stop = false;
            while (!stop && (node = nodeStack.pop())) {
                if (node.nodeType === 3) {
                    var nextCharIndex = charIndex + node.length;
                    if (!foundStart && savedSel.start >= charIndex && savedSel.start <= nextCharIndex ) {
                        range.setStart(node, savedSel.start - charIndex);
                        foundStart = true;
                    }
                    if (foundStart && savedSel.end >= charIndex && savedSel.end <= nextCharIndex ) {
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
            if ( node.parentElement.className.indexOf('locked') !== -1 ) {
                node = (node.parentNode.className.indexOf('locked') !== -1 ) ? node.parentNode.parentNode: node.parentNode;
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
            textRange.moveEnd("character", savedSel.end);
            textRange.moveStart("character", savedSel.start);
            textRange.select();
        }
    }

    componentWillUpdate(e){
        try {
            if (this.props.segment.opened) {
                this.cursorPosition = this.saveCursorPosition(this.editAreaRef);
            }
        } catch ( e ) {
            console.log("Error saving cursor position in EditArea component", e)
        }
    }
    componentDidMount() {
        SegmentStore.addListener(SegmentConstants.HIGHLIGHT_EDITAREA, this.hightlightEditarea);
        SegmentStore.addListener(SegmentConstants.ADD_EDITAREA_CLASS, this.addClass);
    }
    componentWillUnmount() {
        SegmentStore.removeListener(SegmentConstants.HIGHLIGHT_EDITAREA, this.hightlightEditarea);
        SegmentStore.removeListener(SegmentConstants.ADD_EDITAREA_CLASS, this.addClass);
        if ( this.props.segment.modified ) {
            let textToSend = this.editAreaRef.innerHTML ;
            let sid = this.props.segment.sid;
            setTimeout(()=>SegmentActions.replaceEditAreaTextContent(sid, null, textToSend), 200);
        }
    }
    componentWillMount() {
        Speech2Text.enabled() && this.state.editAreaClasses.push( 'micActive' ) ;
    }
    shouldComponentUpdate(nextProps, nextState) {
        return (!Immutable.fromJS(nextState.editAreaClasses).equals(Immutable.fromJS(this.state.editAreaClasses)) ||
            nextProps.locked !== this.props.locked || this.props.translation !== nextProps.translation ||
            !Immutable.fromJS(nextProps.segment).equals(Immutable.fromJS(this.props.segment))
        ) && !this.editAreaIsEditing
    }
    componentDidUpdate() {
        if (this.cursorPosition) {
            try {
                if (this.props.segment.opened) {
                    this.restoreCursorPosition( this.editAreaRef, this.cursorPosition );
                }
            } catch ( e ) {
                console.log("Error restoring cursor position in EditArea component", e)
            }
        }
        this.checkEmptyText();
        // setTimeout(function (  ) {
        //     if ( !_.isNull(self.editAreaRef) ) {
        //         self.emitTrackChanges();
        //     }
        // });
        focusOnPlaceholder();
    }
    render() {
        let lang = '';
        let readonly = false;
        if (this.props.segment){
            lang = config.target_rfc.toLowerCase();
            readonly = ((this.props.readonly) || this.props.locked || this.props.segment.muted);
        }
        let classes = this.state.editAreaClasses.slice();
        if (this.props.locked || this.props.readonly) {
            classes.push('area')
        } else {
            classes.push('editarea')
        }

        return <div className={classes.join(' ')}
                    id={'segment-' + this.props.segment.sid + '-editarea'}
                    lang={lang}
                    data-gramm_editor="false"
                    contentEditable={!readonly}
                    spellCheck="true"
                    data-sid={this.props.segment.sid}
                    dangerouslySetInnerHTML={ this.allowHTML(this.props.translation) }
                    onMouseUp={this.onMouseUpEvent}
                    onDragStart={this.onDragEvent.bind(this)}
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
                    onInput={()=>{
                        this.props.sendTranslationWithoutUpdate();
                        this.onInputDebounced();
                    }}
                    onCompositionStart={this.onCompositionStartEvent.bind(this)}
                    onCompositionEnd={this.onCompositionEndEvent.bind(this)}
                    onPaste={this.onPasteEvent}
                    ref={(ref) => this.editAreaRef = ref}
                    tabIndex="-1"
        />;
    }
}

export default Editarea ;

