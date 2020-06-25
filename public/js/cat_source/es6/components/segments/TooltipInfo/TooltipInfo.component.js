import React, {Component} from 'react';
// import "./TooltipInfo.scss";

class TooltipInfo extends Component {

    state = {

    };

    /*render() {
        return <div className="tag-tooltip">
            <span className="tooltip-txt">

            </span>
        </div>
    }*/

    render() {
        return <div className="common-tooltip">
            <div className="tooltip-error-wrapper">
                <div className="tooltip-error-container">
                    <span className="tooltip-error-category">{this.props.text}</span>
                    {/*<div className="tooltip-error-ignore">
                        <span className="icon-cancel-circle"/>
                        <span className="tooltip-error-ignore-text" onClick={()=>this.ignoreError(message)}>Ignore</span>
                    </div>*/}
                </div>
            </div>
        </div>
    }
}


//common-tooltip

export default TooltipInfo;