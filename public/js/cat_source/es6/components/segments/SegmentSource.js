/**
 * React Component .

 */
import React  from 'react';
import Immutable  from 'immutable';
import SegmentStore  from '../../stores/SegmentStore';
import SegmentActions  from '../../actions/SegmentActions';
import TextUtils  from '../../utils/textUtils';
import Shortcuts  from '../../utils/shortcuts';
import {Editor, EditorState, Modifier} from "draft-js";
import TagEntity from "./TagEntity/TagEntity.component";
import SegmentUtils from "../../utils/segmentUtils";
import DraftMatecatUtils from "./utils/DraftMatecatUtils";
import * as DraftMatecatConstants from "./utils/DraftMatecatUtils/editorConstants";
import SegmentConstants from "../../constants/SegmentConstants";
import CompoundDecorator from "./utils/CompoundDecorator"
import LexiqaUtils from "../../utils/lxq.main";
import updateLexiqaWarnings from "./utils/DraftMatecatUtils/updateLexiqaWarnings";
import getFragmentFromSelection from "./utils/DraftMatecatUtils/DraftSource/src/component/handlers/edit/getFragmentFromSelection";
import TagUtils from "../../utils/tagUtils";
import {getSplitPointTag} from "./utils/DraftMatecatUtils/tagModel";


class SegmentSource extends React.Component {

    constructor(props) {
        super(props);
        const {onEntityClick, getUpdatedSegmentInfo, getClickedTagInfo} = this;
        this.originalSource = this.props.segment.segment;
        this.afterRenderActions = this.afterRenderActions.bind(this);
        this.openConcordance = this.openConcordance.bind(this);
        this.decoratorsStructure = [
            {
                name: 'tags',
                strategy: getEntityStrategy('IMMUTABLE'),
                component: TagEntity,
                props: {
                    onClick: onEntityClick,
                    getUpdatedSegmentInfo: getUpdatedSegmentInfo,
                    getClickedTagInfo: getClickedTagInfo,
                    isTarget: false,
                    getSearchParams: this.getSearchParams,
                    isRTL: config.isSourceRTL
                }
            }];
        const decorator = new CompoundDecorator(this.decoratorsStructure);
        // const decorator = new CompositeDecorator(this.decoratorsStructure);
        // Initialise EditorState
        const plainEditorState = EditorState.createEmpty(decorator);
        // Escape html
        const translation =  DraftMatecatUtils.unescapeHTMLLeaveTags(this.props.segment.segment);
        // If GuessTag enabled, clean string from tag
        const cleanSource = SegmentUtils.checkCurrentSegmentTPEnabled(this.props.segment) ?
            DraftMatecatUtils.cleanSegmentString(translation) : translation;
        // New EditorState with translation
        const contentEncoded = DraftMatecatUtils.encodeContent(plainEditorState, cleanSource);
        const {editorState, tagRange} =  contentEncoded;
        this.state = {
            source: cleanSource,
            editorState: editorState,
            editAreaClasses : ['targetarea'],
            tagRange: tagRange,
            unlockedForCopy: false,
            editorStateBeforeSplit: editorState,
        };
        this.splitPoint = this.props.segment.split_group ? this.props.segment.split_group.length -1:  0;
        this.onChange = (editorState) => {
            const entityKey = DraftMatecatUtils.selectionIsEntity(editorState)
            if(!entityKey) {
                this.props.setClickedTagId();
                // Accept updated selection only
                const newEditorSelection = EditorState.acceptSelection(this.state.editorState, editorState.getSelection())
                this.setState({
                    editorState: newEditorSelection
                })
            }
        }
    }

