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

  const shareToTranslatorMailChangeNotification = (mail, jobData) => ({
    title:
      'Job sent with <div class="green-label" style="display: inline; background-color: #5ea400; color: white; padding: 2px 5px;">new password </div>',
    text:
      '<div style="margin-top: 16px;">To: <a href="mailto:' +
      mail +
      '">' +
      mail +
      '</a> ' +
      '<div class="job-reference" style="display: inline-block; width: 100%; margin-top: 10px;"> ' +
      '<div class style="display: inline-block; font-size: 14px; color: grey;">(' +
      jobData.id +
      ')</div> ' +
      '<div class="source-target languages-tooltip" style="display: inline-block; font-weight: 700;"> ' +
      '<div class="source-box" style="display: inherit;">' +
      jobData.sourceTxt +
      '</div> ' +
      '<div class="in-to" style="top: 3px; display: inherit; position: relative;"> <i class="icon-chevron-right icon"></i> </div> ' +
      '<div class="target-box" style="display: inherit;">' +
      jobData.targetTxt +
      '</div> </div> </div></div>',
  })

  const shareToTranslatorNotification = (mail, jobData) => ({
    title: 'Job sent',
    text:
      '<div style="margin-top: 16px;">To: <a href="mailto:' +
      mail +
      '">' +
      mail +
      '</a> ' +
      '<div class="job-reference" style="display: inline-block; width: 100%; margin-top: 10px;"> ' +
      '<div class style="display: inline-block; font-size: 14px; color: grey;">' +
      jobData.id +
      ' </div> ' +
      '<div class="source-target languages-tooltip" style="display: inline-block; font-weight: 700;"> ' +
      '<div class="source-box" style="display: inherit;">' +
      jobData.sourceTxt +
      '</div> ' +
      '<div class="in-to" style="top: 3px; display: inherit; position: relative;"> <i class="icon-chevron-right icon"></i> </div> ' +
      '<div class="target-box" style="display: inherit;">' +
      jobData.targetTxt +
      '</div> </div> </div></div>',
  })

  const shareToTranslatorDateChangeNotification = (email, oldDate, newDate) => {
    oldDate = CommonUtils.formatDate(oldDate, 'yyyy-MM-d hh:mm a')
    oldDate = CommonUtils.getGMTDate(oldDate)
    newDate = CommonUtils.formatDate(newDate, 'yyyy-MM-d hh:mm a')
    newDate = CommonUtils.getGMTDate(newDate)
    return {
      title: 'Job delivery update',
      text:
        '<div style="margin-top: 16px;"><div class="job-reference" style="display: inline-block; width: 100%;"> To: ' +
        '<div class="job-delivery" title="Delivery date" style="display: inline-block; margin-bottom: 10px; font-weight: 700; margin-right: 10px;"> ' +
        '<div class="outsource-day-text" style="display: inline-block; margin-right: 3px;">' +
        newDate.day +
        '</div> ' +
        '<div class="outsource-month-text" style="display: inline-block; margin-right: 5px;">' +
        newDate.month +
        '</div> ' +
        '<div class="outsource-time-text" style="display: inline-block;">' +
        newDate.time +
        '</div> ' +
        '<div class="outsource-gmt-text" style="display: inline-block; font-weight: 100;color: grey;">(' +
        newDate.gmt +
        ')</div> ' +
        '</div> <div class="job-delivery not-used" title="Delivery date" style="display: inline-block; margin-bottom: 10px; font-weight: 700; text-decoration: line-through; position: relative;"> ' +
        '<div class="outsource-day-text" style="display: inline-block; margin-right: 3px;">' +
        oldDate.day +
        '</div> ' +
        '<div class="outsource-month-text" style="display: inline-block; margin-right: 5px;">' +
        oldDate.month +
        '</div> ' +
        '<div class="outsource-time-text" style="display: inline-block;">' +
        oldDate.time +
        '</div> ' +
        '<div class="outsource-gmt-text" style="display: inline-block; font-weight: 100; color: grey;">(' +
        oldDate.gmt +
        ')</div> ' +
        '<div class="old" style="width: 100%; height: 1px; border-top: 1px solid black; top: -10px; position: relative;"></div> </div> ' +
        '</div>Translator: <a href="mailto:' +
        email +
        '">' +
        email +
        '</a> </div></div>',
    }
  }

  const showShareTranslatorError = () => {
    ModalsActions.onCloseModal()
    CatToolActions.addNotification({
      title: 'Problems sending the job',
      text: 'Please try later or contact <a href="mailto:support@matecat.com">support@matecat.com</a>',
      type: 'error',
      position: 'bl',
      allowHtml: true,
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
      allowHtml: true,
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
          <div className="ui form">
            <div className="fields">
              <div className="field translator-email">
                <label>Translator email</label>
                <input
                  type="email"
                  placeholder="translator@email.com"
                  defaultValue={translatorEmail}
                  ref={emailRef}
                  onKeyUp={checkSendToTranslatorButton}
                />
              </div>
              <div className="field translator-delivery ">
                <label>Delivery date</label>
                <div className="ui calendar">
                  <div className="ui input">
                    <DatePicker
                      selected={deliveryDate}
                      onChange={(date) => {
                        setDeliveryDate(date)
                        checkSendToTranslatorButton()
                      }}
                    />
                  </div>
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
