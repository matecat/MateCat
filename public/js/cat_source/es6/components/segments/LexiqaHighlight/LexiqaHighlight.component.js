
import React, {Component} from 'react';
import LexiqaTooltipInfo from "../TooltipInfo/LexiqaTooltipInfo.component";
import LexiqaUtils from "../../../utils/lxq.main"

class LexiqaHighlight extends Component {
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
    getWarning = () => {
        let {start, end, warnings, isSource, sid} = this.props;
        let warning =  _.find(warnings, (warn) => warn.start === start || warn.end === end);
        // Todo check why myClass is missing sometimes
        if ( warning && warning.myClass ) {
            warning.messages = LexiqaUtils.buildTooltipMessages(warning, sid, isSource);
        }
        return warning;
    };

    render() {
        const { children, sid } = this.props;
        const {showTooltip} = this.state;
        const warning = this.getWarning();

        return warning ? <div className="lexiqahighlight"
                 onMouseEnter={() => this.tooltipToggle()}
                 onMouseLeave={() => this.tooltipToggle()}>
                {showTooltip && warning && <LexiqaTooltipInfo messages={warning.messages}/>}
                <span
                    style={{backgroundColor: warning.color}}
                >
                {children}
            </span>
            </div> : null
    };
}


export default LexiqaHighlight;
