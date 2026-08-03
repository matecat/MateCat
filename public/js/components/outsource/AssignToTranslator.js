import React from 'react'
import $ from 'jquery'
import Cookies from 'js-cookie'
import DatePicker from 'react-datepicker'
import {GMTSelect} from './GMTSelect'
import CommonUtils from '../../utils/commonUtils'
import {addJobTranslator} from '../../api/addJobTranslator'
import ModalsActions from '../../actions/ModalsActions'
import CatToolActions from '../../actions/CatToolActions'
import ManageActions from '../../actions/ManageActions'
import 'react-datepicker/dist/react-datepicker.css'
import {Select} from '../common/Select'

class AssignToTranslator extends React.Component {
  constructor(props) {
    super(props)
    let time = 12
    if (this.props.job.get('translator')) {
      let date = CommonUtils.getGMTDate(
        this.props.job.get('translator').get('delivery_timestamp') * 1000,
      )
      time = date.time.split(':')[0]
    }
    this.state = {
      timezone: Cookies.get('matecat_timezone'),
      deliveryDate: this.props.job.get('translator')
        ? new Date(
            this.props.job.get('translator').get('delivery_timestamp') * 1000,
          )
        : new Date(),
      time: parseInt(time),
    }
  }

  shareJob() {
    //Check email and validations errors

    let date = this.state.deliveryDate
    let time = this.state.time
    date.setHours(time)
    date.setMinutes(0)

    let email = this.email.value
    const job = this.props.job.toJS()
    const project = this.props.project.toJS()
    addJobTranslator(email, date, this.state.timezone, job)
      .then((data) => {
        ModalsActions.onCloseModal()
        if (data.job) {
          this.checkShareToTranslatorResponse(data, email, date, job, project)
        } else {
          this.showShareTranslatorError()
        }
      })
      .catch(() => {
        this.showShareTranslatorError()
      })
    this.props.closeOutsource()
  }

  checkShareToTranslatorResponse(response, mail, date, job, project) {
    let message = ''
    if (job.translator) {
      let newDate = new Date(date)
      let oldDate = new Date(job.translator.delivery_date)
      if (oldDate.getTime() !== newDate.getTime()) {
        message = this.shareToTranslatorDateChangeNotification(
          mail,
          oldDate,
          newDate,
        )
      } else if (job.translator.email !== mail) {
        message = this.shareToTranslatorMailChangeNotification(mail, job)
      } else {
        message = this.shareToTranslatorNotification(mail, job)
      }
    } else {
      message = this.shareToTranslatorNotification(mail, job)
    }
    const notification = {
      title: message.title,
      text: message.text,
      type: 'success',
      position: 'bl',
      timer: 10000,
    }
    CatToolActions.addNotification(notification)
    ManageActions.changeJobPasswordFromOutsource(
      project,
      job,
      job.password,
      response.job.password,
    )
    ManageActions.assignTranslator(
      project.id,
      job.id,
      job.password,
      response.job.translator,
    )
  }
  shareToTranslatorMailChangeNotification(mail, job) {
    return {
      title: (
        <>
          Job sent with{' '}
          <div
            className="green-label"
            style={{
              display: 'inline',
              backgroundColor: '#5ea400',
              color: 'white',
              padding: '2px 5px',
            }}
          >
            new password{' '}
          </div>
        </>
      ),
      text: (
        <div style={{marginTop: '16px'}}>
          To: <a href={`mailto:${mail}`}>{mail}</a>{' '}
          <div
            className="job-reference"
            style={{display: 'inline-block', width: '100%', marginTop: '10px'}}
          >
            {' '}
            <div
              style={{display: 'inline-block', fontSize: '14px', color: 'grey'}}
            >
              ({job.id})
            </div>{' '}
            <div
              className="source-target languages-tooltip"
              style={{display: 'inline-block', fontWeight: 700}}
            >
              {' '}
              <div className="source-box" style={{display: 'inherit'}}>
                {job.sourceTxt}
              </div>{' '}
              <div
                className="in-to"
                style={{top: '3px', display: 'inherit', position: 'relative'}}
              >
                {' '}
                <i className="icon-chevron-right icon"></i>{' '}
              </div>{' '}
              <div className="target-box" style={{display: 'inherit'}}>
                {job.targetTxt}
              </div>{' '}
            </div>{' '}
          </div>
        </div>
      ),
    }
  }
  shareToTranslatorNotification(mail, job) {
    return {
      title: 'Job sent',
      text: (
        <div style={{marginTop: '16px'}}>
          To: <a href={`mailto:${mail}`}>{mail}</a>{' '}
          <div
            className="job-reference"
            style={{display: 'inline-block', width: '100%', marginTop: '10px'}}
          >
            {' '}
            <div
              style={{display: 'inline-block', fontSize: '14px', color: 'grey'}}
            >
              {job.id}{' '}
            </div>{' '}
            <div
              className="source-target languages-tooltip"
              style={{display: 'inline-block', fontWeight: 700}}
            >
              {' '}
              <div className="source-box" style={{display: 'inherit'}}>
                {job.sourceTxt}
              </div>{' '}
              <div
                className="in-to"
                style={{top: '3px', display: 'inherit', position: 'relative'}}
              >
                {' '}
                <i className="icon-chevron-right icon"></i>{' '}
              </div>{' '}
              <div className="target-box" style={{display: 'inherit'}}>
                {job.targetTxt}
              </div>{' '}
            </div>{' '}
          </div>
        </div>
      ),
    }
  }
  shareToTranslatorDateChangeNotification(email, oldDate, newDate) {
    oldDate = CommonUtils.formatDate(oldDate, 'yyyy-MM-d hh:mm a')
    oldDate = CommonUtils.getGMTDate(oldDate)
    newDate = CommonUtils.formatDate(newDate, 'yyyy-MM-d hh:mm a')
    newDate = CommonUtils.getGMTDate(newDate)
    const dateBlock = (date, notUsed) => (
      <div
        className={`job-delivery${notUsed ? ' not-used' : ''}`}
        title="Delivery date"
        style={{
          display: 'inline-block',
          marginBottom: '10px',
          fontWeight: 700,
          ...(notUsed
            ? {textDecoration: 'line-through', position: 'relative'}
            : {marginRight: '10px'}),
        }}
      >
        {' '}
        <div
          className="outsource-day-text"
          style={{display: 'inline-block', marginRight: '3px'}}
        >
          {date.day}
        </div>{' '}
        <div
          className="outsource-month-text"
          style={{display: 'inline-block', marginRight: '5px'}}
        >
          {date.month}
        </div>{' '}
        <div className="outsource-time-text" style={{display: 'inline-block'}}>
          {date.time}
        </div>{' '}
        <div
          className="outsource-gmt-text"
          style={{display: 'inline-block', fontWeight: 100, color: 'grey'}}
        >
          ({date.gmt})
        </div>{' '}
        {notUsed && (
          <div
            className="old"
            style={{
              width: '100%',
              height: '1px',
              borderTop: '1px solid black',
              top: '-10px',
              position: 'relative',
            }}
          ></div>
        )}
      </div>
    )
    return {
      title: 'Job delivery update',
      text: (
        <div style={{marginTop: '16px'}}>
          <div
            className="job-reference"
            style={{display: 'inline-block', width: '100%'}}
          >
            {' '}
            To: {dateBlock(newDate, false)} {dateBlock(oldDate, true)}
            Translator: <a href={`mailto:${email}`}>{email}</a>{' '}
          </div>
        </div>
      ),
    }
  }
  showShareTranslatorError() {
    ModalsActions.onCloseModal()
    const notification = {
      title: 'Problems sending the job',
      text: (
        <>
          Please try later or contact{' '}
          <a href="mailto:support@matecat.com">support@matecat.com</a>
        </>
      ),
      type: 'error',
      position: 'bl',
      timer: 10000,
    }
    CatToolActions.addNotification(notification)
  }

