import React, {useEffect, useLayoutEffect, useRef, useState} from 'react'

import PropTypes from 'prop-types'
import ChevronDown from '../../../../img/icons/ChevronDown'
import useResizeObserver from '../../../hooks/useResizeObserver'
import styles from './Accordion.module.scss'

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
  const contentRef = useRef()

  const {height: contentHeight} = useResizeObserver(contentRef)

  const handleClick = () => {
    onShow(id)
    setIsRenderingContent(true)
  }

  useEffect(() => {
    const transitionEndClose = () => setIsRenderingContent(false)
    const transitionEndOpen = () => {
      panelRef.current.style.maxHeight = `${panelRef.current.scrollHeight}px`
      panelRef.current.parentNode.style.overflow = 'visible'
    }

    const {current} = panelRef
    const maxHeight = window.getComputedStyle(panelRef.current).maxHeight

    if (!expanded && maxHeight !== '0px')
      current.addEventListener('transitionend', transitionEndClose)

    if (expanded) current.addEventListener('transitionend', transitionEndOpen)
    else {
      panelRef.current.parentNode.style.overflow = 'hidden'
    }

    return () => {
      current.removeEventListener('transitionend', transitionEndClose)
      current.removeEventListener('transitionend', transitionEndOpen)
    }
  }, [expanded, id])

  useLayoutEffect(() => {
    if (expanded && panelRef.current.scrollHeight > 0)
      panelRef.current.style.maxHeight = `${panelRef.current.scrollHeight}px`
    else if (!expanded) panelRef.current.style.maxHeight = 0
  }, [expanded, contentHeight])

  const titleClassName = [
    styles.title,
    expanded && styles['title--expanded'],
  ]
    .filter(Boolean)
    .join(' ')

  return (
    <div className={`${styles.component} ${className}`.trim()}>
      <div
        className={titleClassName}
        data-expanded={expanded || undefined}
        onClick={handleClick}
      >
        {title} <ChevronDown size={10} />
      </div>
      <div ref={panelRef} className={styles.content}>
        <div ref={contentRef}>{isRenderingContent && children}</div>
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
