import ConfirmMessageModal from '../components/modals/ConfirmMessageModal'
import SplitJobModal from '../components/modals/SplitJob'
import CreateTeamModal from '../components/modals/CreateTeam'
import ModifyTeamModal from '../components/modals/ModifyTeam'
import {mergeJobChunks} from '../api/mergeJobChunks'
import AppDispatcher from '../stores/AppDispatcher'
import ModalsConstants from '../constants/ModalsConstants'
import LoginModal from '../components/modals/LoginModal'
import RegisterModal from '../components/modals/RegisterModal'
import PreferencesModal from '../components/modals/PreferencesModal'
import SuccessModal from '../components/modals/SuccessModal'
import ResetPasswordModal from '../components/modals/ResetPasswordModal'

let ModalsActions = {
  showModalComponent: (component, props, title, style, onCloseCallback) => {
    AppDispatcher.dispatch({
      actionType: ModalsConstants.SHOW_MODAL,
      component,
      props,
      title,
      style,
      onCloseCallback,
    })
  },
  openLoginModal: function (param = {}) {
    const title = 'Add project to your management panel'
    const style = {
      width: '80%',
      maxWidth: '800px',
      minWidth: '600px',
    }
    const props = {
      googleUrl: config.authURL,
      ...param,
    }

    ModalsActions.showModalComponent(LoginModal, props, title, style)
  },
  openRegisterModal: (params) => {
    let props = {
      googleUrl: config.authURL,
    }
    if (params) {
      props = {
        ...props,
        ...params,
      }
    }
    ModalsActions.showModalComponent(RegisterModal, props, 'Register Now')
  },
  openPreferencesModal: ({showGDriveMessage = false} = {}) => {
    const style = {
      width: '700px',
      maxWidth: '700px',
    }
    ModalsActions.showModalComponent(
      PreferencesModal,
      {showGDriveMessage},
      'Profile',
      style,
    )
  },
  openSuccessModal: (props) => {
    ModalsActions.showModalComponent(SuccessModal, props, props.title)
  },
  openResetPassword: () => {
    let props = {closeOnOutsideClick: false, showOldPassword: true}
    if (APP.lookupFlashServiceParam('popup')) {
      props.showOldPassword = false
    }
    ModalsActions.showModalComponent(
      ResetPasswordModal,
      props,
      'Reset Password',
    )
  },
  onCloseModal: function () {
    AppDispatcher.dispatch({
      actionType: ModalsConstants.CLOSE_MODAL,
    })
  },
  openCreateTeamModal: function () {
    this.showModalComponent(CreateTeamModal, {}, 'Create New Team')
  },
  openModifyTeamModal: function (team, hideChangeName) {
    const props = {
      team: team,
      hideChangeName: hideChangeName,
    }
    this.showModalComponent(ModifyTeamModal, props, 'Manage Team')
  },

  openSplitJobModal: function (job, project, callback) {
    const props = {
      job: job,
      project: project,
      callback: callback,
    }
    const style = {width: '670px', maxWidth: '670px'}
    this.showModalComponent(SplitJobModal, props, 'Split Job', style)
  },
  openMergeModal: function (project, job, successCallback) {
    const props = {
      text:
        'This will cause the merging of all chunks in only one job. ' +
        'This operation cannot be canceled.',
      successText: 'Continue',
      successCallback: () => {
        mergeJobChunks(project, job).then(function () {
          if (successCallback) {
            successCallback.call()
          }
        })
        this.onCloseModal()
      },
      cancelText: 'Cancel',
      cancelCallback: () => {
        this.onCloseModal()
      },
    }
    this.showModalComponent(ConfirmMessageModal, props, 'Confirmation required')
  },

  showDownloadWarningsModal: function (successCallback, cancelCallback) {
    ModalsActions.showModalComponent(
      ConfirmMessageModal,
      {
        cancelText: 'Fix issues',
        cancelCallback: () => cancelCallback(),
        successCallback: () => successCallback(),
        successText: 'Download anyway',
        text:
          'Unresolved tag issues may prevent the successful download of your translation.<br />' +
          'For information on how to fix them, please open <a style="color: #4183C4; font-weight: 700; text-decoration: underline;"' +
          ' href="https://guides.matecat.com/fixing-tags" target="_blank">the dedicated support page </a>. <br /><br /> ' +
          ' If you download the file anyway, part of the content may be untranslated - look for the string UNTRANSLATED_CONTENT in the downloaded files.',
      },
      'Confirmation required',
    )
  },
}

export default ModalsActions
