import React, {useState, useMemo, useCallback} from 'react'

import InputField from '../common/InputField'
import {Select} from '../common/Select'
import FilterIcon from '../../../img/icons/FilterIcon'

const defaultState = (segmentToFilter) => ({
  filter: {
    status: '',
    issue_category: null,
    severity: null,
    id_segment: segmentToFilter,
  },
})

const getSeverities = (categories) => {
  const seen = new Set()
  const severities = []

  const collectSeverity = (sev) => {
    const label = sev.get('label')
    if (!seen.has(label)) {
      seen.add(label)
      severities.push(sev)
    }
  }

  categories.forEach((cat) => {
    const subcategories = cat.get('subcategories')
    if (subcategories.size === 0) {
      cat.get('severities').forEach(collectSeverity)
    } else {
      subcategories.forEach((subCat) => {
        subCat.get('severities').forEach(collectSeverity)
      })
    }
  })

  return severities
}

const getFilterClassName = (value) =>
  (Array.isArray(value) ? value.length > 0 : value && value !== '')
    ? 'filtered'
    : 'not-filtered'

// Add the id when missing, remove it when present; an empty selection becomes null (no filter)
const toggleInList = (list, id) => {
  const ids = list ? [...list] : []
  const index = ids.indexOf(id)
  if (index > -1) {
    ids.splice(index, 1)
  } else {
    ids.push(id)
  }
  return ids.length ? ids : null
}

const REVISION_OPTIONS = [
  {id: '1', name: 'Revise 1'},
  {id: '2', name: 'Revise 2'},
]

const FilterSegments = ({
  categories,
  segmentToFilter,
  applyFilter,
  updateSegmentToFilter,
  secondPassReviewEnabled,
}) => {
  const [state, setState] = useState(() => defaultState(segmentToFilter))

  const severities = useMemo(() => getSeverities(categories), [categories])

  const updateFilter = useCallback(
    (updates) => {
      const filter = {...state.filter, ...updates}
      setState({filter})
      setTimeout(() => applyFilter(filter))
    },
    [state.filter, applyFilter],
  )

  const filterSelectChanged = useCallback(
    (type, value) => {
      const updates = {[type]: value, revision_number: null}

      if (type === 'status') {
        if (value === 'APPROVED-2') {
          updates.revision_number = 2
          updates[type] = 'APPROVED 2'
        } else if (value === 'APPROVED') {
          updates.revision_number = 1
        }
      }

      const filter = {...state.filter, ...updates}
      setState({filter})
      applyFilter(filter)
    },
    [state.filter, applyFilter],
  )

  const filterIdSegmentChange = useCallback(
    (value) => {
      if (value && value !== '') {
        filterSelectChanged('id_segment', value)
      } else {
        updateFilter({id_segment: null})
      }
      updateSegmentToFilter(value)
    },
    [filterSelectChanged, updateFilter, updateSegmentToFilter],
  )

  const statusOptions = useMemo(
    () =>
      config.searchable_statuses
        .filter(({value}) => value !== 'REJECTED')
        .map((item) => ({
          name: (
            <>
              <span className="status-dropdown-option">
                <span
                  className={`status-dot ${item.label.toLowerCase()}-color`}
                />
                {item.label}
              </span>
            </>
          ),
          id: item.value,
        })),
    [],
  )

  const optionsCategory = useMemo(() => {
    const arr = categories.toJS().map((item) => ({
      name: item.label.replace(/\s*\(.*?\)\s*/g, ''),
      id: String(item.id),
    }))
    arr.unshift({name: 'Any', id: 'all'})
    return arr
  }, [categories])

  const optionsSeverities = useMemo(
    () =>
      severities.map((item) => ({
        name: item.get('label'),
        id: item.get('label'),
      })),
    [severities],
  )

  const {filter} = state

  return (
    <div className="qr-filter-list">
      <FilterIcon size={18} />
      <div className="filter-dropdown">
        <div className="filter-idSegment ">
          <InputField
            placeholder="ID segment"
            name="id_segment"
            onFieldChanged={filterIdSegmentChange}
            tabindex={0}
            showCancel={true}
            value={filter.id_segment}
          />
        </div>
        <div className={`filter-status ${getFilterClassName(filter.status)}`}>
          <Select
            options={statusOptions}
            onSelect={(value) => filterSelectChanged('status', value.id)}
            activeOption={
              statusOptions.find((item) => item.id === filter.status) ||
              undefined
            }
            placeholder="Segment status"
            showResetButton={true}
            resetFunction={() =>
              updateFilter({status: '', revision_number: null})
            }
          />
        </div>
        {secondPassReviewEnabled && (
          <div
            className={`filter-status ${getFilterClassName(filter.issues_in_r)}`}
          >
            <Select
              options={REVISION_OPTIONS}
              onSelect={(value) => filterSelectChanged('issues_in_r', value.id)}
              // The stored id may come back as a number or a string
              activeOption={REVISION_OPTIONS.find(
                (option) => option.id === String(filter.issues_in_r),
              )}
              placeholder="Revision phase"
              showResetButton={true}
              resetFunction={() => updateFilter({issues_in_r: null})}
            />
          </div>
        )}
        <div
          className={`filter-category ${getFilterClassName(filter.issue_category)}`}
        >
          <Select
            multipleSelect="dropdown"
            options={optionsCategory}
            onToggleOption={(value) =>
              filterSelectChanged(
                'issue_category',
                toggleInList(filter.issue_category, value.id),
              )
            }
            activeOptions={
              filter.issue_category?.length
                ? optionsCategory.filter(
                    (item) => filter.issue_category?.indexOf(item.id) > -1,
                  )
                : undefined
            }
            placeholder="Issue category"
            showResetButton={true}
            resetFunction={() => updateFilter({issue_category: null})}
          />
        </div>
        <div
          className={`filter-severity ${getFilterClassName(filter.severity)}`}
        >
          <Select
            multipleSelect="dropdown"
            options={optionsSeverities}
            onToggleOption={(value) =>
              filterSelectChanged(
                'severity',
                toggleInList(filter.severity, value.id),
              )
            }
            activeOptions={
              filter.severity?.length
                ? optionsSeverities.filter((item) =>
                    filter.severity.includes(item.id),
                  )
                : undefined
            }
            placeholder="Issue severity"
            showResetButton={true}
            resetFunction={() => updateFilter({severity: null})}
          />
        </div>
      </div>
    </div>
  )
}

export default FilterSegments
