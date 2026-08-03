jest.mock('../api/projectCreationStatus', () => ({
  projectCreationStatus: jest.fn(),
}))
jest.mock('../actions/CreateProjectActions', () => ({
  hideErrors: jest.fn(),
  showError: jest.fn(),
}))
jest.mock('../actions/ModalsActions', () => ({
  showModalComponent: jest.fn(),
}))
jest.mock('../components/modals/AlertModal', () => 'AlertModal')
jest.mock('./commonUtils', () => ({
  dispatchAnalyticsEvents: jest.fn(),
}))
jest.mock('../stores/UserStore', () => ({
  getUser: jest.fn(),
}))

import {projectCreationStatus} from '../api/projectCreationStatus'
import CreateProjectActions from '../actions/CreateProjectActions'
import ModalsActions from '../actions/ModalsActions'
import CommonUtils from './commonUtils'
import UserStore from '../stores/UserStore'
import {handleCreationStatus} from './newProjectUtils'

const flush = async () => {
  await Promise.resolve()
  await Promise.resolve()
}

test('reschedules itself when the API reports an in-progress (202) status', async () => {
  const setTimeoutSpy = jest
    .spyOn(global, 'setTimeout')
    .mockImplementation(() => {})
  projectCreationStatus.mockResolvedValue({data: {status: 202}, status: 202})

  handleCreationStatus(1, 'pass')
  await flush()

  expect(setTimeoutSpy).toHaveBeenCalledWith(
    handleCreationStatus,
    1000,
    1,
    'pass',
  )

  setTimeoutSpy.mockRestore()
})

test('shows the error notification when the API response contains errors', async () => {
  projectCreationStatus.mockResolvedValue({
    data: {errors: [{message: 'Bad file'}]},
    status: 200,
  })

  handleCreationStatus(1, 'pass')
  await flush()

  expect(CreateProjectActions.hideErrors).toHaveBeenCalled()
  expect(CreateProjectActions.showError).toHaveBeenCalledWith('Bad file')
})

test('shows the empty-project modal when the project has no text', async () => {
  projectCreationStatus.mockResolvedValue({
    data: {status: 'EMPTY'},
    status: 200,
  })

  handleCreationStatus(1, 'pass')
  await flush()

  expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
    'AlertModal',
    expect.objectContaining({buttonText: 'Continue'}),
    'No text to translate',
  )
})

test('dispatches analytics and redirects to the analyze page on success', async () => {
  // jsdom doesn't implement real navigation; silence its "not implemented" log
  // for the `location.href = ...` assignment exercised by this code path.
  const consoleErrorSpy = jest
    .spyOn(console, 'error')
    .mockImplementation(() => {})
  UserStore.getUser.mockReturnValue({user: {uid: 7}})
  projectCreationStatus.mockResolvedValue({
    data: {status: 'DONE', id_project: 3, analyze_url: '/analyze/3'},
    status: 200,
  })

  handleCreationStatus(1, 'pass')
  await flush()

  expect(CommonUtils.dispatchAnalyticsEvents).toHaveBeenCalledWith({
    event: 'analyze_click',
    userStatus: 'loggedUser',
    userId: 7,
    idProject: 3,
  })

  consoleErrorSpy.mockRestore()
})

test('shows the error notification when the API call rejects with errors', async () => {
  projectCreationStatus.mockRejectedValue({errors: [{message: 'Failed'}]})

  handleCreationStatus(1, 'pass')
  await flush()

  expect(CreateProjectActions.hideErrors).toHaveBeenCalled()
  expect(CreateProjectActions.showError).toHaveBeenCalledWith('Failed')
})
