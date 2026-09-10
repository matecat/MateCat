import React from 'react'

const TooltipInfo = (props) => {
  const {text, isTag, tagStyle} = props

  /*render() {
        return <div className="tag-tooltip">
            <span className="tooltip-txt">

            </span>
        </div>
    }*/

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

//common-tooltip

export default TooltipInfo
