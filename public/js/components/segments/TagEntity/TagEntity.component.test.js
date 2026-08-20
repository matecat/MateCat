import React from 'react'
import {render, screen, fireEvent, act} from '@testing-library/react'

import TagEntity from './TagEntity.component'
import SegmentStore from '../../../stores/SegmentStore'
import CatToolStore from '../../../stores/CatToolStore'
import SegmentActions from '../../../actions/SegmentActions'
import SegmentConstants from '../../../constants/SegmentConstants'
import CatToolConstants from '../../../constants/CatToolConstants'
import EditAreaConstants from '../../../constants/EditAreaConstants'
import SearchUtils from '../../header/cattol/search/searchUtils'

jest.mock('../../../stores/SegmentStore', () => ({
  addListener: jest.fn(),
  removeListener: jest.fn(),
}))

jest.mock('../../../stores/CatToolStore', () => ({
  isPhTagsCompressed: jest.fn(() => false),
  addListener: jest.fn(),
  removeListener: jest.fn(),
}))

jest.mock('../../../actions/SegmentActions', () => ({
  setIsCurrentSearchOccurrenceTag: jest.fn(),
  highlightTags: jest.fn(),
}))

jest.mock('../../header/cattol/search/searchUtils', () => ({
  __esModule: true,
  default: {
    getSearchRegExp: jest.fn((text) => new RegExp(`(${text})`, 'gi')),
  },
}))

jest.mock('../../common/Tooltip', () => ({
  __esModule: true,
  default: ({children, content}) => (
    <div data-testid="tooltip">
      <div data-testid="tooltip-content">{content}</div>
      {children}
    </div>
  ),
}))

const makeContentState = (entityData) => ({
  getEntity: jest.fn(() => ({data: entityData})),
})

const baseProps = (overrides = {}) => ({
  entityKey: '1',
  offsetkey: '1-0-0',
  contentState: makeContentState({
    id: 'e1',
    placeholder: 'PH',
    index: 0,
    name: 'ph',
  }),
  getSearchParams: jest.fn(() => ({active: false})),
  getUpdatedSegmentInfo: jest.fn(() => ({
    segmentOpened: true,
    tagMismatch: null,
    missingTagsInTarget: [],
    openSplit: false,
  })),
  isTarget: false,
  isRTL: false,
  sid: 1,
  start: 0,
  end: 1,
  onClick: jest.fn(),
  children: <span>{'<ph/>'}</span>,
  ...overrides,
})

afterEach(() => {
  jest.clearAllMocks()
})

describe('TagEntity constructor', () => {
  test('marks tooltipAvailable true for tags with showTooltip enabled (ph)', () => {
    const instance = new TagEntity(baseProps())
    expect(instance.state.tooltipAvailable).toBe(true)
  })

  test('marks tooltipAvailable false for tags with showTooltip disabled', () => {
    const instance = new TagEntity(
      baseProps({
        contentState: makeContentState({id: 'e2', name: 'gSc'}),
      }),
    )
    expect(instance.state.tooltipAvailable).toBe(false)
  })
})

describe('TagEntity.selectCorrectStyle', () => {
  test('uses the LTR style by default', () => {
    const instance = new TagEntity(baseProps())
    expect(instance.selectCorrectStyle()).toContain('tag-selfclosed tag-ph')
  })

  test('uses the RTL style when isRTL and a styleRTL is defined', () => {
    const instance = new TagEntity(
      baseProps({
        isRTL: true,
        contentState: makeContentState({id: 'g1', name: 'g'}),
      }),
    )
    expect(instance.selectCorrectStyle()).toContain('tag-close')
  })

  test('adds tag-inactive when the segment is not opened', () => {
    const instance = new TagEntity(
      baseProps({
        getUpdatedSegmentInfo: jest.fn(() => ({segmentOpened: false})),
      }),
    )
    expect(instance.selectCorrectStyle()).toContain('tag-inactive')
  })

  test('adds tag-clicked when the clicked tag id/text match this entity', () => {
    const instance = new TagEntity(baseProps())
    expect(instance.selectCorrectStyle('e1', 'PH')).toContain('tag-clicked')
  })

  test('does not add tag-clicked when the clicked tag id does not match', () => {
    const instance = new TagEntity(baseProps())
    expect(instance.selectCorrectStyle('other', 'PH')).not.toContain(
      'tag-clicked',
    )
  })
})

