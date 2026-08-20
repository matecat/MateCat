import React from 'react'
import {render, act} from '@testing-library/react'
import {SegmentTargetToolbar} from './SegmentTargetToolbar'

const mockToggleCompression = jest.fn()
const mockRegisterHotkey = jest.fn()
let mockCompressed = false
let mockHasCompressiblePhTags = true
let mockStoreListeners = {}
let capturedTagMenuItems = null
let capturedHoverMenuItems = null

const REMOVE_TAGS_SHORTCUT = 'ctrl+shift+z'
const ADD_TAGS_SHORTCUT = 'ctrl+shift+i'

jest.mock('../../utils/shortcuts', () => ({
  Shortcuts: {
    shortCutsKeyType: 'general',
    cattol: {
      events: {
        removeTags: {keystrokes: {general: 'ctrl+shift+z'}},
        addTags: {keystrokes: {general: 'ctrl+shift+i'}},
      },
    },
  },
}))

jest.mock('../../hooks/UseHotKeysComponent', () => ({
  UseHotKeysComponent: ({shortcut, callback}) => {
    mockRegisterHotkey(shortcut, callback)
    return null
  },
}))

// The tag menu is asserted through the items contract it hands to
// DropdownMenu: the real Radix menu only mounts its content when open, which
// is precisely what must not gate the shortcuts.
jest.mock('../common/DropdownMenu/DropdownMenu', () => ({
  DropdownMenu: ({items, triggerMode}) => {
    if (triggerMode !== 'hover') capturedTagMenuItems = items
    else capturedHoverMenuItems = items
    return <div data-testid="dropdown-menu" />
  },
  DROPDOWN_MENU_ITEM_TYPE: {DEFAULT: 'default', CRITICAL: 'critical'},
  DROPDOWN_MENU_TRIGGER_MODE: {CLICK: 'click', HOVER: 'hover'},
  DROPDOWN_SEPARATOR: 'separator',
}))

jest.mock('./utils/DraftMatecatUtils/pcTagUtils', () => ({
  hasCompressiblePhTags: () => mockHasCompressiblePhTags,
}))

jest.mock('../../actions/CatToolActions', () => ({
  togglePhTagsCompressed: () => mockToggleCompression(),
}))

jest.mock('../../stores/CatToolStore', () => ({
  isPhTagsCompressed: () => mockCompressed,
  addListener: jest.fn((event, handler) => {
    mockStoreListeners[event] = handler
  }),
  removeListener: jest.fn((event) => {
    delete mockStoreListeners[event]
  }),
}))

jest.mock('../../constants/CatToolConstants', () => ({
  TOGGLE_PH_TAGS_COMPRESSED: 'TOGGLE_PH_TAGS_COMPRESSED',
}))

jest.mock('./ToolbarFeatures/Ai/LaraStyles', () => ({LaraStyles: () => null}))
jest.mock('./ToolbarFeatures/Ai/AiFeedback', () => ({AiFeedback: () => null}))
jest.mock('./ToolbarFeatures/Ai/AiAlternatives', () => ({
  AiAlternatives: () => null,
}))

const defaultProps = {
  sid: '1-1',
  segment: {segment: 'source text', translation: 'target text'},
  editArea: {
    formatSelection: jest.fn(),
    addMissingSourceTagsToTarget: jest.fn(),
  },
  textHasTags: true,
  removeTagsFromText: jest.fn(),
  missingTagsInTarget: ['<ph id="1"/>'],
  addMissingSourceTagsToTarget: jest.fn(),
}

const renderToolbar = (props = {}) =>
  render(<SegmentTargetToolbar {...defaultProps} {...props} />)

const tagMenuItem = (testId) =>
  capturedTagMenuItems?.find((item) => item && item.testId === testId)

const registeredShortcuts = () =>
  mockRegisterHotkey.mock.calls.map(([shortcut]) => shortcut)

// useResizeObserver observes the toolbar's parent node to decide when
// icons should collapse into a dropdown, which jsdom doesn't implement.
beforeAll(() => {
  global.ResizeObserver = class {
    observe() {}
    unobserve() {}
    disconnect() {}
  }
})

