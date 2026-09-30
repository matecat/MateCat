jest.mock('../stores/AppDispatcher', () => ({
  dispatch: jest.fn(),
}))

jest.mock('../constants/ApplicationConstants', () => ({
  SET_LANGUAGES: 'SET_LANGUAGES',
}))

import ApplicationActions from './ApplicationActions'
import AppDispatcher from '../stores/AppDispatcher'

describe('ApplicationActions', () => {
  beforeEach(() => {
    jest.clearAllMocks()
  })

  test('setLanguages dispatches SET_LANGUAGES with languages', () => {
    ApplicationActions.setLanguages(['en', 'it'])

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'SET_LANGUAGES',
      languages: ['en', 'it'],
    })
  })
})
