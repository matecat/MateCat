import React, {useEffect, useLayoutEffect, useRef, useState} from 'react'
import PropTypes from 'prop-types'
import useResizeObserver from '../../../hooks/useResizeObserver'
import styles from './AnimatedCollapse.module.scss'

export const AnimatedCollapse = ({expanded, children, className = ''}) => {
  const [isRenderingContent, setIsRenderingContent] = useState(expanded)

  const wrapperRef = useRef()
  const contentRef = useRef()

  const {height: contentHeight} = useResizeObserver(contentRef)

  useEffect(() => {
    if (expanded) setIsRenderingContent(true)
  }, [expanded])

  useLayoutEffect(() => {
    const wrapper = wrapperRef.current
    const content = contentRef.current
    if (!wrapper || !content) return

    wrapper.style.height = expanded ? `${content.scrollHeight}px` : '0px'
  }, [expanded, contentHeight, isRenderingContent])

  useEffect(() => {
    const wrapper = wrapperRef.current
    if (!wrapper) return

    const handleTransitionEnd = (event) => {
      if (event.target !== wrapper || event.propertyName !== 'height') return

      if (expanded) {
        wrapper.style.overflow = 'visible'
      } else {
        wrapper.style.overflow = 'hidden'
        setIsRenderingContent(false)
      }
    }

    wrapper.addEventListener('transitionend', handleTransitionEnd)
    return () => wrapper.removeEventListener('transitionend', handleTransitionEnd)
  }, [expanded])

  return (
    <div className={`${styles.wrap} ${className}`.trim()} ref={wrapperRef}>
      <div ref={contentRef}>{isRenderingContent ? children : null}</div>
    </div>
  )
}

AnimatedCollapse.propTypes = {
  expanded: PropTypes.bool.isRequired,
  children: PropTypes.node,
  className: PropTypes.string,
}
