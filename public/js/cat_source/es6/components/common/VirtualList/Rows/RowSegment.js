import React, {useContext, useEffect, useRef} from 'react'
import PropTypes from 'prop-types'
import {ListContext} from '../App'
import useResizeObserver from '../hooks/useResizeObserver'
import Segment from '../../../segments/Segment'

function RowSegment({id, defaultHeight, ...restProps}) {
  const {updateHeight} = useContext(ListContext)
  const ref = useRef()
  const {height: newHeight} = useResizeObserver(ref, {defaultHeight})

  useEffect(() => {
    updateHeight(id, newHeight)
  }, [id, newHeight, defaultHeight, updateHeight])

  return (
    <div ref={ref} className="row">
      <Segment {...{id, ...restProps}} />
    </div>
  )
}

RowSegment.propTypes = {
  id: PropTypes.number.isRequired,
  defaultHeight: PropTypes.number.isRequired,
  children: PropTypes.node,
}

export default RowSegment
