import React from 'react'
import {render, screen, act, fireEvent} from '@testing-library/react'
import {SegmentFooterTabLaraStyles} from './SegmentFooterTabLaraStyles'
import SegmentStore from '../../stores/SegmentStore'
import SegmentConstants from '../../constants/SegmentConstants'
import {SegmentContext} from './SegmentContext'

// --- Mocks ---

jest.mock('../../stores/SegmentStore', () => {
  const listeners = {}
  return {
    addListener: jest.fn((event, cb) => {
      listeners[event] = cb
    }),
    removeListener: jest.fn(),
    __emit: (event, data) => listeners[event] && listeners[event](data),
  }
})

jest.mock('../../stores/CatToolStore', () => ({
  getJobMetadata: () => ({
    project: {mt_extra: {lara_glossaries: []}},
  }),
}))

jest.mock('../../utils/segmentUtils', () => ({
  getSegmentContext: () => ({contextListBefore: [], contextListAfter: []}),
}))

jest.mock('./utils/DraftMatecatUtils', () => ({
  __esModule: true,
  default: {
    transformTagsToHtml: jest.fn((text) => text),
  },
}))

jest.mock('./utils/DraftMatecatUtils/tagUtils', () => ({
  __esModule: true,
  decodePlaceholdersToPlainText: jest.fn((t) => t),
  encodePlaceholdersToTags: jest.fn((t) => t),
}))

jest.mock('./utils/translationMatches', () => ({
  __esModule: true,
  default: {
    segmentsWaitingForContributions: [],
    renderContributionErrors: jest.fn(),
  },
}))

jest.mock('../../api/laraAuth', () => ({
  laraAuthJob: jest.fn(() => Promise.resolve({token: 'test-token'})),
}))

jest.mock('../../api/laraTranslate', () => ({
  laraTranslate: jest.fn(),
}))

jest.mock('../../api/getContributions', () => ({
  getContributions: jest.fn(() => Promise.resolve()),
}))

jest.mock('../../actions/SegmentActions', () => ({
  setFocusOnEditArea: jest.fn(),
  disableTPOnSegment: jest.fn(),
  replaceEditAreaTextContent: jest.fn(),
}))

jest.mock('../../actions/CatToolActions', () => ({
  processErrors: jest.fn(),
}))

jest.mock('../common/Button/Button', () => ({
  Button: ({children, onClick}) => (
    <button onClick={onClick}>{children}</button>
  ),
  BUTTON_MODE: {OUTLINE: 'outline'},
}))

jest.mock('../../utils/MemoizeRequest', () => ({
  MemoizeRequest: jest.fn().mockImplementation(() => {
    const instance = {get: jest.fn(() => undefined), set: jest.fn()}
    globalThis.__mockMemoizeInstance = instance
    return instance
  }),
}))

// --- Helpers ---

const defaultSegment = {
  sid: '1',
  segment: 'Hello world',
  translation: 'Ciao mondo',
}

const defaultProps = {
  code: 'lara',
  active_class: 'active',
  tab_class: 'lara-tab',
  segment: defaultSegment,
}

const renderComponent = (props = {}, contextValue = {}) =>
  render(
    <SegmentContext.Provider value={{multiMatchLangs: null, ...contextValue}}>
      <SegmentFooterTabLaraStyles {...defaultProps} {...props} />
    </SegmentContext.Provider>,
  )

beforeAll(() => {
  global.config = Object.assign(global.config ?? {}, {
    id_job: 42,
    password: 'test-password',
    isTargetRTL: false,
  })
})

afterEach(() => {
  jest.clearAllMocks()
})

// --- Tests ---

const stylesPayload = {
  sid: '1',
  styles: [
    {id: 'formal', name: 'Formal', isDefault: true},
    {id: 'casual', name: 'Casual', isDefault: false},
  ],
}

const laraValues = [
  {
    translation: [
      {translatable: true, text: 'Formal translation'},
      {translatable: false, text: 'ignored'},
    ],
  },
  {
    translation: [
      {translatable: true, text: 'Casual translation'},
      {translatable: false, text: 'ignored'},
    ],
  },
]

