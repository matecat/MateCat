/**
 * React Component for the editarea.

 */
import React  from 'react';
import SegmentConstants  from '../../constants/SegmentConstants';
import EditAreaConstants  from '../../constants/EditAreaConstants';
import SegmentStore  from '../../stores/SegmentStore';
import Immutable  from 'immutable';
import Speech2Text from '../../utils/speech2text';

import DraftMatecatUtils from './utils/DraftMatecatUtils'

import {
    activateLexiqa,
    activateQaCheckBlacklist,
    activateSearch
} from "./utils/DraftMatecatUtils/ContentEncoder";
import {Modifier, Editor, EditorState, getDefaultKeyBinding, KeyBindingUtil, ContentState} from "draft-js";
import TagEntity from "./TagEntity/TagEntity.component";
import SegmentUtils from "../../utils/segmentUtils";
import CompoundDecorator from "./utils/CompoundDecorator"
import TagBox from "./utils/DraftMatecatUtils/TagMenu/TagBox";
import insertTag from "./utils/DraftMatecatUtils/TagMenu/insertTag";
import matchTag from "./utils/DraftMatecatUtils/matchTag";
import checkForMissingTags from "./utils/DraftMatecatUtils/TagMenu/checkForMissingTag";
import updateEntityData from "./utils/DraftMatecatUtils/updateEntityData";
const {hasCommandModifier, isOptionKeyCommand} = KeyBindingUtil;
import LexiqaUtils from "../../utils/lxq.main";
import updateLexiqaWarnings from "./utils/DraftMatecatUtils/updateLexiqaWarnings";
import insertText from "./utils/DraftMatecatUtils/insertText";
import {tagSignatures, TagStruct} from "./utils/DraftMatecatUtils/tagModel";
import structFromType from "./utils/DraftMatecatUtils/tagFromTagType";
import SegmentActions from "../../actions/SegmentActions";
import getFragmentFromSelection
    from "./utils/DraftMatecatUtils/DraftSource/src/component/handlers/edit/getFragmentFromSelection";

const editorSync = {
    inTransitionToFocus: false,
    editorFocused: false,
    clickedOnTag: false,
    onComposition: false
};

class Editarea extends React.Component {

