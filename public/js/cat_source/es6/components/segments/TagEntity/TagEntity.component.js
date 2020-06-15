import React, {Component} from 'react';
import TooltipInfo from "../TooltipInfo/TooltipInfo.component";

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
            showTooltip: false,
            tagWarningStyle: '',
            tagStyle
        };
    }

    emitSelectionParameters = (blockKey, selection, forceSelection) => {
    };

    tooltipToggle = () => {
        // this will trigger a rerender in the main Editor Component
        const {showTooltip} = this.state;
        this.setState({
            showTooltip: !showTooltip
        })
    };

    highlightTag = () => {
        const {start, end, children} = this.props;
        const {selection} = children.props.children[0];
    };

    // markSearch = (text) => {
    //     let {active, currentActive, textToReplace, params, occurrences, currentInSearchIndex} = this.props.getSearchParams();
    //     let currentOccurrence = _.find(occurrences, (occ) => occ.searchProgressiveIndex === currentInSearchIndex);
    //     let isCurrent = (currentOccurrence && currentOccurrence.matchPosition >= this.props.start && currentOccurrence.matchPosition < this.props.end);
    //     if ( active ) {
    //         let regex = SearchUtils.getSearchRegExp(textToReplace, params.ingnoreCase, params.exactMatch);
    //         var parts = text.split(regex);
    //         for (var i = 1; i < parts.length; i += 2) {
    //             if ( currentActive && isCurrent ) {
    //                 parts[i] = <span key={i} style={{backgroundColor: 'rgb(255 210 14)'}}>{parts[i]}</span>;
    //             } else {
    //                 parts[i] = <span key={i} style={{backgroundColor: 'rgb(255 255 0)'}}>{parts[i]}</span>;
    //             }
    //         }
    //         return parts;
    //     }
    //     return text;
    // };



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
        const {selected, tyselectionStateInputs , showTooltip, tagWarningStyle, tagStyle} = this.state;
        const {decoratedText, entityKey, offsetkey, blockKey, start, end, onClick, contentState, getClickedTagId} = this.props;
        const { children } = this.props.children.props;
        const {selection, forceSelection} = children[0];
        const {emitSelectionParameters,tooltipToggle,highlightTag} = this;
        // let text = this.markSearch(children[0].props.text);
        const entity = contentState.getEntity(entityKey);
        const clickedTagId = getClickedTagId();
        const mirrorClickedStyle = entity.data.id && clickedTagId === entity.data.id ? 'clicked' : '';
        
        return <div className={"tag-container"}
                    /*contentEditable="true"
                    suppressContentEditableWarning={true}*/>
            {showTooltip && <TooltipInfo/>}
            <span data-offset-key={offsetkey}
                className={`tag ${tagStyle} ${tagWarningStyle} ${mirrorClickedStyle}`}
                unselectable="on"
                suppressContentEditableWarning={true}
                /*onMouseEnter={()=> console.log(entity.data)}*/
                /*onMouseLeave={() => tooltipToggle()}*/
                onDoubleClick={() => emitSelectionParameters(blockKey, selection, forceSelection)}
                /*contentEditable="false"*/
                onClick={() => onClick(start, end, entity.data.id)}>
                {children}
            </span>
            {/*<span style={{display:'none'}}>{children}</span>*/}
        </div>
    }

    updateTagStyle = () => {
        const {selectCorrectStyle, highlightOnWarnings} = this;
        const tagStyle = selectCorrectStyle();
        const tagWarningStyle = highlightOnWarnings();
        this.setState({
            tagStyle,
            tagWarningStyle
        })
    };

    selectCorrectStyle = () => {
        const {entityKey, contentState, getUpdatedSegmentInfo} = this.props;
        const entityInstance = contentState.getEntity(entityKey);
        const {segmentOpened} = getUpdatedSegmentInfo();
        let tagStyle = [];

        // Check for tag type
        if(entityInstance.data.openTagId){
            tagStyle.push('tag-close');
        }else if(entityInstance.data.closeTagId){
            tagStyle.push('tag-open');
        }else{
            tagStyle.push('tag-selfclosed');
        }
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
        if(tagMismatch.target > 0 && isTarget){
            let tagObject;
            let tagInfo;
            tagMismatch.target.forEach(tagString => {
                //tagObject = DraftMatecatUtils.tagFromString(tagString);
                //tagInfo = DraftMatecatUtils.decodeTagInfo(tagObject);
                if(draftEntity.data.encodedText === tagString){
                    tagWarningStyle = 'tag-mismatch-error'
                }
            });
        }else if(tagMismatch.source && !isTarget && missingTagsInTarget){
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
