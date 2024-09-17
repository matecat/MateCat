import React, {useEffect, useLayoutEffect, useRef, useState} from 'react'

import PropTypes from 'prop-types'
import ChevronDown from '../../../../../../img/icons/ChevronDown'

export const Accordion = ({
  children,
  title,
  id,
  expanded = false,
  onShow = () => {},
  className = '',
}) => {
  const [isRenderingContent, setIsRenderingContent] = useState(expanded)

  const panelRef = useRef()

  const {scrollHeight} = panelRef.current ?? {}

  const handleClick = () => {
    onShow(id)
    setIsRenderingContent(true)
  }

  useEffect(() => {
    const transitionEndClose = () => setIsRenderingContent(false)
    const transitionEndOpen = () =>
      (panelRef.current.style.maxHeight = `${panelRef.current.scrollHeight}px`)

    const {current} = panelRef
    const maxHeight = window.getComputedStyle(panelRef.current).maxHeight

    if (!expanded && maxHeight !== '0px')
      current.addEventListener('transitionend', transitionEndClose)

    if (expanded) current.addEventListener('transitionend', transitionEndOpen)

    return () => {
      current.removeEventListener('transitionend', transitionEndClose)
      current.removeEventListener('transitionend', transitionEndOpen)
    }
  }, [expanded, id])

  useLayoutEffect(() => {
    if (expanded)
      panelRef.current.style.maxHeight = `${scrollHeight > 0 ? scrollHeight : panelRef.current.scrollHeight}px`
    else panelRef.current.style.maxHeight = 0
  }, [expanded, scrollHeight])

  return (
    <div className={`accordion-component ${className}`}>
      <div
        className={`accordion-component-title ${expanded ? 'accordion-expanded' : ''}`}
        onClick={handleClick}
      >
        {title} <ChevronDown size={10} />
      </div>
      <div ref={panelRef} className="accordion-component-content">
        {isRenderingContent && children}
      </div>
    </div>
  )
}

Accordion.propTypes = {
  children: PropTypes.node.isRequired,
  title: PropTypes.node.isRequired,
  id: PropTypes.string.isRequired,
  expanded: PropTypes.bool,
  onShow: PropTypes.func,
  className: PropTypes.string,
}
