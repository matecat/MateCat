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

  // Names describe a capability in the domain. A name that instead described
  // who replaces it would both leak and rot.
  test('every name is a dotted capability name', () => {
    Object.values(names).forEach((name) => {
      expect(name).toMatch(/^[a-z][a-zA-Z]*\.[a-z][a-zA-Z]*$/)
    })
  })
})
