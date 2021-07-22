import _ from 'lodash'

let ModalsActions = {
  //********* Modals **************//

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

  openChangeTeamModal: function (teams, project, teamId) {
    var props = {
      teams: teams,
      project: project,
      selectedTeam: teamId,
    }
    APP.ModalWindow.showModalComponent(ChangeTeamModal, props, 'Move project')
  },

  openOutsourceModal: function (
    project,
    job,
    url,
    fromManage,
    translatorOpen,
    showTranslatorBox,
  ) {
    var props = {
      project: project,
      job: job,
      url: url,
      fromManage: fromManage,
      translatorOpen: translatorOpen,
      showTranslatorBox: !_.isUndefined(showTranslatorBox)
        ? showTranslatorBox
        : true,
    }
    var style = {width: '970px', maxWidth: '970px'}
    APP.ModalWindow.showModalComponent(
      OutsourceModal,
      props,
      'Translate',
      style,
    )
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
        API.JOB.confirmMerge(project, job).done(function () {
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

module.exports = ModalsActions
