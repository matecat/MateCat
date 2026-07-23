jest.mock('../api/mergeJobChunks', () => ({
  mergeJobChunks: jest.fn(),
}))
jest.mock('../stores/AppDispatcher', () => ({
  dispatch: jest.fn(),
}))
jest.mock('../constants/ModalsConstants', () => ({
  SHOW_MODAL: 'SHOW_MODAL',
  CLOSE_MODAL: 'CLOSE_MODAL',
}))
jest.mock('../constants/ModalKeys', () => ({
  MODAL_KEY: {
    ONBOARDING: 'Onboarding',
    PREFERENCES: 'Preferences',
    SUCCESS: 'Success',
    CREATE_TEAM: 'CreateTeam',
    MODIFY_TEAM: 'ModifyTeam',
    SPLIT_JOB: 'SplitJob',
    CONFIRM_MESSAGE: 'ConfirmMessage',
    DOWNLOAD_ALERT: 'DownloadAlert',
  },
}))
jest.mock('../constants/OnBoardingConstants', () => ({
  ONBOARDING_STEP: {
    REGISTER: 'register',
    SET_NEW_PASSWORD: 'setNewPassword',
    PASSWORD_RESET: 'passwordReset',
  },
}))

import ModalsActions from './ModalsActions'
import AppDispatcher from '../stores/AppDispatcher'
import {mergeJobChunks} from '../api/mergeJobChunks'

describe('ModalsActions', () => {
  beforeEach(() => {
    jest.clearAllMocks()
  })

  test('showModalComponent dispatches SHOW_MODAL with all props', () => {
    const onCloseCallback = jest.fn()
    ModalsActions.showModalComponent(
      'Component',
      {a: 1},
      'Title',
      {width: 1},
      onCloseCallback,
      false,
      {b: 2},
      true,
    )

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'SHOW_MODAL',
      component: 'Component',
      props: {a: 1},
      title: 'Title',
      style: {width: 1},
      onCloseCallback,
      showHeader: false,
      styleBody: {b: 2},
      isCloseButtonDisabled: true,
    })
  })

  test('openLoginModal shows onboarding modal', () => {
    ModalsActions.openLoginModal()

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({
        actionType: 'SHOW_MODAL',
        component: 'Onboarding',
        props: {isCloseButtonEnabled: true},
      }),
    )
  })

  test('openRegisterModal shows onboarding modal at register step', () => {
    ModalsActions.openRegisterModal()

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({
        component: 'Onboarding',
        props: {step: 'register', isCloseButtonEnabled: true},
      }),
    )
  })

  test('openPreferencesModal shows preferences modal', () => {
    ModalsActions.openPreferencesModal({showGDriveMessage: true})

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({
        component: 'Preferences',
        props: {showGDriveMessage: true},
        title: 'Profile',
      }),
    )
  })

  test('openPreferencesModal defaults showGDriveMessage to false', () => {
    ModalsActions.openPreferencesModal()

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({
        props: {showGDriveMessage: false},
      }),
    )
  })

  test('openSuccessModal shows success modal with props.title', () => {
    ModalsActions.openSuccessModal({title: 'Great success'})

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({
        component: 'Success',
        title: 'Great success',
      }),
    )
  })

  test('openResetPassword shows password reset step by default', () => {
    ModalsActions.openResetPassword()

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({
        props: {step: 'passwordReset', isCloseButtonEnabled: true},
      }),
    )
  })

  test('openResetPassword shows set-new-password step when requested', () => {
    ModalsActions.openResetPassword({setNewPassword: true})

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({
        props: {step: 'setNewPassword', isCloseButtonEnabled: true},
      }),
    )
  })

  test('onCloseModal dispatches CLOSE_MODAL', () => {
    ModalsActions.onCloseModal()

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'CLOSE_MODAL',
    })
  })

  test('openCreateTeamModal shows create team modal', () => {
    ModalsActions.openCreateTeamModal()

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({
        component: 'CreateTeam',
        title: 'Create New Team',
      }),
    )
  })

  test('openModifyTeamModal shows modify team modal', () => {
    const team = {id: 1}
    ModalsActions.openModifyTeamModal(team, true)

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({
        component: 'ModifyTeam',
        props: {team, hideChangeName: true},
        title: 'Manage Team',
      }),
    )
  })

  test('openSplitJobModal shows split job modal', () => {
    const job = {id: 1}
    const project = {id: 2}
    const callback = jest.fn()
    ModalsActions.openSplitJobModal(job, project, callback)

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({
        component: 'SplitJob',
        props: {job, project, callback},
        title: 'Split Job',
        style: {width: '670px', maxWidth: '670px'},
      }),
    )
  })

  test('openMergeModal successCallback merges chunks and closes modal', () => {
    mergeJobChunks.mockResolvedValueOnce({})
    const project = {id: 1}
    const job = {id: 2}
    const successCallback = jest.fn()
    ModalsActions.openMergeModal(project, job, successCallback)

    const dispatchedProps = AppDispatcher.dispatch.mock.calls[0][0].props
    dispatchedProps.successCallback()

    expect(mergeJobChunks).toHaveBeenCalledWith(project, job)
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'CLOSE_MODAL',
    })

    return Promise.resolve().then(() => {
      expect(successCallback).toHaveBeenCalled()
    })
  })

  test('openMergeModal cancelCallback closes modal', () => {
    const project = {id: 1}
    const job = {id: 2}
    ModalsActions.openMergeModal(project, job)

    const dispatchedProps = AppDispatcher.dispatch.mock.calls[0][0].props
    dispatchedProps.cancelCallback()

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'CLOSE_MODAL',
    })
  })

  test('showDownloadWarningsModal shows download alert modal', () => {
    const successCallback = jest.fn()
    const successCallbackWithoutErrors = jest.fn()
    const cancelCallback = jest.fn()
    ModalsActions.showDownloadWarningsModal(
      successCallback,
      successCallbackWithoutErrors,
      cancelCallback,
    )

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({
        component: 'DownloadAlert',
        props: {successCallback, successCallbackWithoutErrors, cancelCallback},
        title: 'Unresolved Major Issues',
      }),
    )
  })
})
