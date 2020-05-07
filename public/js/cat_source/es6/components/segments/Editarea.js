/**
 * React Component for the editarea.

 */
import React  from 'react';
import SegmentConstants  from '../../constants/SegmentConstants';
import EditAreaConstants  from '../../constants/EditAreaConstants';
import SegmentStore  from '../../stores/SegmentStore';
import Immutable  from 'immutable';
import EditArea  from './utils/editarea';
import TagUtils  from '../../utils/tagUtils';
import Speech2Text from '../../utils/speech2text';
import EventHandlersUtils  from './utils/eventsHandlersUtils';
import TextUtils from "../../utils/textUtils";

import DraftMatecatUtils from './utils/DraftMatecatUtils'

import {
    activateSearch
} from "./utils/DraftMatecatUtils/ContentEncoder";
import {CompositeDecorator, Editor, EditorState} from "draft-js";
import TagEntity from "./TagEntity/TagEntity.component";
import SegmentUtils from "../../utils/segmentUtils";


class Editarea extends React.Component {

    constructor(props) {
        super(props);
        this.decoratorsStructure = [
            {
                strategy: getEntityStrategy('IMMUTABLE'),
                component: TagEntity,
                props: {
                    onClick: this.onEntityClick,
                    getSearchParams: this.getSearchParams //TODO: Make it general ?
                }
            }
        ];
        const decorator = new CompositeDecorator(this.decoratorsStructure);
        // If GuessTag is Enabled, clean translation from tags
        const cleanTranslation = SegmentUtils.checkCurrentSegmentTPEnabled(this.props.segment) ?
            DraftMatecatUtils.cleanSegmentString(this.props.translation) : this.props.translation;

          // Inizializza Editor State con solo testo
        const plainEditorState = EditorState.createEmpty(decorator);
        const contentEncoded = DraftMatecatUtils.encodeContent(plainEditorState, cleanTranslation);
        const {editorState, tagRange} =  contentEncoded;

        this.state = {
            translation: cleanTranslation,
            editorState: editorState,
            editAreaClasses : ['targetarea'],
            tagRange: tagRange
        };

        this.updateTranslationDebounced = _.debounce(this.updateTranslationInStore, 500);

        this.onChange = (editorState) =>  {
            this.setState({editorState});
            setTimeout(()=>{this.updateTranslationDebounced()});
        } ;
    }

    getSearchParams = () => {
        const {inSearch,
            currentInSearch,
            searchParams,
            occurrencesInSearch,
            currentInSearchIndex
        } = this.props.segment;
        if ( inSearch && searchParams.target) {
            return {
                active: inSearch,
                currentActive: currentInSearch,
                textToReplace: searchParams.target,
                params: searchParams,
                occurrences : occurrencesInSearch.occurrences,
                currentInSearchIndex
            }
        } else {
            return {
                active: false
            }
        }
    };

    activateSearch = () => {
        let { editorState } = this.state;
        let { searchParams, occurrencesInSearch, currentInSearchIndex } = this.props.segment;
        this.setState( {
            editorState: activateSearch( editorState, this.decoratorsStructure, searchParams.target,
                searchParams, occurrencesInSearch.occurrences, currentInSearchIndex ),
        } );
    };

    deactivateSearch = () => {
        const newDecorator = new CompositeDecorator( this.decoratorsStructure );
        this.setState( {
            editorState: EditorState.set( this.state.editorState, {decorator: newDecorator} ),
        } );
    };

    //Receive the new translation and decode it for draftJS
    setNewTranslation = (sid, translation) => {
        if ( sid === this.props.segment.sid) {
            const contentEncoded = DraftMatecatUtils.encodeContent(this.state.editorState, translation );
            const {editorState, tagRange} =  contentEncoded;
            this.setState( {
                translation: translation,
                editorState: editorState,
            } );
        }
        //TODO MOVE THIS
        setTimeout(()=>this.updateTranslationInStore());

    };

    replaceCurrentSearch = (text) => {
        let { searchParams, occurrencesInSearch, currentInSearchIndex, currentInSearch } = this.props.segment;
        if ( currentInSearch && searchParams.target ) {
            let index = _.findIndex(occurrencesInSearch.occurrences, (item)=>item.searchProgressiveIndex === currentInSearchIndex);
            this.setState( {
                editorState: DraftMatecatUtils.replaceOccurrences(this.state.editorState, searchParams.target, text, index)
            } );
        }
        setTimeout(()=>this.updateTranslationInStore());
    };

    updateTranslationInStore = () => {
        if ( this.state.translation !== '' ) {
            const {editorState, tagRange} = this.state;
            let contentState = editorState.getCurrentContent();
            let plainText = contentState.getPlainText();
            SegmentActions.updateTranslation(this.props.segment.sid, DraftMatecatUtils.decodeSegment(this.state.editorState), plainText, tagRange);
        }
    };

    componentDidMount() {
        SegmentStore.addListener(SegmentConstants.REPLACE_TRANSLATION, this.setNewTranslation);
        SegmentStore.addListener(EditAreaConstants.REPLACE_SEARCH_RESULTS, this.replaceCurrentSearch);
        if ( this.props.segment.inSearch ) {
            setTimeout(this.activateSearch());
        }
        setTimeout(()=>this.updateTranslationInStore());
    }

    componentWillUnmount() {
        SegmentStore.removeListener(SegmentConstants.REPLACE_TRANSLATION, this.setNewTranslation);
        SegmentStore.removeListener(EditAreaConstants.REPLACE_SEARCH_RESULTS, this.replaceCurrentSearch);
    }

    // shouldComponentUpdate(nextProps, nextState) {}

    // getSnapshotBeforeUpdate(prevProps) {}

    componentDidUpdate(prevProps, prevState, snapshot) {
        if (this.props.segment.inSearch && this.props.segment.searchParams.target && (
            (!prevProps.segment.inSearch) ||  //Before was not active
            (prevProps.segment.inSearch && !Immutable.fromJS(prevProps.segment.searchParams).equals(Immutable.fromJS(this.props.segment.searchParams))) ||//Before was active but some params change
            (prevProps.segment.inSearch && prevProps.segment.currentInSearch !== this.props.segment.currentInSearch ) ||   //Before was the current
            (prevProps.segment.inSearch && prevProps.segment.currentInSearchIndex !== this.props.segment.currentInSearchIndex ) ) )   //There are more occurrences and the current change
        {
            this.activateSearch();
        } else if ( prevProps.segment.inSearch && !this.props.segment.inSearch ) {
            this.deactivateSearch();
        }
    }

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
            const clipboardEditorPasted = DraftMatecatUtils.duplicateFragment(internalClipboard, editorState);
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

