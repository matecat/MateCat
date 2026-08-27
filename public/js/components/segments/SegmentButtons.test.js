import React from 'react'
import {render, fireEvent, waitFor, act} from '@testing-library/react'
import '@testing-library/jest-dom'

import SegmentButton from './SegmentButtons'
import SegmentStore from '../../stores/SegmentStore'
import CatToolStore from '../../stores/CatToolStore'
import SegmentFilter from '../header/cattol/segment_filter/segment_filter'
import SegmentUtils from '../../utils/segmentUtils'
import SegmentActions from '../../actions/SegmentActions'
import UserStore from '../../stores/UserStore'
import CommonUtils from '../../utils/commonUtils'
import {
  decodePlaceholdersToPlainText,
  removeTagsFromText,
} from './utils/DraftMatecatUtils/tagUtils'
import {useHotkeys} from 'react-hotkeys-hook'

jest.mock('../../stores/SegmentStore', () => ({
  getNextSegment: jest.fn(),
}))

jest.mock('../../stores/CatToolStore', () => ({
  addListener: jest.fn(),
  removeListener: jest.fn(),
}))

jest.mock('../header/cattol/segment_filter/segment_filter', () => ({
  enabled: jest.fn(() => false),
  filtering: jest.fn(() => false),
  open: false,
  getStoredState: jest.fn(() => ({})),
  goToNextRepetition: jest.fn(),
  goToNextRepetitionGroup: jest.fn(),
}))

jest.mock('../../utils/segmentUtils', () => ({
  checkCurrentSegmentTPEnabled: jest.fn(() => false),
  isIceSegment: jest.fn(() => false),
}))

jest.mock('../../constants/CatToolConstants', () => ({
  SET_PROGRESS: 'SET_PROGRESS',
}))

jest.mock('./utils/DraftMatecatUtils/tagUtils', () => ({
  decodePlaceholdersToPlainText: jest.fn((str) => str),
  removeTagsFromText: jest.fn((str) => str),
}))

jest.mock('../../actions/SegmentActions', () => ({
  clickOnTranslatedButton: jest.fn(),
  clickOnApprovedButton: jest.fn(),
  replaceEditAreaTextContent: jest.fn(),
  setSegmentAsTagged: jest.fn(),
  startSegmentTagProjection: jest.fn(),
}))

jest.mock('../../stores/UserStore', () => ({
  getUser: jest.fn(() => null),
}))

jest.mock('../../utils/commonUtils', () => ({
  dispatchAnalyticsEvents: jest.fn(),
}))

jest.mock('../../utils/Utils', () => ({
  isMacOS: jest.fn(() => false),
}))

jest.mock('react-hotkeys-hook', () => ({
  useHotkeys: jest.fn(),
}))

const renderButton = (props = {}) => {
  const defaultProps = {
    segment: {sid: '1', contributions: {}},
    disabled: false,
    isReview: false,
  }
  return render(<SegmentButton {...defaultProps} {...props} />)
}