describe('TagEntity.getChildrenContent', () => {
  test('shows the index counter and children for an open ph tag', () => {
    const instance = new TagEntity(baseProps())
    const content = instance.getChildrenContent(2, 'ph', undefined)
    const {container} = render(<div>{content}</div>)
    expect(container.querySelector('.index-counter').textContent).toBe('3')
  })

  test('hides children content when ph tags are compressed', () => {
    const instance = new TagEntity(baseProps())
    instance.state.phTagsCompressed = true
    const content = instance.getChildrenContent(0, 'ph', undefined)
    const {container} = render(<div>{content}</div>)
    expect(container.querySelector('.index-counter')).toBeTruthy()
    expect(container.textContent).toBe('1')
  })

  test('a closing pc tag never shows its content, even uncompressed', () => {
    const instance = new TagEntity(baseProps())
    const content = instance.getChildrenContent(0, 'ph', 'close')
    const {container} = render(<div>{content}</div>)
    expect(container.textContent).toBe('1')
  })

  test('returns raw children for non-ph tags', () => {
    const instance = new TagEntity(baseProps())
    const content = instance.getChildrenContent(-1, 'g', undefined)
    expect(content).toBe(instance.props.children)
  })
})

describe('TagEntity.markSearch', () => {
  test('returns the raw text when search is not active', () => {
    const instance = new TagEntity(baseProps())
    const result = instance.markSearch('hello', {active: false})
    expect(result).toBe('hello')
  })

  test('wraps matches and flags the current occurrence via SegmentActions', () => {
    const instance = new TagEntity(baseProps({start: 0, end: 5}))
    const searchParams = {
      active: true,
      currentActive: true,
      textToReplace: 'ell',
      params: {ingnoreCase: true, exactMatch: false},
      occurrences: [{searchProgressiveIndex: 1, matchPosition: 0}],
      currentInSearchIndex: 1,
    }

    const result = instance.markSearch('hello', searchParams)

    expect(SegmentActions.setIsCurrentSearchOccurrenceTag).toHaveBeenCalledWith(
      true,
    )
    expect(SearchUtils.getSearchRegExp).toHaveBeenCalledWith('ell', true, false)
    expect(Array.isArray(result)).toBe(true)
  })

  test('does not flag current occurrence when match position is outside the tag range', () => {
    const instance = new TagEntity(baseProps({start: 10, end: 15}))
    const searchParams = {
      active: true,
      currentActive: true,
      textToReplace: 'ell',
      params: {ingnoreCase: true, exactMatch: false},
      occurrences: [{searchProgressiveIndex: 1, matchPosition: 0}],
      currentInSearchIndex: 1,
    }

    instance.markSearch('hello', searchParams)

    expect(
      SegmentActions.setIsCurrentSearchOccurrenceTag,
    ).not.toHaveBeenCalled()
  })
})

