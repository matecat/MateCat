import React from 'react'

import DQFCredentials from './DQFCredentials'
import ModalsActions from '../../actions/ModalsActions'

class DQFModal extends React.Component {
  constructor(props) {
    super(props)

    this.state = {
      dqfOptions: this.props.metadata.dqf_options,
      dqfCredentials: {
        dqfUsername: this.props.metadata.dqf_username,
        dqfPassword: this.props.metadata.dqf_password,
      },
    }
  }

  saveDQFOptions() {
    let dqf_options = {}
    let errors = false

    if (this.contentType.value === '') {
      errors = true
      this.contentType.classList.add('error')
    } else {
      this.contentType.classList.remove('error')
    }

    if (this.industry.value === '') {
      errors = true
      this.industry.classList.add('error')
    } else {
      this.industry.classList.remove('error')
    }

    if (this.process.value === '') {
      errors = true
      this.process.classList.add('error')
    } else {
      this.process.classList.remove('error')
    }

    if (this.qualityLevel.value === '') {
      errors = true
      this.qualityLevel.classList.add('error')
    } else {
      this.qualityLevel.classList.remove('error')
    }

    if (!errors) {
      this.saveButton.classList.add('disabled')
      dqf_options.contentType = this.contentType.value
      dqf_options.industry = this.industry.value
      dqf_options.process = this.process.value
      dqf_options.qualityLevel = this.qualityLevel.value
      $('.dqf-box #dqf_switch').trigger('dqfEnable')
      APP.USER.STORE.metadata.dqf_options = dqf_options
      ModalsActions.onCloseModal()
    }
  }

  resetOptions() {
    this.saveButton.classList.remove('disabled')
    this.contentType.classList.remove('error')
    this.process.classList.remove('error')
    this.industry.classList.remove('error')
    this.qualityLevel.classList.remove('error')
  }

  getOptions() {
    let validUser = !!(
      this.state.dqfValid || this.state.dqfCredentials.dqfUsername
    )
    let containerStyle =
      validUser && !config.dqf_active_on_project
        ? {}
        : {opacity: 0.5, pointerEvents: 'none'}
    let contentTypeOptions = config.dqf_content_types.map(function (item) {
      return (
        <option key={item.id} value={item.id}>
          {item.name}
        </option>
      )
    })
    let industryOptions = config.dqf_industry.map(function (item) {
      return (
        <option key={item.id} value={item.id}>
          {item.name}
        </option>
      )
    })
    let processOptions = config.dqf_process.map(function (item) {
      return (
        <option key={item.id} value={item.id}>
          {item.name}
        </option>
      )
    })
    let qualityOptions = config.dqf_quality_level.map(function (item) {
      return (
        <option key={item.id} value={item.id}>
          {item.name}
        </option>
      )
    })
    return (
      <div className="dqf-options-container" style={containerStyle}>
        <h2>DQF Options</h2>
        <div className="dqf-option">
          <h4>Content Type</h4>
          <select
            name="contentType"
            id="contentType"
            ref={(select) => (this.contentType = select)}
            onChange={this.resetOptions.bind(this)}
          >
            <option value="">Choose</option>
            {contentTypeOptions}
          </select>
        </div>
        <div className="dqf-option">
          <h4>Industry</h4>
          <select
            name="industry"
            id="industry"
            ref={(select) => (this.industry = select)}
            onChange={this.resetOptions.bind(this)}
          >
            <option value="">Choose</option>
            {industryOptions}
          </select>
        </div>
        <div className="dqf-option">
          <h4>Process</h4>
          <select
            name="process"
            id="process"
            ref={(select) => (this.process = select)}
            onChange={this.resetOptions.bind(this)}
          >
            <option value="">Choose</option>
            {processOptions}
          </select>
        </div>
        <div className="dqf-option">
          <h4>Quality level</h4>
          <select
            name="qualityLevel"
            id="qualityLevel"
            ref={(select) => (this.qualityLevel = select)}
            onChange={this.resetOptions.bind(this)}
          >
            <option value="">Choose</option>
            {qualityOptions}
          </select>
        </div>
        {!config.is_cattool ? (
          <div
            className="ui primary button"
            style={{margin: '0 auto', marginTop: '16px'}}
            onClick={this.saveDQFOptions.bind(this)}
            ref={(button) => (this.saveButton = button)}
          >
            Save
          </div>
        ) : (
          ''
        )}
      </div>
    )
  }

  getDqfHtml() {
    return (
      <div className="dqf-container">
        <h2>DQF Credentials</h2>
        <DQFCredentials metadata={this.props.metadata} />
        {this.getOptions()}
      </div>
    )
  }

  componentDidMount() {
    if (this.state.dqfOptions) {
      this.contentType.value = this.state.dqfOptions.contentType
      this.industry.value = this.state.dqfOptions.industry
      this.process.value = this.state.dqfOptions.process
      this.qualityLevel.value = this.state.dqfOptions.qualityLevel
      this.saveButton.classList.add('disabled')
    } else if (config.dqf_active_on_project) {
      this.contentType.value = config.dqf_selected_content_types
      this.industry.value = config.dqf_selected_industry
      this.process.value = config.dqf_selected_process
      this.qualityLevel.value = config.dqf_selected_quality_level
    }
  }

  render() {
    return (
      <div className="dqf-modal">
        <div className="user-info-attributes">{this.getDqfHtml()}</div>
      </div>
    )
  }
}

export default DQFModal
