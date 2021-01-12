import React, {Component} from 'react';
import TooltipInfo from "../TooltipInfo/TooltipInfo.component";
import {tagSignatures, getTooltipTag} from "../utils/DraftMatecatUtils/tagModel";
import SegmentStore from "../../../stores/SegmentStore";
import SegmentConstants from "../../../constants/SegmentConstants";
import EditAreaConstants from "../../../constants/EditAreaConstants";
import SegmentActions from "../../../actions/SegmentActions";

class TagEntity extends Component {

    constructor(props) {
        super(props);

        const {entityKey, contentState} = this.props;
        const {data: {name: entityName}} = contentState.getEntity(entityKey);

        this.state = {
            showTooltip: false,
            tagStyle: this.selectCorrectStyle(),
            tagWarningStyle: '',
            tooltipAvailable: getTooltipTag().includes(entityName),
            shouldTooltipOnHover: false,
            clicked: false,
            focused: false,
            searchParams: this.props.getSearchParams(),
            entityKey: this.props.entityKey
        };
        this.updateTagStyleDebounced = _.debounce(this.updateTagStyle, 500);
        this.updateTagWarningStyleDebounced = _.debounce(this.updateTagWarningStyle, 500);
    }

    tooltipToggle = (show = false) => {
        // this will trigger a rerender in the main Editor Component
        this.setState({showTooltip: show});
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

    addSearchParams = (sid) => {
        const {getSearchParams, isTarget} = this.props;
        if ( sid !== this.props.sid ) return;
        let searchParams = getSearchParams();
        if (searchParams.active && ((searchParams.isTarget && isTarget) || (!searchParams.isTarget && !isTarget))) {
            this.setState( {
                searchParams
            } );
        }
    }

    updateSearchParams = (sid, currentInSearchIndex) => {
        const {getSearchParams} = this.props;
        if ( sid !== this.props.sid || sid === this.props.sid && !this.state.searchParams.active) return;
        let searchParamsNew = getSearchParams();
        searchParamsNew.currentInSearchIndex = currentInSearchIndex;
        this.setState({
            searchParams: searchParamsNew
        })
    }

    removeSearchParams = () => {
        if ( this.state.searchParams.active) {
            const {getSearchParams} = this.props;
            let searchParams = getSearchParams();
            this.setState( {
                searchParams
            } );
        }
    }

    componentDidMount() {
        SegmentStore.addListener(SegmentConstants.SET_SEGMENT_WARNINGS, this.updateTagWarningStyleDebounced);
        SegmentStore.addListener(SegmentConstants.HIGHLIGHT_TAGS, this.highlightTags);
        SegmentStore.addListener(EditAreaConstants.EDIT_AREA_CHANGED, this.updateTagStyleDebounced);
        SegmentStore.addListener(SegmentConstants.ADD_SEARCH_RESULTS, this.addSearchParams);
        SegmentStore.addListener(SegmentConstants.ADD_CURRENT_SEARCH, this.updateSearchParams);
        SegmentStore.addListener(SegmentConstants.REMOVE_SEARCH_RESULTS, this.removeSearchParams);
        const textSpanDisplayed = this.tagRef && this.tagRef.querySelector('span[data-text="true"]');
        const shouldTooltipOnHover = textSpanDisplayed && textSpanDisplayed.offsetWidth < textSpanDisplayed.scrollWidth;
        this.setState({shouldTooltipOnHover})
    }


    shouldComponentUpdate(nextProps, nextState, nextContext) {
        const searchChange = (this.state.searchParams.active !== nextState.searchParams.active) ||
            (nextState.searchParams.active && nextState.searchParams.currentInSearchIndex !== this.state.searchParams.currentInSearchIndex);
        const entityChanged = this.props.entityKey !== nextProps.entityKey;
        const styleChanged = this.state.tagStyle !== nextState.tagStyle;
        const warningChanged = this.state.tagWarningStyle !== nextState.tagWarningStyle;
        const tooltipChanged = this.state.showTooltip !== nextState.showTooltip ||
            this.state.shouldTooltipOnHover !== nextState.shouldTooltipOnHover;
        return entityChanged || styleChanged || warningChanged || tooltipChanged || searchChange;
    }



    componentDidUpdate(prevProps, prevState, snapshot) {
        if(prevProps.entitykey !== this.props.entityKey){
            const textSpanDisplayed = this.tagRef && this.tagRef.querySelector('span[data-text="true"]');
            const shouldTooltipOnHover = textSpanDisplayed && textSpanDisplayed.offsetWidth < textSpanDisplayed.scrollWidth;
            if(shouldTooltipOnHover !== this.state.shouldTooltipOnHover){
                this.setState({shouldTooltipOnHover})
            }
        }
    }

    componentWillUnmount() {
        SegmentStore.removeListener(SegmentConstants.SET_SEGMENT_WARNINGS, this.updateTagWarningStyleDebounced);
        SegmentStore.removeListener(SegmentConstants.HIGHLIGHT_TAGS, this.highlightTags);
        SegmentStore.removeListener(EditAreaConstants.EDIT_AREA_CHANGED, this.updateTagStyleDebounced);
        SegmentStore.removeListener(SegmentConstants.ADD_SEARCH_RESULTS, this.addSearchParams);
        SegmentStore.removeListener(SegmentConstants.ADD_CURRENT_SEARCH, this.updateSearchParams);
        SegmentStore.removeListener(SegmentConstants.REMOVE_SEARCH_RESULTS, this.removeSearchParams);
    }

    render() {
        const {children, entityKey, blockKey, start, end, contentState, getUpdatedSegmentInfo, isTarget} = this.props;
        const {tagStyle, tagWarningStyle, tooltipAvailable, showTooltip, shouldTooltipOnHover, searchParams} = this.state;
        const {tooltipToggle, markSearch} = this;
        const style = (this.props.entityKey === this.state.entityKey) ? tagStyle : this.selectCorrectStyle();
        const {sid, openSplit} = getUpdatedSegmentInfo();


        const {type: entityType, data: {id: entityId, placeholder: entityPlaceholder, name: entityName}} = contentState.getEntity(entityKey);
        const decoratedText = Array.isArray(children) ? children[0].props.text : children.props.decoratedText;

        return <div className={'tag-container'}
                    ref={(ref) => this.tagRef = ref}>
            {tooltipAvailable && showTooltip && <TooltipInfo text={entityPlaceholder} isTag tagStyle={style}/>}
            <span className={`tag ${style} ${tagWarningStyle}`}
                data-offset-key={this.props.offsetkey}
                unselectable="on"
                suppressContentEditableWarning={true}
                onMouseEnter={()=> tooltipToggle(shouldTooltipOnHover)}
                onMouseLeave={() => tooltipToggle()}
                onClick={(e) => {
                    e.stopPropagation();
                    this.onClickBound(entityId, entityPlaceholder);
                    !openSplit && setTimeout(() =>{
                        SegmentActions.highlightTags(entityId, entityPlaceholder, entityKey);
                    })
                }}>
                {searchParams.active && markSearch(decoratedText, searchParams)}
                {searchParams.active ? <span style={{display: 'none'}}>{children}</span> : children}
            </span>

        </div>
    }

    onClickBound = (entityId, entityPlaceholder) =>{
        const {start, end, onClick: onClickAction} = this.props;
        onClickAction(start, end, entityId, entityPlaceholder);
    }

    highlightTags = (tagId, tagPlaceholder, triggerEntityKey) => {
        const {entityKey, contentState} = this.props;
        const {clicked} = this.state;
        const {data: {id: entityId, placeholder: entityPlaceholder}} = contentState.getEntity(entityKey);
        // Turn OFF
        if(clicked && (!tagId || tagId !== entityId)){
            this.setState({
                tagStyle: this.selectCorrectStyle(),
                clicked: false,
                focused: false,
                entityKey
            })
        }else if(entityKey === triggerEntityKey){
            this.setState({
                tagStyle: this.selectCorrectStyle(tagId, tagPlaceholder, true),
                clicked: true,
                focused: true,
                entityKey
            })
        }else if(tagId === entityId && entityPlaceholder === tagPlaceholder && entityKey !== triggerEntityKey) {
            this.setState({
                tagStyle: this.selectCorrectStyle(tagId, tagPlaceholder),
                clicked: true,
                focused: false,
                entityKey
            })
        }
    };

    updateTagStyle = (sid, isTarget) => {
        if(!this.props.isTarget && isTarget) return;
        const {selectCorrectStyle} = this;
        const newStyle = selectCorrectStyle();
        if(newStyle !== this.state.tagStyle){
            this.setState({
                tagStyle: newStyle,
                entityKey: this.props.entityKey
            })
        }
    };

    updateTagWarningStyle = (sid, isTarget) => {
        const {tagWarningStyle: prevTagWarningStyle} = this.state;
        const tagWarningStyle = this.highlightOnWarnings();
        if(prevTagWarningStyle !== tagWarningStyle){
            this.setState({tagWarningStyle})
        }
    };


    selectCorrectStyle = (clickedTagId= null, clickedTagText= null, focused = false) => {
        const {entityKey, contentState, getUpdatedSegmentInfo, isRTL, isTarget, start, end, blockKey} = this.props;
        const {currentSelection: {anchorOffset, focusOffset, anchorKey, hasFocus}, segmentOpened} = getUpdatedSegmentInfo();
        const {data: {id: entityId, placeholder: entityPlaceholder, name: entityName}} = contentState.getEntity(entityKey);

        // Basic style accordin to language direction
        const baseStyle = isRTL && tagSignatures[entityName].styleRTL
            ? tagSignatures[entityName].styleRTL
            : tagSignatures[entityName].style;

        // Check if tag is in an active segment
        const tagInactive = !segmentOpened ? 'tag-inactive' : ''

        // Click
        const tagClicked = entityId &&
        clickedTagId &&
        clickedTagId === entityId &&
        clickedTagText &&
        clickedTagText === entityPlaceholder
            ? 'tag-clicked' : ''; // green

        // Focus
        /*const tagFocused = anchorOffset === start &&
        focusOffset === end &&
        anchorKey === blockKey &&
        (tagClickedInSource && !isTarget || !tagClickedInSource && isTarget) &&
        hasFocus ? 'tag-focused' : ''; // blue with shadow*/

        const tagFocused = focused ? 'tag-focused' : ''; // blue with shadow
        return `${baseStyle} ${tagInactive} ${tagClicked} ${tagFocused}`.trim();
    };


    highlightOnWarnings = () => {
        //console.log('highlightOnWarnings')
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
        }else if(tagMismatch.source && tagMismatch.source.length > 0 && !isTarget/* && missingTagsInTarget*/){
            // Find tag and specific Tag ID in missing tags in target array
            /*const missingTagInError = missingTagsInTarget.filter( tag => {
                return tag.data.encodedText === encodedText && tag.data.id === entityId
            });*/

            tagMismatch.source.forEach(tagString => {
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

            // Array should contain only one item
            //if(missingTagInError.length === 1) tagWarningStyle = 'tag-mismatch-error';
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