    constructor(props) {
        super(props);
        const {onEntityClick, updateTagsInEditor, getUpdatedSegmentInfo, getClickedTagInfo} = this;

        this.decoratorsStructure = [
            {
                strategy: getEntityStrategy('IMMUTABLE'),
                component: TagEntity,
                props: {
                    isTarget: true,
                    onClick: onEntityClick,
                    getUpdatedSegmentInfo: getUpdatedSegmentInfo,
                    getClickedTagInfo: getClickedTagInfo,
                    getSearchParams: this.getSearchParams, //TODO: Make it general ?
                    isRTL: config.isTargetRTL
                }
            }
        ];
        // const decorator = new CompositeDecorator(this.decoratorsStructure);
        const decorator = new CompoundDecorator(this.decoratorsStructure);

        // Escape html
        const translation =  DraftMatecatUtils.unescapeHTMLLeaveTags(this.props.translation);

        // If GuessTag is Enabled, clean translation from tags
        const cleanTranslation = SegmentUtils.checkCurrentSegmentTPEnabled(this.props.segment) ?
            DraftMatecatUtils.cleanSegmentString(translation) : translation;

          // Inizializza Editor State con solo testo
        const plainEditorState = EditorState.createEmpty(decorator);
        const contentEncoded = DraftMatecatUtils.encodeContent(plainEditorState, cleanTranslation);
        const {editorState, tagRange} =  contentEncoded;

        this.state = {
            translation: cleanTranslation,
            editorState: editorState,
            editAreaClasses : ['targetarea'],
            tagRange: tagRange,
            // TagMenu
            autocompleteSuggestions: [],
            focusedTagIndex: 0,
            displayPopover: false,
            popoverPosition: {},
            editorFocused: true,
            clickedOnTag: false,
            triggerText: null
        };
        this.updateTranslationDebounced = _.debounce(this.updateTranslationInStore, 500);
        this.updateTagsInEditorDebounced = _.debounce(updateTagsInEditor, 500);
        this.onCompositionStopDebounced = _.debounce(this.onCompositionStop, 1000);
        this.focusEditorDebounced = _.debounce(this.focusEditor, 500);
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

    addSearchDecorator = () => {
        let { editorState, tagRange } = this.state;
        let { searchParams, occurrencesInSearch, currentInSearchIndex } = this.props.segment;
        const textToSearch = searchParams.target ? searchParams.target : "";
        const { editorState: newEditorState, decorators } = activateSearch( editorState, this.decoratorsStructure, textToSearch,
            searchParams, occurrencesInSearch.occurrences, currentInSearchIndex, tagRange );
        this.decoratorsStructure = decorators;
        this.setState( {
            editorState: newEditorState,
        } );
    };

    removeSearchDecorator = () => {
        _.remove(this.decoratorsStructure, (decorator) => decorator.name === 'search');
        // const newDecorator = new CompositeDecorator( this.decoratorsStructure );
        const decorator = new CompoundDecorator(this.decoratorsStructure);
        this.setState( {
            editorState: EditorState.set( this.state.editorState, {decorator: decorator} ),
        } );
    };

    addQaBlacklistGlossaryDecorator = () => {
        let { editorState } = this.state;
        let { qaBlacklistGlossary, sid } = this.props.segment;
        const { editorState : newEditorState, decorators } = activateQaCheckBlacklist( editorState, this.decoratorsStructure, qaBlacklistGlossary, sid );
        this.decoratorsStructure = decorators;
        this.setState( {
            editorState: newEditorState,
        } );
    };

    removeQaBlacklistGlossaryDecorator = () => {
        _.remove(this.decoratorsStructure, (decorator) => decorator.name === 'qaCheckBlacklist');
        // const newDecorator = new CompositeDecorator( this.decoratorsStructure );
        const decorator = new CompoundDecorator(this.decoratorsStructure);
        this.setState( {
            editorState: EditorState.set( this.state.editorState, {decorator: decorator} ),
        } );
    };

    addLexiqaDecorator = () => {
        let { editorState } = this.state;
        let { lexiqa, sid, decodedTranslation } = this.props.segment;
        // passare la decoded translation con i tag <g id='1'> e non 1
        let ranges = LexiqaUtils.getRanges(_.cloneDeep(lexiqa.target), decodedTranslation, false);
        const updatedLexiqaWarnings = updateLexiqaWarnings(editorState, ranges);
        if ( ranges.length > 0 ) {
            const { editorState : newEditorState, decorators } = activateLexiqa( editorState,
                this.decoratorsStructure,
                updatedLexiqaWarnings,
                sid,
                false,
                this.getUpdatedSegmentInfo);
            this.decoratorsStructure = decorators;
            this.setState( {
                editorState: newEditorState,
            }, () => this.focusEditorDebounced );
        } else {
            this.removeLexiqaDecorator();
        }
    };

    removeLexiqaDecorator = () => {
        _.remove(this.decoratorsStructure, (decorator) => decorator.name === 'lexiqa');
        const decorator = new CompoundDecorator(this.decoratorsStructure);
        this.setState( {
            editorState: EditorState.set( this.state.editorState, {decorator: decorator} ),
        } );
    };

    //Receive the new translation and decode it for draftJS
    setNewTranslation = (sid, translation) => {
        if ( sid === this.props.segment.sid) {
            const {editorState} = this.state;
            const contentEncoded = DraftMatecatUtils.encodeContent(editorState, DraftMatecatUtils.unescapeHTMLLeaveTags(translation) );
            // this must be done to make the Undo action possible, otherwise encodeContent will delete all editor history
            let {editorState: newEditorState} =  contentEncoded;
            const newContentState = newEditorState.getCurrentContent();
            newEditorState = EditorState.push(editorState, newContentState, 'insert-fragment');
            this.setState( {
                translation: translation,
                editorState: newEditorState,
            }, () => {
                this.updateTranslationDebounced();
            });
        }
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
            const {segment, segment: {sourceTagMap}} = this.props;
            const {editorState, tagRange} = this.state;
            const decodedSegment = DraftMatecatUtils.decodeSegment(editorState);
            let contentState = editorState.getCurrentContent();
            let plainText = contentState.getPlainText();
            // Match tag without compute tag id
            const currentTagRange = DraftMatecatUtils.matchTagInEditor(editorState);
            // Add missing tag to store for highlight warnings on tags
            const {missingTags} = checkForMissingTags(sourceTagMap, currentTagRange);
            //const currentTagRange = matchTag(decodedSegment); //deactivate if updateTagsInEditor is active
            SegmentActions.updateTranslation(segment.sid, decodedSegment, plainText, currentTagRange, missingTags);
            console.log('updatingTranslationInStore');
            UI.registerQACheck();
        }
    };

