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
import {Modifier, Editor, EditorState, getDefaultKeyBinding, KeyBindingUtil} from "draft-js";
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


const editorSync = {
    inTransitionToFocus: false,
    editorFocused: false,
    clickedOnTag: false
};

class Editarea extends React.Component {

    constructor(props) {
        super(props);
        const {onEntityClick, updateTagsInEditor, getUpdatedSegmentInfo, getClickedTagId} = this;

        this.decoratorsStructure = [
            {
                strategy: getEntityStrategy('IMMUTABLE'),
                component: TagEntity,
                props: {
                    isTarget: true,
                    onClick: onEntityClick,
                    getUpdatedSegmentInfo: getUpdatedSegmentInfo,
                    getClickedTagId: getClickedTagId
                    // getSearchParams: this.getSearchParams //TODO: Make it general ?
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
    }

    // getSearchParams = () => {
    //     const {inSearch,
    //         currentInSearch,
    //         searchParams,
    //         occurrencesInSearch,
    //         currentInSearchIndex
    //     } = this.props.segment;
    //     if ( inSearch && searchParams.target) {
    //         return {
    //             active: inSearch,
    //             currentActive: currentInSearch,
    //             textToReplace: searchParams.target,
    //             params: searchParams,
    //             occurrences : occurrencesInSearch.occurrences,
    //             currentInSearchIndex
    //         }
    //     } else {
    //         return {
    //             active: false
    //         }
    //     }
    // };

    addSearchDecorator = () => {
        let { editorState } = this.state;
        let { searchParams, occurrencesInSearch, currentInSearchIndex } = this.props.segment;
        const { editorState: newEditorState, decorators } = activateSearch( editorState, this.decoratorsStructure, searchParams.target,
            searchParams, occurrencesInSearch.occurrences, currentInSearchIndex );
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
            const { editorState : newEditorState, decorators } = activateLexiqa( editorState, this.decoratorsStructure, updatedLexiqaWarnings, sid, false);
            this.decoratorsStructure = decorators;
            this.setState( {
                editorState: newEditorState,
            } );
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
            const {segment, segment: {sourceTagMap, targetTagMap}} = this.props;
            const {editorState, tagRange} = this.state;
            const decodedSegment = DraftMatecatUtils.decodeSegment(editorState);
            let contentState = editorState.getCurrentContent();
            let plainText = contentState.getPlainText();
            // Add missing tag to store for highlight warnings on tags
            const {missingTags} = checkForMissingTags(sourceTagMap, targetTagMap);
            // Match tag without compute tag id
            const currentTagRange = DraftMatecatUtils.matchTagInEditor(editorState);
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
        } else if ((prevLexiqa && prevLexiqa.length > 0 ) && ( !lexiqa ||  _.size(lexiqa) === 0 || !lexiqa.target ) ) {
            this.removeLexiqaDecorator()
        }
    };

    componentDidMount() {
        //console.log(`componentDidMount@EditArea ${this.props.segment.sid}`)
        SegmentStore.addListener(SegmentConstants.REPLACE_TRANSLATION, this.setNewTranslation);
        SegmentStore.addListener(EditAreaConstants.REPLACE_SEARCH_RESULTS, this.replaceCurrentSearch);
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

    componentWillUnmount() {
        SegmentStore.removeListener(SegmentConstants.REPLACE_TRANSLATION, this.setNewTranslation);
        SegmentStore.removeListener(EditAreaConstants.REPLACE_SEARCH_RESULTS, this.replaceCurrentSearch);
    }

    // shouldComponentUpdate(nextProps, nextState) {}

    // getSnapshotBeforeUpdate(prevProps) {}

    componentDidUpdate(prevProps, prevState, snapshot) {

        this.checkDecorators(prevProps);
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
                    onMouseUp={onMouseUpEvent}
                    onBlur={onBlurEvent}
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

    myKeyBindingFn = (e) => {
        const {displayPopover} = this.state;
        if(e.keyCode === 84 && isOptionKeyCommand(e) && !e.shiftKey) {
            this.setState({
                triggerText: null
            });
            return 'toggle-tag-menu';
        }else if(e.keyCode === 188 && !hasCommandModifier(e)) {
            const textToInsert = '<';
            const {editorState} = this.state;
            const newEditorState = DraftMatecatUtils.insertText(editorState, textToInsert);
            this.setState({
                editorState: newEditorState,
                triggerText: textToInsert
            });
            return 'toggle-tag-menu';
        }else if(e.keyCode === 38 && !hasCommandModifier(e)){ //
            if(displayPopover) return 'up-arrow-press';
        }else if(e.keyCode === 40 && !hasCommandModifier(e)){ // giù
            if(displayPopover) return 'down-arrow-press';
        }else if(e.keyCode === 13 && !hasCommandModifier(e)){ // enter
            if(displayPopover) return 'enter-press';
        }else if(e.keyCode === 27){ // enter
            return 'close-tag-menu';
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
            acceptTagMenuSelection
        } = this;
        const {segment: {sourceTagMap, targetTagMap}} = this.props;

        switch (command) {
            case 'toggle-tag-menu':
                // Todo: prenderla dallo state
                const tagSuggestions = checkForMissingTags(sourceTagMap, targetTagMap);
                openPopover(tagSuggestions, getEditorRelativeSelectionOffset());
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
            default:
                return 'not-handled';
        }
    };

    onMouseUpEvent = () => {
        const {toggleFormatMenu} = this.props;
        toggleFormatMenu(!this.editor._latestEditorState.getSelection().isCollapsed());
    };

    onBlurEvent = () => {
        // Hide Edit Toolbar
        const {toggleFormatMenu} = this.props;
        toggleFormatMenu(false);
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

    onChange = (editorState) =>  {
        const {setClickedTagId} = this.props;
        const {displayPopover} = this.state;
        const {closePopover, updateTagsInEditorDebounced, selectionIsEntity} = this;

        // Se non ti trovi ancora su un'entità, annulla eventuali TagClickedId settati
        const entityKey = selectionIsEntity(editorState);
        if(!entityKey) {setClickedTagId();}

        this.setState({
            editorState: editorState,
        }/*, () => {
            updateTagsInEditorDebounced()
        }*/);

        if(displayPopover){
            closePopover();
        }
        setTimeout(()=>{this.updateTranslationDebounced()});
    };

    selectionIsEntity = (editorState) => {
        const contentState = editorState.getCurrentContent();
        const selectionKey = editorState.getSelection().getAnchorKey();
        const selectionOffset =  editorState.getSelection().getAnchorOffset();
        const block = contentState.getBlockForKey(selectionKey);
        return block.getEntityAt(selectionOffset);
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
        const editorStateWithSuggestedTag = insertTag(selectedTag, editorState, triggerText);
        // Todo: Force to recompute every tag association

        this.setState({
            editorState: editorStateWithSuggestedTag,
            displayPopover: false,
            clickedTag: selectedTag,
            clickedOnTag: true,
            triggerText: null
        });
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
        let editorStateWithSuggestedTag = insertTag(suggestionTag, editorState, triggerText);

        this.setState({
            editorState: editorStateWithSuggestedTag,
            editorFocused: true,
            clickedOnTag: true,
            clickedTag: suggestionTag,
            displayPopover: false,
            triggerText: null
        });
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
        if(text) {
            try {
                const fragmentContent = JSON.parse(text);
                let fragment = DraftMatecatUtils.buildFragmentFromText(fragmentContent.orderedMap);

                console.log('Fragment --> ',fragment );

                const clipboardEditorPasted = DraftMatecatUtils.duplicateFragment(
                    fragment,
                    editorState,
                    fragmentContent.entitiesMap
                );

                this.onChange(clipboardEditorPasted);
                this.setState({
                    editorState: clipboardEditorPasted,
                });
                return true;
            } catch (e) {
                return false;
            }
        }
        return false;
    };

    copyFragment = (e) => {
        const internalClipboard = this.editor.getClipboard();
        const {editorState} = this.state;

        if (internalClipboard) {
            console.log('InternalClipboard ', internalClipboard)
            const entitiesMap = DraftMatecatUtils.getEntitiesInFragment(internalClipboard, editorState)

            const fragment = JSON.stringify({
                orderedMap: internalClipboard,
                entitiesMap: entitiesMap
            });
            e.clipboardData.clearData();
            e.clipboardData.setData('text/html', fragment);
            e.clipboardData.setData('text/plain', fragment);
            console.log("Copied -> ", e.clipboardData.getData('text/html'));
            e.preventDefault();
        }
    };

    onEntityClick = (start, end, id) => {
        const {editorState} = this.state;
        const {setClickedTagId} = this.props;
        setClickedTagId(id);
        const selectionState = editorState.getSelection();
        let newSelection = selectionState.merge({
            anchorOffset: start,
            focusOffset: end,
        });
        const newEditorState = EditorState.forceSelection(
            editorState,
            newSelection,
        );
        this.setState({editorState: newEditorState});
    };

    getClickedTagId = () => {
        const {clickedTagId} = this.props;
        return clickedTagId;
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
        return {
            top: selectionBoundingRect.bottom - editorBoundingRect.top + selectionBoundingRect.height,
            left: leftAdjusted
        };
    };

    getUpdatedSegmentInfo = () => {
        const {segment: { warnings, tagMismatch, opened, missingTagsInTarget}} = this.props;
        const {tagRange} = this.state;
        return{
            warnings,
            tagMismatch,
            tagRange,
            segmentOpened: opened,
            missingTagsInTarget
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

