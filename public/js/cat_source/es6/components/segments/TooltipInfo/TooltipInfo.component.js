import React, {Component} from 'react';
// import "./TooltipInfo.scss";

class TooltipInfo extends Component {

    state = {

    };

    render() {
        return <div className="tag-tooltip">
            <span className="tooltip-txt">
                {this.props.text}
            </span>
        </div>
    }
}


export default TooltipInfo;