    checkDecorators = (prevProps) => {
        //Search
        if (this.props.segment.inSearch && this.props.segment.searchParams.target && (
            (!prevProps.segment.inSearch) ||  //Before was not active
            (prevProps.segment.inSearch && !Immutable.fromJS(prevProps.segment.searchParams).equals(Immutable.fromJS(this.props.segment.searchParams))) ||//Before was active but some params change
            (prevProps.segment.inSearch && prevProps.segment.currentInSearch !== this.props.segment.currentInSearch ) ||   //Before was the current
            (prevProps.segment.inSearch && prevProps.segment.currentInSearchIndex !== this.props.segment.currentInSearchIndex ) ) )   //There are more occurrences and the current change
        {
            this.addSearchDecorator();
        } else if ( prevProps.segment.inSearch && !this.props.segment.inSearch ) {
            this.removeSearchDecorator();
        }

        //Qa Check Blacklist
        const { qaBlacklistGlossary } = this.props.segment;
        const { qaBlacklistGlossary : prevQaBlacklistGlossary } = prevProps.segment;
        if ( qaBlacklistGlossary && qaBlacklistGlossary.length > 0 &&
            (_.isUndefined(prevQaBlacklistGlossary) || !Immutable.fromJS(prevQaBlacklistGlossary).equals(Immutable.fromJS(qaBlacklistGlossary)) ) ) {
            this.addQaBlacklistGlossaryDecorator();
        } else if ((prevQaBlacklistGlossary && prevQaBlacklistGlossary.length > 0 ) && ( !qaBlacklistGlossary ||  qaBlacklistGlossary.length === 0 ) ) {
            this.removeQaBlacklistGlossaryDecorator();
        }

        //Lexiqa
        const { lexiqa  } = this.props.segment;
        const { lexiqa : prevLexiqa } = prevProps.segment;
        if ( lexiqa && _.size(lexiqa) > 0 && lexiqa.target &&
            (_.isUndefined(prevLexiqa) || !Immutable.fromJS(prevLexiqa).equals(Immutable.fromJS(lexiqa)) ) ) {
            this.addLexiqaDecorator();
        } else if ((prevLexiqa && _.size(prevLexiqa) > 0 ) && ( !lexiqa ||  _.size(lexiqa) === 0 || !lexiqa.target ) ) {
            this.removeLexiqaDecorator()
        }
    };

    componentDidMount() {
        //console.log(`componentDidMount@EditArea ${this.props.segment.sid}`)
        SegmentStore.addListener(SegmentConstants.REPLACE_TRANSLATION, this.setNewTranslation);
        SegmentStore.addListener(EditAreaConstants.REPLACE_SEARCH_RESULTS, this.replaceCurrentSearch);
        SegmentStore.addListener(EditAreaConstants.COPY_GLOSSARY_IN_EDIT_AREA, this.copyGlossaryToEditArea);
        if ( this.props.segment.inSearch ) {
            setTimeout(this.addSearchDecorator());
        }
        const {lexiqa} = this.props.segment;
        if ( lexiqa && _.size(lexiqa) > 0 && lexiqa.target ) {
            setTimeout(this.addLexiqaDecorator());
        }
        setTimeout(()=>{
            this.updateTranslationInStore();
            /*if(this.props.segment.opened && this.editor){
                this.editor.focus();
            }*/
        });
    }

    copyGlossaryToEditArea = (segment, glossaryTranslation) =>{
        if(segment.sid === this.props.segment.sid) {
            const {editorState} = this.state;
            const newEditorState = DraftMatecatUtils.insertText(editorState, glossaryTranslation)
            this.setState({
                editorState: newEditorState
            }, () => {
                this.updateTranslationDebounced();
            })
        }
    }

    componentWillUnmount() {
        SegmentStore.removeListener(SegmentConstants.REPLACE_TRANSLATION, this.setNewTranslation);
        SegmentStore.removeListener(EditAreaConstants.REPLACE_SEARCH_RESULTS, this.replaceCurrentSearch);
        SegmentStore.removeListener(EditAreaConstants.COPY_GLOSSARY_IN_EDIT_AREA, this.copyGlossaryToEditArea);
    }

    // shouldComponentUpdate(nextProps, nextState) {}

    // getSnapshotBeforeUpdate(prevProps) {}

    componentDidUpdate(prevProps, prevState, snapshot) {
        if(!prevProps.segment.opened && this.props.segment.opened){
            this.focusEditor();
        }
        if(!editorSync.onComposition){
            this.checkDecorators(prevProps);
        }
    }

