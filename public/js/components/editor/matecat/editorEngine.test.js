import {isNewEditorActive, isNewEditorEnabled} from './editorEngine'

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

describe('isNewEditorActive (build flag plus the user editor_engine choice)', () => {
  const original = process.env.ENABLE_NEW_EDITOR
  afterEach(() => {
    if (original === undefined) delete process.env.ENABLE_NEW_EDITOR
    else process.env.ENABLE_NEW_EDITOR = original
  })

  test.each([
    ['the user never picked one', {}],
    ['there is no user metadata at all', undefined],
    ['the user picked the new editor', {editor_engine: 'cm6'}],
  ])('new editor when the build allows it and %s', (_, metadata) => {
    process.env.ENABLE_NEW_EDITOR = 'true'
    expect(isNewEditorActive(metadata)).toBe(true)
  })

  test('previous editor when the user switched back to it', () => {
    process.env.ENABLE_NEW_EDITOR = 'true'
    expect(isNewEditorActive({editor_engine: 'draft'})).toBe(false)
  })

  test('previous editor when the build switches the new one off, whatever the user picked', () => {
    process.env.ENABLE_NEW_EDITOR = 'false'
    expect(isNewEditorActive({editor_engine: 'cm6'})).toBe(false)
    expect(isNewEditorActive({})).toBe(false)
  })
})
