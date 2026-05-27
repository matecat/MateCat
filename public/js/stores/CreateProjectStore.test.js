import AppDispatcher from './AppDispatcher'
import CreateProjectStore from './CreateProjectStore'
import NewProjectConstants from '../constants/NewProjectConstants'

describe('CreateProjectStore', () => {
  const initialProjectData = {
    sourceLang: undefined,
    targetLang: undefined,
    selectedTeam: undefined,
    filtersTemplate: undefined,
    laraStyleGuide: undefined,
  }

  beforeEach(() => {
    CreateProjectStore.projectData = {...initialProjectData}
    jest.clearAllMocks()
  })

  test('updateProject merges incoming data', () => {
    CreateProjectStore.updateProject({
      sourceLang: {id: 'en-US', name: 'English'},
      laraStyleGuide: 'sg-1',
    })

    expect(CreateProjectStore.projectData).toEqual({
      ...initialProjectData,
      sourceLang: {id: 'en-US', name: 'English'},
      laraStyleGuide: 'sg-1',
    })
  })

  test('getSourceLang returns source language id', () => {
    CreateProjectStore.updateProject({
      sourceLang: {id: 'it-IT', name: 'Italian'},
    })

    expect(CreateProjectStore.getSourceLang()).toBe('it-IT')
  })

  test('getTargetLangs returns comma separated target language ids', () => {
    CreateProjectStore.updateProject({
      targetLangs: [
        {id: 'fr-FR', name: 'French'},
        {id: 'de-DE', name: 'German'},
      ],
    })

    expect(CreateProjectStore.getTargetLangs()).toBe('fr-FR,de-DE')
  })

  test('getSourceLangName returns source language name', () => {
    CreateProjectStore.updateProject({
      sourceLang: {id: 'en-US', name: 'English'},
    })

    expect(CreateProjectStore.getSourceLangName()).toBe('English')
  })

  test('getTargetLangsNames returns comma separated target language names', () => {
    CreateProjectStore.updateProject({
      targetLangs: [
        {id: 'fr-FR', name: 'French'},
        {id: 'de-DE', name: 'German'},
      ],
    })

    expect(CreateProjectStore.getTargetLangsNames()).toBe('French,German')
  })

  test('getFiltersTemplate and getLaraStyleGuide return stored values', () => {
    CreateProjectStore.updateProject({
      filtersTemplate: {id: 99, name: 'Default'},
      laraStyleGuide: 'sg-5',
    })

    expect(CreateProjectStore.getFiltersTemplate()).toEqual({
      id: 99,
      name: 'Default',
    })
    expect(CreateProjectStore.getLaraStyleGuide()).toBe('sg-5')
  })

  test('UPDATE_PROJECT_DATA action updates store and emits change with data', () => {
    const emitSpy = jest.spyOn(CreateProjectStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: NewProjectConstants.UPDATE_PROJECT_DATA,
      data: {
        sourceLang: {id: 'en-US', name: 'English'},
        laraStyleGuide: 'sg-action',
      },
    })

    expect(CreateProjectStore.projectData.sourceLang).toEqual({
      id: 'en-US',
      name: 'English',
    })
    expect(CreateProjectStore.getLaraStyleGuide()).toBe('sg-action')
    expect(emitSpy).toHaveBeenCalledWith(
      NewProjectConstants.UPDATE_PROJECT_DATA,
      CreateProjectStore.projectData,
    )
  })

  test('HIDE_ERROR_WARNING action emits change with action type', () => {
    const emitSpy = jest.spyOn(CreateProjectStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: NewProjectConstants.HIDE_ERROR_WARNING,
    })

    expect(emitSpy).toHaveBeenCalledWith(NewProjectConstants.HIDE_ERROR_WARNING)
  })

  test('SHOW_ERROR action updates project and emits message', () => {
    const emitSpy = jest.spyOn(CreateProjectStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: NewProjectConstants.SHOW_ERROR,
      data: {filtersTemplate: {id: 1}},
      message: 'error text',
    })

    expect(CreateProjectStore.getFiltersTemplate()).toEqual({id: 1})
    expect(emitSpy).toHaveBeenCalledWith(
      NewProjectConstants.SHOW_ERROR,
      'error text',
    )
  })

  test('CREATE_KEY_FROM_TMX_FILE action updates project and emits message', () => {
    const emitSpy = jest.spyOn(CreateProjectStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: NewProjectConstants.CREATE_KEY_FROM_TMX_FILE,
      data: {laraStyleGuide: 'sg-key'},
      message: 'created',
    })

    expect(CreateProjectStore.getLaraStyleGuide()).toBe('sg-key')
    expect(emitSpy).toHaveBeenCalledWith(
      NewProjectConstants.CREATE_KEY_FROM_TMX_FILE,
      'created',
    )
  })

  test('ENABLE_ANALYZE_BUTTON action emits action value', () => {
    const emitSpy = jest.spyOn(CreateProjectStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: NewProjectConstants.ENABLE_ANALYZE_BUTTON,
      value: true,
    })

    expect(emitSpy).toHaveBeenCalledWith(
      NewProjectConstants.ENABLE_ANALYZE_BUTTON,
      true,
    )
  })

  test('UPDATE_PROJECT_TEMPLATES action emits templates payload', () => {
    const emitSpy = jest.spyOn(CreateProjectStore, 'emitChange')
    const templates = [{id: 1}, {id: 2}]
    const modifiedPropsCurrentProjectTemplate = ['lara_style']

    AppDispatcher.dispatch({
      actionType: NewProjectConstants.UPDATE_PROJECT_TEMPLATES,
      templates,
      modifiedPropsCurrentProjectTemplate,
    })

    expect(emitSpy).toHaveBeenCalledWith(
      NewProjectConstants.UPDATE_PROJECT_TEMPLATES,
      {
        templates,
        modifiedPropsCurrentProjectTemplate,
      },
    )
  })
})
