/* global config */
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

const getSeverities = (lqaNestedCategories) => {
  const severities = []
  const severitiesNames = []
  lqaNestedCategories.forEach((cat) => {
    if (cat.get('subcategories').size === 0) {
      cat.get('severities').forEach((sev) => {
        if (severitiesNames.indexOf(sev.get('label')) === -1) {
          severities.push(sev)
          severitiesNames.push(sev.get('label'))
        }
      })
    } else {
      cat.get('subcategories').forEach((subCat) => {
        subCat.get('severities').forEach((sev) => {
          if (severitiesNames.indexOf(sev.get('label')) === -1) {
            severities.push(sev)
            severitiesNames.push(sev.get('label'))
          }
        })
      })
    }
  })
  return severities
}

const FilterSegments = ({
  categories,
  segmentToFilter,
  applyFilter,
  updateSegmentToFilter,
  secondPassReviewEnabled,
}) => {
  const [state, setState] = useState(() => defaultState(segmentToFilter))
  const lqaNestedCategories = categories
  const severities = useMemo(
    () => getSeverities(lqaNestedCategories),
    [lqaNestedCategories],
  )

  const filterSelectChanged = useCallback(
    (type, value) => {
      const filter = {...state.filter}
      filter[type] = value
      if (type === 'status' && value === 'APPROVED-2') {
        filter.revision_number = 2
        filter[type] = 'APPROVED 2'
      } else if (type === 'status' && value === 'APPROVED') {
        filter.revision_number = 1
      } else {
        filter.revision_number = null
      }
      setState({filter})
      applyFilter(filter)
    },
    [state.filter, applyFilter],
  )

  const resetStatusFilter = useCallback(() => {
    const filter = {...state.filter, status: '', revision_number: null}
    setState({filter})
    setTimeout(() => {
      applyFilter(filter)
    })
  }, [state.filter, applyFilter])

  const resetCategoryFilter = useCallback(() => {
    const filter = {...state.filter, issue_category: null}
    setState({filter})
    setTimeout(() => {
      applyFilter(filter)
    })
  }, [state.filter, applyFilter])

  const resetSeverityFilter = useCallback(() => {
    const filter = {...state.filter, severity: null}
    setState({filter})
    setTimeout(() => {
      applyFilter(filter)
    })
  }, [state.filter, applyFilter])

  const filterIdSegmentChange = useCallback(
    (value) => {
      if (value && value !== '') {
        filterSelectChanged('id_segment', value)
      } else {
        const filter = {...state.filter, id_segment: null}
        setState({filter})
        setTimeout(() => {
          applyFilter(filter)
        })
      }
      updateSegmentToFilter(value)
    },
    [state.filter, filterSelectChanged, applyFilter, updateSegmentToFilter],
  )

  const statusOptions = useMemo(() => {
    return config.searchable_statuses
      .filter(({value}) => value !== 'REJECTED')
      .map((item) => ({
        name: (
          <>
            <div
              className={
                'ui ' + item.label.toLowerCase() + '-color empty circular label'
              }
            />
            {item.label}
          </>
        ),
        id: item.value,
      }))
  }, [])

  const optionsCategory = useMemo(() => {
    const arr = lqaNestedCategories.toJS().map((item) => ({
      name: item.label.replace(/\s*\(.*?\)\s*/g, ''),
      id: item.id,
    }))
    arr.unshift({name: 'Any', id: 'all'})
    return arr
  }, [lqaNestedCategories])

  const optionsSeverities = useMemo(() => {
    return severities.map((item) => ({
      name: item.get('label'),
      id: item.get('label'),
    }))
  }, [severities])

  const statusFilterClass =
    state.filter.status && state.filter.status !== ''
      ? 'filtered'
      : 'not-filtered'
  const categoryFilterClass =
    state.filter.issue_category && state.filter.issue_category !== ''
      ? 'filtered'
      : 'not-filtered'
  const severityFilterClass =
    state.filter.severity && state.filter.severity !== ''
      ? 'filtered'
      : 'not-filtered'

  return (
    <div className="qr-filter-list">
      <FilterIcon size={18} />
      <div className="filter-dropdown">
        <div className={'filter-idSegment '}>
          <InputField
            placeholder="Id Segment"
            name="id_segment"
            onFieldChanged={filterIdSegmentChange}
            tabindex={0}
            showCancel={true}
            value={state.filter.id_segment}
          />
        </div>
        <div className={'filter-status ' + statusFilterClass}>
          <Select
            options={statusOptions}
            onSelect={(value) => {
              filterSelectChanged('status', value.id)
            }}
            activeOption={
              statusOptions.find((item) => item.id === state.filter.status) ||
              undefined
            }
            placeholder={'Segment status'}
            checkSpaceToReverse={false}
            showResetButton={true}
            resetFunction={resetStatusFilter}
          />
        </div>
        {secondPassReviewEnabled && (
          <div className={'filter-status ' + statusFilterClass}>
            <Select
              options={[
                {
                  id: '1',
                  name: 'Revise 1',
                },
                ...(secondPassReviewEnabled
                  ? [
                      {
                        id: '2',
                        name: 'Revise 2',
                      },
                    ]
                  : []),
              ]}
              onSelect={(value) => {
                filterSelectChanged('issues_in_r', value.id)
              }}
              activeOption={
                state.filter.issues_in_r
                  ? {
                      id: state.filter.issues_in_r,
                      name:
                        state.filter.issues_in_r === 1
                          ? 'Revise 1'
                          : 'Revise 2',
                    }
                  : undefined
              }
              placeholder={'Revision phase'}
              checkSpaceToReverse={false}
              showResetButton={true}
              resetFunction={() => {
                const filter = {...state.filter, issues_in_r: null}
                setState({filter})
                setTimeout(() => {
                  applyFilter(filter)
                })
              }}
            />
          </div>
        )}
        <div className={'filter-category ' + categoryFilterClass}>
          <Select
            multipleSelect={'dropdown'}
            options={optionsCategory}
            onToggleOption={(value) => {
              const optionsIds = state.filter.issue_category || []
              if (optionsIds?.includes(value.id)) {
                optionsIds.splice(optionsIds.indexOf(value.id), 1)
              } else {
                optionsIds.push(value.id)
              }
              filterSelectChanged('issue_category', optionsIds)
            }}
            activeOptions={
              state.filter.issue_category?.length
                ? optionsCategory.filter(
                    (item) =>
                      state.filter.issue_category?.indexOf(item.id) > -1,
                  )
                : undefined
            }
            placeholder={'Issue category'}
            checkSpaceToReverse={false}
            showResetButton={true}
            resetFunction={resetCategoryFilter}
          />
        </div>
        <div className={'filter-category ' + severityFilterClass}>
          <Select
            options={optionsSeverities}
            onSelect={(value) => {
              filterSelectChanged('severity', value.id)
            }}
            activeOption={
              optionsSeverities.find(
                (item) => item.id == state.filter.severity,
              ) || undefined
            }
            placeholder={'Issue severity'}
            checkSpaceToReverse={false}
            showResetButton={true}
            resetFunction={resetSeverityFilter}
          />
        </div>
      </div>
    </div>
  )
}

export default FilterSegments