    render() {

        const {editorState,
            displayPopover,
            autocompleteSuggestions,
            focusedTagIndex,
            popoverPosition
        } = this.state;

        const {
            onChange,
            copyFragment,
            pasteFragment,
            onTagClick,
            handleKeyCommand,
            myKeyBindingFn,
            onMouseUpEvent,
            onBlurEvent
        } = this;

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
                    onCopy={copyFragment}
                    onCut={copyFragment}
                    onMouseUp={onMouseUpEvent}
                    onBlur={onBlurEvent}
                    onDragStart={this.onDragEvent}
                    onDragEnd={this.onDragEnd}
                    onDrop={this.onDragEnd}
        >
            <Editor
                lang={lang}
                editorState={editorState}
                onChange={onChange}
                handlePastedText={pasteFragment}
                ref={(el) => this.editor = el}
                readOnly={readonly}
                handleKeyCommand={handleKeyCommand}
                keyBindingFn={myKeyBindingFn}
                handleDrop={this.handleDrop}
            />
            <TagBox
                displayPopover={displayPopover}
                suggestions={autocompleteSuggestions}
                onTagClick={onTagClick}
                focusedTagIndex={focusedTagIndex}
                popoverPosition={popoverPosition}
            />
        </div>;
    }

    focusEditor = () =>{
        if(this.editor) this.editor.focus();
    }

    myKeyBindingFn = (e) => {
        const {displayPopover} = this.state;
        if((e.key === 't' || e.key === '™') && (isOptionKeyCommand(e) || e.altKey) && !e.shiftKey) {
            this.setState({
                triggerText: null
            });
            return 'toggle-tag-menu';
        }else if(e.key === '<' && !hasCommandModifier(e)) {
            const textToInsert = '<';
            const {editorState} = this.state;
            const newEditorState = DraftMatecatUtils.insertText(editorState, textToInsert);
            this.setState({
                editorState: newEditorState,
                triggerText: textToInsert
            });
            return 'toggle-tag-menu';
        }else if(e.key === 'ArrowUp' && !hasCommandModifier(e)){
            if(displayPopover) return 'up-arrow-press';
        }else if(e.key === 'ArrowDown' && !hasCommandModifier(e)){
            if(displayPopover) return 'down-arrow-press';
        }else if(e.key === 'Enter' && !hasCommandModifier(e)){
            if(displayPopover) return 'enter-press';
        }else if(e.key === 'Escape'){
            return 'close-tag-menu';
        }else if(e.key === 'Tab'){
            return e.shiftKey ? null : 'insert-tab-tag';
        }else if( (e.key === ' ' || e.key === 'Spacebar' || e.key === ' ') &&
            hasCommandModifier(e) &&
            e.shiftKey){ // e.key is an &nbsp;
            return 'insert-nbsp-tag';
        }else if (e.key === 'ArrowLeft' && !hasCommandModifier(e) && !e.altKey) {
            if (e.shiftKey) {
                return 'left-nav-shift';
            } else {
                return 'left-nav';
            }
        } else if (e.key === 'ArrowRight' && !hasCommandModifier(e) && !e.altKey) {
            if (e.shiftKey) {
                return 'right-nav-shift';
            } else {
                return 'right-nav';
            }
        }
        return getDefaultKeyBinding(e);
    };

    handleKeyCommand = (command) => {
        const {
            openPopover,
            closePopover,
            getEditorRelativeSelectionOffset,
            moveDownTagMenuSelection,
            moveUpTagMenuSelection,
            acceptTagMenuSelection,
            handleCursorMovement
        } = this;
        const {segment: {sourceTagMap, missingTagsInTarget}} = this.props;

        switch (command) {
            case 'toggle-tag-menu':
                const tagSuggestions = {
                    missingTags: missingTagsInTarget,
                    sourceTags: sourceTagMap
                }
                if(tagSuggestions.sourceTags && tagSuggestions.sourceTags.length > 0){
                    openPopover(tagSuggestions, getEditorRelativeSelectionOffset());
                }
                return 'handled';
            case 'close-tag-menu':
                closePopover();
                return 'handled';
            case 'up-arrow-press':
                moveUpTagMenuSelection();
                return 'handled';
            case 'down-arrow-press':
                moveDownTagMenuSelection();
                return 'handled';
            case 'enter-press':
                acceptTagMenuSelection();
                return 'handled';
            case 'left-nav':
                handleCursorMovement(-1, false, config.isTargetRTL);
                return 'handled';
            case 'left-nav-shift':
                handleCursorMovement(-1, true, config.isTargetRTL);
                return 'handled';
            case 'right-nav':
                handleCursorMovement(1, false, config.isTargetRTL);
                return 'handled';
            case 'right-nav-shift':
                handleCursorMovement(1, true, config.isTargetRTL);
                return 'handled';
            case 'insert-tab-tag':
                this.insertTagAtSelection('tab');
                return 'handled';
            case 'insert-nbsp-tag':
                this.insertTagAtSelection('nbsp');
                return 'handled';
            default:
                return 'not-handled';
        }
    };

