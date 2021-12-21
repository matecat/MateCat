import React from 'react'
import Cookies from 'js-cookie'

import GMTSelect from './GMTSelect'
import CommonUtils from '../../utils/commonUtils'

class AssignToTranslator extends React.Component {
  constructor(props) {
    super(props)
    this.state = {
      timezone: Cookies.get('matecat_timezone'),
    }
  }

  shareJob() {
    //Check email and validations errors

    let date = $(this.dateInput).calendar('get date')
    let time = $(this.dropdownTime).dropdown('get value')
    date.setHours(time)
    // TODO : Change this line when the time change
    date.setMinutes(
      date.getMinutes() + (1 - parseFloat(this.state.timezone)) * 60,
    )

    let email = this.email.value

    UI.sendJobToTranslator(
      email,
      date,
      this.state.timezone,
      this.props.job.toJS(),
      this.props.project.toJS(),
    )
    this.props.closeOutsource()
  }

  GmtSelectChanged(value) {
    Cookies.get('matecat_timezone', value)
    this.checkSendToTranslatorButton()
    this.setState({
      timezone: value,
    })
  }

  checkSendToTranslatorButton() {
    if (
      this.email.value.length > 0 &&
      CommonUtils.checkEmail(this.email.value)
    ) {
      $(this.sendButton).removeClass('disabled')
      return true
    } else {
      $(this.sendButton).addClass('disabled')
    }
  }

  initDate() {
    let self = this
    let today = new Date()
    $(this.dateInput).calendar({
      type: 'date',
      minDate: new Date(today.getFullYear(), today.getMonth(), today.getDate()),
      className: {
        calendar: 'calendar-outsource',
      },
      onChange: function (date, text) {
        if (text === '') return false
        self.checkSendToTranslatorButton()
      },
    })
  }

  initTime() {
    let self = this
    let time = 12
    if (this.props.job.get('translator')) {
      let date = CommonUtils.getGMTDate(
        this.props.job.get('translator').get('delivery_timestamp') * 1000,
      )
      time = date.time.split(':')[0]
    }
    $(this.dropdownTime).dropdown({
      onChange: function () {
        self.checkSendToTranslatorButton()
      },
    })
    $(this.dropdownTime).dropdown('set selected', parseInt(time))
  }

  componentDidMount() {
    this.initDate()
    this.initTime()
  }

  componentDidUpdate() {
    this.initDate()
  }

  render() {
    let date = new Date()
    let translatorEmail = ''
    if (this.props.job.get('translator')) {
      date = new Date(
        this.props.job.get('translator').get('delivery_timestamp') * 1000,
      )
      translatorEmail = this.props.job.get('translator').get('email')
    }
    return (
      <div className="assign-job-translator sixteen wide column">
        <div className="title">Assign Job to translator</div>
        <div className="title-url">
          <div className="translator-assignee">
            <div className="ui form">
              <div className="fields">
                <div className="field translator-email">
                  <label>Translator email</label>
                  <input
                    type="email"
                    placeholder="translator@email.com"
                    defaultValue={translatorEmail}
                    ref={(email) => (this.email = email)}
                    onKeyUp={this.checkSendToTranslatorButton.bind(this)}
                  />
                </div>
                <div className="field translator-delivery ">
                  <label>Delivery date</label>
                  <div
                    className="ui calendar"
                    ref={(date) => (this.dateInput = date)}
                  >
                    <div className="ui input">
                      <input
                        type="text"
                        placeholder="Date"
                        value={date}
                        onChange={this.checkSendToTranslatorButton.bind(this)}
                      />
                    </div>
                  </div>
                </div>
                <div className="field translator-time">
                  <label>Time</label>
                  <select
                    className="ui dropdown"
                    ref={(dropdown) => (this.dropdownTime = dropdown)}
                  >
                    <option value="1">1:00 AM</option>
                    <option value="2">2:00 AM</option>
                    <option value="3">3:00 AM</option>
                    <option value="4">4:00 AM</option>
                    <option value="5">5:00 AM</option>
                    <option value="6">6:00 AM</option>
                    <option value="7">7:00 AM</option>
                    <option value="8">8:00 AM</option>
                    <option value="9">9:00 AM</option>
                    <option value="10">10:00 AM</option>
                    <option value="11">11:00 AM</option>
                    <option value="12">12:00 AM</option>
                    <option value="13">1:00 PM</option>
                    <option value="14">2:00 PM</option>
                    <option value="15">3:00 PM</option>
                    <option value="16">4:00 PM</option>
                    <option value="17">5:00 PM</option>
                    <option value="18">6:00 PM</option>
                    <option value="19">7:00 PM</option>
                    <option value="20">8:00 PM</option>
                    <option value="21">9:00 PM</option>
                    <option value="22">10:00 PM</option>
                    <option value="23">11:00 PM</option>
                    <option value="24">12:00 PM</option>
                  </select>
                </div>
                <div className="field gmt">
                  <GMTSelect changeValue={this.GmtSelectChanged.bind(this)} />
                </div>
                <div className="field send-job-box">
                  <button
                    className="send-job ui primary button disabled"
                    onClick={this.shareJob.bind(this)}
                    ref={(send) => (this.sendButton = send)}
                  >
                    Send Job to Translator
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    )
  }
}

export default AssignToTranslator
