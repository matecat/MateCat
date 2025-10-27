import React, {Component} from 'react'

class TooltipInfo extends Component {
  state = {}

  /*render() {
        return <div className="tag-tooltip">
            <span className="tooltip-txt">

            </span>
        </div>
    }*/

  render() {
    const {text, isTag, tagStyle} = this.props
    return (
      <div className="common-tooltip">
        <div className="tooltip-error-wrapper">
          <div className="tooltip-error-container">
            {isTag ? (
              <span className={`tag ${tagStyle}`}>
                <span>{text}</span>
              </span>
            ) : (
              <span className="tooltip-error-category">{text}</span>
            )}
          </div>
        </div>
      </div>
    )
  }
}

//common-tooltip

export default TooltipInfo