  GmtSelectChanged(value) {
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

  render() {
    let translatorEmail = ''
    if (this.props.job.get('translator')) {
      translatorEmail = this.props.job.get('translator').get('email')
    }
    const timeOptions = [
      {name: '1:00 AM', id: '1'},
      {name: '2:00 AM', id: '2'},
      {name: '3:00 AM', id: '3'},
      {name: '4:00 AM', id: '4'},
      {name: '5:00 AM', id: '5'},
      {name: '6:00 AM', id: '6'},
      {name: '7:00 AM', id: '7'},
      {name: '8:00 AM', id: '8'},
      {name: '9:00 AM', id: '9'},
      {name: '10:00 AM', id: '10'},
      {name: '11:00 AM', id: '11'},
      {name: '12:00 AM', id: '12'},
      {name: '1:00 PM', id: '13'},
      {name: '2:00 PM', id: '14'},
      {name: '3:00 PM', id: '15'},
      {name: '4:00 PM', id: '16'},
      {name: '5:00 PM', id: '17'},
      {name: '6:00 PM', id: '18'},
      {name: '7:00 PM', id: '19'},
      {name: '8:00 PM', id: '20'},
      {name: '9:00 PM', id: '21'},
      {name: '10:00 PM', id: '22'},
      {name: '11:00 PM', id: '23'},
      {name: '12:00 PM', id: '24'},
    ]
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
                  <div className="ui calendar">
                    <div className="ui input">
                      <DatePicker
                        selected={this.state.deliveryDate}
                        onChange={(date) => {
                          this.setState({
                            deliveryDate: date,
                          })
                          this.checkSendToTranslatorButton()
                        }}
                      />
                    </div>
                  </div>
                </div>
                <div className="field translator-time">
                  <Select
                    label="Time"
                    onSelect={({id}) => {
                      this.setState({
                        time: parseInt(id),
                      })
                      this.checkSendToTranslatorButton()
                    }}
                    activeOption={timeOptions.find(
                      ({id}) => parseInt(id) === this.state.time,
                    )}
                    options={timeOptions}
                  />
                </div>
                <div className="field gmt">
                  <GMTSelect
                    changeValue={this.GmtSelectChanged.bind(this)}
                    showLabel={true}
                  />
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
