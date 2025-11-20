import React, {useContext, useRef} from 'react'
import PropTypes from 'prop-types'
import {ActivityLogContext} from '../../pages/ActivityLog'
import IconDown from '../icons/IconDown'

export const ColumnSorting = ({
  id,
  label,
  onSorting,
  currentSortingColumnId,
  sortingType,
}) => {
  const {activityLogWithoutOrdering, setActivityLog} =
    useContext(ActivityLogContext)

  const orderDirection = useRef()

  const getSortingValue = (value) =>
    sortingType === 'date' ? new Date(value).getTime() : value

  const setOrderDirection = () => {
    const prevValue = orderDirection.current
    orderDirection.current = !prevValue
      ? 'desc'
      : prevValue === 'desc'
        ? 'asc'
        : prevValue === 'asc' && undefined

    setActivityLog(() => {
      if (!orderDirection.current) return activityLogWithoutOrdering.current

      const newState = [...activityLogWithoutOrdering.current]

      newState.sort((a, b) => {
        const compareA = getSortingValue(a[id])
        const compareB = getSortingValue(b[id])

        if (orderDirection.current === 'desc' && compareA > compareB) return -1
        if (orderDirection.current === 'asc' && compareA < compareB) return -1
      })
      return newState
    })

    onSorting(id)
  }

  if (currentSortingColumnId !== id) orderDirection.current = undefined

  return (
    <div
      className={`activity-table-column-order${orderDirection.current === 'asc' ? ' activity-table-column-order-asc' : ''}`}
      onClick={setOrderDirection}
    >
      {label}
      {typeof orderDirection.current === 'string' && <IconDown size={20} />}
    </div>
  )
}

ColumnSorting.propTypes = {
  id: PropTypes.string.isRequired,
  label: PropTypes.string.isRequired,
  onSorting: PropTypes.func.isRequired,
  currentSortingColumnId: PropTypes.string,
  sortingType: PropTypes.string,
}
