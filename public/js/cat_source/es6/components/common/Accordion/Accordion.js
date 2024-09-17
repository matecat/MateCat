import React, {useEffect, useRef} from 'react'

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
  const panelRef = useRef()

  const handleClick = () => {
    onShow(id)
  }

  useEffect(() => {
    if (expanded) {
      panelRef.current.style.maxHeight = `${panelRef.current.scrollHeight}px`
    } else {
      panelRef.current.style.maxHeight = 0
    }
  }, [expanded])

  return (
    <div className={`accordion-component ${className}`}>
      <div
        className={`accordion-component-title ${expanded ? 'accordion-expanded' : ''}`}
        onClick={handleClick}
      >
        {title} <ChevronDown />
      </div>
      <div ref={panelRef} className="accordion-component-content">
        {children}
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
