import React from 'react'
import {render, screen, fireEvent, act, waitFor} from '@testing-library/react'
import {SegmentFooterTabGlossary} from './SegmentFooterTabGlossary'
import {SegmentContext} from '../SegmentContext'
import SegmentConstants from '../../../constants/SegmentConstants'
import CatToolConstants from '../../../constants/CatToolConstants'
import '../../../extensions/extensionManifest'
import {
  resetCapabilities,
  setCapability,
} from '../../../extensions/capabilities'
import {GLOSSARY_EDIT} from '../../../extensions/capabilityNames'

jest.mock('../../../stores/SegmentStore', () => {
  const listeners = {}
  return {
    addListener: jest.fn((event, cb) => {
      listeners[event] = listeners[event] || []
      listeners[event].push(cb)
    }),
    removeListener: jest.fn((event, cb) => {
      if (!listeners[event]) return
      listeners[event] = listeners[event].filter((fn) => fn !== cb)
    }),
    getCurrentSegment: jest.fn(() => ({sid: '1'})),
    __emit: (event, ...args) =>
      (listeners[event] || []).forEach((cb) => cb(...args)),
  }
})

jest.mock('../../../stores/CatToolStore', () => {
  const listeners = {}
  return {
    addListener: jest.fn((event, cb) => {
      listeners[event] = listeners[event] || []
      listeners[event].push(cb)
    }),
    removeListener: jest.fn((event, cb) => {
      if (!listeners[event]) return
      listeners[event] = listeners[event].filter((fn) => fn !== cb)
    }),
    __emit: (event, ...args) =>
      (listeners[event] || []).forEach((cb) => cb(...args)),
  }
})

jest.mock('../../../actions/SegmentActions', () => ({
  getSegmentsQa: jest.fn(),
  getGlossaryForSegment: jest.fn(),
  setGlossaryForSegmentBySearch: jest.fn(),
  searchGlossary: jest.fn(),
}))

jest.mock('../../../actions/CatToolActions', () => ({
  retrieveJobKeys: jest.fn(),
  openSettingsPanel: jest.fn(),
  setDomains: jest.fn(),
}))

jest.mock('../../../api/checkMymemoryStatus', () => ({
  checkMymemoryStatus: jest.fn(),
}))

const SegmentStore = require('../../../stores/SegmentStore')
const CatToolStore = require('../../../stores/CatToolStore')
const SegmentActions = require('../../../actions/SegmentActions')
const CatToolActions = require('../../../actions/CatToolActions')
const AppDispatcher = require('../../../stores/AppDispatcher').default
const {checkMymemoryStatus} = require('../../../api/checkMymemoryStatus')

jest.spyOn(AppDispatcher, 'dispatch').mockImplementation(() => {})

const segment = {
  sid: '1',
  segment: 'Hello world',
}

const renderComponent = (props = {}, contextValue = {}) =>
  render(
    <SegmentContext.Provider
      value={{clientConnected: true, clientId: 'c1', ...contextValue}}
    >
      <SegmentFooterTabGlossary
        code="glossary"
        active_class="active"
        segment={segment}
        {...props}
      />
    </SegmentContext.Provider>,
  )

beforeAll(() => {
  global.config = Object.assign(global.config ?? {}, {
    id_job: 1,
    password: 'pwd',
    source_code: 'en-US',
    target_code: 'it-IT',
    isSourceRTL: false,
    isTargetRTL: false,
  })
  // jsdom does not implement Element.scrollTo, used by GlossaryList
  Element.prototype.scrollTo = jest.fn()
})

afterEach(() => {
  jest.clearAllMocks()
})