    insertTagAtSelection = (tagType) => {
        const {editorState} = this.state;
        const customTag = DraftMatecatUtils.structFromType(tagType);
        // If tag creation has failed, return
        if(!customTag) return;
        // Start composition mode and remove lexiqa
        editorSync.onComposition = true;
        let newEditorState = this.disableDecorator(editorState, 'lexiqa');
        newEditorState = DraftMatecatUtils.insertEntityAtSelection(newEditorState, customTag);

        this.setState({
            editorState: newEditorState
        }, () => {
            this.updateTranslationDebounced();
        });
        // Stop composition mode
        this.onCompositionStopDebounced();
    }

    handleCursorMovement = (step, shift = false, isRTL = false) =>{
        const {editorState} = this.state;
        step = isRTL ? step * -1 : step;
        const newEditorState = DraftMatecatUtils.moveCursorJumpEntity(editorState, step, shift);
        this.setState({
            editorState: newEditorState
        })
    }

    onMouseUpEvent = () => {
        const {toggleFormatMenu} = this.props;
        toggleFormatMenu(!this.editor._latestEditorState.getSelection().isCollapsed());
    };

    onBlurEvent = () => {
        // Hide Edit Toolbar
        const {toggleFormatMenu, setClickedTagId} = this.props;
        toggleFormatMenu(false);
        setClickedTagId();

    };

    // Focus on editor trigger 2 onChange events
    /*onBlur = () => {
        if (!editorSync.clickedOnTag) {
            this.setState({
                displayPopover: false,
                editorFocused: false
            });
            editorSync.editorFocused = false;
        }
    };*/

    /*onFocus = () => {
        editorSync.editorFocused = true;
        editorSync.inTransitionToFocus = true;
        this.setState({
            editorFocused: true,
        });
    };*/

    updateTagsInEditor = () => {
        console.log('Executing updateTagsInEditor');
        const {editorState, tagRange} = this.state;
        let newEditorState = editorState;
        let newTagRange = tagRange;
        // Cerco i tag attualmente presenti nell'editor
        // Todo: Se ci sono altre entità oltre i tag nell'editor, aggiungere l'entityType alla chiamata
        const entities = DraftMatecatUtils.getEntities(editorState);
        if(tagRange.length !== entities.length){
            console.log('Aggiorno tutte le entità');
            const lastSelection = editorState.getSelection();
            // Aggiorna i tag presenti
            const decodedSegment = DraftMatecatUtils.decodeSegment(editorState);
            newTagRange = DraftMatecatUtils.matchTag(decodedSegment); // range update
            // Aggiornamento live dei collegamenti tra i tag non self-closed
            newEditorState = updateEntityData(editorState, newTagRange, lastSelection, entities);
        }
        this.setState({
            editorState: newEditorState,
            tagRange: newTagRange
        });
    };

    onCompositionStop = () => {
        editorSync.onComposition = false;
    }

    disableDecorator = (editorState, decoratorName) => {
        _.remove(this.decoratorsStructure, (decorator) => decorator.name === decoratorName);
        const decorator = new CompoundDecorator(this.decoratorsStructure);
        return EditorState.set( editorState, {decorator} )
    }

    onChange = (editorState) =>  {
        const {setClickedTagId} = this.props;
        const {displayPopover} = this.state;
        const {closePopover, updateTagsInEditorDebounced} = this;
        const contentChanged = editorState.getCurrentContent().getPlainText() !== this.state.editorState.getCurrentContent().getPlainText();
        // if not on an entity, remove any previous selection highlight
        const entityKey = DraftMatecatUtils.selectionIsEntity(editorState)
        if(!entityKey) setClickedTagId();
        // while onComposition, remove unwanted decorators like lexiqa
        if(contentChanged){
            console.log('contentChanged')
            editorSync.onComposition = true;
            editorState = this.disableDecorator(editorState, 'lexiqa')
            /*// remove lexiqa
            _.remove(this.decoratorsStructure, (decorator) => decorator.name === 'lexiqa');
            const decorator = new CompoundDecorator(this.decoratorsStructure);
            editorState = EditorState.set( editorState, {decorator} )*/
        }
        // if opened, close TagsMenu
        if(displayPopover) closePopover();

        this.setState({
            editorState: editorState,
            translation: DraftMatecatUtils.decodeSegment(editorState)
        }, () => {
            //updateTagsInEditorDebounced()
            if(contentChanged) this.updateTranslationDebounced();
        });
        this.onCompositionStopDebounced()
    };

