jest.mock('../stores/AppDispatcher', () => ({
  dispatch: jest.fn(),
  register: jest.fn(),
}))

jest.mock('../stores/CatToolStore', () => ({
  getCurrentSegment: jest.fn(),
  getMatecatEventsEnabled: jest.fn(() => false),
}))

jest.mock('../stores/SegmentStore', () => ({
  getCurrentSegmentId: jest.fn(),
  getSegmentsArray: jest.fn(() => []),
}))

jest.mock('./ModalsActions', () => ({
  showModalComponent: jest.fn(),
}))

jest.mock('./SegmentActions', () => ({}))

jest.mock('../utils/commonUtils', () => ({
  dispatchCustomEvent: jest.fn(),
}))
jest.mock('../utils/offlineUtils', () => ({
  failedConnection: jest.fn(),
}))

// API imports (only those actually imported by CatToolActions.js)
jest.mock('../api/getJobStatistics', () => ({ getJobStatistics: jest.fn() }))
jest.mock('../api/sendRevisionFeedback', () => ({
  sendRevisionFeedback: jest.fn(),
}))
jest.mock('../api/getTmKeysJob', () => ({ getTmKeysJob: jest.fn() }))
jest.mock('../api/getDomainsList', () => ({ getDomainsList: jest.fn() }))
jest.mock('../api/checkJobKeysHaveGlossary', () => ({
  checkJobKeysHaveGlossary: jest.fn(),
}))
jest.mock('../api/getJobMetadata', () => ({ getJobMetadata: jest.fn() }))
jest.mock('../api/getGlobalWarnings', () => ({ getGlobalWarnings: jest.fn() }))

jest.mock('../components/modals/AlertModal', () => 'AlertModal')
jest.mock('../components/modals/RevisionFeedbackModal', () => 'RevisionFeedbackModal')
jest.mock('../components/modals/ConfirmMessageModal', () => 'ConfirmMessageModal')

jest.mock('../constants/CatToolConstants', () => ({
  SET_FIRST_LOAD: 'SET_FIRST_LOAD',
}))
jest.mock('lodash', () => ({
  isUndefined: (v) => typeof v === 'undefined',
}))

import CatToolActions from './CatToolActions'
import ModalsActions from './ModalsActions'
import AlertModal from '../components/modals/AlertModal'

describe('CatToolActions.processErrors', () => {
  beforeEach(() => {
    global.config = {id_job: 2}
    jest.clearAllMocks()
  })

  test('shows "Segment disabled" modal with refresh callback for -5 on setTranslation', () => {
    const onRenderSpy = jest
      .spyOn(CatToolActions, 'onRender')
      .mockImplementation(() => {})

    CatToolActions.processErrors([{code: '-5'}], 'setTranslation')

    expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
      AlertModal,
      expect.objectContaining({
        text: 'This segment has been disabled by the project owner.<br />Refresh the page to update segment status.',
        buttonText: 'Refresh page',
        successCallback: expect.any(Function),
      }),
      'Segment disabled',
    )

    const modalProps = ModalsActions.showModalComponent.mock.calls[0][1]
    modalProps.successCallback()
    expect(onRenderSpy).toHaveBeenCalledTimes(1)
  })

  test('shows generic error modal when code is not -5 and not -10 on setTranslation', () => {
    CatToolActions.processErrors([{code: '-1'}], 'setTranslation')

    expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
      AlertModal,
      expect.objectContaining({
        text: expect.stringContaining('Error in saving the translation'),
      }),
      'Error',
    )
  })

  test('does NOT show generic error modal when code is -10 on setTranslation', () => {
    CatToolActions.processErrors([{code: '-10'}], 'setTranslation')

    // Should not have been called with the generic error text
    const calls = ModalsActions.showModalComponent.mock.calls
    const genericErrorCall = calls.find(
      (call) =>
        call[2] === 'Error' &&
        call[1]?.text?.includes('Error in saving the translation'),
    )
    expect(genericErrorCall).toBeUndefined()
  })

  test('shows "Job canceled" modal when code is -10 and operation is not getSegments', () => {
    CatToolActions.processErrors([{code: '-10'}], 'setSuggestion')

    expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
      AlertModal,
      expect.objectContaining({
        text: 'Job canceled or assigned to another translator',
        successCallback: expect.any(Function),
      }),
      'Error',
    )
  })
})