    getSearchParams = () => {
        const {inSearch,
            currentInSearch,
            searchParams,
            occurrencesInSearch,
            currentInSearchIndex
        } = this.props.segment;
        if ( inSearch && searchParams.source) {
            return {
                active: inSearch,
                currentActive: currentInSearch,
                textToReplace: searchParams.source,
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

    // Restore tagged source in draftJS after GuessTag
    setTaggedSource = (sid) => {
        if ( sid === this.props.segment.sid) {
            // Escape html
            const translation =  DraftMatecatUtils.unescapeHTMLLeaveTags(this.props.segment.segment);
            // TODO: get taggedSource from store
            const contentEncoded = DraftMatecatUtils.encodeContent( this.state.editorState, translation );
            const {editorState, tagRange} =  contentEncoded;
            this.setState( {
                editorState: editorState,
                tagRange: tagRange
            } );
            setTimeout(()=>this.updateSourceInStore());        }
    };

    afterRenderActions(prevProps) {
        let self = this;
        if ( this.splitContainer ) {
            $(this.splitContainer).on('mousedown', '.splitArea .splitpoint', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).remove();
                self.updateSplitNumber();
            })
        }
    }

    // TODO: replaced by updateSplitNumberNew, remove
    updateSplitNumber() {
        if (this.props.segment.splitted) return;
        let numSplits = $(this.splitContainer).find('.splitpoint').length + 1;
        let splitnum = $(this.splitContainer).find('.splitNum');
        $(splitnum).find('.num').text(numSplits);
        this.splitNum = numSplits;
        if (numSplits > 1) {
            $(splitnum).find('.plural').text('s');
            $(this.splitContainer).find('.btn-ok').removeClass('disabled');
        } else {
            $(splitnum).find('.plural').text('');
            splitnum.hide();
            $(this.splitContainer).find('.btn-ok').addClass('disabled');
        }
        $(this.splitContainer).find('.splitArea').blur();
    }

    // TODO: replaced by addSplitTag, remove
    addSplitPoint(event) {
        if(window.getSelection().type === 'Range') return false;
        TextUtils.pasteHtmlAtCaret('<span class="splitpoint"><span class="splitpoint-delete"/></span>');

        this.updateSplitNumber();
    }

    // TODO: replaced by splitSegmentNew, remove
    splitSegment(split) {
        let text = $(this.splitContainer).find('.splitArea').html();
        text = text.replace(/<span class=\"splitpoint\"><span class=\"splitpoint-delete\"><\/span><\/span>/gi, '##$_SPLIT$##');
        // Rimuovi lo span del vecchio split ( verrà ricalcolato con ##$_SPLIT_$## )
        text = text.replace(/<span class=\"currentSplittedSegment\">(.*?)<\/span>/gi, '$1');
        // Remove split placeholer if inside tag
        text = text.replace(/<span contenteditable="false" class=\"(.*?)tag(.*?)\">((?:(?!<\/span>).)*?)##\$_SPLIT\$##((?:(?!<\/span>).)*?)<\/span>/gi,
            '<span contenteditable="false" class="$1tag$2">$3$4</span>');
        text = TagUtils.prepareTextToSend(text);
        // let splitArray = text.split('##_SPLIT_##');
        SegmentActions.splitSegment(this.props.segment.original_sid, text, split);
    }

    // markLexiqa(source) {
    //     let searchEnabled = this.props.segment.inSearch;
    //     if (LXQ.enabled() && this.props.segment.lexiqa && this.props.segment.lexiqa.source && !searchEnabled) {
    //         source = LXQ.highLightText(source, this.props.segment.lexiqa.source, true, true, true );
    //     }
    //     return source;
    // }

    openConcordance(e) {
        e.preventDefault();
        var selection = window.getSelection();
        if (selection.type === 'Range') { // something is selected
            var str = selection.toString().trim();
            if (str.length) { // the trimmed string is not empty
                SegmentActions.openConcordance(this.props.segment.sid, str, false);
            }
        }
    }

    addSearchDecorator = () => {
        let { editorState, tagRange } = this.state;
        let { searchParams, occurrencesInSearch, currentInSearchIndex } = this.props.segment;
        const textToSearch = searchParams.source ? searchParams.source : "";
        const { editorState: newEditorState, decorators } = DraftMatecatUtils.activateSearch( editorState, this.decoratorsStructure, textToSearch,
            searchParams, occurrencesInSearch.occurrences, currentInSearchIndex, tagRange );
        this.decoratorsStructure = decorators;
        this.setState( {
            editorState: newEditorState,
        } );
    };

    addGlossaryDecorator = () => {
        let { editorState } = this.state;
        let { glossary, segment, sid } = this.props.segment;
        const { editorState : newEditorState, decorators } = DraftMatecatUtils.activateGlossary( editorState, this.decoratorsStructure, glossary, segment, sid, SegmentActions.activateTab );
        this.decoratorsStructure = decorators;
        this.setState( {
            editorState: newEditorState,
        } );
    };

    addQaCheckGlossaryDecorator = () => {
        let { editorState } = this.state;
        let { qaCheckGlossary, segment, sid } = this.props.segment;
        const { editorState : newEditorState, decorators } = DraftMatecatUtils.activateQaCheckGlossary( editorState, this.decoratorsStructure, qaCheckGlossary, segment, sid, SegmentActions.activateTab  );
        this.decoratorsStructure = decorators;
        this.setState( {
            editorState: newEditorState,
        } );
    };

    addLexiqaDecorator = () => {
        let { editorState } = this.state;
        let { lexiqa, sid, lxqDecodedSource } = this.props.segment;
        let ranges = LexiqaUtils.getRanges(_.cloneDeep(lexiqa.source), lxqDecodedSource, true);
        const updatedLexiqaWarnings = updateLexiqaWarnings(editorState, ranges);
        if ( ranges.length > 0 ) {
            const { editorState : newEditorState, decorators } = DraftMatecatUtils.activateLexiqa( editorState,
                this.decoratorsStructure,
                updatedLexiqaWarnings,
                sid,
                true,
                this.getUpdatedSegmentInfo);
            this.decoratorsStructure = decorators;
            this.setState( {
                editorState: newEditorState,
            } );
        } else {
            this.removeDecorator(DraftMatecatConstants.LEXIQA_DECORATOR);
        }
    };

    updateSourceInStore = () => {
        if ( this.state.source !== '' ) {
            const {editorState, tagRange} = this.state;
            let contentState = editorState.getCurrentContent();
            let plainText = contentState.getPlainText();
            const lxqDecodedSource = DraftMatecatUtils.prepareTextForLexiqa(editorState);
            SegmentActions.updateSource(this.props.segment.sid, DraftMatecatUtils.decodeSegment(this.state.editorState), plainText, tagRange, lxqDecodedSource);
        }
    };

    checkDecorators = (prevProps) => {
        //Search
        const { inSearch, searchParams, currentInSearch, currentInSearchIndex } = this.props.segment;
        if (inSearch && searchParams.source && (
            (!prevProps.segment.inSearch) ||  //Before was not active
            (prevProps.segment.inSearch && !Immutable.fromJS(prevProps.segment.searchParams).equals(Immutable.fromJS(searchParams))) ||//Before was active but some params change
            (prevProps.segment.inSearch && prevProps.segment.currentInSearch !== currentInSearch ) ||   //Before was the current
            (prevProps.segment.inSearch && prevProps.segment.currentInSearchIndex !== currentInSearchIndex ) ) )   //There are more occurrences and the current change
        {
            this.addSearchDecorator();
        } else if ( prevProps.segment.inSearch && !this.props.segment.inSearch ) {
            this.removeDecorator(DraftMatecatConstants.SEARCH_DECORATOR);
        }

        //Glossary
        const { glossary } = this.props.segment;
        const { glossary : prevGlossary } = prevProps.segment;
        if ( glossary && _.size(glossary) > 0 && (_.isUndefined(prevGlossary) || !Immutable.fromJS(prevGlossary).equals(Immutable.fromJS(glossary)) ) ) {
            this.addGlossaryDecorator();
        } else if ( _.size(prevGlossary) > 0 && ( !glossary || _.size(glossary) === 0 ) ) {
            this.removeDecorator(DraftMatecatConstants.GLOSSARY_DECORATOR)
        }

        //Qa Check Glossary
        const { qaCheckGlossary } = this.props.segment;
        const { qaCheckGlossary : prevQaCheckGlossary } = prevProps.segment;
        if ( qaCheckGlossary && qaCheckGlossary.length > 0 && (_.isUndefined(prevQaCheckGlossary) || !Immutable.fromJS(prevQaCheckGlossary).equals(Immutable.fromJS(qaCheckGlossary)) ) ) {
            this.addQaCheckGlossaryDecorator();
        } else if ( (prevQaCheckGlossary && prevQaCheckGlossary.length > 0 ) && ( !qaCheckGlossary ||  qaCheckGlossary.length === 0 ) ) {
            this.removeDecorator(DraftMatecatConstants.QA_GLOSSARY_DECORATOR);
        }

        //Lexiqa
        const { lexiqa  } = this.props.segment;
        const { lexiqa : prevLexiqa } = prevProps.segment;
        if ( lexiqa && _.size(lexiqa) > 0 && lexiqa.source && prevLexiqa && _.size(prevLexiqa) > 0 && prevLexiqa.source &&
            (_.isUndefined(prevLexiqa) || !Immutable.fromJS(prevLexiqa.source).equals(Immutable.fromJS(lexiqa.source)) ) ) {
            this.addLexiqaDecorator();
        } else if ((prevLexiqa && prevLexiqa.length > 0 ) && ( !lexiqa ||  _.size(lexiqa) === 0 || !lexiqa.source ) ) {
            this.removeDecorator(DraftMatecatConstants.LEXIQA_DECORATOR);
        }
    };

    componentDidMount() {

        SegmentStore.addListener(SegmentConstants.CLOSE_SPLIT_SEGMENT, this.endSplitMode );
        SegmentStore.addListener(SegmentConstants.SET_SEGMENT_TAGGED, this.setTaggedSource);

        this.$source = $(this.source);
        if ( this.props.segment.inSearch ) {
            setTimeout(this.addSearchDecorator());
        }
        if ( this.props.segment.qaCheckGlossary ) {
            setTimeout(this.addQaCheckGlossaryDecorator());
        }
        const {lexiqa} = this.props.segment;
        if ( lexiqa && _.size(lexiqa) > 0 && lexiqa.source ) {
            setTimeout(this.addLexiqaDecorator());
        }
        /*this.afterRenderActions();*/
        this.$source.on('keydown', null, Shortcuts.cattol.events.searchInConcordance.keystrokes[Shortcuts.shortCutsKeyType], this.openConcordance);

        setTimeout(()=>this.updateSourceInStore());
    }

    componentWillUnmount() {
        SegmentStore.removeListener(SegmentConstants.CLOSE_SPLIT_SEGMENT, this.endSplitMode);
        this.$source.on('keydown', this.openConcordance);
    }

    componentDidUpdate(prevProps) {
        /*this.afterRenderActions(prevProps);*/
        this.checkDecorators(prevProps);
        this.forceSelectionToUnlockCopy();
        // Check if splitMode
        if ( !prevProps.segment.openSplit && this.props.segment.openSplit ) {
            // if segment splitted, rebuild its original content
            if ( this.props.segment.splitted ) {
                let segmentsSplit = this.props.segment.split_group;
                let sourceHtml = '';
                // join splitted segment content
                segmentsSplit.forEach((sid, index)=>{
                    let segment = SegmentStore.getSegmentByIdToJS(sid);
                    if ( sid === this.props.segment.sid) {
                        // if splitted wrap inside highlight span
                        //sourceHtml += `##$_SPLITSTART$##${segment.segment}##$_SPLITEND$##`
                        sourceHtml += segment.segment
                    } else {
                        // if not splitted, add only content
                        sourceHtml += segment.segment
                    }
                    // add splitPoint after every segment content except for last one
                    if(index !== segmentsSplit.length - 1){
                        sourceHtml += '##$_SPLIT$##'
                    }
                });
                // create a new editorState
                const decorator = new CompoundDecorator(this.decoratorsStructure);
                const plainEditorState = EditorState.createEmpty(decorator);
                // add the content
                const contentEncoded = DraftMatecatUtils.encodeContent(plainEditorState, sourceHtml);
                const {editorState: editorStateSplitGroup} =  contentEncoded;
                // update current editorState
                this.setState({editorState: editorStateSplitGroup})
            }
        }
    }

    allowHTML(string) {
        return { __html: string };
    }

    render() {
        const {segment} = this.props;
        const {editorState} = this.state;
        const {onChange, copyFragment, onBlurEvent, dragFragment, onDragEndEvent, addSplitTag, splitSegmentNew} = this;
        // Set correct handlers
        const handlers = !segment.openSplit ?
            {
                onCopy: copyFragment,
                onBlur: onBlurEvent,
                onDragStart: dragFragment,
                onDragEnd: onDragEndEvent
            } :
            {
                onClick: () => addSplitTag(),
                onBlur: onBlurEvent
            };

        // Standard editor
        const editorHtml = <div ref={(source)=>this.source=source}
                                className={"source item"}
                                tabIndex={0}
                                id={"segment-" + segment.sid +"-source"}
                                data-original={this.originalSource}
                                {...handlers}>
            <Editor
                editorState={editorState}
                onChange={onChange}
                ref={(el) => this.editor = el}
                readOnly={false}
            />
        </div>;

        // Wrap editor in splitContainer
        return segment.openSplit ?
            <div className="splitContainer" ref={(splitContainer)=>this.splitContainer=splitContainer}>
                {editorHtml}
                <div className="splitBar">
                    <div className="buttons">
                        <a className="ui button cancel-button cancel btn-cancel" onClick={ ()=>SegmentActions.closeSplitSegment() }>Cancel</a >
                        <a className = {`ui primary button done btn-ok pull-right ${!!this.splitPoint ? '' : 'disabled'}`} onClick={() => splitSegmentNew()}> Confirm </a>
                    </div>
                    {!!this.splitPoint && <div className="splitNum pull-right">
                        Split in <span className="num">
                        {this.splitPoint}
                        </span> segment<span className="plural"/>
                    </div>}
                </div>
            </div >
            :
            editorHtml;
    }

    disableDecorator = (editorState, decoratorName) => {
        _.remove(this.decoratorsStructure, (decorator) => decorator.name === decoratorName);
        const decorator = new CompoundDecorator(this.decoratorsStructure);
        return EditorState.set( editorState, {decorator} )
    }

    removeDecorator = (decoratorName) => {
        this.setState( {
            editorState: this.disableDecorator(this.state.editorState, decoratorName)
        });
    }

    insertTagAtSelection = (tagName) => {
        const {editorState} = this.state;
        const customTag = DraftMatecatUtils.structFromName(tagName);
        // If tag creation has failed, return
        if(!customTag) return;
        // remove lexiqa to avoid insertion error
        let newEditorState = this.disableDecorator(editorState, DraftMatecatConstants.LEXIQA_DECORATOR);
        newEditorState = this.disableDecorator(newEditorState, DraftMatecatConstants.SPLIT_DECORATOR);
        newEditorState = DraftMatecatUtils.insertEntityAtSelection(newEditorState, customTag);
        this.setState({editorState: newEditorState});
    }

    addSplitTag = () => {
        this.insertTagAtSelection('splitPoint');
        this.updateSplitNumberNew(1);
    }

    updateSplitNumberNew = (step) => {
        if (this.props.segment.splitted) return;
        this.splitPoint += step;
    }

    splitSegmentNew = (split) => {
        const {editorState} = this.state;
        let text = DraftMatecatUtils.decodeSegment(editorState);
        // Prepare text for backend
        text = text.replace(/&lt;/g, '<').replace(/&gt;/g, '>');
        SegmentActions.splitSegment(this.props.segment.original_sid, text, split);
    }

    endSplitMode = () => {
        const {editorStateBeforeSplit} = this.state;
        const {segment} = this.props;
        this.splitPoint = segment.split_group ? segment.split_group.length -1:  0;
        // TODO: why so much calls endSplitMode??
        if(segment.openSplit){
            this.setState({
                editorState: editorStateBeforeSplit
            });
        }
    }

    onBlurEvent = () => {
        const {setClickedTagId, clickedTagId} = this.props;
        if (clickedTagId) setClickedTagId();
    };

    onEntityClick = (start, end, id, text) => {
        const {editorState} = this.state;
        const {setClickedTagId, segment} = this.props;
        const {isSplitPoint} = this;
        try{
            // Get latest selection
            let newSelection = this.editor._latestEditorState.getSelection();
            // force selection on entity
            newSelection = newSelection.merge({
                anchorOffset: start,
                focusOffset: end,
            });
            let newEditorState = EditorState.forceSelection(
                editorState,
                newSelection,
            );
            const contentState = newEditorState.getCurrentContent();
            // remove split tag
            if(segment.openSplit && isSplitPoint(contentState, newSelection)){
                const contentStateWithoutSplitPoint = Modifier.removeRange(
                    contentState,
                    newSelection,
                    'forward'
                );
                // set selection before entity
                newSelection = newSelection.merge({
                    focusOffset: start,
                });
                newEditorState = EditorState.forceSelection(
                    newEditorState,
                    newSelection,
                );
                this.updateSplitNumberNew(-1);
                newEditorState = EditorState.set(newEditorState, {currentContent: contentStateWithoutSplitPoint});
            }
            // update editorState
            setClickedTagId(id, text, true);
            this.setState({editorState: newEditorState});
        }catch (e) {
            console.log(e)
        }
    };

    isSplitPoint = (contentState, selection) => {
        const anchorKey = selection.getAnchorKey();
        const anchorBlock = contentState.getBlockForKey(anchorKey);
        const anchorOffset =  selection.getAnchorOffset();
        const anchorEntityKey = anchorBlock.getEntityAt(anchorOffset);
        const entityInstance = contentState.getEntity(anchorEntityKey);
        const entityData = entityInstance.getData();
        const tagName = entityData ? entityData.name : '';
        return getSplitPointTag().includes(tagName);
    }

    getClickedTagInfo = () => {
        const {clickedTagId, tagClickedInSource, clickedTagText} = this.props;
        return {clickedTagId, tagClickedInSource, clickedTagText};
    };

    // Needed to "unlock" segment for a successful copy/paste or dragNdrop
    forceSelectionToUnlockCopy = () => {
        if(this.props.segment.opened && !this.state.unlockedForCopy){
            const {editorState} = this.state;
            const selectionState = editorState.getSelection();
            let newSelection = selectionState.merge({
                anchorOffset: 0,
                focusOffset: 0,
            });
            const newEditorState = EditorState.forceSelection(
                editorState,
                newSelection
            );
            this.setState({
                editorState: newEditorState,
                unlockedForCopy: true
            });
        }
    }

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
            SegmentActions.copyFragmentToClipboard(fragment, plainText);
        }
    };

    dragFragment = (e) => {
        const {editorState} = this.state;
        let fragment = getFragmentFromSelection(editorState);
        if(fragment){
            const entitiesMap = DraftMatecatUtils.getEntitiesInFragment(fragment, editorState)
            fragment = JSON.stringify({
                orderedMap: fragment,
                entitiesMap: entitiesMap
            });
            e.dataTransfer.clearData();
            e.dataTransfer.setData("text/plain", fragment);
            e.dataTransfer.setData("text/html", fragment);
        }

    };

    onDragEndEvent = (e) => {
        e.dataTransfer.clearData();
    }

    getUpdatedSegmentInfo= () => {
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
    }
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

export default SegmentSource;
