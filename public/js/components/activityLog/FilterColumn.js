import React, {useContext} from 'react'
import {Select} from '../common/Select'
import {ACTIVITY_LOG_COLUMNS} from './ActivityLogTable'
import {ActivityLogContext} from '../../pages/ActivityLog'

export const FilterColumn = () => {
  const {filterByColumn, setFilterByColumn} = useContext(ActivityLogContext)

  const activeColumn = {...filterByColumn, name: filterByColumn.label}
  const columns = ACTIVITY_LOG_COLUMNS.map(({id, label}) => ({id, name: label}))

  const onSelect = (option) =>
    option &&
    setFilterByColumn((prevState) => ({
      ...prevState,
      id: option.id,
      label: option.name,
    }))

  const onChangeQuery = ({currentTarget: {value}}) =>
    setFilterByColumn((prevState) => ({...prevState, query: value}))

  return (
    <div className="activity-log-filter-column">
      <h2>Project Related Activities:</h2>
      <div className="activity-log-filter-column-container">
        <Select
          name="filterColumn"
          label="Filter by column"
          placeholder="Filter column"
          options={columns}
          activeOption={activeColumn}
          checkSpaceToReverse={false}
          onSelect={onSelect}
        />
        <input
          className="activity-log-filter-column-input"
          name="filterQuery"
          type="text"
          placeholder={`Filter by ${filterByColumn.label}`}
          value={filterByColumn.query}
          onChange={onChangeQuery}
        />
      </div>
    </div>
  )
}
