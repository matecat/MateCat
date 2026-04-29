import {mergeJobChunks} from '../api/mergeJobChunks'
import AppDispatcher from '../stores/AppDispatcher'
import ModalsConstants from '../constants/ModalsConstants'
import {MODAL_KEY} from '../constants/ModalKeys'
import {ONBOARDING_STEP} from '../constants/OnBoardingConstants'

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
      MODAL_KEY.ONBOARDING,
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
      MODAL_KEY.ONBOARDING,
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
      MODAL_KEY.PREFERENCES,
      {showGDriveMessage},
      'Profile',
      style,
    )
  },
  openSuccessModal: (props) => {
    ModalsActions.showModalComponent(MODAL_KEY.SUCCESS, props, props.title)
  },
  openResetPassword: ({setNewPassword = false} = {}) => {
    ModalsActions.showModalComponent(
      MODAL_KEY.ONBOARDING,
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
    this.showModalComponent(MODAL_KEY.CREATE_TEAM, {}, 'Create New Team')
  },
  openModifyTeamModal: function (team, hideChangeName) {
    const props = {
      team: team,
      hideChangeName: hideChangeName,
    }
    this.showModalComponent(MODAL_KEY.MODIFY_TEAM, props, 'Manage Team')
  },

  openSplitJobModal: function (job, project, callback) {
    const props = {
      job: job,
      project: project,
      callback: callback,
    }
    const style = {width: '670px', maxWidth: '670px'}
    this.showModalComponent(MODAL_KEY.SPLIT_JOB, props, 'Split Job', style)
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
    this.showModalComponent(
      MODAL_KEY.CONFIRM_MESSAGE,
      props,
      'Confirmation required',
    )
  },

  showDownloadWarningsModal: function (
    successCallback,
    successCallbackWithoutErrors,
    cancelCallback,
  ) {
    ModalsActions.showModalComponent(
      MODAL_KEY.DOWNLOAD_ALERT,
      {successCallback, successCallbackWithoutErrors, cancelCallback},
      'Unresolved Major Issues',
    )
  },
}

export default ModalsActions