describe('SegmentFooterTabGlossary', () => {
  test('shows loading label while the client is connected and glossary keys are unknown', () => {
    renderComponent()
    expect(screen.getByText('Loading')).toBeInTheDocument()
  })

  test('shows nothing but the wrapper when the client connection status is unknown', () => {
    renderComponent({}, {clientConnected: undefined})
    expect(screen.queryByText('Loading')).not.toBeInTheDocument()
    expect(
      document.querySelector('.tab.sub-editor.glossary'),
    ).toBeInTheDocument()
  })

  test('shows the error tab when the client failed to connect', () => {
    renderComponent({}, {clientConnected: false})
    expect(
      document.querySelector('.tab.sub-editor.glossary'),
    ).toBeInTheDocument()
    expect(screen.queryByText('Loading')).not.toBeInTheDocument()
  })

  test('requests job keys once the client connects', () => {
    renderComponent()
    expect(CatToolActions.retrieveJobKeys).toHaveBeenCalled()
  })

  test('shows the no-glossary state and opens the form to create one', () => {
    renderComponent()

    act(() => {
      CatToolStore.__emit(CatToolConstants.HAVE_KEYS_GLOSSARY, {
        value: false,
        wasAlreadyVerified: true,
      })
    })

    expect(screen.getByText('No glossary available.')).toBeInTheDocument()

    fireEvent.click(screen.getByText('+ Click here to create one'))

    expect(
      document.querySelector('input[name="glossary-term-original"]'),
    ).toBeInTheDocument()
  })

  test('fetches the glossary for the segment when keys exist and were not already verified', () => {
    renderComponent()

    act(() => {
      CatToolStore.__emit(CatToolConstants.HAVE_KEYS_GLOSSARY, {
        value: true,
        wasAlreadyVerified: false,
      })
    })

    expect(SegmentActions.getGlossaryForSegment).toHaveBeenCalledWith({
      sid: segment.sid,
      text: segment.segment,
    })
  })

  test('renders the search terms and glossary list when glossary keys already exist', () => {
    renderComponent()

    act(() => {
      CatToolStore.__emit(CatToolConstants.HAVE_KEYS_GLOSSARY, {
        value: true,
        wasAlreadyVerified: true,
      })
    })

    expect(screen.getByPlaceholderText('Search term')).toBeInTheDocument()
  })

  test('opening the add-term form from the search bar shows TermForm', () => {
    renderComponent()

    act(() => {
      CatToolStore.__emit(CatToolConstants.HAVE_KEYS_GLOSSARY, {
        value: true,
        wasAlreadyVerified: true,
      })
    })

    fireEvent.click(screen.getByRole('button', {name: /Add term/}))

    expect(
      document.querySelector('input[name="glossary-term-original"]'),
    ).toBeInTheDocument()
  })

  test('prefills and opens the form on OPEN_GLOSSARY_FORM_PREFILL', () => {
    renderComponent()

    act(() => {
      SegmentStore.__emit(SegmentConstants.OPEN_GLOSSARY_FORM_PREFILL, {
        sid: segment.sid,
        actionType: SegmentConstants.OPEN_GLOSSARY_FORM_PREFILL,
        originalTerm: 'gatto',
        translatedTerm: 'cat',
      })
    })

    expect(
      document.querySelector('input[name="glossary-term-original"]'),
    ).toHaveValue('gatto')
    expect(
      document.querySelector('input[name="glossary-term-translated"]'),
    ).toHaveValue('cat')
  })

  test('polls mymemory status and shows a footer message once the term is added', async () => {
    jest.setTimeout(10000)
    checkMymemoryStatus.mockResolvedValue({responseData: {id: 42}})
    renderComponent()

    act(() => {
      SegmentStore.__emit(SegmentConstants.ADD_GLOSSARY_ITEM, {
        request_id: 'uuid-1',
      })
    })

    await waitFor(
      () =>
        expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
          expect.objectContaining({
            actionType: SegmentConstants.SHOW_FOOTER_MESSAGE,
            sid: segment.sid,
            message: 'A termbase entry has been added',
          }),
        ),
      {timeout: 3000},
    )
    expect(SegmentActions.getGlossaryForSegment).toHaveBeenCalledWith(
      expect.objectContaining({sid: segment.sid, shouldRefresh: true}),
    )
  })

  test('sets loading to false when an error is received while adding a term', () => {
    renderComponent()

    act(() => {
      CatToolStore.__emit(CatToolConstants.HAVE_KEYS_GLOSSARY, {
        value: true,
        wasAlreadyVerified: false,
      })
    })

    act(() => {
      SegmentStore.__emit(SegmentConstants.ERROR_ADD_GLOSSARY_ITEM)
    })

    expect(screen.getByPlaceholderText('Search term')).toBeInTheDocument()
  })

  test('unregisters listeners on unmount', () => {
    const {unmount} = renderComponent()
    unmount()
    expect(SegmentStore.removeListener).toHaveBeenCalledWith(
      SegmentConstants.ADD_GLOSSARY_ITEM,
      expect.any(Function),
    )
    expect(CatToolStore.removeListener).toHaveBeenCalledWith(
      CatToolConstants.HAVE_KEYS_GLOSSARY,
      expect.any(Function),
    )
  })

  describe('when the deployment may not edit the glossary', () => {
    afterEach(() => {
      resetCapabilities()
    })

    const withoutKeys = () =>
      act(() => {
        CatToolStore.__emit(CatToolConstants.HAVE_KEYS_GLOSSARY, {
          value: false,
          wasAlreadyVerified: true,
        })
      })

    test('the no-glossary state offers no way to create one', () => {
      setCapability(GLOSSARY_EDIT, false)
      renderComponent()
      withoutKeys()

      expect(screen.getByText('No glossary available.')).toBeInTheDocument()
      expect(
        screen.queryByText('+ Click here to create one'),
      ).not.toBeInTheDocument()
    })

    test('the term form does not open even when a prefill is requested', () => {
      setCapability(GLOSSARY_EDIT, false)
      renderComponent()
      withoutKeys()

      act(() => {
        SegmentStore.__emit(SegmentConstants.OPEN_GLOSSARY_FORM_PREFILL, {
          sid: '1',
        })
      })

      expect(screen.queryByText('Add term')).not.toBeInTheDocument()
      expect(screen.getByText('No glossary available.')).toBeInTheDocument()
    })
  })
})
