import React, {useContext, useState} from 'react'
import {ColumnSorting} from './ColumnSorting'
import {ActivityLogContext} from './ActivityLogContext'
import LabelWithTooltip from '../common/LabelWithTooltip'

import {ACTIVITY_LOG_COLUMNS} from './ActivityLogConstants'

export const ActivityLogTable = () => {
  const {activityLog, filterByColumn} = useContext(ActivityLogContext)

  const [currentSortingColumnId, setCurrentSortingColumnId] = useState()

  const onSorting = (id) => setCurrentSortingColumnId(id)

  const activityLogFiltered = activityLog
    .map((log) => {
      const d = new Date(log.event_date)

      return {
        ...log,
        event_date: `${d.toDateString()} ${d.toLocaleTimeString()}`,
      }
    })
    .filter((log) => {
      const regex = new RegExp(filterByColumn.query, 'gmi')
      return regex.test(log[filterByColumn.id])
    })

  return (
    <div className="activity-log-table">
      <div className="activity-log-table-columns-name">
        {ACTIVITY_LOG_COLUMNS.map((props) => (
          <ColumnSorting
            key={props.id}
            {...{...props, currentSortingColumnId, onSorting}}
          />
        ))}
      </div>
      {activityLogFiltered.length === 0 && (
        <h2 className="activity-table-column-empty-state">No records</h2>
      )}
      {activityLogFiltered.map((log) => (
        <div key={log.id} className="activity-log-table-columns-content">
          {ACTIVITY_LOG_COLUMNS.map(({id}) => (
            <LabelWithTooltip key={id}>
              <span>{log[id]}</span>
            </LabelWithTooltip>
          ))}
        </div>
      ))}
    </div>
  )
}
