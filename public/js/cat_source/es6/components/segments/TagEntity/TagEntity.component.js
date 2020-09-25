import React, {Component} from 'react';
import TooltipInfo from "../TooltipInfo/TooltipInfo.component";
import {tagSignatures, getTooltipTag, getNoLexiqaTag} from "../utils/DraftMatecatUtils/tagModel";

class TagEntity extends Component {
    constructor(props) {
        super(props);

        const tagStyle = this.selectCorrectStyle();

        this.state = {
            selected: false,
            selectionStateInputs: {
                anchorOffset: null,
                focusOffset: null,
                anchorKey: '',
                focusKey: '',
            },
            tagWarningStyle: '',
            showTooltip: false,
            tagFocusedStyle: '',
            tagStyle
        };
    }

    emitSelectionParameters = (blockKey, selection, forceSelection) => {
    };

    tooltipToggle = (show = false) => {
        // this will trigger a rerender in the main Editor Component
        this.setState({
            showTooltip: show
        })
    };

    highlightTag = () => {
        const {start, end, children} = this.props;
        const {selection} = children.props.children[0];
    };

    markSearch = (text, searchParams) => {
        let {active, currentActive, textToReplace, params, occurrences, currentInSearchIndex} = searchParams;
        let currentOccurrence = _.find(occurrences, (occ) => occ.searchProgressiveIndex === currentInSearchIndex);
        let isCurrent = (currentOccurrence && currentOccurrence.matchPosition >= this.props.start && currentOccurrence.matchPosition < this.props.end);
        if ( active ) {
            let regex = SearchUtils.getSearchRegExp(textToReplace, params.ingnoreCase, params.exactMatch);
            var parts = text.split(regex);
            for (var i = 1; i < parts.length; i += 2) {
                if ( currentActive && isCurrent ) {
                    parts[i] = <span key={i} style={{backgroundColor: 'rgb(255 210 14)'}}>{parts[i]}</span>;
                } else {
                    parts[i] = <span key={i} style={{backgroundColor: 'rgb(255 255 0)'}}>{parts[i]}</span>;
                }
            }
            return parts;
        }
        return text;
    };



    componentDidMount() {
        this.warningCheck = setInterval(
            () => {
                //this.highlightOnWarnings()
                this.updateTagStyle()
            },
            500
        );
    }

    componentWillUnmount() {
        clearInterval(this.warningCheck);
    }



    render() {
        let searchParams = this.props.getSearchParams();
        const {selected, tagStyle, tagWarningStyle} = this.state;
        const {entityKey, offsetkey, blockKey, start, end, onClick, contentState, getUpdatedSegmentInfo,getClickedTagInfo, isTarget} = this.props;
        const {currentSelection} = getUpdatedSegmentInfo();
        const {tagClickedInSource, clickedTagId, clickedTagText} = getClickedTagInfo();
        const {anchorOffset, focusOffset, anchorKey, hasFocus} = currentSelection;
        const { children } = this.props.children.props;
        const {selection, forceSelection} = children[0];
        const {emitSelectionParameters, tooltipToggle} = this;

        //const entity = contentState.getEntity(entityKey);
        const {type: entityType, data: {id: entityId, placeholder: entityPlaceholder}} = contentState.getEntity(entityKey);

        // Apply style on clicked tag and draggable tag, placed here for performance
        const tagFocusedStyle = anchorOffset === start &&
        focusOffset === end &&
        anchorKey === blockKey &&
        (tagClickedInSource && !isTarget || !tagClickedInSource && isTarget) &&
        hasFocus ? 'tag-focused' : '';
        const tagClickedStyle = entityId &&
        clickedTagId &&
        clickedTagId === entityId &&
        clickedTagText &&
        clickedTagText === entityPlaceholder
            ? 'tag-clicked' : '';

        // show tooltip only on configured tag
        const showTooltip = this.state.showTooltip && getTooltipTag().includes(entityType);
        // show tooltip only if text too long
        const textSpanDisplayed = this.tagRef && this.tagRef.querySelector('span[data-text="true"]');
        const shouldTooltipOnHover = textSpanDisplayed && textSpanDisplayed.offsetWidth < textSpanDisplayed.scrollWidth;

        if ( searchParams.active )  {
            let text = this.markSearch(children[0].props.text, searchParams);
            return <div className={"tag-container"}
                        ref={(ref) => this.tagRef = ref}
                /*contentEditable="false"
                suppressContentEditableWarning={true}*/>
                {showTooltip && <TooltipInfo text={entityPlaceholder} isTag tagStyle={tagStyle}/>}
                <span data-offset-key={offsetkey}
                      className={`tag ${tagStyle} ${tagWarningStyle} ${tagClickedStyle} ${tagFocusedStyle}`}
                      unselectable="on"
                      suppressContentEditableWarning={true}
                      onMouseEnter={()=> tooltipToggle(shouldTooltipOnHover)}
                      onMouseLeave={() => tooltipToggle()}
                      onDoubleClick={() => emitSelectionParameters(blockKey, selection, forceSelection)}
                      /*contentEditable="false"*/
                      onClick={() => onClick(start, end, entityId, entityPlaceholder)}>
                {text}
            </span>
                <span style={{display:'none'}}>{children}</span>
            </div>
        } else {
            return <div className={"tag-container"}
                        ref={(ref) => this.tagRef = ref}
                        /*contentEditable="false"
                        suppressContentEditableWarning={true}*/>
                        {showTooltip && <TooltipInfo text={entityPlaceholder} isTag tagStyle={tagStyle}/>}
                        <span data-offset-key={offsetkey}
                              className={`tag ${tagStyle} ${tagWarningStyle} ${tagClickedStyle} ${tagFocusedStyle}`}
                              unselectable="on"
                              suppressContentEditableWarning={true}
                              onMouseEnter={()=> tooltipToggle(shouldTooltipOnHover)}
                              onMouseLeave={() => tooltipToggle()}
                              onDoubleClick={() => emitSelectionParameters(blockKey, selection, forceSelection)}
                              /*contentEditable="false"*/
                              onClick={() => onClick(start, end, entityId, entityPlaceholder)}>
                        {children}
                    </span>
                </div>
        }
    }
    
