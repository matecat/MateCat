import React from 'react'

import InputField from '../common/InputField'

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
    $(this.statusDropdown).dropdown('restore defaults')
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
    $(this.categoryDropdown).dropdown('restore defaults')
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
    $(this.severityDropdown).dropdown('restore defaults')
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

  initDropDown() {
    let self = this
    $(this.statusDropdown).dropdown({
      onChange: function (value) {
        if (value && value !== '') {
          self.filterSelectChanged('status', value)
        }
      },
    })
    $(this.categoryDropdown).dropdown({
      onChange: (value) => {
        if (value && value !== '') {
          self.filterSelectChanged('issue_category', value)
        }
      },
    })
    $(this.severityDropdown).dropdown({
      onChange: (value) => {
        if (value && value !== '') {
          self.filterSelectChanged('severity', value)
        }
      },
    })
    this.dropdownInitialized = true
  }

  componentDidMount() {
    setTimeout(this.initDropDown.bind(this), 100)
  }

  componentDidUpdate() {
    if (!this.dropdownInitialized) {
      this.initDropDown()
    }
  }

  render() {
    let optionsStatus = config.searchable_statuses.map((item, index) => {
      return (
        <React.Fragment key={index}>
          {item.value === 'APPROVED2' ? (
            <div className="item" key={index + '-2'} data-value={'APPROVED-2'}>
              <div
                className={
                  'ui ' + 'approved-2ndpass-color empty circular label'
                }
              />
              APPROVED
            </div>
          ) : (
            <div className="item" key={index} data-value={item.value}>
              <div
                className={
                  'ui ' +
                  item.label.toLowerCase() +
                  '-color empty circular label'
                }
              />
              {item.label}
            </div>
          )}
        </React.Fragment>
      )
    })
    let optionsCategory = this.lqaNestedCategories.map((item, index) => {
      return (
        <div className="item" key={index} data-value={item.get('id')}>
          {item.get('label')}
        </div>
      )
    })
    optionsCategory = optionsCategory.insert(
      0,
      <div className="item" key={'all'} data-value={'all'}>
        All
      </div>,
    )
    let optionsSeverities = this.severities.map((item, index) => {
      return (
        <div className="item" key={index} data-value={item.get('label')}>
          {item.get('label')}
        </div>
      )
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
            <div
              className="ui top left pointing dropdown basic tiny button right-0"
              ref={(dropdown) => (this.statusDropdown = dropdown)}
            >
              <div className="text">
                <div>Segment status</div>
              </div>
              <div className="ui cancel label">
                <i
                  className="icon-cancel3"
                  onClick={this.resetStatusFilter.bind(this)}
                />
              </div>
              <div className="menu">{optionsStatus}</div>
            </div>
          </div>
          <div className={'filter-category ' + categoryFilterClass}>
            <div
              className="ui top left pointing dropdown basic tiny button right-0"
              ref={(dropdown) => (this.categoryDropdown = dropdown)}
            >
              <div className="text">
                <div>Issue category</div>
              </div>
              <div className="ui cancel label">
                <i
                  className="icon-cancel3"
                  onClick={this.resetCategoryFilter.bind(this)}
                />
              </div>
              <div className="menu">{optionsCategory}</div>
            </div>
          </div>
          <div className={'filter-category ' + severityFilterClass}>
            <div
              className="ui top left pointing dropdown basic tiny button right-0"
              ref={(dropdown) => (this.severityDropdown = dropdown)}
            >
              <div className="text">
                <div>Issue severity</div>
              </div>
              <div className="ui cancel label">
                <i
                  className="icon-cancel3"
                  onClick={this.resetSeverityFilter.bind(this)}
                />
              </div>
              <div className="menu">{optionsSeverities}</div>
            </div>
          </div>
        </div>
      </div>
    )
  }
}

export default FilterSegments