describe('TagEntity search param listeners', () => {
  test('addSearchParams ignores updates for a different segment', () => {
    const getSearchParams = jest.fn(() => ({active: true, isTarget: true}))
    const instance = new TagEntity(
      baseProps({sid: 1, isTarget: true, getSearchParams}),
    )
    jest.spyOn(instance, 'setState')

    instance.addSearchParams(2)

    expect(instance.setState).not.toHaveBeenCalled()
  })

  test('addSearchParams updates state when the search targets this segment/side', () => {
    const searchParams = {active: true, isTarget: true}
    const getSearchParams = jest.fn(() => searchParams)
    const instance = new TagEntity(
      baseProps({sid: 1, isTarget: true, getSearchParams}),
    )
    jest.spyOn(instance, 'setState')

    instance.addSearchParams(1)

    expect(instance.setState).toHaveBeenCalledWith({searchParams})
  })

  test('updateSearchParams ignores when search is not active for this segment', () => {
    const instance = new TagEntity(
      baseProps({
        sid: 1,
        getSearchParams: jest.fn(() => ({active: false})),
      }),
    )
    jest.spyOn(instance, 'setState')

    instance.updateSearchParams(1, 3)

    expect(instance.setState).not.toHaveBeenCalled()
  })

  test('updateSearchParams updates the current search index when active', () => {
    const instance = new TagEntity(baseProps({sid: 1}))
    instance.state.searchParams = {active: true}
    const getSearchParams = jest.fn(() => ({active: true}))
    instance.props.getSearchParams = getSearchParams
    jest.spyOn(instance, 'setState')

    instance.updateSearchParams(1, 5)

    expect(instance.setState).toHaveBeenCalledWith({
      searchParams: {active: true, currentInSearchIndex: 5},
    })
  })

  test('removeSearchParams refreshes state only when search was active', () => {
    const searchParams = {active: false}
    const instance = new TagEntity(
      baseProps({getSearchParams: jest.fn(() => searchParams)}),
    )
    instance.state.searchParams = {active: false}
    jest.spyOn(instance, 'setState')

    instance.removeSearchParams()

    expect(instance.setState).not.toHaveBeenCalled()

    instance.state.searchParams = {active: true}
    instance.removeSearchParams()

    expect(instance.setState).toHaveBeenCalledWith({searchParams})
  })
})

describe('TagEntity.onPhTagsCompressedToggle', () => {
  test('reads the compressed flag from CatToolStore', () => {
    CatToolStore.isPhTagsCompressed.mockReturnValue(true)
    const instance = new TagEntity(baseProps())
    jest.spyOn(instance, 'setState')

    instance.onPhTagsCompressedToggle()

    expect(instance.setState).toHaveBeenCalledWith({phTagsCompressed: true})
  })
})

describe('TagEntity lifecycle', () => {
  test('componentDidMount registers store listeners', () => {
    const instance = new TagEntity(baseProps())
    instance.setState = jest.fn()
    instance.componentDidMount()

    expect(SegmentStore.addListener).toHaveBeenCalledWith(
      SegmentConstants.SET_SEGMENT_WARNINGS,
      instance.updateTagWarningStyleDebounced,
    )
    expect(SegmentStore.addListener).toHaveBeenCalledWith(
      SegmentConstants.HIGHLIGHT_TAGS,
      instance.highlightTags,
    )
    expect(SegmentStore.addListener).toHaveBeenCalledWith(
      EditAreaConstants.EDIT_AREA_CHANGED,
      instance.updateTagStyleDebounced,
    )
    expect(SegmentStore.addListener).toHaveBeenCalledWith(
      SegmentConstants.ADD_SEARCH_RESULTS,
      instance.addSearchParams,
    )
    expect(SegmentStore.addListener).toHaveBeenCalledWith(
      SegmentConstants.ADD_CURRENT_SEARCH,
      instance.updateSearchParams,
    )
    expect(SegmentStore.addListener).toHaveBeenCalledWith(
      SegmentConstants.REMOVE_SEARCH_RESULTS,
      instance.removeSearchParams,
    )
    expect(SegmentStore.addListener).toHaveBeenCalledWith(
      SegmentConstants.FOCUS_TAGS,
      instance.focusTag,
    )
    expect(CatToolStore.addListener).toHaveBeenCalledWith(
      CatToolConstants.TOGGLE_PH_TAGS_COMPRESSED,
      instance.onPhTagsCompressedToggle,
    )
  })

  test('componentWillUnmount removes all listeners added in componentDidMount', () => {
    const instance = new TagEntity(baseProps())
    instance.componentWillUnmount()

    expect(SegmentStore.removeListener).toHaveBeenCalledWith(
      SegmentConstants.SET_SEGMENT_WARNINGS,
      instance.updateTagWarningStyleDebounced,
    )
    expect(SegmentStore.removeListener).toHaveBeenCalledWith(
      SegmentConstants.FOCUS_TAGS,
      instance.focusTag,
    )
    expect(CatToolStore.removeListener).toHaveBeenCalledWith(
      CatToolConstants.TOGGLE_PH_TAGS_COMPRESSED,
      instance.onPhTagsCompressedToggle,
    )
  })

  test('shouldComponentUpdate always returns true', () => {
    const instance = new TagEntity(baseProps())
    expect(instance.shouldComponentUpdate()).toBe(true)
  })

  test('componentDidUpdate recomputes shouldTooltipOnHover on entity change', () => {
    const instance = new TagEntity(baseProps())
    instance.tagRef = {
      querySelector: jest.fn(() => ({offsetWidth: 10, scrollWidth: 20})),
    }
    instance.state.shouldTooltipOnHover = false
    jest.spyOn(instance, 'setState')

    instance.componentDidUpdate({entitykey: 'previous'})

    expect(instance.setState).toHaveBeenCalledWith({shouldTooltipOnHover: true})
  })
})

