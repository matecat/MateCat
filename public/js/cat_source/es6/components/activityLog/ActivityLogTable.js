import React, {useContext} from 'react'
import {ColumnOrder} from './ColumnOrder'
import {ActivityLogContext} from '../../pages/ActivityLog'

export const ActivityLogTable = () => {
  const {activityLog} = useContext(ActivityLogContext)

  console.log(activityLog)

  return (
    <div className="activity-log-table">
      <div className="activity-log-table-columns-name">
        <ColumnOrder id="ip" label="User IP" />
        <ColumnOrder id="event_date" label="Event Date" />
        <ColumnOrder id="id_project" label="Project ID" />
        <ColumnOrder id="id_job" label="Job ID" />
        <ColumnOrder id="languagePair" label="Language Pair" />
        <ColumnOrder id="userName" label="User Name" />
        <ColumnOrder id="email" label="User Email" />
        <ColumnOrder id="action" label="Action" />
      </div>
      {activityLog.map((log) => (
        <div key={log.id} className="activity-log-table-columns-content">
          <span>{log.ip}</span>
          <span>{log.event_date}</span>
          <span>{log.id_project}</span>
          <span>{log.id_job}</span>
          <span>{log.languagePair}</span>
          <span>{log.userName}</span>
          <span>{log.email}</span>
          <span>{log.action}</span>
        </div>
      ))}
    </div>
  )
}
