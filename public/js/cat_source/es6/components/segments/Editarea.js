/**
 * React Component for the editarea.

 */
import React  from 'react';
import SegmentConstants  from '../../constants/SegmentConstants';
import EditAreaConstants  from '../../constants/EditAreaConstants';
import SegmentStore  from '../../stores/SegmentStore';
import Immutable  from 'immutable';
import DraftMatecatUtils from './utils/DraftMatecatUtils'
import * as DraftMatecatConstants from "./utils/DraftMatecatUtils/editorConstants";
import {Modifier, Editor, EditorState, getDefaultKeyBinding, KeyBindingUtil, ContentState, CompositeDecorator} from "draft-js";
import TagEntity from "./TagEntity/TagEntity.component";
import SegmentUtils from "../../utils/segmentUtils";
import CompoundDecorator from "./utils/CompoundDecorator"
import TagBox from "./utils/DraftMatecatUtils/TagMenu/TagBox";
import insertTag from "./utils/DraftMatecatUtils/TagMenu/insertTag";
import checkForMissingTags from "./utils/DraftMatecatUtils/TagMenu/checkForMissingTag";
import updateEntityData from "./utils/DraftMatecatUtils/updateEntityData";
const {hasCommandModifier, isOptionKeyCommand, isCtrlKeyCommand} = KeyBindingUtil;
import LexiqaUtils from "../../utils/lxq.main";
import updateLexiqaWarnings from "./utils/DraftMatecatUtils/updateLexiqaWarnings";
import insertText from "./utils/DraftMatecatUtils/insertText";
import {tagSignatures} from "./utils/DraftMatecatUtils/tagModel";
import SegmentActions from "../../actions/SegmentActions";
import getFragmentFromSelection
    from "./utils/DraftMatecatUtils/DraftSource/src/component/handlers/edit/getFragmentFromSelection";

const editorSync = {
    editorFocused: true,
    clickedOnTag: false,
    onComposition: false
};

class Editarea extends React.Component {

    constructor(props) {
        super(props);
        const {onEntityClick, updateTagsInEditor, getUpdatedSegmentInfo, getClickedTagInfo} = this;

        this.decoratorsStructure = [
            {
                name: 'tags',
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
        const decorator = new CompositeDecorator(this.decoratorsStructure);
        //const decorator = new CompoundDecorator(this.decoratorsStructure);
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
            triggerText: null,
            activeDecorators: {
                [DraftMatecatConstants.LEXIQA_DECORATOR]: false,
                [DraftMatecatConstants.QA_BLACKLIST_DECORATOR]: false,
                [DraftMatecatConstants.SEARCH_DECORATOR]: false
            }
        };
        this.updateTranslationDebounced = _.debounce(this.updateTranslationInStore, 100);
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
        const newDecorator = DraftMatecatUtils.activateSearch(textToSearch,
            searchParams, occurrencesInSearch.occurrences, currentInSearchIndex, tagRange );
        _.remove(this.decoratorsStructure, (decorator) => decorator.name === DraftMatecatConstants.SEARCH_DECORATOR);
        this.decoratorsStructure.push(newDecorator);
    };

    addQaBlacklistGlossaryDecorator = () => {
        let { editorState } = this.state;
        let { qaBlacklistGlossary, sid } = this.props.segment;
        const newDecorator = DraftMatecatUtils.activateQaCheckBlacklist(qaBlacklistGlossary, sid );
        _.remove(this.decoratorsStructure, (decorator) => decorator.name === DraftMatecatConstants.QA_BLACKLIST_DECORATOR);
        this.decoratorsStructure.push(newDecorator);
    };

    addLexiqaDecorator = () => {
        let { editorState } = this.state;
        let { lexiqa, sid, lxqDecodedTranslation, targetTagMap } = this.props.segment;
        // pass decoded translation with tags like <g id='1'>
        let ranges = LexiqaUtils.getRanges(_.cloneDeep(lexiqa.target), lxqDecodedTranslation, false);
        const updatedLexiqaWarnings = updateLexiqaWarnings(editorState, ranges);
        if ( updatedLexiqaWarnings.length > 0 ) {
            const newDecorator = DraftMatecatUtils.activateLexiqa( editorState,
                updatedLexiqaWarnings,
                sid,
                false,
                this.getUpdatedSegmentInfo);
            _.remove(this.decoratorsStructure, (decorator) => decorator.name === DraftMatecatConstants.LEXIQA_DECORATOR);
            this.decoratorsStructure.push(newDecorator);
        } else {
            this.removeDecorator(DraftMatecatConstants.LEXIQA_DECORATOR);
        }
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
            const newEditorState = DraftMatecatUtils.replaceOccurrences(this.state.editorState, searchParams.target, text, index)
            this.setState( {
                editorState: newEditorState,
            }, () => {
                this.updateTranslationInStore();
            });
        }
    };

