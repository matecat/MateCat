import AppDispatcher from './AppDispatcher'
import ApplicationStore from './ApplicationStore'
import ApplicationConstants from '../constants/ApplicationConstants'

describe('ApplicationStore', () => {
  beforeEach(() => {
    ApplicationStore.languages = []
    jest.clearAllMocks()
  })

  test('setLanguages/getLanguages stores and returns languages', () => {
    const languages = [{code: 'en-US', name: 'English', plurals: 2}]
    ApplicationStore.setLanguages(languages)

    expect(ApplicationStore.getLanguages()).toBe(languages)
  })

  test('getLanguageNameFromLocale returns the matching language name', () => {
    ApplicationStore.setLanguages([{code: 'it-IT', name: 'Italian'}])

    expect(ApplicationStore.getLanguageNameFromLocale('it-IT')).toBe('Italian')
  })

  test('getLanguageNameFromLocale returns empty string when not found', () => {
    ApplicationStore.setLanguages([])

    expect(ApplicationStore.getLanguageNameFromLocale('xx-XX')).toBe('')
  })

  test('getPluralRulesForLocale returns the matching plural rules', () => {
    ApplicationStore.setLanguages([{code: 'it-IT', plurals: 2}])

    expect(ApplicationStore.getPluralRulesForLocale('it-IT')).toBe(2)
  })

  test('getPluralRulesForLocale returns null when not found', () => {
    ApplicationStore.setLanguages([])

    expect(ApplicationStore.getPluralRulesForLocale('xx-XX')).toBeNull()
  })

  test('SET_LANGUAGES action updates store and emits change', () => {
    const emitSpy = jest.spyOn(ApplicationStore, 'emitChange')
    const languages = [{code: 'fr-FR', name: 'French'}]

    AppDispatcher.dispatch({
      actionType: ApplicationConstants.SET_LANGUAGES,
      languages,
    })

    expect(ApplicationStore.getLanguages()).toBe(languages)
    expect(emitSpy).toHaveBeenCalledWith(
      ApplicationConstants.SET_LANGUAGES,
      languages,
    )
  })
})
