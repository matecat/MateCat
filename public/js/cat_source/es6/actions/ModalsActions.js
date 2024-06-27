import ConfirmMessageModal from '../components/modals/ConfirmMessageModal'
import SplitJobModal from '../components/modals/SplitJob'
import {CreateTeam} from '../components/modals/CreateTeam'
import {ModifyTeam} from '../components/modals/ModifyTeam'
import {mergeJobChunks} from '../api/mergeJobChunks'
import AppDispatcher from '../stores/AppDispatcher'
import ModalsConstants from '../constants/ModalsConstants'

let ModalsActions = {
  showModalComponent: (
    component,
    props,
    title,
    style,
    onCloseCallback,
    isCloseButtonDisabled,
  ) => {
    AppDispatcher.dispatch({
      actionType: ModalsConstants.SHOW_MODAL,
      component,
      props,
      title,
      style,
      onCloseCallback,
      isCloseButtonDisabled,
    })
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
    var props = {
      team: team,
      hideChangeName: hideChangeName,
    }
    this.showModalComponent(ModifyTeam, props, 'Manage Team')
  },

  openSplitJobModal: function (job, project, callback) {
    var props = {
      job: job,
      project: project,
      callback: callback,
    }
    var style = {width: '670px', maxWidth: '670px'}
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