    updateTranslationInStore = () => {
        const translation = DraftMatecatUtils.decodeSegment(this.state.editorState)
        if ( translation !== '' ) {
            const {segment, segment: {sourceTagMap}} = this.props;
            const {editorState, tagRange} = this.state;
            const decodedSegment = DraftMatecatUtils.decodeSegment(editorState);
            let contentState = editorState.getCurrentContent();
            let plainText = contentState.getPlainText();
            // Match tag without compute tag id
            const currentTagRange = DraftMatecatUtils.matchTagInEditor(editorState);
            // Add missing tag to store for highlight warnings on tags
            const {missingTags} = checkForMissingTags(sourceTagMap, currentTagRange);
            const lxqDecodedTranslation = DraftMatecatUtils.prepareTextForLexiqa(editorState);
            //const currentTagRange = matchTag(decodedSegment); //deactivate if updateTagsInEditor is active
            SegmentActions.updateTranslation(segment.sid, decodedSegment, plainText, currentTagRange, missingTags, lxqDecodedTranslation);
            // console.log('updatingTranslationInStore');
            UI.registerQACheck();
        }
    };

    checkDecorators = (prevProps) => {
        let changedDecorator = false;
        const { inSearch } = this.props.segment;
        const sid = this.props.segment.sid
        const { activeDecorators: prevActiveDecorators, editorState} = this.state;
        const activeDecorators = {...prevActiveDecorators}

        if(!inSearch){

            //Qa Check Blacklist
            const { qaBlacklistGlossary } = this.props.segment;
            const { qaBlacklistGlossary : prevQaBlacklistGlossary } = prevProps.segment;
            if ( qaBlacklistGlossary && qaBlacklistGlossary.length > 0 &&
                (_.isUndefined(prevQaBlacklistGlossary) || !Immutable.fromJS(prevQaBlacklistGlossary).equals(Immutable.fromJS(qaBlacklistGlossary)) ) ) {
                activeDecorators[DraftMatecatConstants.QA_BLACKLIST_DECORATOR] = true
                changedDecorator = true
                this.addQaBlacklistGlossaryDecorator();
            } else if ((prevQaBlacklistGlossary && prevQaBlacklistGlossary.length > 0 ) && ( !qaBlacklistGlossary ||  qaBlacklistGlossary.length === 0 ) ) {
                activeDecorators[DraftMatecatConstants.QA_BLACKLIST_DECORATOR] = false
                changedDecorator = true
                this.removeDecorator(DraftMatecatConstants.QA_BLACKLIST_DECORATOR);
            }

            //Lexiqa
            const { lexiqa  } = this.props.segment;
            const { lexiqa : prevLexiqa } = prevProps.segment;
            const currentLexiqaTarget = lexiqa && lexiqa.target && _.size(lexiqa.target)
            const prevLexiqaTarget = prevLexiqa && prevLexiqa.target && _.size(prevLexiqa.target)
            const lexiqaChanged = prevLexiqaTarget && currentLexiqaTarget && !Immutable.fromJS(prevLexiqa.target).equals(Immutable.fromJS(lexiqa.target))

            if(currentLexiqaTarget && (!prevLexiqaTarget || lexiqaChanged || !prevActiveDecorators[DraftMatecatConstants.LEXIQA_DECORATOR])){
                activeDecorators[DraftMatecatConstants.LEXIQA_DECORATOR] = true
                changedDecorator = true
                this.addLexiqaDecorator();
            } else if (prevLexiqaTarget && !currentLexiqaTarget) {
                activeDecorators[DraftMatecatConstants.LEXIQA_DECORATOR] = false
                changedDecorator = true
                this.removeDecorator(DraftMatecatConstants.LEXIQA_DECORATOR);
            }
            //Search
            if ( prevProps.segment.inSearch) {
                activeDecorators[DraftMatecatConstants.SEARCH_DECORATOR] = false
                changedDecorator = true
                this.removeDecorator(DraftMatecatConstants.SEARCH_DECORATOR);
            }
        }else{
            //Search
            if (this.props.segment.searchParams.target && (
                (!prevProps.segment.inSearch) ||  //Before was not active
                (prevProps.segment.inSearch && !Immutable.fromJS(prevProps.segment.searchParams).equals(Immutable.fromJS(this.props.segment.searchParams))) ||//Before was active but some params change
                (prevProps.segment.inSearch && prevProps.segment.currentInSearch !== this.props.segment.currentInSearch ) ||   //Before was the current
                (prevProps.segment.inSearch && prevProps.segment.currentInSearchIndex !== this.props.segment.currentInSearchIndex ) ) )   //There are more occurrences and the current change
            {
                // Cleanup all decorators
                this.removeDecorator();
                activeDecorators[DraftMatecatConstants.LEXIQA_DECORATOR]= false,
                activeDecorators[DraftMatecatConstants.QA_BLACKLIST_DECORATOR]= false,
                this.addSearchDecorator();
                activeDecorators[DraftMatecatConstants.SEARCH_DECORATOR] = true
                changedDecorator = true            }
        }

        if(changedDecorator){
            const decorator = new CompositeDecorator( this.decoratorsStructure );
            this.setState( {
                editorState: EditorState.set( editorState, {decorator} ),
                activeDecorators
            });
        }

    };

