
import React, {Component} from 'react';
import SegmentActions from '../../../actions/SegmentActions';
import TooltipInfo from "../TooltipInfo/TooltipInfo.component";

class QaCheckGlossaryHighlight extends Component {
    constructor(props) {
        super(props);
        this.state = {
            showTooltip: false
        };
    }
    tooltipToggle = () => {
        // this will trigger a rerender in the main Editor Component
        const {showTooltip} = this.state;
        this.setState({
            showTooltip: !showTooltip
        })
    };
    render() {
        const { children, sid } = this.props;
        const {showTooltip} = this.state;
        return <div className="qaCheckGlossaryItem">
            {showTooltip && <TooltipInfo text={'Unused glossary term'}/>}
            <span onMouseEnter={() => this.tooltipToggle()}
                    onMouseLeave={() => this.tooltipToggle()}
                onClick={()=>SegmentActions.activateTab(sid, 'glossary')}>
                {children}
            </span>
        </div>
    };
}


export default QaCheckGlossaryHighlight;