describe('SegmentFooterTabLaraStyles', () => {
  test('renders loading spinner initially', () => {
    renderComponent()
    expect(document.querySelector('.loader.loader_on')).toBeInTheDocument()
  })

  test('registers LARA_STYLES listener on mount', () => {
    renderComponent()
    expect(SegmentStore.addListener).toHaveBeenCalledWith(
      SegmentConstants.LARA_STYLES,
      expect.any(Function),
    )
  })

  test('unregisters LARA_STYLES listener on unmount', () => {
    const {unmount} = renderComponent()
    unmount()
    expect(SegmentStore.removeListener).toHaveBeenCalledWith(
      SegmentConstants.LARA_STYLES,
      expect.any(Function),
    )
  })

  test('renders translation styles after store event resolves', async () => {
    const {laraAuthJob} = require('../../api/laraAuth')
    const {laraTranslate} = require('../../api/laraTranslate')
    laraAuthJob.mockResolvedValue({token: 'tok'})
    laraTranslate
      .mockResolvedValueOnce(laraValues[0])
      .mockResolvedValueOnce(laraValues[1])

    renderComponent()

    await act(async () => {
      SegmentStore.__emit(SegmentConstants.LARA_STYLES, stylesPayload)
    })

    expect(screen.getByText('Formal')).toBeInTheDocument()
    expect(screen.getByText('Casual')).toBeInTheDocument()
    expect(screen.getByText('(Original)')).toBeInTheDocument()
  })

  test('renders error message when lara translate rejects', async () => {
    const {laraAuthJob} = require('../../api/laraAuth')
    const {laraTranslate} = require('../../api/laraTranslate')
    laraAuthJob.mockResolvedValue({token: 'tok'})
    laraTranslate.mockRejectedValue(new Error('network error'))

    renderComponent()

    await act(async () => {
      SegmentStore.__emit(SegmentConstants.LARA_STYLES, stylesPayload)
    })

    expect(
      screen.getByText(/Lara couldn't generate translations/i),
    ).toBeInTheDocument()
  })

  test('uses cached result instead of calling laraAuthJob again', async () => {
    const {laraAuthJob} = require('../../api/laraAuth')

    // Simulate a cache hit: the module-level aiCache instance is captured in
    // globalThis by the mock factory, so it survives jest.clearAllMocks()
    globalThis.__mockMemoizeInstance.get.mockReturnValueOnce(laraValues)

    renderComponent()

    await act(async () => {
      SegmentStore.__emit(SegmentConstants.LARA_STYLES, stylesPayload)
    })

    expect(laraAuthJob).not.toHaveBeenCalled()
    expect(screen.getByText('Formal')).toBeInTheDocument()
  })

  test('calls getContributions and SegmentActions when switching style', async () => {
    const {laraAuthJob} = require('../../api/laraAuth')
    const {laraTranslate} = require('../../api/laraTranslate')
    const {getContributions} = require('../../api/getContributions')
    const SegmentActions = require('../../actions/SegmentActions')

    laraAuthJob.mockResolvedValue({token: 'tok'})
    laraTranslate
      .mockResolvedValueOnce(laraValues[0])
      .mockResolvedValueOnce(laraValues[1])

    renderComponent()

    await act(async () => {
      SegmentStore.__emit(SegmentConstants.LARA_STYLES, stylesPayload)
    })

    const switchButtons = screen.getAllByRole('button')
    await act(async () => {
      fireEvent.click(switchButtons[0])
    })

    expect(SegmentActions.setFocusOnEditArea).toHaveBeenCalled()
    expect(SegmentActions.disableTPOnSegment).toHaveBeenCalledWith(
      defaultSegment,
    )
    expect(getContributions).toHaveBeenCalledWith(
      expect.objectContaining({
        idSegment: defaultSegment.sid,
        laraStyle: stylesPayload.styles[0].id,
        reasoning: false,
      }),
    )
  })

  test('passes crossLanguages from context multiMatchLangs when switching style', async () => {
    const {laraAuthJob} = require('../../api/laraAuth')
    const {laraTranslate} = require('../../api/laraTranslate')
    const {getContributions} = require('../../api/getContributions')

    laraAuthJob.mockResolvedValue({token: 'tok'})
    laraTranslate
      .mockResolvedValueOnce(laraValues[0])
      .mockResolvedValueOnce(laraValues[1])

    renderComponent(
      {},
      {multiMatchLangs: {primary: 'en-US', secondary: 'fr-FR'}},
    )

    await act(async () => {
      SegmentStore.__emit(SegmentConstants.LARA_STYLES, stylesPayload)
    })

    const switchButtons = screen.getAllByRole('button')
    await act(async () => {
      fireEvent.click(switchButtons[0])
    })

    expect(getContributions).toHaveBeenCalledWith(
      expect.objectContaining({
        crossLanguages: ['en-US', 'fr-FR'],
      }),
    )
  })
})
