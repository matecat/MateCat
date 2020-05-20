import React, {Component} from 'react';
import TooltipInfo from "../TooltipInfo/TooltipInfo.component";

class TagEntity extends Component {
    constructor(props) {
        super(props);
        this.state = {
            selected: false,
            selectionStateInputs: {
                anchorOffset: null,
                focusOffset: null,
                anchorKey: '',
                focusKey: '',
            },
            showTooltip: false,
            tagStyle: '',
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

    highlightOnWarnings = () => {
        const {getUpdatedWarnings, contentState, entityKey, isTarget} = this.props;
        const {warnings, tagMismatch, tagRange, segmentOpened} = getUpdatedWarnings();
        const draftEntity = contentState.getEntity(entityKey);
        if(!segmentOpened || !tagMismatch) return;
        let tagStyle = '';
        if(tagMismatch.target > 0 && isTarget){
            let tagObject;
            let tagInfo;
            tagMismatch.target.forEach(tagString => {
                //tagObject = DraftMatecatUtils.tagFromString(tagString);
                //tagInfo = DraftMatecatUtils.decodeTagInfo(tagObject);
                if(draftEntity.data.encodedText === tagString){
                    tagStyle = 'tag-mismatch-error'
                }
            });
        }else if(tagMismatch.source && !isTarget){
            tagMismatch.source.forEach(tagString => {
                if(draftEntity.data.encodedText === tagString){
                    tagStyle = 'tag-mismatch-error'
                }
            });
        }else if(tagMismatch.order && isTarget){
            tagMismatch.order.forEach(tagString => {
                if(draftEntity.data.encodedText === tagString){
                    tagStyle = 'tag-mismatch-warning'
                }
            });
        }
        this.setState({
            tagStyle: tagStyle
        });
    };

    componentDidMount() {

        this.warningCheck = setInterval(
            () => this.highlightOnWarnings(),
            2000
        );

    }

    componentWillUnmount() {
        clearInterval(this.warningCheck);
    }

    render() {
        const {selected, tyselectionStateInputs ,showTooltip, tagStyle} = this.state;
        const {decoratedText, entityKey, offsetkey, blockKey, start, end, onClick, contentState} = this.props;
        const { children } = this.props.children.props;
        const {selection, forceSelection} = children[0];
        const {emitSelectionParameters,tooltipToggle,highlightTag} = this;
        // let text = this.markSearch(children[0].props.text);

        const entity = contentState.getEntity(entityKey);

        return <div className={"tag-container"}
                    contentEditable="false"
                    suppressContentEditableWarning={true}>
            {showTooltip && <TooltipInfo/>}
            <span data-offset-key={offsetkey}
                className={`tag ${tagStyle}`}
                unselectable="on"
                suppressContentEditableWarning={true}
                onMouseEnter={()=> console.log(entity.data)}
                /*onMouseLeave={() => tooltipToggle()}*/
                onDoubleClick={() => emitSelectionParameters(blockKey, selection, forceSelection)}
                /*contentEditable="false"*/
                onClick={() => onClick(start, end)}>
                {children}
            </span>
            {/*<span style={{display:'none'}}>{children}</span>*/}
        </div>
    }
}


export default TagEntity;
