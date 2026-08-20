import './extensionManifest'
import {getExtensionDefault, listExtensionPoints} from './extensionPoints'
import * as names from './extensionPointNames'
import * as defaults from './segmentEditorDefaults'

jest.mock('../stores/SegmentStore', () => ({
  getPrevSegment: jest.fn(),
  getNextSegment: jest.fn(),
}))
jest.mock('../actions/SegmentActions', () => ({registerTab: jest.fn()}))

describe('the extension manifest', () => {
  test('defines exactly the points named in extensionPointNames', () => {
    expect(
      listExtensionPoints()
        .map(({name}) => name)
        .sort(),
    ).toEqual(Object.values(names).sort())
  })

  test('nothing is extended until something registers', () => {
    expect(listExtensionPoints().every(({overridden}) => !overridden)).toBe(
      true,
    )
  })

  test('every point defaults to the matching core implementation', () => {
    expect(getExtensionDefault(names.SEGMENT_CONTEXT_BEFORE)).toBe(
      defaults.getContextBefore,
    )
    expect(getExtensionDefault(names.SEGMENT_CONTEXT_AFTER)).toBe(
      defaults.getContextAfter,
    )
    expect(getExtensionDefault(names.SEGMENT_ID_BEFORE)).toBe(
      defaults.getIdBefore,
    )
    expect(getExtensionDefault(names.SEGMENT_ID_AFTER)).toBe(
      defaults.getIdAfter,
    )
    expect(getExtensionDefault(names.SEGMENT_FOOTER_TABS)).toBe(
      defaults.registerFooterTabs,
    )
  })

  // The points whose default is small enough to live in the manifest have no
  // module to compare identity against, so assert the behaviour instead.
  describe('the defaults declared inline', () => {
    const callDefault = (name, ...args) => getExtensionDefault(name)(...args)

    test('a segment is ice when it carries the ice_locked flag', () => {
      expect(callDefault(names.SEGMENT_IS_ICE, {ice_locked: true})).toBe(true)
      expect(callDefault(names.SEGMENT_IS_ICE, {ice_locked: false})).toBe(false)
    })

    test('a segment has a note from notes, a context group, or metadata', () => {
      expect(callDefault(names.SEGMENT_HAS_NOTE, {notes: 'a note'})).toBe(true)
      expect(
        callDefault(names.SEGMENT_HAS_NOTE, {
          context_groups: {context_json: '{}'},
        }),
      ).toBe(true)
      expect(callDefault(names.SEGMENT_HAS_NOTE, {metadata: [1]})).toBe(true)
      expect(callDefault(names.SEGMENT_HAS_NOTE, {})).toBe(false)
    })

    test('a segment file id is the segment id_file', () => {
      expect(callDefault(names.SEGMENT_FILE_ID, {id_file: 7})).toBe(7)
    })

    test('parsing files leaves them untouched', () => {
      const files = [{id: 1}]
      expect(callDefault(names.FILES_PARSE, files)).toBe(files)
    })

    test('a file has instructions when its metadata says so', () => {
      expect(
        callDefault(names.FILE_HAS_INSTRUCTIONS, {
          metadata: {instructions: 'x'},
        }),
      ).toBe('x')
      expect(callDefault(names.FILE_HAS_INSTRUCTIONS, null)).toBeFalsy()
    })

    test('core declares no character counter preset', () => {
      expect(callDefault(names.CHARS_COUNTER_MODE)).toBeUndefined()
    })

    test('no link is allowed to redirect', () => {
      expect(callDefault(names.LINK_ALLOWED_REDIRECT, 'https://x.test')).toBe(
        false,
      )
    })
  })

  // Names describe a capability in the domain. A name that instead described
  // who replaces it would both leak and rot.
  test('every name is a dotted capability name', () => {
    Object.values(names).forEach((name) => {
      expect(name).toMatch(/^[a-z][a-zA-Z]*\.[a-z][a-zA-Z]*$/)
    })
  })
})
