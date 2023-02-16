import {dqfConfirmAssignment as dqfConfirmAssignmentApi} from './cat_source/es6/api/dqfConfirmAssignment'
import AlertModal from './cat_source/es6/components/modals/AlertModal'
import ConfirmMessageModal from './cat_source/es6/components/modals/ConfirmMessageModal'
import ModalsActions from './cat_source/es6/actions/ModalsActions'

if (config.dqf_enabled === 1) {
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
      ModalsActions.showModalComponent(AlertModal, {
        text: UI.messageForClickOnReadonly(),
        successCallback: () => dqfConfirmSignin,
      })
    }

    function dqfConfirmSignin() {
      $('#modal').trigger('openlogin')
    }

    function showAssignmentModal() {
      ModalsActions.showModalComponent(ConfirmMessageModal, {
        text: 'This DQF project is not assigned yet, do you want to assign it yourself?',
        successText: 'Yes, assign this project to me',
        cancelText: 'No, leave it unassigned',
        successCallback: () => UI.dqfConfirmAssignment(),
        closeOnSuccess: true,
      })
    }

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

    $.extend(UI, {
      isReadonlySegment: isReadonlySegment,
      readonlyClickDisplay: readonlyClickDisplay,
      dqfConfirmSignin: dqfConfirmSignin,
    })
  })(UI)
}
