import ManageActions from './cat_source/es6/actions/ManageActions'
import {addJobTranslator} from './cat_source/es6/api/addJobTranslator'
import CommonUtils from './cat_source/es6/utils/commonUtils'
import CatToolActions from './cat_source/es6/actions/CatToolActions'
import ModalsActions from './cat_source/es6/actions/ModalsActions'
if (!window.UI) {
  window.UI = {}
}

$.extend(window.UI, {
  sendJobToTranslator: function (email, date, timezone, job, project) {
    addJobTranslator(email, date, timezone, job)
      .then(function (data) {
        ModalsActions.onCloseModal()
        if (data.job) {
          UI.checkShareToTranslatorResponse(data, email, date, job, project)
        } else {
          UI.showShareTranslatorError()
        }
      })
      .catch(function () {
        UI.showShareTranslatorError()
      })
  },

  checkShareToTranslatorResponse: function (
    response,
    mail,
    date,
    job,
    project,
  ) {
    var message = ''
    if (job.translator) {
      var newDate = new Date(date)
      var oldDate = new Date(job.translator.delivery_date)
      if (oldDate.getTime() !== newDate.getTime()) {
        message = this.shareToTranslatorDateChangeNotification(
          mail,
          oldDate,
          newDate,
        )
      } else if (job.translator.email !== mail) {
        message = this.shareToTranslatorMailChangeNotification(mail)
      } else {
        message = this.shareToTranslatorNotification(mail, job)
      }
    } else {
      message = this.shareToTranslatorNotification(mail, job)
    }
    var notification = {
      title: message.title,
      text: message.text,
      type: 'success',
      position: 'bl',
      allowHtml: true,
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
  },

  shareToTranslatorNotification: function (mail, job) {
    return {
      title: 'Job sent',
      text:
        '<div style="margin-top: 16px;">To: <a href="mailto:' +
        mail +
        '">' +
        mail +
        '</a> ' +
        '<div class="job-reference" style="display: inline-block; width: 100%; margin-top: 10px;"> ' +
        '<div class style="display: inline-block; font-size: 14px; color: grey;">' +
        job.id +
        ' </div> ' +
        '<div class="source-target languages-tooltip" style="display: inline-block; font-weight: 700;"> ' +
        '<div class="source-box" style="display: inherit;">' +
        job.sourceTxt +
        '</div> ' +
        '<div class="in-to" style="top: 3px; display: inherit; position: relative;"> <i class="icon-chevron-right icon"></i> </div> ' +
        '<div class="target-box" style="display: inherit;">' +
        job.targetTxt +
        '</div> </div> </div></div>',
    }
  },

  shareToTranslatorDateChangeNotification: function (email, oldDate, newDate) {
    oldDate = $.format.date(oldDate, 'yyyy-MM-d hh:mm a')
    oldDate = CommonUtils.getGMTDate(oldDate)
    newDate = $.format.date(newDate, 'yyyy-MM-d hh:mm a')
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
  },

  shareToTranslatorMailChangeNotification: function (mail) {
    return {
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
        UI.currentOutsourceJob.id +
        ')</div> ' +
        '<div class="source-target languages-tooltip" style="display: inline-block; font-weight: 700;"> ' +
        '<div class="source-box" style="display: inherit;">' +
        UI.currentOutsourceJob.sourceTxt +
        '</div> ' +
        '<div class="in-to" style="top: 3px; display: inherit; position: relative;"> <i class="icon-chevron-right icon"></i> </div> ' +
        '<div class="target-box" style="display: inherit;">' +
        UI.currentOutsourceJob.targetTxt +
        '</div> </div> </div></div>',
    }
  },
  showShareTranslatorError: function () {
    ModalsActions.onCloseModal()
    var notification = {
      title: 'Problems sending the job',
      text: 'Please try later or contact <a href="mailto:support@matecat.com">support@matecat.com</a>',
      type: 'error',
      position: 'bl',
      allowHtml: true,
      timer: 10000,
    }
    CatToolActions.addNotification(notification)
  },
})
