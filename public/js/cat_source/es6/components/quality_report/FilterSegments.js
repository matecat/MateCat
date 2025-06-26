import React from 'react'

import InputField from '../common/InputField'
import {Select} from '../common/Select'

class FilterSegments extends React.Component {
  constructor(props) {
    super(props)

    this.state = this.defaultState()
    this.lqaNestedCategories = this.props.categories
    this.severities = this.getSeverities()
  }

  defaultState() {
    return {
      filter: {
        status: '',
        issue_category: null,
        severity: null,
        id_segment: this.props.segmentToFilter,
      },
    }
  }

  getSeverities() {
    let severities = []
    let severitiesNames = []
    this.lqaNestedCategories.forEach((cat) => {
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

  filterSelectChanged(type, value) {
    let filter = {...this.state.filter}
    filter[type] = value
    if (type === 'status' && value === 'APPROVED-2') {
      filter.revision_number = 2
      filter[type] = 'APPROVED2'
    } else if (type === 'status' && value === 'APPROVED') {
      filter.revision_number = 1
    } else {
      filter.revision_number = null
    }
    this.setState({
      filter: filter,
    })

    this.props.applyFilter(filter)
  }

  resetStatusFilter() {
    let filter = {...this.state.filter}
    filter.status = ''
    filter.revision_number = null
    this.setState({
      filter: filter,
    })
    setTimeout(() => {
      this.props.applyFilter(this.state.filter)
    })
  }
  resetCategoryFilter() {
    let filter = {...this.state.filter}
    filter.issue_category = null
    this.setState({
      filter: filter,
    })
    setTimeout(() => {
      this.props.applyFilter(this.state.filter)
    })
  }

  resetSeverityFilter() {
    let filter = {...this.state.filter}
    filter.severity = null
    this.setState({
      filter: filter,
    })
    setTimeout(() => {
      this.props.applyFilter(this.state.filter)
    })
  }

  filterIdSegmentChange(value) {
    if (value && value !== '') {
      this.filterSelectChanged('id_segment', value)
    } else {
      let filter = {...this.state.filter}
      filter.id_segment = null
      this.setState({
        filter: filter,
      })
      setTimeout(() => {
        this.props.applyFilter(this.state.filter)
      })
    }
    this.props.updateSegmentToFilter(value)
  }

  render() {
    let statusOptions = config.searchable_statuses.map((item) => {
      return {
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
      }
    })
    if (config.secondRevisionsCount) {
      statusOptions.push({
        name: (
          <>
            <div
              className={'ui ' + 'approved-2ndpass-color empty circular label'}
            />
            APPROVED
          </>
        ),
        id: 'APPROVED-2',
      })
    }
    let optionsCategory = this.lqaNestedCategories
      .map((item) => {
        return {
          name: item.get('label'),
          id: item.get('id'),
        }
      })
      .unshift({
        name: 'All',
        id: 1,
      })
    let optionsSeverities = this.severities.map((item) => {
      return {
        name: item.get('label'),
        id: item.get('label'),
      }
    })
    let statusFilterClass =
      this.state.filter.status && this.state.filter.status !== ''
        ? 'filtered'
        : 'not-filtered'
    let categoryFilterClass =
      this.state.filter.issue_category &&
      this.state.filter.issue_category !== ''
        ? 'filtered'
        : 'not-filtered'
    let severityFilterClass =
      this.state.filter.severity && this.state.filter.severity !== ''
        ? 'filtered'
        : 'not-filtered'
    return (
      <div className="qr-filter-list">
        Filters by
        <div className="filter-dropdown left-10">
          <div className={'filter-idSegment '}>
            <InputField
              placeholder="Id Segment"
              name="id_segment"
              onFieldChanged={this.filterIdSegmentChange.bind(this)}
              tabindex={0}
              showCancel={true}
              value={this.state.filter.id_segment}
            />
          </div>
          <div className={'filter-status ' + statusFilterClass}>
            <Select
              options={statusOptions}
              onSelect={(value) => {
                this.filterSelectChanged('status', value.id)
              }}
              activeOption={
                statusOptions.find(
                  (item) => item.id === this.state.filter.status,
                ) || undefined
              }
              placeholder={'Segment status'}
              checkSpaceToReverse={false}
              showResetButton={true}
              resetFunction={() => this.resetStatusFilter()}
            />
          </div>
          <div className={'filter-category ' + categoryFilterClass}>
            <Select
              options={optionsCategory}
              onSelect={(value) => {
                this.filterSelectChanged('issue_category', value.id)
              }}
              activeOption={
                optionsCategory.find(
                  (item) => item.id == this.state.filter.issue_category,
                ) || undefined
              }
              placeholder={'Issue category'}
              checkSpaceToReverse={false}
              showResetButton={true}
              resetFunction={() => this.resetCategoryFilter()}
            />
          </div>
          <div className={'filter-category ' + severityFilterClass}>
            <Select
              options={optionsSeverities}
              onSelect={(value) => {
                this.filterSelectChanged('severity', value.id)
              }}
              activeOption={
                optionsSeverities.find(
                  (item) => item.id == this.state.filter.severity,
                ) || undefined
              }
              placeholder={'Issue severity'}
              checkSpaceToReverse={false}
              showResetButton={true}
              resetFunction={() => this.resetSeverityFilter()}
            />
          </div>
        </div>
      </div>
    )
  }
}

export default FilterSegments
