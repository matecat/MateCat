import React, {PureComponent, Component} from 'react';
import TooltipInfo from "../TooltipInfo/TooltipInfo.component";
import {tagSignatures, getTooltipTag} from "../utils/DraftMatecatUtils/tagModel";
import SegmentStore from "../../../stores/SegmentStore";
import SegmentConstants from "../../../constants/SegmentConstants";

class TagEntity extends Component {
    constructor(props) {
        super(props);
        this.state = {
            showTooltip: false,
            tagStyle: '',
            tagFocusedStyle: '',
            tagWarningStyle: ''
        };
        this.warningCheck = null;
    }

    tooltipToggle = (show = false) => {
        // this will trigger a rerender in the main Editor Component
        this.setState({
            showTooltip: show
        });
    };

    markSearch = (text, searchParams) => {
        let { active, currentActive, textToReplace, params, occurrences, currentInSearchIndex } = searchParams;
        let currentOccurrence = _.find(occurrences, (occ) => occ.searchProgressiveIndex === currentInSearchIndex);
        let isCurrent =
            currentOccurrence &&
            currentOccurrence.matchPosition >= this.props.start &&
            currentOccurrence.matchPosition < this.props.end;
        if (active) {
            let regex = SearchUtils.getSearchRegExp(textToReplace, params.ingnoreCase, params.exactMatch);
            let parts = text.split(regex);
            for (let i = 1; i < parts.length; i += 2) {
                let color = currentActive && isCurrent ? 'rgb(255 210 14)' : 'rgb(255 255 0)';
                parts[i] = <span key={i} style={{backgroundColor: color}}>{parts[i]}</span>;
            }
            return parts;
        }
        return text;
    };

    startChecks = (sid, focused) => {
        const { sid:  currentSid } = this.props.getUpdatedSegmentInfo();
        if (sid === currentSid && !this.warningCheck && focused){
            this.warningCheck = setInterval(() => {
                this.updateTagStyle();
            }, 500);
        } else if (this.warningCheck && sid === currentSid && !focused) {
            clearInterval(this.warningCheck);
            this.warningCheck = null;
            this.updateTagStyle();
        }
    }

    componentDidMount() {
        SegmentStore.addListener(SegmentConstants.SEGMENT_FOCUSED, this.startChecks);
        // Update style once
        this.updateTagStyle();
    }

    componentDidUpdate() {
        const { segmentOpened } = this.props.getUpdatedSegmentInfo();
        // if segment already opened, start interval anyway
        if (segmentOpened && !this.warningCheck){
            this.warningCheck = setInterval(() => {
                this.updateTagStyle();
            }, 500);
        }
    }

    componentWillUnmount() {
        this.warningCheck && clearInterval(this.warningCheck);
        SegmentStore.removeListener(SegmentConstants.SEGMENT_FOCUSED, this.startChecks);
    }

    render() {
        const {children, entityKey, blockKey, start, end, onClick, contentState, getUpdatedSegmentInfo, getClickedTagInfo, getSearchParams, isTarget} = this.props;
        const {tagStyle, tagWarningStyle} = this.state;
        const {tooltipToggle, markSearch} = this;
        const {currentSelection} = getUpdatedSegmentInfo();
        const {anchorOffset, focusOffset, anchorKey, hasFocus} = currentSelection;
        const {tagClickedInSource, clickedTagId, clickedTagText} = getClickedTagInfo();

        let searchParams = getSearchParams();
        const {type: entityType, data: {id: entityId, placeholder: entityPlaceholder, name: entityName}} = contentState.getEntity(entityKey);

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
        const showTooltip = this.state.showTooltip && getTooltipTag().includes(entityName);
        // show tooltip only if text too long
        const textSpanDisplayed = this.tagRef && this.tagRef.querySelector('span[data-text="true"]');
        const shouldTooltipOnHover = textSpanDisplayed && textSpanDisplayed.offsetWidth < textSpanDisplayed.scrollWidth;
        const decoratedText = Array.isArray(children) ? children[0].props.text : children.props.decoratedText;
        return <div className={'tag-container'}
                    ref={(ref) => this.tagRef = ref}>
            {showTooltip && <TooltipInfo text={entityPlaceholder} isTag tagStyle={tagStyle}/>}
            <span className={`tag ${tagStyle} ${tagWarningStyle} ${tagClickedStyle} ${tagFocusedStyle}`}
                unselectable="on"
                suppressContentEditableWarning={true}
                onMouseEnter={()=> tooltipToggle(shouldTooltipOnHover)}
                onMouseLeave={() => tooltipToggle()}
                onClick={(e) => {
                    e.stopPropagation()
                    onClick(start, end, entityId, entityPlaceholder)
                }}>
                {searchParams.active && markSearch(decoratedText, searchParams)}
                {searchParams.active ? <span style={{display: 'none'}}>{children}</span> : children}
            </span>
        </div>
    }

