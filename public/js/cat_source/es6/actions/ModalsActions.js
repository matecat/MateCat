import ConfirmMessageModal from '../components/modals/ConfirmMessageModal'
import DQFModal from '../components/modals/DQFModal'
import SplitJobModal from '../components/modals/SplitJob'
import CreateTeamModal from '../components/modals/CreateTeam'
import ModifyTeamModal from '../components/modals/ModifyTeam'
import {mergeJobChunks} from '../api/mergeJobChunks'

let ModalsActions = {
  openCreateTeamModal: function () {
    APP.ModalWindow.showModalComponent(CreateTeamModal, {}, 'Create New Team')
  },

  openModifyTeamModal: function (team, hideChangeName) {
    var props = {
      team: team,
      hideChangeName: hideChangeName,
    }
    APP.ModalWindow.showModalComponent(ModifyTeamModal, props, 'Modify Team')
  },

  openSplitJobModal: function (job, project, callback) {
    var props = {
      job: job,
      project: project,
      callback: callback,
    }
    var style = {width: '670px', maxWidth: '670px'}
    APP.ModalWindow.showModalComponent(SplitJobModal, props, 'Split Job', style)
  },
  openMergeModal: function (project, job, successCallback) {
    var props = {
      text:
        'This will cause the merging of all chunks in only one job. ' +
        'This operation cannot be canceled.',
      successText: 'Continue',
      successCallback: function () {
        mergeJobChunks(project, job).then(function () {
          if (successCallback) {
            successCallback.call()
          }
        })
        APP.ModalWindow.onCloseModal()
      },
      cancelText: 'Cancel',
      cancelCallback: function () {
        APP.ModalWindow.onCloseModal()
      },
    }
    APP.ModalWindow.showModalComponent(
      ConfirmMessageModal,
      props,
      'Confirmation required',
    )
  },

  openDQFModal: function () {
    var props = {
      metadata: APP.USER.STORE.metadata ? APP.USER.STORE.metadata : {},
    }
    var style = {width: '670px', maxWidth: '670px'}
    APP.ModalWindow.showModalComponent(
      DQFModal,
      props,
      'DQF Preferences',
      style,
    )
  },
}

export default ModalsActions