    componentDidMount() {
        SegmentStore.addListener(SegmentConstants.REPLACE_TRANSLATION, this.setNewTranslation);
        SegmentStore.addListener(EditAreaConstants.REPLACE_SEARCH_RESULTS, this.replaceCurrentSearch);
        SegmentStore.addListener(EditAreaConstants.COPY_GLOSSARY_IN_EDIT_AREA, this.copyGlossaryToEditArea);
        setTimeout(()=>{
            this.updateTranslationInStore();
            if(this.props.segment.opened){
                this.focusEditor();
            }
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
            const newEditorState = EditorState.moveFocusToEnd(this.state.editorState);
            this.setState({editorState: newEditorState})
        }else if(prevProps.segment.opened && !this.props.segment.opened){
            const newEditorState = EditorState.moveSelectionToEnd(this.state.editorState);
            this.setState({editorState: newEditorState})
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
            onBlurEvent,
            onFocus
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
                    onFocus={onFocus}
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
                spellCheck={true}
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

    typeTextInEditor = (textToInsert) => {
        const {editorState} = this.state;
        editorSync.onComposition = true;
        let newEditorState = this.disableDecorator(editorState, DraftMatecatConstants.LEXIQA_DECORATOR);
        newEditorState = DraftMatecatUtils.insertText(newEditorState, textToInsert);
        this.setState(prevState => ({
            activeDecorators: {
                ...prevState.activeDecorators,
                [DraftMatecatConstants.LEXIQA_DECORATOR]: false
            },
            editorState: newEditorState,
            triggerText: textToInsert
        }), () => {
            this.onCompositionStopDebounced()
        })
    }

    myKeyBindingFn = (e) => {
        const {displayPopover} = this.state;
        if((e.key === 't' || e.key === '™') && (isOptionKeyCommand(e) || e.altKey) && !e.shiftKey) {
            this.setState({triggerText: null});
            return 'toggle-tag-menu';
        }else if(e.key === '<' && !hasCommandModifier(e)) {
            this.typeTextInEditor('<')
            return 'toggle-tag-menu';
        }else if(e.key === 'ArrowUp' && !hasCommandModifier(e)){
            if(displayPopover) return 'up-arrow-press';
        }else if(e.key === 'ArrowDown' && !hasCommandModifier(e)){
            if(displayPopover) return 'down-arrow-press';
        }else if(e.key === 'Enter'){
            if( (e.altKey && e.ctrlKey) || (e.ctrlKey && isOptionKeyCommand(e) && e.shiftKey)){
                return 'add-issue'
            } else if(displayPopover && !hasCommandModifier(e) ) {
                return 'enter-press';
            }
        }else if(e.key === 'Escape'){
            return 'close-tag-menu';
        }else if(e.key === 'Tab'){
            return e.shiftKey ? null : 'insert-tab-tag';
        }else if( (e.key === ' ' || e.key === 'Spacebar' || e.key === ' ') &&
            isCtrlKeyCommand(e) &&
            e.shiftKey){
            return 'insert-nbsp-tag'; // Windows
        }else if( (e.key === ' ' || e.key === 'Spacebar' || e.key === ' ') &&
            !e.shiftKey &&
            e.altKey &&
            isOptionKeyCommand(e)){
            return 'insert-nbsp-tag'; // MacOS
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
            case 'add-issue':
                return 'handled';
            default:
                return 'not-handled';
        }
    };

    insertTagAtSelection = (tagName) => {
        const {editorState} = this.state;
        const customTag = DraftMatecatUtils.structFromName(tagName);
        // If tag creation has failed, return
        if(!customTag) return;
        // Start composition mode and remove lexiqa
        editorSync.onComposition = true;
        let newEditorState = this.disableDecorator(editorState, DraftMatecatConstants.LEXIQA_DECORATOR);
        newEditorState = DraftMatecatUtils.insertEntityAtSelection(newEditorState, customTag);
        this.setState(prevState => ({
            activeDecorators: {
                ...prevState.activeDecorators,
                [DraftMatecatConstants.LEXIQA_DECORATOR]: false
            },
            editorState: newEditorState
        }), () => {
            // Reactivate decorators
            this.updateTranslationDebounced();
            // Stop composition mode
            this.onCompositionStopDebounced();
        })
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
        const {toggleFormatMenu, setClickedTagId} = this.props;
        editorSync.editorFocused = false;
        // Hide Edit Toolbar
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

    onFocus = () => {
        editorSync.editorFocused = true;
    };

    updateTagsInEditor = () => {
        const {editorState, tagRange} = this.state;
        let newEditorState = editorState;
        let newTagRange = tagRange;
        // Cerco i tag attualmente presenti nell'editor
        // Todo: Se ci sono altre entità oltre i tag nell'editor, aggiungere l'entityName alla chiamata
        const entities = DraftMatecatUtils.getEntities(editorState);
        if(tagRange.length !== entities.length){
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

    removeDecorator = (decoratorName) => {
        if(!decoratorName){
            _.remove(this.decoratorsStructure, (decorator) => decorator.name !== DraftMatecatConstants.TAGS_DECORATOR);
        }else{
            _.remove(this.decoratorsStructure, (decorator) => decorator.name === decoratorName);
        }
    }

    // has to be followed by a setState for editorState
    disableDecorator = (editorState, decoratorName) => {
        _.remove(this.decoratorsStructure, (decorator) => decorator.name === decoratorName);
        //const decorator = new CompoundDecorator(this.decoratorsStructure);
        const decorator = new CompositeDecorator(this.decoratorsStructure);
        return EditorState.set( editorState, {decorator} )
    }

    onChange = (editorState) =>  {
        const {setClickedTagId} = this.props;
        const {displayPopover, editorState: prevEditorState} = this.state;
        const {closePopover, updateTagsInEditorDebounced} = this;
        const contentChanged = editorState.getCurrentContent().getPlainText() !==
            prevEditorState.getCurrentContent().getPlainText();
        // if not on an entity, remove any previous selection highlight
        const entityKey = DraftMatecatUtils.selectionIsEntity(editorState)
        // select no tag
        if(!entityKey) setClickedTagId();
        // if opened, close TagsMenu
        if(displayPopover) closePopover();
        if(contentChanged){
            // Stop checking decorators while typing...
            editorSync.onComposition = true;
            // ...remove unwanted decorators like lexiqa...
            editorState = this.disableDecorator(editorState, DraftMatecatConstants.LEXIQA_DECORATOR);
            editorState = this.forceSelectionFocus(editorState);
            this.setState(prevState => ({
                activeDecorators: {
                    ...prevState.activeDecorators,
                    [DraftMatecatConstants.LEXIQA_DECORATOR]: false
                },
                editorState: editorState
            }), () => {
                // Reactivate decorators
                this.updateTranslationDebounced();
            })
        }else{
            this.setState({editorState: editorState});
            this.onCompositionStopDebounced()
        }
    };

    // fix cursor jump at the beginning
    forceSelectionFocus = (editorState) => {
        const currentSelection = editorState.getSelection();
        if (!currentSelection.getHasFocus()) {
            const selection = currentSelection.set('hasFocus', true);
            editorState = EditorState.acceptSelection(editorState, selection);
        }
        return editorState;
    }

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
        // Start typing
        editorSync.onComposition = true;
        // Remove lexiqa while typing
        let newEditorState = this.disableDecorator(editorState, DraftMatecatConstants.LEXIQA_DECORATOR)
        const editorStateWithSuggestedTag = insertTag(selectedTag, newEditorState, triggerText);
        this.setState(prevState => ({
            activeDecorators: {
                ...prevState.activeDecorators,
                [DraftMatecatConstants.LEXIQA_DECORATOR]: false
            },
            editorState: editorStateWithSuggestedTag,
            displayPopover: false,
            clickedTag: selectedTag,
            clickedOnTag: true,
            triggerText: null
        }), () => {
            // Reactivate decorators
            this.updateTranslationDebounced();
            // Stop typing
            this.onCompositionStopDebounced();
        })
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
        // Start typing...
        editorSync.onComposition = true;
        // Disable lexiqa while typing
        let newEditorState = this.disableDecorator(editorState, DraftMatecatConstants.LEXIQA_DECORATOR)
        let editorStateWithSuggestedTag = insertTag(suggestionTag, newEditorState, triggerText);
        this.setState(prevState => ({
            activeDecorators: {
                ...prevState.activeDecorators,
                [DraftMatecatConstants.LEXIQA_DECORATOR]: false
            },
            editorState: editorStateWithSuggestedTag,
            editorFocused: true,
            clickedOnTag: true,
            clickedTag: suggestionTag,
            displayPopover: false,
            triggerText: null
        }), () => {
            // Reactivate decorators
            this.updateTranslationDebounced();
            // Stop typing
            this.onCompositionStopDebounced();
        })
    };

    // Methods for TagMenu ---- END

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
        // set selection to drop point and check dropping zone
        editorState = EditorState.forceSelection(editorState, selection);
        // Check: Cannot drop anything on entities
        if(DraftMatecatUtils.selectionIsEntity(editorState)){
            return 'handled';
        }

        if(text && !editorSync.draggingFromEditArea) {
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

    onEntityClick = (start, end, id, text) => {
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
            setClickedTagId(id, text);
        }catch (e) {
            console.log('Invalid selection')
        }
    };

    getClickedTagInfo = () => {
        const {clickedTagId, tagClickedInSource, clickedTagText} = this.props;
        return {clickedTagId, tagClickedInSource, clickedTagText};
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
        const {segment: { sid, warnings, tagMismatch, opened, missingTagsInTarget}} = this.props;
        const {tagRange, editorState} = this.state;
        return{
            sid,
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
        // Todo: if selectionIsEntity return
        if(editorState.getSelection().isCollapsed()) {
            return;
        }
        let selectedText = DraftMatecatUtils.getSelectedText(editorState);
        selectedText = DraftMatecatUtils.formatText(selectedText, format);
        const newEditorState = insertText(editorState, selectedText);
        this.setState({
            editorState: newEditorState
        },() => {
            this.updateTranslationDebounced();
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

