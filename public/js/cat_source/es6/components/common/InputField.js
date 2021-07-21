import React from 'react'
import _ from 'lodash'

import PropTypes from 'prop-types'

const styleInput = {
  fontFamily: 'calibri, Arial, Helvetica, sans-serif',
  fontSize: '16px',
  padding: '10px 20px',
  borderRadius: '40px',
  outlineColor: '#e5e9f1',
  border: 'unset',
  boxShadow: '0px 0px 0px 1px rgba(34, 36, 38, 0.25) inset',
  display: 'flex',
  maxWidth: '150px',
}
const styleContainer = {
  position: 'relative',
  marginRight: '5px',
}

const styleIcon = {
  visibility: 'visible',
  right: '7px',
  cursor: 'pointer',
}
export default class InputField extends React.Component {
  constructor(props) {
    super(props)
    this.handleChange = this.handleChange.bind(this)
    this.resetInput = this.resetInput.bind(this)
    this.state = {
      value: this.props.value ? this.props.value : '',
    }
    this.debouncedOnChange = _.debounce(() => {
      this.props.onFieldChanged(this.state.value)
    }, 500)
  }

  handleChange(event) {
    this.setState({value: event.target.value})
    this.debouncedOnChange()
  }

  resetInput() {
    this.setState({value: ''})
    this.props.onFieldChanged('')
  }

  componentDidMount() {
    if (this.props.text) {
      var event = new Event('input', {bubbles: true})
      this.input.dispatchEvent(event)
    }
  }

  render() {
    var type = 'text'

    if (this.props.type) {
      type = this.props.type
    }

    return (
      <div className={'qr-filter-idSegment'} style={styleContainer}>
        <input
          data-testid="input"
          style={styleInput}
          type={type}
          placeholder={this.props.placeholder}
          value={this.state.value}
          name={this.props.name}
          onChange={this.handleChange}
          className={this.props.classes}
          tabIndex={this.props.tabindex}
          onKeyPress={this.props.onKeyPress}
          ref={(input) => (this.input = input)}
        />
        {this.props.showCancel && this.state.value.length > 0 ? (
          <div
            data-testid="reset-button"
            className="ui cancel label"
            style={styleIcon}
            onClick={this.resetInput}
          >
            <i className="icon-cancel3" />
          </div>
        ) : null}
      </div>
    )
  }
}

InputField.propTypes = {
  onFieldChanged: PropTypes.func.isRequired,
}