describe('TagEntity.onClickBound', () => {
  test('invokes the onClick prop with start, end and entity name', () => {
    const onClick = jest.fn()
    const instance = new TagEntity(
      baseProps({onClick, start: 3, end: 8}),
    )

    instance.onClickBound()

    expect(onClick).toHaveBeenCalledWith(3, 8, 'ph')
  })
})

describe('TagEntity.highlightTags', () => {
  test('turns off the clicked style when a different tag id is targeted', () => {
    const instance = new TagEntity(baseProps())
    instance.state.clicked = true
    jest.spyOn(instance, 'setState')

    instance.highlightTags('other-id', 'ph', 'some-key')

    expect(instance.setState).toHaveBeenCalledWith(
      expect.objectContaining({clicked: false}),
    )
  })

  test('turns on the clicked style when this entity is the trigger', () => {
    const instance = new TagEntity(baseProps())

    jest.spyOn(instance, 'setState')

    instance.highlightTags('e1', 'PH', '1')

    expect(instance.setState).toHaveBeenCalledWith(
      expect.objectContaining({clicked: true}),
    )
  })

  test('turns on the clicked style for a matching sibling entity', () => {
    const instance = new TagEntity(baseProps())
    jest.spyOn(instance, 'setState')

    instance.highlightTags('e1', 'PH', 'different-key')

    expect(instance.setState).toHaveBeenCalledWith(
      expect.objectContaining({clicked: true}),
    )
  })

  test('does nothing for an unrelated tag/entity combination', () => {
    const instance = new TagEntity(baseProps())
    jest.spyOn(instance, 'setState')

    instance.highlightTags('other-id', 'OTHER', 'different-key')

    expect(instance.setState).not.toHaveBeenCalled()
  })
})

describe('TagEntity.updateTagStyle', () => {
  test('skips the update when this is a source tag update for the target side', () => {
    const instance = new TagEntity(baseProps({isTarget: false}))
    jest.spyOn(instance, 'setState')

    instance.updateTagStyle(1, true)

    expect(instance.setState).not.toHaveBeenCalled()
  })

  test('updates state when the computed style differs', () => {
    const instance = new TagEntity(baseProps({isTarget: true}))
    instance.state.tagStyle = 'stale-style'
    jest.spyOn(instance, 'setState')

    instance.updateTagStyle(1, false)

    expect(instance.setState).toHaveBeenCalledWith(
      expect.objectContaining({tagStyle: expect.any(String)}),
    )
  })
})

