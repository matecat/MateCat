import React, {useContext, useEffect, useRef} from 'react'
import PropTypes from 'prop-types'
import {ActivityLogContext} from '../../pages/ActivityLog'
import ArrowDown from '../../../../../img/icons/ArrowDown'

export const ColumnOrder = ({id, label}) => {
  const {activityLogWithoutOrdering, setActivityLog} =
    useContext(ActivityLogContext)

  const orderDirection = useRef()

  const setOrderDirection = () => {
    const prevValue = orderDirection.current
    orderDirection.current = !prevValue
      ? 'asc'
      : prevValue === 'asc'
        ? 'desc'
        : prevValue === 'desc' && undefined

    setActivityLog(() => {
      if (!orderDirection) return activityLogWithoutOrdering.current

      const newState = [...activityLogWithoutOrdering.current]
      newState.sort((a, b) => {
        if (orderDirection.current === 'asc' && a[id] > b[id]) return -1
        if (orderDirection.current === 'desc' && a[id] < b[id]) return 1
      })
      return newState
    })
  }

  useEffect(() => {}, [orderDirection])

  return (
    <div
      className={`activity-table-column-order${orderDirection.current === 'desc' ? ' activity-table-column-order-desc' : ''}`}
      onClick={setOrderDirection}
    >
      {label}
      {typeof orderDirection.current === 'string' && <ArrowDown />}
    </div>
  )
}

ColumnOrder.propTypes = {
  id: PropTypes.string.isRequired,
  label: PropTypes.string.isRequired,
}
