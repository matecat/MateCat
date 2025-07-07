import ConfirmMessageModal from '../components/modals/ConfirmMessageModal'
import SplitJobModal from '../components/modals/SplitJob'
import {CreateTeam} from '../components/modals/CreateTeam'
import {ModifyTeam} from '../components/modals/ModifyTeam'
import {mergeJobChunks} from '../api/mergeJobChunks'
import AppDispatcher from '../stores/AppDispatcher'
import ModalsConstants from '../constants/ModalsConstants'
import PreferencesModal from '../components/modals/PreferencesModal'
import SuccessModal from '../components/modals/SuccessModal'
import OnBoarding, {ONBOARDING_STEP} from '../components/onBoarding/OnBoarding'

let ModalsActions = {
  showModalComponent: (
    component,
    props,
    title,
    style,
    onCloseCallback,
    showHeader = true,
    styleBody,
    isCloseButtonDisabled,
  ) => {
    AppDispatcher.dispatch({
      actionType: ModalsConstants.SHOW_MODAL,
      component,
      props,
      title,
      style,
      onCloseCallback,
      showHeader,
      styleBody,
      isCloseButtonDisabled,
    })
  },
  openLoginModal: function () {
    ModalsActions.showModalComponent(
      OnBoarding,
      {isCloseButtonEnabled: true},
      null,
      {maxWidth: 'unset', width: 'auto'},
      null,
      false,
      {borderRadius: 'unset', backgroundColor: 'unset'},
    )
  },
  openRegisterModal: () => {
    ModalsActions.showModalComponent(
      OnBoarding,
      {
        step: ONBOARDING_STEP.REGISTER,
        isCloseButtonEnabled: true,
      },
      null,
      {maxWidth: 'unset', width: 'auto'},
      null,
      false,
      {borderRadius: 'unset', backgroundColor: 'unset'},
    )
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
  openResetPassword: ({setNewPassword = false} = {}) => {
    ModalsActions.showModalComponent(
      OnBoarding,
      {
        step: setNewPassword
          ? ONBOARDING_STEP.SET_NEW_PASSWORD
          : ONBOARDING_STEP.PASSWORD_RESET,
        isCloseButtonEnabled: true,
      },
      null,
      {maxWidth: 'unset', width: 'auto'},
      null,
      false,
      {borderRadius: 'unset', backgroundColor: 'unset'},
    )
  },
  onCloseModal: function () {
    AppDispatcher.dispatch({
      actionType: ModalsConstants.CLOSE_MODAL,
    })
  },
  openCreateTeamModal: function () {
    this.showModalComponent(CreateTeam, {}, 'Create New Team')
  },
  openModifyTeamModal: function (team, hideChangeName) {
    const props = {
      team: team,
      hideChangeName: hideChangeName,
    }
    this.showModalComponent(ModifyTeam, props, 'Manage Team')
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