describe('SegmentButtons', () => {
  beforeEach(() => {
    window.config = {
      isReview: false,
      revisionNumber: 1,
      status_labels: {TRANSLATED: 'Translate', APPROVED: 'Approve'},
      id_project: 100,
    }
    SegmentStore.getNextSegment.mockReturnValue(undefined)
    SegmentUtils.checkCurrentSegmentTPEnabled.mockReturnValue(false)
    SegmentUtils.isIceSegment.mockReturnValue(false)
    SegmentFilter.enabled.mockReturnValue(false)
    SegmentFilter.filtering.mockReturnValue(false)
    SegmentFilter.open = false
    sessionStorage.clear()
  })

  test('renders nothing when the segment is muted', () => {
    const {container} = renderButton({segment: {sid: '1', muted: true}})
    expect(container).toBeEmptyDOMElement()
  })

  test('renders the translate confirm button by default', () => {
    const {container} = renderButton()
    expect(
      container.querySelector('#segment-1-buttons'),
    ).toBeInTheDocument()
    expect(container.textContent).toContain('Translate')
    expect(
      container.querySelector('[title="Translate and go to next untranslated"]'),
    ).not.toBeInTheDocument()
  })

  test('shows the "go to next untranslated" button when eligible and confirms translation on click', async () => {
    SegmentStore.getNextSegment.mockReturnValue({
      status: 'TRANSLATED',
      autopropagated_from: 0,
    })
    const segment = {sid: '1', contributions: {}, decodedTranslation: ''}
    const {container} = renderButton({segment})
    const nextButton = container.querySelector(
      '[title="Translate and go to next untranslated"]',
    )
    expect(nextButton).toBeInTheDocument()

    fireEvent.click(nextButton)

    await waitFor(() =>
      expect(SegmentActions.clickOnTranslatedButton).toHaveBeenCalledWith(
        segment,
        true,
      ),
    )
    expect(CommonUtils.dispatchAnalyticsEvents).toHaveBeenCalled()
  })

  test('shows the "Guess tags" button when TP is enabled and starts tag projection on mismatch', async () => {
    SegmentUtils.checkCurrentSegmentTPEnabled.mockReturnValue(true)
    const segment = {
      sid: '1',
      decodedTranslation: 'hello',
      contributions: {matches: []},
    }
    const {container, getByText} = renderButton({segment})
    const guessButton = getByText('Guess tags')
    expect(guessButton).toBeInTheDocument()

    fireEvent.click(guessButton)

    await waitFor(() =>
      expect(SegmentActions.startSegmentTagProjection).toHaveBeenCalledWith(
        '1',
      ),
    )
    expect(SegmentActions.replaceEditAreaTextContent).not.toHaveBeenCalled()
  })

  test('replaces edit area content and marks segment as tagged when the MT contribution matches', async () => {
    SegmentUtils.checkCurrentSegmentTPEnabled.mockReturnValue(true)
    const segment = {
      sid: '1',
      decodedTranslation: 'hello world',
      contributions: {
        matches: [{translation: 'hello world', match: 'MT'}],
      },
    }
    const {getByText} = renderButton({segment})
    fireEvent.click(getByText('Guess tags'))

    await waitFor(() =>
      expect(SegmentActions.setSegmentAsTagged).toHaveBeenCalledWith('1'),
    )
    expect(SegmentActions.replaceEditAreaTextContent).toHaveBeenCalledWith(
      '1',
      'hello world',
    )
    expect(SegmentActions.startSegmentTagProjection).not.toHaveBeenCalled()
  })

  test('renders the approve confirm button in review mode', () => {
    const {container} = renderButton({isReview: true})
    expect(container.textContent).toContain('Approve')
  })

  test('shows "go to next translated" button in review mode and confirms approval on click', async () => {
    SegmentStore.getNextSegment.mockReturnValue({
      status: 'APPROVED',
      autopropagated_from: 0,
      revision_number: null,
    })
    const segment = {sid: '1', contributions: {}}
    const {container} = renderButton({segment, isReview: true})
    const nextButton = container.querySelector(
      '[title="Revise and go to next translated"]',
    )
    expect(nextButton).toBeInTheDocument()

    fireEvent.click(nextButton)

    await waitFor(() =>
      expect(SegmentActions.clickOnApprovedButton).toHaveBeenCalledWith(
        segment,
        true,
      ),
    )
  })

  test('shows repetition buttons in translate mode when filtering repetitions and triggers filter navigation', async () => {
    SegmentFilter.enabled.mockReturnValue(true)
    SegmentFilter.filtering.mockReturnValue(true)
    SegmentFilter.open = true
    SegmentFilter.getStoredState.mockReturnValue({
      reactState: {samplingType: 'repetitions'},
    })
    const {getByTitle} = renderButton()

    fireEvent.click(getByTitle('Translate and go to next repetition'))
    await waitFor(() =>
      expect(SegmentFilter.goToNextRepetition).toHaveBeenCalledWith(
        'translated',
      ),
    )

    fireEvent.click(getByTitle('Translate and go to next repetition group'))
    await waitFor(() =>
      expect(SegmentFilter.goToNextRepetitionGroup).toHaveBeenCalledWith(
        'translated',
      ),
    )
  })

  test('registers hotkeys and CatToolStore progress listener on mount, and cleans up on unmount', () => {
    const {unmount} = renderButton()
    expect(useHotkeys).toHaveBeenCalledTimes(2)
    expect(CatToolStore.addListener).toHaveBeenCalledWith(
      'SET_PROGRESS',
      expect.any(Function),
    )
    unmount()
    expect(CatToolStore.removeListener).toHaveBeenCalledWith(
      'SET_PROGRESS',
      expect.any(Function),
    )
  })

  test('confirms translation via the translate hotkey when not disabled', async () => {
    jest.useFakeTimers()
    const segment = {sid: '1', contributions: {}, decodedTranslation: ''}
    renderButton({segment, disabled: false})

    const translateHotkeyCall = useHotkeys.mock.calls[1]
    const hotkeyHandler = translateHotkeyCall[1]

    act(() => {
      hotkeyHandler({})
      jest.advanceTimersByTime(200)
    })

    expect(SegmentActions.clickOnTranslatedButton).toHaveBeenCalledWith(
      segment,
      false,
    )
    jest.useRealTimers()
  })

  test('does not trigger the hotkey action when disabled', () => {
    jest.useFakeTimers()
    renderButton({disabled: true})

    const translateHotkeyCall = useHotkeys.mock.calls[1]
    const hotkeyHandler = translateHotkeyCall[1]

    act(() => {
      hotkeyHandler({})
      jest.advanceTimersByTime(200)
    })

    expect(SegmentActions.clickOnTranslatedButton).not.toHaveBeenCalled()
    jest.useRealTimers()
  })

  test('confirms approval via the nextUntranslated hotkey when in review mode', () => {
    jest.useFakeTimers()
    window.config.isReview = true
    const segment = {sid: '1', contributions: {}}
    renderButton({segment, isReview: true, disabled: false})

    const nextUntranslatedHotkeyCall = useHotkeys.mock.calls[0]
    const hotkeyHandler = nextUntranslatedHotkeyCall[1]

    act(() => {
      hotkeyHandler({})
      jest.advanceTimersByTime(200)
    })

    expect(SegmentActions.clickOnApprovedButton).toHaveBeenCalledWith(
      segment,
      true,
    )
    jest.useRealTimers()
  })
})
