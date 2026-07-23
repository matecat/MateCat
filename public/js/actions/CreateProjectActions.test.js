jest.mock('../stores/AppDispatcher', () => ({
  dispatch: jest.fn(),
}))

jest.mock('../constants/NewProjectConstants', () => ({
  UPDATE_PROJECT_DATA: 'UPDATE_PROJECT_DATA',
  HIDE_ERROR_WARNING: 'HIDE_ERROR_WARNING',
  SHOW_ERROR: 'SHOW_ERROR',
  CREATE_KEY_FROM_TMX_FILE: 'CREATE_KEY_FROM_TMX_FILE',
  ENABLE_ANALYZE_BUTTON: 'ENABLE_ANALYZE_BUTTON',
  UPDATE_PROJECT_TEMPLATES: 'UPDATE_PROJECT_TEMPLATES',
}))

import CreateProjectActions from './CreateProjectActions'
import AppDispatcher from '../stores/AppDispatcher'

describe('CreateProjectActions', () => {
  beforeEach(() => {
    jest.clearAllMocks()
  })

  test('updateProjectParams dispatches UPDATE_PROJECT_DATA', () => {
    CreateProjectActions.updateProjectParams({foo: 'bar'})

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'UPDATE_PROJECT_DATA',
      data: {foo: 'bar'},
    })
  })

  test('hideErrors dispatches HIDE_ERROR_WARNING', () => {
    CreateProjectActions.hideErrors()

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'HIDE_ERROR_WARNING',
    })
  })

  test('showError dispatches SHOW_ERROR with message', () => {
    CreateProjectActions.showError('oops')

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'SHOW_ERROR',
      message: 'oops',
    })
  })

  test('createKeyFromTMXFile dispatches CREATE_KEY_FROM_TMX_FILE', () => {
    CreateProjectActions.createKeyFromTMXFile('msg')

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'CREATE_KEY_FROM_TMX_FILE',
      message: 'msg',
    })
  })

  test('enableAnalyzeButton dispatches ENABLE_ANALYZE_BUTTON', () => {
    CreateProjectActions.enableAnalyzeButton(true)

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'ENABLE_ANALYZE_BUTTON',
      value: true,
    })
  })

  test('updateProjectTemplates dispatches UPDATE_PROJECT_TEMPLATES', () => {
    CreateProjectActions.updateProjectTemplates({
      templates: ['t1'],
      modifiedPropsCurrentProjectTemplate: {a: 1},
    })

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'UPDATE_PROJECT_TEMPLATES',
      templates: ['t1'],
      modifiedPropsCurrentProjectTemplate: {a: 1},
    })
  })
})
