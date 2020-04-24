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

    render() {
        const {selected, tyselectionStateInputs ,showTooltip} = this.state;
        const {decoratedText, entityKey, offsetkey, blockKey, start, end, children} = this.props;
        const {selection, forceSelection} = children[0].props;
        const {emitSelectionParameters,tooltipToggle,highlightTag} = this;

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
                {children}
            </span>
        </div>
    }
}


export default TagEntity;