    // Methods for TagMenu ---- START
    moveUpTagMenuSelection = () => {
        const {displayPopover} = this.state;
        if (!displayPopover) return;
        const {focusedTagIndex, autocompleteSuggestions : {missingTags, sourceTags}} = this.state;
        const mergeAutocompleteSuggestions = [...missingTags, ...sourceTags];
        const newFocusedTagIndex = focusedTagIndex - 1 < 0 ?
            mergeAutocompleteSuggestions.length - 1
            :
            (focusedTagIndex - 1) % mergeAutocompleteSuggestions.length;

        this.setState({
            focusedTagIndex: newFocusedTagIndex
        });
    };

    moveDownTagMenuSelection = () => {
        const {displayPopover} = this.state;
        if (!displayPopover) return;
        const {focusedTagIndex, autocompleteSuggestions : {missingTags, sourceTags}} = this.state;
        const mergeAutocompleteSuggestions = [...missingTags, ...sourceTags];
        this.setState({focusedTagIndex: (focusedTagIndex + 1) % mergeAutocompleteSuggestions.length});
    };

    acceptTagMenuSelection = () => {
        const {focusedTagIndex,
            displayPopover,
            editorState,
            triggerText,
            autocompleteSuggestions : {missingTags, sourceTags}
        } = this.state;
        if (!displayPopover) return;
        const mergeAutocompleteSuggestions = [...missingTags, ...sourceTags];
        const selectedTag = mergeAutocompleteSuggestions[focusedTagIndex];

        editorSync.onComposition = true;
        let newEditorState = this.disableDecorator(editorState, 'lexiqa')
        const editorStateWithSuggestedTag = insertTag(selectedTag, newEditorState, triggerText);

        this.setState({
            editorState: editorStateWithSuggestedTag,
            displayPopover: false,
            clickedTag: selectedTag,
            clickedOnTag: true,
            triggerText: null
        }, () => {
            this.updateTranslationDebounced();
        });
        this.onCompositionStopDebounced();
    };

    openPopover = (suggestions, position) => {
        // Posizione da salvare e passare al compoennte
        const popoverPosition = {
            top: position.top,
            left: position.left
        };

        this.setState({
            displayPopover: true,
            autocompleteSuggestions: suggestions,
            focusedTagIndex: 0,
            popoverPosition: popoverPosition,
        });
    };

    closePopover = () => {
        this.setState({
            displayPopover: false,
            triggerText: null
        });
    };

    onTagClick = (suggestionTag) => {
        const {editorState, triggerText} = this.state;
        editorSync.onComposition = true;
        let newEditorState = this.disableDecorator(editorState, 'lexiqa')
        let editorStateWithSuggestedTag = insertTag(suggestionTag, newEditorState, triggerText);
        this.setState({
            editorState: editorStateWithSuggestedTag,
            editorFocused: true,
            clickedOnTag: true,
            clickedTag: suggestionTag,
            displayPopover: false,
            triggerText: null
        }, () => {
            this.updateTranslationDebounced();
        });
        this.onCompositionStopDebounced();
    };

    // Methods for TagMenu ---- END

