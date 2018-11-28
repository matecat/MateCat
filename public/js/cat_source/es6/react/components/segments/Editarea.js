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
        this.hightlightEditarea = this.hightlightEditarea.bind(this);
        this.addClass = this.addClass.bind(this);
        this.onMouseUpEvent = this.onMouseUpEvent.bind(this);
        this.onInputEvent = this.onInputEvent.bind(this);
        this.onKeyDownEvent = this.onKeyDownEvent.bind(this);
        this.onKeyPressEvent = this.onKeyPressEvent.bind(this);
        this.onPasteEvent = this.onPasteEvent.bind(this);
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
    onMouseUpEvent(e) {
        let self = this;
        setTimeout(function () {
            if(!$(self.editAreaRef).find('.locked.selected').length) {
                if(!$(window.getSelection().getRangeAt(0))[0].collapsed) { // there's something selected
                    //ShowEditToolbar
                    $('.editor .editToolbar').addClass('visible');
                }
            }
        }, 100);
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
        if (this.props.readonly || this.props.locked) {
            UI.handleClickOnReadOnly( $(event.currentTarget).closest('section') );
        } else {
            UI.editAreaClick(event.currentTarget);
            UI.removeSelectedClassToTags()
        }
    }

    emitTrackChanges(){
		if ( Review.enabled() && (Review.type === 'simple' || Review.type === 'extended' || Review.type === 'extended-footer') ){
			UI.trackChanges(this.editAreaRef);
		}
	}

	checkEmptyText() {
        let text = UI.prepareTextToSend( $(this.editAreaRef).html() );
        if (text === "") {
            UI.disableSegmentButtons(this.props.segment.sid);
        } else {
            UI.enableSegmentsButtons(this.props.segment.sid);
        }
    }

    onInputEvent(e) {
        UI.inputEditAreaEventHandler.call(this.editAreaRef, e);
        this.checkEmptyText();
        this.emitTrackChanges();
    }
    onKeyDownEvent(e) {
    	//on textarea the event of ctrz+z have a preventDefault.
		//We added this lines for fix the bug
		//TODO:delete preventDefault on ui.events.js
		if (e.keyCode === 90 && (e.ctrlKey || e.metaKey) ) {
			this.emitTrackChanges();
		}
        UI.keydownEditAreaEventHandler.call(this.editAreaRef, e);
    }
    onKeyPressEvent(e) {
        UI.keyPressEditAreaEventHandler.call(this.editAreaRef, e);
		this.emitTrackChanges();
    }
    onPasteEvent(e) {
        UI.pasteEditAreaEventHandler.call(this.editAreaRef, e.nativeEvent);
		this.emitTrackChanges();
    }
    onDragEvent(e) {
        UI.handleDragEvent(e);
        this.draggingFromEditArea = true;
    }
    onDragEnd() {
        this.draggingFromEditArea = false;
    }
    onDropEvent(e) {
        if ( this.draggingFromEditArea ) {
            removeSelectedText();
        }
        UI.saveInUndoStack('paste');
        this.emitTrackChanges();
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
    componentDidMount() {
        SegmentStore.addListener(SegmentConstants.HIGHLIGHT_EDITAREA, this.hightlightEditarea);
        SegmentStore.addListener(SegmentConstants.ADD_EDITAREA_CLASS, this.addClass);
    }
    componentWillUnmount() {
        SegmentStore.removeListener(SegmentConstants.HIGHLIGHT_EDITAREA, this.hightlightEditarea);
        SegmentStore.removeListener(SegmentConstants.ADD_EDITAREA_CLASS, this.addClass);
    }
    componentWillMount() {
        Speech2Text.enabled() && this.state.editAreaClasses.push( 'micActive' ) ;
    }
    shouldComponentUpdate(nextProps, nextState) {
        return (!Immutable.fromJS(nextState.editAreaClasses).equals(Immutable.fromJS(this.state.editAreaClasses)) ||
            nextProps.locked !== this.props.locked || this.props.translation !== nextProps.translation ||
            !Immutable.fromJS(nextProps.segment).equals(Immutable.fromJS(this.props.segment)) )
    }
    componentDidUpdate() {
        let self = this;
        this.checkEmptyText();
        setTimeout(function (  ) {
            if ( !_.isNull(self.editAreaRef) ) {
                self.emitTrackChanges();
            }
        });
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
                    onKeyDown={this.onKeyDownEvent}
                    onKeyPress={this.onKeyPressEvent}
                    onInput={this.onInputEvent}
                    onPaste={this.onPasteEvent}
                    ref={(ref) => this.editAreaRef = ref}
                    tabIndex="-1"
        />;
    }
}

export default Editarea ;

