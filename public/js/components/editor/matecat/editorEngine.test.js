import {isNewEditorEnabled} from './editorEngine'

describe('isNewEditorEnabled (ENABLE_NEW_EDITOR build flag)', () => {
  const original = process.env.ENABLE_NEW_EDITOR
  afterEach(() => {
    if (original === undefined) delete process.env.ENABLE_NEW_EDITOR
    else process.env.ENABLE_NEW_EDITOR = original
  })

  test('on when the flag is missing, as in a build from a config without it', () => {
    delete process.env.ENABLE_NEW_EDITOR
    expect(isNewEditorEnabled()).toBe(true)
  })

  test('on when the build injects true', () => {
    process.env.ENABLE_NEW_EDITOR = 'true'
    expect(isNewEditorEnabled()).toBe(true)
  })

  test('off when the build injects false', () => {
    process.env.ENABLE_NEW_EDITOR = 'false'
    expect(isNewEditorEnabled()).toBe(false)
  })
})