    onPaste = (text, html) => {
        const {editorState} = this.state;
        const internalClipboard = this.editor.getClipboard();
        if (internalClipboard) {
            console.log('Fragment --> ',internalClipboard )
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

    pasteFragment = (text, html) => {
        const {editorState} = this.state;
        const {fragment: clipboardFragment, plainText: clipboardPlainText} = SegmentStore.getFragmentFromClipboard();
        // if text in standard clipboard matches the the plainClipboard saved in store proceed using fragment
        // otherwise we're handling an external copy
        if(clipboardFragment && text && clipboardPlainText === text) {
            try {
                const fragmentContent = JSON.parse(clipboardFragment);
                let fragment = DraftMatecatUtils.buildFragmentFromJson(fragmentContent.orderedMap);
                const clipboardEditorPasted = DraftMatecatUtils.duplicateFragment(
                    fragment,
                    editorState,
                    fragmentContent.entitiesMap
                );
                this.setState({
                    editorState: clipboardEditorPasted,
                },() => {
                    this.updateTranslationDebounced();
                });
                // Paste fragment
                return true;
            } catch (e) {
                // Paste plain standard clipboard
                return false;
            }
        }else if(text){
            // we're handling an external copy, special chars must be striped from text
            // and we have to add tag for external entities like nbsp or tab
            let cleanText = DraftMatecatUtils.cleanSegmentString(DraftMatecatUtils.unescapeHTML(text));
            // Replace with placeholder
            const nbspSign = tagSignatures['nbsp'].encodedPlaceholder;
            const tabSign = tagSignatures['tab'].encodedPlaceholder
            cleanText = cleanText.replace(/°/gi, nbspSign).replace(/\t/gi, tabSign);
            const plainTextClipboardFragment = DraftMatecatUtils.buildFragmentFromText(cleanText);
            const clipboardEditorPasted = DraftMatecatUtils.duplicateFragment(
                plainTextClipboardFragment,
                editorState
            );
            this.setState({
                editorState: clipboardEditorPasted,
            },() => {
                this.updateTranslationDebounced();
            });
            // Paste fragment
            return true;
        }
        // Paste plain standard clipboard
        return false;
    };

    copyFragment = (e) => {
        const internalClipboard = this.editor.getClipboard();
        const {editorState} = this.state;
        if (internalClipboard) {
            // Get plain text form internalClipboard fragment
            const plainText = internalClipboard.map((block) => block.getText()).join('\n');
            const entitiesMap = DraftMatecatUtils.getEntitiesInFragment(internalClipboard, editorState)
            const fragment = JSON.stringify({
                orderedMap: internalClipboard,
                entitiesMap: entitiesMap
            });
            SegmentActions.copyFragmentToClipboard(fragment, plainText)
        }
    };

    onDragEvent = (e) => {
        editorSync.draggingFromEditArea = true;
    }

    onDragEnd = (e) => {
        editorSync.draggingFromEditArea = false;
    }

    handleDrop = (selection, dataTransfer, isInternal) => {
        let {editorState} = this.state;
        const text = dataTransfer.getText();

        // get selection of dragged text
        const dragSelection = editorState.getSelection();
        const dragSelectionLength = dragSelection.focusOffset - dragSelection.anchorOffset;
        // get the fragment from current selection in editor (the highlighted tag)
        let fragmentFromSelection = getFragmentFromSelection(editorState);
        // Il fragment di draft NON FUNZIONA quindi lo ricostruisco
        let tempFrag = DraftMatecatUtils.buildFragmentFromJson(fragmentFromSelection);

        console.log('Entity check:before', DraftMatecatUtils.getEntities(editorState))

        // set selection to drop point and check dropping zone
        editorState = EditorState.forceSelection(editorState, selection);
        // Check: Cannot drop anything on entities
        if(DraftMatecatUtils.selectionIsEntity(editorState)){
            return 'handled';
        }
        if(text && !editorSync.draggingFromEditArea) {
            /*console.log('External drag', text)*/
            try {
                const fragmentContent = JSON.parse(text);
                let fragment = DraftMatecatUtils.buildFragmentFromJson(fragmentContent.orderedMap);
                const editorStateWithFragment = DraftMatecatUtils.duplicateFragment(
                    fragment,
                    editorState,
                    fragmentContent.entitiesMap
                );
                this.setState({
                    editorState: editorStateWithFragment,
                }, () => {
                    this.updateTranslationDebounced();
                });
                return 'handled';
            } catch (err) {
                return 'not-handled';
            }
        }else{
            /*console.log('Internal drag')*/
            // when drop is inside the same editor, use default behavior
            // update: default behavior not working
            try {
                // remove drag selected range from editor state
                let contentState = editorState.getCurrentContent();
                contentState = Modifier.removeRange(
                    contentState,
                    dragSelection,
                    dragSelection.isBackward ? 'backward' : 'forward'
                );

                // Aggiornala nel caso in cui sposti in avanti il drag nello stesso blocco
                const dragBlockKey = dragSelection.getAnchorKey();
                const dropBlockKey = selection.getAnchorKey();
                selection = dragSelection.anchorOffset < selection.anchorOffset &&
                dragBlockKey === dropBlockKey ?
                    selection.merge({
                        anchorOffset: selection.anchorOffset - dragSelectionLength,
                        focusOffset: selection.focusOffset - dragSelectionLength,
                    }) :
                    selection

                // Inserisci il fragment
                contentState = Modifier.replaceWithFragment(
                    contentState,
                    selection,
                    tempFrag
                )

                editorState = EditorState.push(editorState, contentState, 'insert-fragment');
                editorState = EditorState.forceSelection(editorState, selection);

                console.log('Entity check:after', DraftMatecatUtils.getEntities(editorState))
                this.setState({
                    editorState: editorState,
                }, () => {
                    this.updateTranslationDebounced();
                    this.props.setClickedTagId();
                });
                return 'handled';
            } catch (err) {
                console.log(err)
                return 'not-handled';
            }
        }
        return 'not-handled';
    }

    onEntityClick = (start, end, id) => {
        const {editorState} = this.state;
        const {setClickedTagId} = this.props;
        // Use _latestEditorState
        try{
            // Selection
            const selectionState = this.editor._latestEditorState.getSelection();
            let newSelection = selectionState.merge({
                anchorOffset: start,
                focusOffset: end,
            });
            const newEditorState = EditorState.forceSelection(
                editorState,
                newSelection,
            );
            this.setState({editorState: newEditorState});
            // Highlight
            setClickedTagId(id);
        }catch (e) {
            console.log('Invalid selection')
        }
    };

    getClickedTagInfo = () => {
        const {clickedTagId, tagClickedInSource} = this.props;
        return {clickedTagId, tagClickedInSource};
    };

    /**
     *
     * @param minWidth - min length of element to show
     * @returns {{top: number, left: number}}
     */
    getEditorRelativeSelectionOffset = (minWidth = 300) => {
        const editorBoundingRect = this.editor.editor.getBoundingClientRect();
        const selectionBoundingRect = window.getSelection().getRangeAt(0).getBoundingClientRect();
        const leftInitial = selectionBoundingRect.x - editorBoundingRect.x;
        const leftAdjusted =  editorBoundingRect.right - selectionBoundingRect.left < minWidth ?
            leftInitial  - (minWidth - (editorBoundingRect.right - selectionBoundingRect.left))
            :
            leftInitial;
        if(selectionBoundingRect.bottom === 0 &&
            selectionBoundingRect.left === 0 &&
            selectionBoundingRect.height === 0){
            return {
                top: 50,
                left: 50
            };
        }
        return {
            top: selectionBoundingRect.bottom - editorBoundingRect.top + selectionBoundingRect.height,
            left: leftAdjusted
        };
    };

    getUpdatedSegmentInfo = () => {
        const {segment: { warnings, tagMismatch, opened, missingTagsInTarget}} = this.props;
        const {tagRange, editorState} = this.state;
        return{
            warnings,
            tagMismatch,
            tagRange,
            segmentOpened: opened,
            missingTagsInTarget,
            currentSelection: editorState.getSelection()
        }
    };

    formatSelection = (format) =>{
        const {editorState} = this.state;
        if(editorState.getSelection().isCollapsed()) {
            return;
        }
        let selectedText = DraftMatecatUtils.getSelectedText(editorState);
        selectedText = DraftMatecatUtils.formatText(selectedText, format);
        const newEditorState = insertText(editorState, selectedText);
        this.setState({
            editorState: newEditorState
        });
    };

    addMissingSourceTagsToTarget = () => {
        const {segment} = this.props;
        const {editorState} = this.state;
        // Append missing tag at the end of the current translation string
        let newTranslation = segment.translation;
        let newDecodedTranslation = segment.decodedTranslation;
        let newEditorState = editorState;
        segment.missingTagsInTarget.forEach( tag => {
            newTranslation += tag.data.encodedText;
            newDecodedTranslation+= tag.data.placeholder;
            newEditorState = DraftMatecatUtils.addTagEntityToEditor(newEditorState, tag);
        });
        // Append missing tags to targetTagMap
        let segmentTargetTagMap = [...segment.targetTagMap, ...segment.missingTagsInTarget];
        // Insert tag entity in current editor without recompute tags associations
        this.setState({
            editorState: newEditorState
        });
        //lock tags and run again getWarnings
        setTimeout( (  )=> {
            SegmentActions.updateTranslation(segment.sid, newTranslation, newDecodedTranslation, segmentTargetTagMap, []);
            UI.segmentQA(UI.getSegmentById(this.props.segment.sid));
        }, 100);
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