    updateTagStyle = () => {
        this.setState({
            tagStyle: this.selectCorrectStyle(),
            tagWarningStyle: this.highlightOnWarnings()
        })
    };

    selectCorrectStyle = () => {
        const {entityKey, contentState, getUpdatedSegmentInfo, isRTL, isTarget, start, end, getClickedTagInfo} = this.props;
        const entityInstance = contentState.getEntity(entityKey);
        const { tagClickedInSource } = getClickedTagInfo();
        const { segmentOpened, currentSelection } = getUpdatedSegmentInfo();
        const tagStyle = [];
        // Check for tag type
        const {data: { name: entityName}} = entityInstance;
        const { anchorOffset, focusOffset, hasFocus } = currentSelection;
        const style =
            isRTL && tagSignatures[entityName].styleRTL
                ? tagSignatures[entityName].styleRTL
                : tagSignatures[entityName].style;
        anchorOffset <= start &&
            focusOffset >= end &&
            ((tagClickedInSource && !isTarget) || (!tagClickedInSource && isTarget)) &&
            hasFocus &&
            tagStyle.push('tag-focused');
        tagStyle.push(style);
        // Check if tag is in an active segment
        if (!segmentOpened) tagStyle.push('tag-inactive');
        return tagStyle.join(' ');
    };


    highlightOnWarnings = () => {
        const {getUpdatedSegmentInfo, contentState, entityKey, isTarget} = this.props;
        const {warnings, tagMismatch, tagRange, segmentOpened, missingTagsInTarget} = getUpdatedSegmentInfo();
        const {type: entityType, data: entityData} = contentState.getEntity(entityKey) || {};
        const {id: entityId, encodedText, openTagId, closeTagId} = entityData || {};

        if(!segmentOpened || !tagMismatch) return;
        let tagWarningStyle = '';
        if(tagMismatch.target && tagMismatch.target.length > 0 && isTarget){
            let tagObject;
            // Todo: Check tag type and tag id instead of string
            tagMismatch.target.forEach(tagString => {
                // build tag from string
                //tagObject = DraftMatecatUtils.tagFromString(tagString);
                /*if(entityType === tagObject.type){
                    // If tag is closure and openTagId/closeTagId are null, then the tag was added after initial rendering
                    if(getClosureTag().includes(tagObject.type)){
                        /!*if(!entityData.openTagId && !entityData.closeTagId){
                            tagWarningStyle = 'tag-mismatch-error'
                        }*!/
                        tagWarningStyle = 'tag-mismatch-error'
                    }else if(entityData.id === tagObject.data.id){
                        tagWarningStyle = 'tag-mismatch-error'
                    }
                }*/
                if(entityData.encodedText === tagString){
                    tagWarningStyle = 'tag-mismatch-error'
                }
            });
        }else if(tagMismatch.source && tagMismatch.source.length > 0 && !isTarget && missingTagsInTarget){
            // Find tag and specific Tag ID in missing tags in target array
            const missingTagInError = missingTagsInTarget.filter( tag => {
                return tag.data.encodedText === encodedText && tag.data.id === entityId
            });
            // Array should contain only one item
            if(missingTagInError.length === 1) tagWarningStyle = 'tag-mismatch-error';
            /*tagMismatch.source.forEach(tagString => {
                if(entityData.encodedText === tagString){
                    tagWarningStyle = 'tag-mismatch-error'
                }
            });*/
        }else if(tagMismatch.order && isTarget){
            tagMismatch.order.forEach(tagString => {
                if(entityData.encodedText === tagString){
                    tagWarningStyle = 'tag-mismatch-warning'
                }
            });
        }/*else if(entityData.id){
            tagWarningStyle = 'tag-mismatch-error'
        }*/

        return tagWarningStyle;

    };
}

export default TagEntity;
