jest.mock('../stores/AppDispatcher', () => ({
  dispatch: jest.fn(),
}))

jest.mock('../constants/SegmentConstants', () => ({
  UPDATE_GLOBAL_WARNINGS: 'UPDATE_GLOBAL_WARNINGS',
}))

import {updateGlobalWarnings} from './warningActions'
import AppDispatcher from '../stores/AppDispatcher'

describe('warningActions', () => {
  beforeEach(() => {
    jest.clearAllMocks()
  })

  test('updateGlobalWarnings dispatches UPDATE_GLOBAL_WARNINGS with warnings', () => {
    updateGlobalWarnings({tag: ['1', '2']})

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'UPDATE_GLOBAL_WARNINGS',
      warnings: {tag: ['1', '2']},
    })
  })
})
