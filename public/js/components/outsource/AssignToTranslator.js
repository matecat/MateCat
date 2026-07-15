import React, {useCallback, useRef, useState} from 'react'
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
import {Button, BUTTON_TYPE} from '../common/Button/Button'
import {timeOptions} from './outsourceConstants'
const AssignToTranslator = ({job, project, closeOutsource}) => {
  const getInitialTime = () => {
    if (job.get('translator')) {
      const date = CommonUtils.getGMTDate(
        job.get('translator').get('delivery_timestamp') * 1000,
      )
      return date.time.split(':')[0]
    }
    return '12'
  }

  const [timezone, setTimezone] = useState(Cookies.get('matecat_timezone'))
  const [deliveryDate, setDeliveryDate] = useState(
    job.get('translator')
      ? new Date(job.get('translator').get('delivery_timestamp') * 1000)
      : new Date(),
  )
  const [time, setTime] = useState(getInitialTime())
  const [isSendDisabled, setIsSendDisabled] = useState(true)

  const emailRef = useRef(null)

  const checkSendToTranslatorButton = useCallback(() => {
    if (
      emailRef.current?.value.length > 0 &&
      CommonUtils.checkEmail(emailRef.current.value)
    ) {
      setIsSendDisabled(false)
      return true
    } else {
      setIsSendDisabled(true)
      return false
    }
  }, [])

  const jobReference = (jobData) => (
    <div
      className="job-reference"
      style={{display: 'inline-block', width: '100%', marginTop: 10}}
    >
      <div style={{display: 'inline-block', fontSize: 14, color: 'grey'}}>
        ({jobData.id})
      </div>{' '}
      <div
        className="source-target languages-tooltip"
        style={{display: 'inline-block', fontWeight: 700}}
      >
        <div className="source-box" style={{display: 'inherit'}}>
          {jobData.sourceTxt}
        </div>{' '}
        <div
          className="in-to"
          style={{top: 3, display: 'inherit', position: 'relative'}}
        >
          {' '}
          <svg
            width="16"
            height="16"
            viewBox="0 0 24 24"
            style={{verticalAlign: 'middle'}}
          >
            <path
              fill="currentColor"
              d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"
            />
          </svg>{' '}
        </div>{' '}
        <div className="target-box" style={{display: 'inherit'}}>
          {jobData.targetTxt}
        </div>
      </div>
    </div>
  )

  const shareToTranslatorMailChangeNotification = (mail, jobData) => ({
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
      <div style={{marginTop: 16}}>
        To: <a href={`mailto:${mail}`}>{mail}</a> {jobReference(jobData)}
      </div>
    ),
  })

  const shareToTranslatorNotification = (mail, jobData) => ({
    title: 'Job sent',
    text: (
      <div style={{marginTop: 16}}>
        To: <a href={`mailto:${mail}`}>{mail}</a> {jobReference(jobData)}
      </div>
    ),
  })

  const shareToTranslatorDateChangeNotification = (email, oldDate, newDate) => {
    oldDate = CommonUtils.formatDate(oldDate, 'yyyy-MM-d hh:mm a')
    oldDate = CommonUtils.getGMTDate(oldDate)
    newDate = CommonUtils.formatDate(newDate, 'yyyy-MM-d hh:mm a')
    newDate = CommonUtils.getGMTDate(newDate)
    const dateBlock = (date, notUsed) => (
      <div
        className={notUsed ? 'job-delivery not-used' : 'job-delivery'}
        title="Delivery date"
        style={{
          display: 'inline-block',
          marginBottom: 10,
          fontWeight: 700,
          marginRight: notUsed ? undefined : 10,
          textDecoration: notUsed ? 'line-through' : undefined,
          position: notUsed ? 'relative' : undefined,
        }}
      >
        <div
          className="outsource-day-text"
          style={{display: 'inline-block', marginRight: 3}}
        >
          {date.day}
        </div>{' '}
        <div
          className="outsource-month-text"
          style={{display: 'inline-block', marginRight: 5}}
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
              height: 1,
              borderTop: '1px solid black',
              top: -10,
              position: 'relative',
            }}
          ></div>
        )}
      </div>
    )

    return {
      title: 'Job delivery update',
      text: (
        <div style={{marginTop: 16}}>
          <div style={{display: 'inline-block', width: '100%'}}>
            {' '}
            To: {dateBlock(newDate, false)} {dateBlock(oldDate, true)}
          </div>
          Translator: <a href={`mailto:${email}`}>{email}</a>
        </div>
      ),
    }
  }

  const showShareTranslatorError = () => {
    ModalsActions.onCloseModal()
    CatToolActions.addNotification({
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
    })
  }

  const checkShareToTranslatorResponse = (
    response,
    mail,
    date,
    jobData,
    projectData,
  ) => {
    let message = ''
    if (jobData.translator) {
      const newDate = new Date(date)
      const oldDate = new Date(jobData.translator.delivery_date)
      if (oldDate.getTime() !== newDate.getTime()) {
        message = shareToTranslatorDateChangeNotification(
          mail,
          oldDate,
          newDate,
        )
      } else if (jobData.translator.email !== mail) {
        message = shareToTranslatorMailChangeNotification(mail, jobData)
      } else {
        message = shareToTranslatorNotification(mail, jobData)
      }
    } else {
      message = shareToTranslatorNotification(mail, jobData)
    }
    CatToolActions.addNotification({
      title: message.title,
      text: message.text,
      type: 'success',
      position: 'bl',
      timer: 10000,
    })
    ManageActions.changeJobPasswordFromOutsource(
      projectData,
      jobData,
      jobData.password,
      response.job.password,
    )
    ManageActions.assignTranslator(
      projectData.id,
      jobData.id,
      jobData.password,
      response.job.translator,
    )
  }

  const shareJob = () => {
    const date = new Date(deliveryDate)
    date.setHours(parseInt(time))
    date.setMinutes(0)

    const email = emailRef.current.value
    const jobData = job.toJS()
    const projectData = project.toJS()

    addJobTranslator(email, date, timezone, jobData)
      .then((data) => {
        ModalsActions.onCloseModal()
        if (data.job) {
          checkShareToTranslatorResponse(
            data,
            email,
            date,
            jobData,
            projectData,
          )
        } else {
          showShareTranslatorError()
        }
      })
      .catch(() => {
        showShareTranslatorError()
      })
    closeOutsource()
  }

  const translatorEmail = job.get('translator')
    ? job.get('translator').get('email')
    : ''

  return (
    <div className="assign-job-translator sixteen wide column">
      <div className="title">Assign Job to translator</div>
      <div className="title-url">
        <div className="translator-assignee">
          <div className="assign-translator-form">
            <div className="fields">
              <div className="field translator-email">
                <label>Translator email</label>
                <div>
                  <input
                    type="email"
                    placeholder="translator@email.com"
                    defaultValue={translatorEmail}
                    ref={emailRef}
                    onKeyUp={checkSendToTranslatorButton}
                  />
                </div>
              </div>
              <div className="field translator-delivery ">
                <label>Delivery date</label>
                <div>
                  <DatePicker
                    selected={deliveryDate}
                    onChange={(date) => {
                      setDeliveryDate(date)
                      checkSendToTranslatorButton()
                    }}
                  />
                </div>
              </div>
              <div className="field translator-time">
                <Select
                  label="Time"
                  onSelect={({id}) => {
                    setTime(id)
                    checkSendToTranslatorButton()
                  }}
                  activeOption={timeOptions.find(({id}) => id === time)}
                  options={timeOptions}
                />
              </div>
              <div className="field gmt">
                <GMTSelect
                  changeValue={(value) => {
                    checkSendToTranslatorButton()
                    setTimezone(value)
                  }}
                  showLabel={true}
                />
              </div>
              <div className="field send-job-box">
                <Button
                  type={BUTTON_TYPE.PRIMARY}
                  onClick={shareJob}
                  disabled={isSendDisabled}
                >
                  Send Job to Translator
                </Button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

export default AssignToTranslator
