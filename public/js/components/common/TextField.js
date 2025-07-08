import PropTypes from 'prop-types'
import React from 'react'

export default class TextField extends React.Component {
  constructor(props) {
    super(props)
    this.shouldDisplayError = this.shouldDisplayError.bind(this)
    this.spanStyle = {
      color: 'red',
      fontSize: '14px',
    }
  }

  shouldDisplayError() {
    return this.props.showError && this.props.errorText != ''
  }

  componentDidMount() {
    if (this.props.text) {
      var event = new Event('input', {bubbles: true})
      this.input.dispatchEvent(event)

      if (this.props.onFieldChanged)
        this.props.onFieldChanged({target: {value: this.props.text}})
    }
  }

  render() {
    var errorHtml = ''
    var type = 'text'

    if (this.props.type) {
      type = this.props.type
    }

    if (this.shouldDisplayError()) {
      errorHtml = (
        <div className="validation-error">
          <div style={this.spanStyle} className="text">
            {this.props.errorText}
          </div>
        </div>
      )
    }

    return (
      <div
        style={{
          position: 'relative',
          marginBottom: '17px',
        }}
      >
        <input
          type={type}
          placeholder={this.props.placeholder}
          defaultValue={this.props.text}
          name={this.props.name}
          onChange={this.props.onFieldChanged}
          className={this.props.classes}
          tabIndex={this.props.tabindex}
          onKeyPress={this.props.onKeyPress}
          ref={(input) => (this.input = input)}
        />
        {errorHtml}
      </div>
    )
  }
}

TextField.propTypes = {
  showError: PropTypes.bool.isRequired,
  onFieldChanged: PropTypes.func.isRequired,
}
