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
            showTooltip: false
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
        const {selection} = children[0].props;
    };

    markSearch = (text) => {
        let {active, currentActive, textToReplace, params, occurrences, currentInSearchIndex} = this.props.getSearchParams();
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

    render() {
        const {selected, tyselectionStateInputs ,showTooltip} = this.state;
        const {decoratedText, entityKey, offsetkey, blockKey, start, end, children} = this.props;
        const {selection, forceSelection} = children[0].props;
        const {emitSelectionParameters,tooltipToggle,highlightTag} = this;
        let text = this.markSearch(children[0].props.text);
        return <div className="tag-container" /*contentEditable="false" suppressContentEditableWarning={true}*/>
            {showTooltip && <TooltipInfo/>}
            <span data-offset-key={offsetkey}
                  className="tag"
                  unselectable="on"
                  suppressContentEditableWarning={true}
                /*onMouseEnter={() => tooltipToggle()}
                onMouseLeave={() => tooltipToggle()}*/
                  onDoubleClick={() => emitSelectionParameters(blockKey, selection, forceSelection)}
                /*contentEditable="false"*/>
                {text}
            </span>
            <span style={{display:'none'}}>{children}</span>
        </div>
    }
}


export default TagEntity;