    updateTagStyle = () => {
        this.setState({
            tagStyle: this.selectCorrectStyle(),
            tagWarningStyle: this.highlightOnWarnings()
        })
    };

    selectCorrectStyle = () => {
        const {entityKey, contentState, getUpdatedSegmentInfo, isRTL} = this.props;
        const entityInstance = contentState.getEntity(entityKey);
        const {segmentOpened} = getUpdatedSegmentInfo();
        let tagStyle = [];

        // Check for tag type
        const entityType = entityInstance.type;
        const style = isRTL && tagSignatures[entityType].styleRTL ? tagSignatures[entityType].styleRTL : tagSignatures[entityType].style;
        tagStyle.push(style);
        // Check if tag is in an active segment
        if(!segmentOpened) tagStyle.push('tag-inactive');
        return tagStyle.join(' ');
    };


    highlightOnWarnings = () => {
        const {getUpdatedSegmentInfo, contentState, entityKey, isTarget} = this.props;
        const {warnings, tagMismatch, tagRange, segmentOpened, missingTagsInTarget} = getUpdatedSegmentInfo();
        const draftEntity = contentState.getEntity(entityKey);
        if(!segmentOpened || !tagMismatch) return;
        let tagWarningStyle = '';
        if(tagMismatch.target && tagMismatch.target.length > 0 && isTarget){
            let tagObject;
            let tagInfo;
            // Todo: Check tag type and tag id instead of string
            tagMismatch.target.forEach(tagString => {
                //tagObject = DraftMatecatUtils.tagFromString(tagString);
                //tagInfo = DraftMatecatUtils.decodeTagInfo(tagObject);
                if(draftEntity.data.encodedText === tagString){
                    tagWarningStyle = 'tag-mismatch-error'
                }
            });
        }else if(tagMismatch.source && tagMismatch.source.length > 0 && !isTarget && missingTagsInTarget){
            // Find tag and specific Tag ID in missing tags in target array
            const missingTagInError = missingTagsInTarget.filter( tag => {
                return tag.data.encodedText === draftEntity.data.encodedText && tag.data.id === draftEntity.data.id
            });
            // Array should contain only one item
            if(missingTagInError.length === 1) tagWarningStyle = 'tag-mismatch-error';
            /*tagMismatch.source.forEach(tagString => {
                if(draftEntity.data.encodedText === tagString){
                    tagWarningStyle = 'tag-mismatch-error'
                }
            });*/
        }else if(tagMismatch.order && isTarget){
            tagMismatch.order.forEach(tagString => {
                if(draftEntity.data.encodedText === tagString){
                    tagWarningStyle = 'tag-mismatch-warning'
                }
            });
        }

        return tagWarningStyle;

    };
}


export default TagEntity;
