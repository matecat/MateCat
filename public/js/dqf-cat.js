import {sprintf} from 'sprintf-js'
import {dqfConfirmAssignment as dqfConfirmAssignmentApi} from './cat_source/es6/api/dqfConfirmAssignment'
;(function (UI) {
  var STATUS_USER_NOT_ASSIGNED = 'not_assigned'
  var STATUS_USER_NOT_MATCHING = 'not_matching'
  var STATUS_USER_NO_CREDENTIALS = 'no_credentials'
  var STATUS_USER_INVALID_CREDENTIALS = 'invalid_credentials'
  var STATUS_USER_ANONYMOUS = 'anonymous'

  var original_isReadonlySegment = UI.isReadonlySegment
  var original_messageForClickOnReadonly = UI.messageForClickOnReadonly
  var original_readonlyClickDisplay = UI.readonlyClickDisplay

  function readonlyClickDisplay() {
    APP.confirm({
      msg: UI.messageForClickOnReadonly(),
      callback: 'dqfConfirmSignin',
      okTxt: 'Ok',
    })
  }

  function dqfConfirmSignin() {
    $('#modal').trigger('openlogin')
  }

  function showAssignmentModal() {
    APP.confirm({
      msg: 'This DQF project is not assigned yet, do you want to assign it yourself?',
      closeOnSuccess: true,
      okTxt: 'Yes, assign this project to me',
      cancelTxt: 'No, leave it unassigned',
      callback: 'dqfConfirmAssignment',
    })
  }

  $(function () {
    if (config.dqf_user_status == STATUS_USER_NOT_ASSIGNED) {
      showAssignmentModal()

      $.extend(UI, {
        readonlyClickDisplay: showAssignmentModal,
        dqfConfirmAssignment: dqfConfirmAssignment,
      })
    } else {
      $.extend(UI, {
        messageForClickOnReadonly: messageForClickOnReadonly,
      })
    }
  })

  var isReadonlySegment = function (segment) {
    return readonlyStatus() || original_isReadonlySegment(segment)
  }

  var messageForClickOnReadonly = function (section) {
    if (readonlyStatus()) {
      return getSegmentClickMessage()
    } else {
      return original_messageForClickOnReadonly()
    }
  }

  var getSegmentClickMessage = function (section) {
    switch (config.dqf_user_status) {
      case STATUS_USER_ANONYMOUS:
        return 'You must be signed in to edit this project.'
      case STATUS_USER_NOT_MATCHING:
        return 'This DQF project is already assigned to another user.'
      case STATUS_USER_INVALID_CREDENTIALS:
      default:
        return 'Generic error'
        break
    }
  }

  function readonlyStatus() {
    console.log('readonlyStatus', config.dqf_user_status)

    switch (config.dqf_user_status) {
      case STATUS_USER_ANONYMOUS:
      case STATUS_USER_NOT_ASSIGNED:
      case STATUS_USER_NOT_MATCHING:
      case STATUS_USER_INVALID_CREDENTIALS:
        return true
        break
      default:
        return false
        break
    }
  }

  function dqfConfirmAssignment() {
    console.log('confirmed')

    dqfConfirmAssignmentApi({
      password: config.review_password
        ? config.review_password
        : config.password,
    }).then(() => location.reload())
  }

  $.extend(UI, {
    isReadonlySegment: isReadonlySegment,
    readonlyClickDisplay: readonlyClickDisplay,
    dqfConfirmSignin: dqfConfirmSignin,
  })
})(UI)
