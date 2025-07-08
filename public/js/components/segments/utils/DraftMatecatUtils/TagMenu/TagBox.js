import React from 'react'

import TagSuggestion from './TagSuggestion'

class TagBox extends React.Component {
  tagBox = React.createRef()
  childRefs = []

  componentDidUpdate(prevProps, prevState, snapshot) {
    this.scrollElementIntoViewIfNeeded(prevProps)
  }

  render() {
    const {
      popoverPosition,
      displayPopover,
      suggestions,
      onTagClick,
      focusedTagIndex,
    } = this.props
    const popoverOpen = Object.assign({}, popoverPosition, styles.popoverOpen)
    const lastIndex = suggestions.missingTags
      ? suggestions.missingTags.length
      : 0

    const missingSuggestions = suggestions.missingTags
      ? suggestions.missingTags.map((suggestion, index) => {
          this.childRefs[index] = React.createRef()
          return (
            <TagSuggestion
              ref={this.childRefs[index]}
              tabIndex={index}
              key={index}
              suggestion={suggestion}
              onTagClick={onTagClick}
              isFocused={focusedTagIndex === index}
            />
          )
        })
      : null

    const allSuggestions = suggestions.sourceTags
      ? suggestions.sourceTags.map((suggestion, index) => {
          this.childRefs[index + lastIndex] = React.createRef()
          return (
            <TagSuggestion
              ref={this.childRefs[index + lastIndex]}
              tabIndex={index + lastIndex}
              key={index + lastIndex}
              suggestion={suggestion}
              onTagClick={onTagClick}
              isFocused={focusedTagIndex === index + lastIndex}
            />
          )
        })
      : null

    return (
      <div
        className={`tag-box`}
        style={displayPopover ? popoverOpen : styles.popoverClosed}
      >
        <div className={`tag-box-inner`} ref={this.tagBox}>
          {missingSuggestions && missingSuggestions.length > 0 && (
            <div className={`missing`}>
              <div className={`tag-box-heading`}>
                Missing source&nbsp;
                <div className={'tag-container'}>
                  <span
                    className={`tag tag-heading tag-selfclosed tag-mismatch-error`}
                  >
                    tags
                  </span>
                </div>
                &nbsp;in target
              </div>
              {missingSuggestions}
            </div>
          )}
          <div className={`all`}>
            <div className={`tag-box-heading`}>
              All&nbsp;
              <div className={'tag-container'}>
                <span className={`tag tag-heading tag-selfclosed`}>tags</span>
              </div>
              &nbsp;available
            </div>
            {allSuggestions}
          </div>
        </div>
      </div>
    )
  }

  scrollElementIntoViewIfNeeded = (prevProps) => {
    const {focusedTagIndex} = this.props
    if (
      this.childRefs[focusedTagIndex] &&
      prevProps.focusedTagIndex !== focusedTagIndex
    ) {
      const tabBoxClientRect = this.tagBox.current.getBoundingClientRect()
      const activeElementClientRect =
        this.childRefs[focusedTagIndex].current.getBoundingClientRect()
      if (
        activeElementClientRect.top < tabBoxClientRect.top ||
        activeElementClientRect.bottom > tabBoxClientRect.bottom
      ) {
        const top =
          focusedTagIndex === 0
            ? 0
            : this.childRefs[focusedTagIndex].current.offsetTop - 60
        this.tagBox.current.scrollTo({
          top: top,
          left: 0,
          behavior: 'smooth',
        })
      }
    }
  }
}

const styles = {
  popoverOpen: {
    position: 'absolute',
    maxHeight: '240px',
    background: '#fff',
    border: '1px solid #dadada',
    cursor: 'pointer',
    zIndex: 1,
    borderRadius: '5px',
    boxSizing: 'border-box',
    maxWidth: '300px',
    boxShadow: '0 1px 2px 0 rgba(0, 0, 0, 0.3)',
    padding: '0 4px',
  },
  popoverClosed: {
    display: 'none',
    position: 'absolute',
    background: 'white',
    border: '2px solid #e2e2e2',
    cursor: 'pointer',
    zIndex: 1,
    borderRadius: '2px',
    width: '18rem',
  },
}

export default TagBox