describe('TagEntity.updateTagWarningStyle', () => {
  test('sets the new warning style when it differs from the previous one', () => {
    const instance = new TagEntity(
      baseProps({
        isTarget: true,
        getUpdatedSegmentInfo: jest.fn(() => ({
          segmentOpened: true,
          tagMismatch: {target: ['x'], source: [], order: []},
        })),
        contentState: makeContentState({
          id: 'e1',
          name: 'ph',
          encodedText: 'x',
        }),
      }),
    )
    jest.spyOn(instance, 'setState')

    instance.updateTagWarningStyle()

    expect(instance.setState).toHaveBeenCalledWith({
      tagWarningStyle: 'tag-mismatch-error',
    })
  })
})

describe('TagEntity.focusTag', () => {
  test('resets focus state when no tags are selected', () => {
    const instance = new TagEntity(baseProps())
    jest.spyOn(instance, 'setState')

    instance.focusTag({tagsSelected: []})

    expect(instance.setState).toHaveBeenCalledWith({focused: false})
  })

  test('updates focus synchronously when skipTmOut is set', () => {
    const instance = new TagEntity(baseProps())
    instance.focusedState.current = {skipTmOut: true}
    jest.spyOn(instance, 'setState')

    instance.focusTag({tagsSelected: [{entityKey: '1'}]})

    expect(instance.setState).toHaveBeenCalledWith({focused: true})
  })

  test('defers focus update with a timeout otherwise', () => {
    jest.useFakeTimers()
    const instance = new TagEntity(baseProps())
    jest.spyOn(instance, 'setState')

    instance.focusTag({tagsSelected: [{entityKey: 'not-this-one'}]})

    expect(instance.setState).not.toHaveBeenCalled()
    act(() => {
      jest.advanceTimersByTime(100)
    })
    expect(instance.setState).toHaveBeenCalledWith({focused: false})
    jest.useRealTimers()
  })

  test('clears a pending timeout before scheduling a new one', () => {
    jest.useFakeTimers()
    const clearSpy = jest.spyOn(global, 'clearTimeout')
    const instance = new TagEntity(baseProps())
    instance.focusedState.current = {tmOut: 123}

    instance.focusTag({tagsSelected: [{entityKey: '1'}]})

    expect(clearSpy).toHaveBeenCalledWith(123)
    jest.useRealTimers()
    clearSpy.mockRestore()
  })
})

describe('TagEntity render', () => {
  test('renders the tag content and triggers highlightTags on click', () => {
    jest.useFakeTimers()
    render(
      <TagEntity {...baseProps()}>
        <span>{'<ph/>'}</span>
      </TagEntity>,
    )

    const tagSpan = document.querySelector('.tag-container .tag')
    expect(tagSpan).toBeTruthy()

    fireEvent.click(tagSpan)
    act(() => {
      jest.runOnlyPendingTimers()
    })

    expect(SegmentActions.highlightTags).toHaveBeenCalledWith(
      'e1',
      'PH',
      '1',
    )
    jest.useRealTimers()
  })

  test('does not schedule highlightTags when a split is open', () => {
    jest.useFakeTimers()
    render(
      <TagEntity
        {...baseProps({
          getUpdatedSegmentInfo: jest.fn(() => ({
            segmentOpened: true,
            openSplit: true,
          })),
        })}
      >
        <span>{'<ph/>'}</span>
      </TagEntity>,
    )

    fireEvent.click(document.querySelector('.tag-container .tag'))
    act(() => {
      jest.runOnlyPendingTimers()
    })

    expect(SegmentActions.highlightTags).not.toHaveBeenCalled()
    jest.useRealTimers()
  })

  test('renders search matches with a hidden duplicate when search is active', () => {
    render(
      <TagEntity
        {...baseProps({
          getSearchParams: jest.fn(() => ({
            active: true,
            currentActive: false,
            textToReplace: 'ph',
            params: {ingnoreCase: true, exactMatch: false},
            occurrences: [],
            currentInSearchIndex: null,
          })),
        })}
      >
        {[{props: {text: '<ph/>'}}]}
      </TagEntity>,
    )

    expect(document.querySelector('.tag-container')).toBeTruthy()
  })
})
