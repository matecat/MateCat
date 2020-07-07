
import React, {Component} from 'react';
import LexiqaTooltipInfo from "../TooltipInfo/LexiqaTooltipInfo.component";
import LexiqaUtils from "../../../utils/lxq.main"

class LexiqaHighlight extends Component {
    constructor(props) {
        super(props);
        this.state = {
            showTooltip: false
        };
        this.delayHideLoop = null;
        this.delayShowLoop = null;
    }

    clearTimer() {
        clearTimeout(this.delayHideLoop);
        clearTimeout(this.delayShowLoop);
    }

    toggleTooltip = (show = false) => {
        this.setState({
            showTooltip: show
        })
    }

    showTooltip= (delayShow) => {
        this.clearTimer();
        if (delayShow) {
            this.delayShowLoop = setTimeout(() => this.toggleTooltip(true), parseInt(delayShow, 10));
        } else {
            this.toggleTooltip(true);
        }
    }

    hideTooltip = (delayHide) => {
        this.clearTimer();

        if (delayHide) {
            this.delayHideLoop = setTimeout(this.toggleTooltip, parseInt(delayHide, 10));
        } else {
            this.toggleTooltip();
        }
    }

    getWarning = () => {
        let {start, end, warnings, isSource, sid} = this.props;
        let warning =  _.find(warnings, (warn) => warn.start === start || warn.end === end);
        // Todo check why myClass is missing sometimes
        if ( warning && warning.myClass ) {
            warning.messages = LexiqaUtils.buildTooltipMessages(warning, sid, isSource);
        }
        return warning;
    };

    componentWillUnmount() {
        this.clearTimer();
    }

    render() {
        const { children, sid } = this.props;
        const {showTooltip} = this.state;
        const warning = this.getWarning();

        return warning ? <div className="lexiqahighlight"
                 onMouseEnter={() => this.showTooltip(300)}
                 onMouseLeave={() => this.hideTooltip(300)}>
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
