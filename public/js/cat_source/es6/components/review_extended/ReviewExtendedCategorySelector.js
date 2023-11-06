import React from 'react'
import classnames from 'classnames'
class ReviewExtendedCategorySelector extends React.Component {
  constructor(props) {
    super(props)
  }
  componentDidMount() {
    $(this.selectRef).dropdown({
      // direction: "auto",
      keepOnScreen: true,
      context: document.getElementById('re-category-list-' + this.props.sid),
    })
  }
  onChangeSelect() {
    let severity = $(this.selectRef).data('value')
    if (severity) {
      this.props.sendIssue(this.props.category, severity)
      $(this.selectRef).dropdown('clear')
    }
  }
  onClick(severity) {
    if (severity) {
      this.props.sendIssue(this.props.category, severity)
    }
  }
  render() {
    // It may happen for a category to come with no severities. In this case
    // the category should be considered to be a header for the nested
    // subcategories. Don't print the select box if no severity is found.
    let select = null
    let severities
    let classCatName =
      this.props.category.options && this.props.category.options.code
        ? this.props.category.options.code
        : ''
    let containerClasses = classnames({
      're-item': true,
      're-category-item': true,
      'severity-buttons': this.props.category.severities.length > 0,
      active: this.props.active,
      classCatName: true,
    })
    if (this.props.category.severities.length > 7) {
      severities = this.props.category.severities.map((severity, i) => {
        return (
          <div
            onClick={this.onChangeSelect.bind(this)}
            className="item"
            key={'value-' + severity.label}
            data-value={severity.label}
          >
            <b>{severity.label}</b>
          </div>
        )
      })

      select = (
        <div
          className="ui icon top right pointing dropdown basic tiny button"
          ref={(input) => {
            this.selectRef = input
          }}
          data-value={this.props.selectedValue}
          autoFocus={this.props.focus}
          name="severities"
          title="Select severities"
        >
          <i className="icon-sort-down icon" />
          <div className="menu">{severities}</div>
        </div>
      )
    } else {
      severities = this.props.category.severities.map((severity, i) => {
        let buttonClass = classnames({
          ui: true,
          attached: true,
          button: true,
          left: i === 0 && this.props.category.severities.length > 1,
          right:
            i === this.props.category.severities.length - 1 ||
            this.props.category.severities.length === 1,
          active: this.props.active && i === this.props.severityActiveIndex,
        })
        let label =
          this.props.category.severities.length === 1
            ? severity.label
            : severity.label.substring(0, 3)
        const sevName = severity.code ? severity.code : label
        return (
          <button
            key={'value-' + severity.label}
            onClick={this.onClick.bind(this, severity.label)}
            className={'ui ' + buttonClass + ' attached button'}
            title={severity.label}
          >
            {sevName}
          </button>
        )
      })

      select = (
        <div
          className="re-severities-buttons ui tiny buttons"
          ref={(input) => {
            this.selectRef = input
          }}
          title="Select severities"
        >
          {severities}
        </div>
      )
    }
    return (
      <div className={containerClasses}>
        <div className="re-item-box re-error">
          <div className="error-name">
            {/*{this.props.category.options && this.props.category.options.code ? (*/}
            {/*<div className="re-abb-issue">{this.props.category.options.code}</div>*/}
            {/*) : (null)}*/}
            {this.props.category.label}
          </div>
          <div className="error-level">{select}</div>
        </div>
      </div>
    )
  }
}

export default ReviewExtendedCategorySelector
