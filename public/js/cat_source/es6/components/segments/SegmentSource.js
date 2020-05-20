/**
 * React Component .

 */
import React  from 'react';
import Immutable  from 'immutable';
import SegmentStore  from '../../stores/SegmentStore';
import SegmentActions  from '../../actions/SegmentActions';
import TextUtils  from '../../utils/textUtils';
import Shortcuts  from '../../utils/shortcuts';
import {
    activateSearch,
    activateGlossary,
    activateQaCheckGlossary,
    activateLexiqa
} from "./utils/DraftMatecatUtils/ContentEncoder";
import {Editor, EditorState} from "draft-js";
import TagEntity from "./TagEntity/TagEntity.component";
import SegmentUtils from "../../utils/segmentUtils";
import DraftMatecatUtils from "./utils/DraftMatecatUtils";
import SegmentConstants from "../../constants/SegmentConstants";
import CompoundDecorator from "./utils/CompoundDecorator"
import LexiqaUtils from "../../utils/lxq.main";

class SegmentSource extends React.Component {

    constructor(props) {
        super(props);
        const {onEntityClick, getUpdatedWarnings} = this;
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
                    getUpdatedWarnings: getUpdatedWarnings,
                    isTarget: false
                    // getSearchParams: this.getSearchParams
                }
            }
        ];
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
            tagRange: tagRange
        };
        this.onChange = () => {console.log('Source is not editable!')}
    }

    // getSearchParams = () => {
    //     const {inSearch,
    //         currentInSearch,
    //         searchParams,
    //         occurrencesInSearch,
    //         currentInSearchIndex
    //     } = this.props.segment;
    //     if ( inSearch && searchParams.source) {
    //         return {
    //             active: inSearch,
    //             currentActive: currentInSearch,
    //             textToReplace: searchParams.source,
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

    // Restore tagged source in draftJS after GuessTag
    setTaggedSource = (sid) => {
        if ( sid === this.props.segment.sid) {
            // TODO: get taggedSource from store
            const contentEncoded = DraftMatecatUtils.encodeContent( this.state.editorState, this.props.segment.segment );
            const {editorState, tagRange} =  contentEncoded;
            this.setState( {
                editorState: editorState,
            } );
        }
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

    addSplitPoint(event) {
        if(window.getSelection().type === 'Range') return false;
        TextUtils.pasteHtmlAtCaret('<span class="splitpoint"><span class="splitpoint-delete"/></span>');

        this.updateSplitNumber();
    }

    splitSegment(split) {
        let text = $(this.splitContainer).find('.splitArea').html();
        text = text.replace(/<span class=\"splitpoint\"><span class=\"splitpoint-delete\"><\/span><\/span>/, '##$_SPLIT$##');
        text = text.replace(/<span class=\"currentSplittedSegment\">(.*?)<\/span>/gi, '$1');
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
        let { editorState } = this.state;
        let { searchParams, occurrencesInSearch, currentInSearchIndex } = this.props.segment;
        const { editorState: newEditorState, decorators } = activateSearch( editorState, this.decoratorsStructure, searchParams.source,
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

    addGlossaryDecorator = () => {
        let { editorState } = this.state;
        let { glossary, segment, sid } = this.props.segment;
        const { editorState : newEditorState, decorators } = activateGlossary( editorState, this.decoratorsStructure, glossary, segment, sid );
        this.decoratorsStructure = decorators;
        this.setState( {
            editorState: newEditorState,
        } );
    };

    removeGlossaryDecorator = () => {
        _.remove(this.decoratorsStructure, (decorator) => decorator.name === 'glossary');
        // const newDecorator = new CompositeDecorator( this.decoratorsStructure );
        const decorator = new CompoundDecorator(this.decoratorsStructure);
        this.setState( {
            editorState: EditorState.set( this.state.editorState, {decorator: decorator} ),
        } );
    };

    addQaCheckGlossaryDecorator = () => {
        let { editorState } = this.state;
        let { qaCheckGlossary, segment, sid } = this.props.segment;
        const { editorState : newEditorState, decorators } = activateQaCheckGlossary( editorState, this.decoratorsStructure, qaCheckGlossary, segment, sid );
        this.decoratorsStructure = decorators;
        this.setState( {
            editorState: newEditorState,
        } );
    };

    removeQaCheckGlossaryDecorator = () => {
        _.remove(this.decoratorsStructure, (decorator) => decorator.name === 'qaCheckGlossary');
        // const newDecorator = new CompositeDecorator( this.decoratorsStructure );
        const decorator = new CompoundDecorator(this.decoratorsStructure);
        this.setState( {
            editorState: EditorState.set( this.state.editorState, {decorator: decorator} ),
        } );
    };

    addLexiqaDecorator = () => {
        let { editorState } = this.state;
        let { lexiqa, sid, decodedTranslation } = this.props.segment;
        let ranges = LexiqaUtils.getRanges(_.cloneDeep(lexiqa.source), decodedTranslation, true);
        if ( ranges.length > 0 ) {
            const { editorState : newEditorState, decorators } = activateLexiqa( editorState, this.decoratorsStructure, ranges, sid, true);
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

    updateSourceInStore = () => {
        if ( this.state.source !== '' ) {
            const {editorState, tagRange} = this.state;
            let contentState = editorState.getCurrentContent();
            let plainText = contentState.getPlainText();
            SegmentActions.updateSource(this.props.segment.sid, DraftMatecatUtils.decodeSegment(this.state.editorState), plainText, tagRange);
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
            this.removeSearchDecorator();
        }

        //Glossary
        const { glossary } = this.props.segment;
        const { glossary : prevGlossary } = prevProps.segment;
        if ( glossary && _.size(glossary) > 0 && (_.isUndefined(prevGlossary) || !Immutable.fromJS(prevGlossary).equals(Immutable.fromJS(glossary)) ) ) {
            this.addGlossaryDecorator();
        } else if ( _.size(prevGlossary) > 0 && ( !glossary || _.size(glossary) === 0 ) ) {
            this.removeGlossaryDecorator();
        }

        //Qa Check Glossary
        const { qaCheckGlossary } = this.props.segment;
        const { qaCheckGlossary : prevQaCheckGlossary } = prevProps.segment;
        if ( qaCheckGlossary && qaCheckGlossary.length > 0 && (_.isUndefined(prevQaCheckGlossary) || !Immutable.fromJS(prevQaCheckGlossary).equals(Immutable.fromJS(qaCheckGlossary)) ) ) {
            this.addQaCheckGlossaryDecorator();
        } else if ( (prevQaCheckGlossary && prevQaCheckGlossary.length > 0 ) && ( !qaCheckGlossary ||  qaCheckGlossary.length === 0 ) ) {
            this.removeQaCheckGlossaryDecorator();
        }

        //Lexiqa
        const { lexiqa  } = this.props.segment;
        const { lexiqa : prevLexiqa } = prevProps.segment;
        if ( lexiqa && _.size(lexiqa) > 0 && lexiqa.source &&
            (_.isUndefined(prevLexiqa) || !Immutable.fromJS(prevLexiqa).equals(Immutable.fromJS(lexiqa)) ) ) {
            this.addLexiqaDecorator();
        } else if ((prevLexiqa && prevLexiqa.length > 0 ) && ( !lexiqa ||  _.size(lexiqa) === 0 || !lexiqa.source ) ) {
            this.removeLexiqaDecorator()
        }
    };

    componentDidMount() {
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

        this.afterRenderActions();

        this.$source.on('keydown', null, Shortcuts.cattol.events.searchInConcordance.keystrokes[Shortcuts.shortCutsKeyType], this.openConcordance);
        SegmentStore.addListener(SegmentConstants.SET_SEGMENT_TAGGED, this.setTaggedSource);

        setTimeout(()=>this.updateSourceInStore());

        // Todo: find a nicer solution to "unlock" the editor for copy event
        setTimeout(()=> {
            const {editorState} = this.state;
            const selectionState = editorState.getSelection();
            let newSelection = selectionState.merge({
                anchorOffset: 0,
                focusOffset: 0,
            });
            const newEditorState = EditorState.forceSelection(
                editorState,
                newSelection,
            );
            this.setState({editorState: newEditorState});
            console.log('Selection --> ', newSelection)
        });
    }

    componentWillUnmount() {
        this.$source.on('keydown', this.openConcordance);
    }

    componentDidUpdate(prevProps) {
        this.afterRenderActions(prevProps);

        this.checkDecorators(prevProps);
    }

    allowHTML(string) {
        return { __html: string };
    }

    render() {

        const {editorState} = this.state;
        const {onChange, copyFragment} = this;

        let html = <div ref={(source)=>this.source=source}
                        className={"source item"}
                        tabIndex={0}
                        id={"segment-" + this.props.segment.sid +"-source"}
                        data-original={this.originalSource}
                        onCopy={copyFragment}
                    >
            <Editor
                editorState={editorState}
                onChange={onChange}
                ref={(el) => this.editor = el}
                readOnly={false}
            />
        </div>;
        if ( this.props.segment.openSplit ) {
            if ( this.props.segment.splitted ) {
                let segmentsSplit = this.props.segment.split_group;
                let sourceHtml = '';
                segmentsSplit.forEach((sid, index)=>{
                    let segment = SegmentStore.getSegmentByIdToJS(sid);
                    if ( sid === this.props.segment.sid) {
                        sourceHtml += '<span class="currentSplittedSegment">'+TagUtils.transformPlaceholdersAndTags(segment.segment)+'</span>';
                    } else {
                        sourceHtml+= TagUtils.transformPlaceholdersAndTags(segment.segment);
                    }
                    if(index !== segmentsSplit.length - 1)
                        sourceHtml += '<span class="splitpoint"><span class="splitpoint-delete"></span></span>';
                });
                html =  <div className="splitContainer" ref={(splitContainer)=>this.splitContainer=splitContainer}>
                    <div className="splitArea" contentEditable = "false"
                         onClick={(e)=>this.addSplitPoint(e)}
                         dangerouslySetInnerHTML={this.allowHTML(sourceHtml)}/>
                    <div className="splitBar">
                        <div className="buttons">
                            <a className="ui button cancel-button cancel btn-cancel" onClick={()=>SegmentActions.closeSplitSegment()}>Cancel</a >
                            <a className = {"ui primary button done btn-ok pull-right" } onClick={()=>this.splitSegment()}> Confirm </a>
                        </div>
                        <div className="splitNum pull-right"> Split in <span className="num">1 </span> segment<span className="plural"/>
                        </div>
                    </div>
                </div >;
            } else {
                html =  <div className="splitContainer" ref={(splitContainer)=>this.splitContainer=splitContainer}>
                    <div className="splitArea" contentEditable = "false"
                         onClick={(e)=>this.addSplitPoint(e)}
                         dangerouslySetInnerHTML={this.allowHTML(TagUtils.transformPlaceholdersAndTags(this.props.segment.segment))}/>
                    <div className="splitBar">
                        <div className="buttons">
                            <a className="ui button cancel-button cancel btn-cancel" onClick={()=>SegmentActions.closeSplitSegment()}>Cancel</a >
                            <a className = {"ui primary button done btn-ok pull-right disabled" } onClick={()=>this.splitSegment()}> Confirm </a>
                        </div>
                        <div className="splitNum pull-right"> Split in <span className="num">1 </span> segment<span className="plural"/>
                        </div>
                    </div>
                </div >;
            }
        }
        return html;

    }

    onEntityClick = (start, end) => {
        const {editorState} = this.state;
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

    getUpdatedWarnings= () => {
        const {segment: { warnings, tagMismatch, opened}} = this.props;
        const {tagRange} = this.state;
        return{
            warnings : warnings,
            tagMismatch: tagMismatch,
            tagRange: tagRange,
            segmentOpened: opened
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
