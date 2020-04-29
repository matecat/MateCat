/**
 * React Component for the editarea.

 */
import React  from 'react';
import $  from 'jquery';
import SegmentConstants  from '../../constants/SegmentConstants';
import SegmentStore  from '../../stores/SegmentStore';
import Immutable  from 'immutable';
import EditArea  from './utils/editarea';
import TagUtils  from '../../utils/tagUtils';
import Speech2Text from '../../utils/speech2text';
import EventHandlersUtils  from './utils/eventsHandlersUtils';
import TextUtils from "../../utils/textUtils";

import DraftMatecatUtils from './utils/DraftUtils'

import {
    findWithRegex,
    encodeContent,
    decodeSegment,
    getEntities,
    duplicateFragment,
    cleanSegmentString
} from "./utils/ContentEncoder";
import {CompositeDecorator, convertFromRaw, convertToRaw, Editor, EditorState} from "draft-js";
import TagEntity from "./TagEntity/TagEntity.component";
import SegmentUtils from "../../utils/segmentUtils";


class Editarea extends React.Component {

    constructor(props) {
        super(props);

        const decorator = new CompositeDecorator([
            {
                strategy: getEntityStrategy('IMMUTABLE'),
                component: TagEntity,
                props: {
                    onClick: this.onEntityClick
                }
            }
        ]);
        const cleanTrans = SegmentUtils.checkCurrentSegmentTPEnabled(this.props.segment) ?
            cleanSegmentString(this.props.translation) : this.props.translation;

        // Inizializza Editor State con solo testo
        const plainEditorState = EditorState.createEmpty(decorator);
        const rawEncoded = encodeContent(plainEditorState, cleanTrans);

        this.state = {
            translation: cleanTrans,
            editorState: rawEncoded,
            editAreaClasses : ['targetarea']
        };

        this.updateTranslationDebounced = _.debounce(this.updateTranslationInStore, 500);

        this.onChange = (editorState) =>  {
            this.setState({editorState});
            setTimeout(()=>{this.updateTranslationDebounced()});
        } ;
    }

    //Receive the new translation and decode it for draftJS
    setNewTranslation = (sid, translation) => {
        if ( sid === this.props.segment.sid) {
            const rawEncoded = encodeContent( this.state.editorState, translation );
            this.setState( {
                translation: translation,
                editorState: rawEncoded,
            } );
        }

        //TODO MOVE THIS
        setTimeout(()=>this.updateTranslationInStore());

    };

    updateTranslationInStore = () => {
        if ( this.state.translation !== '' ) {
            SegmentActions.updateTranslation(this.props.segment.sid, decodeSegment(this.state.editorState))
        }
    };

    componentDidMount() {
        SegmentStore.addListener(SegmentConstants.REPLACE_TRANSLATION, this.setNewTranslation);
    }

    componentWillUnmount() {
        SegmentStore.removeListener(SegmentConstants.REPLACE_TRANSLATION, this.setNewTranslation);
    }

    // shouldComponentUpdate(nextProps, nextState) {}

    // getSnapshotBeforeUpdate(prevProps) {}

    componentDidUpdate(prevProps, prevState, snapshot) {}

    render() {
        const {editorState} = this.state;
        const {onChange, onPaste} = this;

        let lang = '';
        let readonly = false;
        if (this.props.segment){
            lang = config.target_rfc.toLowerCase();
            readonly = (this.props.readonly || this.props.locked || this.props.segment.muted || !this.props.segment.opened);
        }
        let classes = this.state.editAreaClasses.slice();
        if (this.props.locked || this.props.readonly) {
            classes.push('area')
        } else {
            classes.push('editarea')
        }

        return <div className={classes.join(' ')}
                    ref={(ref) => this.editAreaRef = ref}
                    id={'segment-' + this.props.segment.sid + '-editarea'}
                    data-sid={this.props.segment.sid}
                    tabIndex="-1"
        >
            <Editor
                lang={lang}
                editorState={editorState}
                onChange={onChange}
                handlePastedText={onPaste}
                ref={(el) => this.editor = el}
                readOnly={readonly}
            />
        </div>;
    }
    onPaste = (text, html) => {
        const {editorState} = this.state;
        const internalClipboard = this.editor.getClipboard();
        if (internalClipboard) {
            const clipboardEditorPasted = duplicateFragment(internalClipboard, editorState);
            this.onChange(clipboardEditorPasted);
            this.setState({
                editorState: clipboardEditorPasted,
            });
            return true;
        } else {
            return false;
        }
    };
}

function getEntityStrategy(mutability, callback) {
    return function (contentBlock, callback, contentState) {
        contentBlock.findEntityRanges(
            (character) => {
                const entityKey = character.getEntity();
                if (entityKey === null) {
                    return false;
                }
                return contentState.getEntity(entityKey).getMutability() === mutability;
            },
            callback
        );
    };
}

export default Editarea ;