beforeEach(() => {
  mockToggleCompression.mockClear()
  mockRegisterHotkey.mockClear()
  mockCompressed = false
  mockHasCompressiblePhTags = true
  mockStoreListeners = {}
  capturedTagMenuItems = null
  capturedHoverMenuItems = null
  window.config = {}
})

describe('SegmentTargetToolbar', () => {
  describe('keyboard shortcuts', () => {
    test('registers both tag shortcuts without the menu being open', () => {
      renderToolbar()

      expect(registeredShortcuts()).toEqual(
        expect.arrayContaining([REMOVE_TAGS_SHORTCUT, ADD_TAGS_SHORTCUT]),
      )
    })

    test('wires the shortcuts to the matching callbacks', () => {
      const removeTagsFromText = jest.fn()
      const addMissingSourceTagsToTarget = jest.fn()
      renderToolbar({removeTagsFromText, addMissingSourceTagsToTarget})

      const byShortcut = Object.fromEntries(mockRegisterHotkey.mock.calls)
      byShortcut[REMOVE_TAGS_SHORTCUT]()
      byShortcut[ADD_TAGS_SHORTCUT]()

      expect(removeTagsFromText).toHaveBeenCalledTimes(1)
      expect(addMissingSourceTagsToTarget).toHaveBeenCalledTimes(1)
    })

    test('skips the remove shortcut when the target has no tags', () => {
      renderToolbar({textHasTags: false})

      expect(registeredShortcuts()).not.toContain(REMOVE_TAGS_SHORTCUT)
      expect(registeredShortcuts()).toContain(ADD_TAGS_SHORTCUT)
    })

    test('skips the copy shortcut when no tags are missing', () => {
      renderToolbar({missingTagsInTarget: []})

      expect(registeredShortcuts()).not.toContain(ADD_TAGS_SHORTCUT)
      expect(registeredShortcuts()).toContain(REMOVE_TAGS_SHORTCUT)
    })

    test('skips the copy shortcut when there is no edit area', () => {
      renderToolbar({editArea: undefined})

      expect(registeredShortcuts()).not.toContain(ADD_TAGS_SHORTCUT)
    })
  })

  describe('tag menu', () => {
    test('is not rendered when no tag action applies', () => {
      mockHasCompressiblePhTags = false
      renderToolbar({
        textHasTags: false,
        missingTagsInTarget: [],
        showFormatMenu: false,
      })

      expect(capturedTagMenuItems).toBeNull()
    })

    test('keeps the menu open when toggling tag compression', () => {
      renderToolbar()

      expect(tagMenuItem('tags-menu-toggle-compression').keepOpen).toBe(true)
    })

    test('does not keep the menu open for the one-shot actions', () => {
      renderToolbar()

      expect(tagMenuItem('tags-menu-copy-from-source').keepOpen).toBeUndefined()
      expect(tagMenuItem('tags-menu-remove-all').keepOpen).toBeUndefined()
    })

    test('flags remove-all-tags as a critical action', () => {
      renderToolbar()

      expect(tagMenuItem('tags-menu-remove-all').type).toBe('critical')
    })

    test('separates the compression toggle from the tag actions', () => {
      renderToolbar()

      expect(capturedTagMenuItems).toContain('separator')
    })

    test('dispatches the compression toggle on select', () => {
      renderToolbar()

      tagMenuItem('tags-menu-toggle-compression').onClick()

      expect(mockToggleCompression).toHaveBeenCalledTimes(1)
    })

    test('marks the toggle selected while tags are expanded', () => {
      mockCompressed = false
      renderToolbar()

      expect(tagMenuItem('tags-menu-toggle-compression').selected).toBe(true)
    })

    test('clears the selected mark while tags are compressed', () => {
      mockCompressed = true
      renderToolbar()

      expect(tagMenuItem('tags-menu-toggle-compression').selected).toBe(false)
    })

    test('follows store updates to the compression state', () => {
      mockCompressed = false
      renderToolbar()
      expect(tagMenuItem('tags-menu-toggle-compression').selected).toBe(true)

      mockCompressed = true
      act(() => {
        mockStoreListeners.TOGGLE_PH_TAGS_COMPRESSED()
      })

      expect(tagMenuItem('tags-menu-toggle-compression').selected).toBe(false)
    })

    test('stops listening to the store on unmount', () => {
      const {unmount} = renderToolbar()
      expect(mockStoreListeners.TOGGLE_PH_TAGS_COMPRESSED).toBeDefined()

      unmount()

      expect(mockStoreListeners.TOGGLE_PH_TAGS_COMPRESSED).toBeUndefined()
    })

    describe('disabled entries', () => {
      test('disables the toggle when nothing is compressible', () => {
        mockHasCompressiblePhTags = false
        renderToolbar()

        expect(tagMenuItem('tags-menu-toggle-compression').disabled).toBe(true)
      })

      test('disables remove-all-tags when the target has no tags', () => {
        renderToolbar({textHasTags: false})

        expect(tagMenuItem('tags-menu-remove-all').disabled).toBe(true)
      })

      test('disables copy-from-source when no tags are missing', () => {
        renderToolbar({missingTagsInTarget: []})

        expect(tagMenuItem('tags-menu-copy-from-source').disabled).toBe(true)
      })

      test('enables every entry when all actions apply', () => {
        renderToolbar()

        expect(tagMenuItem('tags-menu-toggle-compression').disabled).toBe(false)
        expect(tagMenuItem('tags-menu-copy-from-source').disabled).toBe(false)
        expect(tagMenuItem('tags-menu-remove-all').disabled).toBe(false)
      })
    })
  })

  describe('format menu', () => {
    test('applies uppercase/lowercase/capitalize via the hover format menu', () => {
      const formatSelection = jest.fn()
      renderToolbar({
        showFormatMenu: true,
        editArea: {
          formatSelection,
          addMissingSourceTagsToTarget: jest.fn(),
        },
      })

      const formatItems = capturedHoverMenuItems
      expect(formatItems).toHaveLength(3)

      formatItems[0].onClick()
      formatItems[1].onClick()
      formatItems[2].onClick()

      expect(formatSelection).toHaveBeenCalledWith('uppercase')
      expect(formatSelection).toHaveBeenCalledWith('lowercase')
      expect(formatSelection).toHaveBeenCalledWith('capitalize')
    })

    test('is not rendered when showFormatMenu is false', () => {
      renderToolbar({showFormatMenu: false})
      expect(capturedHoverMenuItems).toBeNull()
    })
  })

  describe('review/quality icons', () => {
    test('opens the quality report link when there are issues to show', () => {
      const openSpy = jest.spyOn(window, 'open').mockImplementation(() => {})
      const {container} = renderToolbar({
        issuesLength: 2,
        qrLink: 'https://example.com/qr',
      })

      const qualityButton = container.querySelector(
        'button[title="Segment Quality Report."]',
      )
      expect(qualityButton).toBeInTheDocument()

      act(() => {
        qualityButton.click()
      })

      expect(openSpy).toHaveBeenCalledWith(
        'https://example.com/qr',
        '_blank',
      )
      openSpy.mockRestore()
    })

    test('does not render the quality report icon without issues or review mode', () => {
      const {container} = renderToolbar({issuesLength: 0})
      expect(
        container.querySelector('button[title="Segment Quality Report."]'),
      ).not.toBeInTheDocument()
    })

    test('triggers lockEditArea when the revise lock icon is clicked in review mode', () => {
      window.config = {isReview: true}
      const lockEditArea = jest.fn()
      const {container} = renderToolbar({lockEditArea})

      const lockButton = container.querySelector(
        'button[title="Highlight text and assign an issue to the selected text."]',
      )
      expect(lockButton).toBeInTheDocument()

      act(() => {
        lockButton.click()
      })

      expect(lockEditArea).toHaveBeenCalled()
    })
  })

  describe('AI features group', () => {
    test('groups Lara AI feature icons into a single dropdown when the engine is Lara', () => {
      window.config = {active_engine: {engine_type: 'Lara'}}
      renderToolbar()

      expect(capturedHoverMenuItems).toHaveLength(3)
      expect(
        capturedHoverMenuItems.every(
          (item) => typeof item.onClick !== 'function',
        ),
      ).toBe(true)
    })
  })
})
