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
                editAreaClasses: editAreaClasses
            });
            setTimeout(function() {
                editAreaClasses.push.apply(editAreaClasses, ['highlighted2']);
                self.setState({
                    editAreaClasses: editAreaClasses
                });
            }, 300);
            setTimeout(function() {
                let index = editAreaClasses.indexOf('highlighted1');
                editAreaClasses.splice(index, 1);
                index = editAreaClasses.indexOf('highlighted2');
                editAreaClasses.splice(index, 1);
                self.setState({
                    editAreaClasses: editAreaClasses
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
        }
    }
    onInputEvent(e) {
        UI.inputEditAreaEventHandler.call(this.editAreaRef, e);
    }
    onKeyDownEvent(e) {
        UI.keydownEditAreaEventHandler.call(this.editAreaRef, e);
    }
    onKeyPressEvent(e) {
        UI.keyPressEditAreaEventHandler.call(this.editAreaRef, e);
    }
    onPasteEvent(e) {
        UI.pasteEditAreaEventHandler.call(this.editAreaRef, e.nativeEvent);
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
            nextProps.locked !== this.props.locked || this.props.translation !== nextProps.translation)
    }
    render() {
        let lang = '';
        let readonly = false;
        if (this.props.segment){
            lang = config.target_rfc.toLowerCase();
            readonly = ((this.props.readonly) || this.props.locked);
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
                    contentEditable={!readonly}
                    spellCheck="true"
                    data-sid={this.props.segment.sid}
                    dangerouslySetInnerHTML={ this.allowHTML(this.props.translation) }
                    onMouseUp={this.onMouseUpEvent}
                    onMouseDown={this.onMouseDownEvent}
                    onContextMenu={this.onMouseUpEvent}
                    onBlur={this.onBlurEvent}
                    onClick={this.onClickEvent.bind(this)}
                    onKeyDown={this.onKeyDownEvent}
                    onKeyPress={this.onKeyPressEvent}
                    onInput={this.onInputEvent}
                    onPaste={this.onPasteEvent}
                    ref={(ref) => this.editAreaRef = ref}
        />;
    }
}

export default Editarea ;

