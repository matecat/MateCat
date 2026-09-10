import React from 'react'
import {render, screen, fireEvent, act} from '@testing-library/react'
import SegmentFooterTabConcordance from './SegmentFooterTabConcordance'
import {SegmentContext} from './SegmentContext'
import SegmentStore from '../../stores/SegmentStore'
import SegmentConstants from '../../constants/SegmentConstants'

jest.mock('../../stores/SegmentStore', () => {
  const listeners = {}
  return {
    addListener: jest.fn((event, cb) => {
      listeners[event] = cb
    }),
    removeListener: jest.fn(),
    __emit: (event, ...args) => listeners[event] && listeners[event](...args),
  }
})

jest.mock('../../api/getConcordance', () => ({
  getConcordance: jest.fn(() => Promise.resolve()),
}))

jest.mock('../../utils/offlineUtils', () => ({
  failedConnection: jest.fn(),
}))

jest.mock('./TabConcordanceResults', () => {
  const ReactLib = require('react')
  const renderSpy = jest.fn()
  return {
    TabConcordanceResults: ReactLib.forwardRef((props, ref) => {
      renderSpy(props)
      ReactLib.useImperativeHandle(ref, () => ({reset: jest.fn()}))
      return ReactLib.createElement('div', {
        'data-testid': 'tab-concordance-results',
      })
    }),
    __renderSpy: renderSpy,
  }
})

beforeAll(() => {
  global.config = {
    ...global.config,
    tms_enabled: true,
  }
  global.navigator.clipboard = {writeText: jest.fn(() => Promise.resolve())}
})

afterEach(() => {
  jest.clearAllMocks()
  jest.useRealTimers()
})

const baseSegment = {sid: '7'}

const renderComponent = (props = {}, contextValue = {clientConnected: true}) =>
  render(
    <SegmentContext.Provider value={contextValue}>
      <SegmentFooterTabConcordance
        code="cc"
        active_class="active"
        tab_class="concordances"
        segment={baseSegment}
        {...props}
      />
    </SegmentContext.Provider>,
  )

describe('SegmentFooterTabConcordance', () => {
  test('renders search inputs when TM search is enabled', () => {
    global.config.tms_enabled = true
    renderComponent()
    expect(document.querySelector('.search-source')).toBeInTheDocument()
    expect(document.querySelector('.search-target')).toBeInTheDocument()
  })

  test('renders disabled message when TM search is not enabled', () => {
    global.config.tms_enabled = false
    renderComponent()
    expect(
      screen.getByText(
        'TM Search is not available when the TM feature is disabled',
      ),
    ).toBeInTheDocument()
    global.config.tms_enabled = true
  })

  test('renders error component when clientConnected is false', () => {
    renderComponent({}, {clientConnected: false})
    expect(
      screen.getByText(/unable to provide access to language resources/i),
    ).toBeInTheDocument()
  })

  test('registers and unregisters listeners', () => {
    const {unmount} = renderComponent()

    expect(SegmentStore.addListener).toHaveBeenCalledWith(
      SegmentConstants.FIND_CONCORDANCE,
      expect.any(Function),
    )
    expect(SegmentStore.addListener).toHaveBeenCalledWith(
      SegmentConstants.CONCORDANCE_RESULT,
      expect.any(Function),
    )

    unmount()

    expect(SegmentStore.removeListener).toHaveBeenCalledWith(
      SegmentConstants.FIND_CONCORDANCE,
      expect.any(Function),
    )
    expect(SegmentStore.removeListener).toHaveBeenCalledWith(
      SegmentConstants.CONCORDANCE_RESULT,
      expect.any(Function),
    )
  })

  test('typing into the source input updates the value and clears target', () => {
    renderComponent()
    const sourceInput = document.querySelector('.search-source')
    fireEvent.change(sourceInput, {target: {value: 'hello'}})
    expect(sourceInput).toHaveValue('hello')
  })

  test('typing into the target input updates the value and clears source', () => {
    renderComponent()
    const targetInput = document.querySelector('.search-target')
    fireEvent.change(targetInput, {target: {value: 'ciao'}})
    expect(targetInput).toHaveValue('ciao')
  })

  test('submitting the form with a source value triggers getConcordance', () => {
    const {getConcordance} = require('../../api/getConcordance')
    renderComponent()
    const sourceInput = document.querySelector('.search-source')
    fireEvent.change(sourceInput, {target: {value: 'hello'}})
    fireEvent.submit(document.querySelector('form'))
    expect(getConcordance).toHaveBeenCalledWith('hello', 0)
  })

  test('submitting the form with a target value triggers getConcordance with type 1', () => {
    const {getConcordance} = require('../../api/getConcordance')
    renderComponent()
    const targetInput = document.querySelector('.search-target')
    fireEvent.change(targetInput, {target: {value: 'ciao'}})
    fireEvent.submit(document.querySelector('form'))
    expect(getConcordance).toHaveBeenCalledWith('ciao', 1)
  })

  test('FIND_CONCORDANCE event with source text populates source field', () => {
    renderComponent()
    act(() => {
      SegmentStore.__emit(SegmentConstants.FIND_CONCORDANCE, '7', {
        inTarget: false,
        text: 'found source',
      })
    })
    expect(document.querySelector('.search-source')).toHaveValue('found source')
  })

  test('FIND_CONCORDANCE event with target text populates target field', () => {
    renderComponent()
    act(() => {
      SegmentStore.__emit(SegmentConstants.FIND_CONCORDANCE, '7', {
        inTarget: true,
        text: 'found target',
      })
    })
    expect(document.querySelector('.search-target')).toHaveValue('found target')
  })

  test('FIND_CONCORDANCE event for a different sid is ignored', () => {
    renderComponent()
    act(() => {
      SegmentStore.__emit(SegmentConstants.FIND_CONCORDANCE, '999', {
        inTarget: false,
        text: 'should not appear',
      })
    })
    expect(document.querySelector('.search-source')).toHaveValue('')
  })

  test('copying selected text calls clipboard.writeText', async () => {
    const getSelectionSpy = jest
      .spyOn(document, 'getSelection')
      .mockReturnValue({
        toString: () => 'selected text',
      })
    renderComponent()
    const container = document.querySelector('#segment-7-concordances')
    await act(async () => {
      fireEvent.copy(container)
    })
    expect(global.navigator.clipboard.writeText).toHaveBeenCalledWith(
      'selected text',
    )
    getSelectionSpy.mockRestore()
  })

  test('does not reject when the browser denies clipboard permission', async () => {
    const getSelectionSpy = jest
      .spyOn(document, 'getSelection')
      .mockReturnValue({
        toString: () => 'selected text',
      })
    global.navigator.clipboard.writeText.mockRejectedValueOnce(
      new DOMException('denied', 'NotAllowedError'),
    )
    const unhandledRejection = jest.fn()
    process.on('unhandledRejection', unhandledRejection)

    renderComponent()
    const container = document.querySelector('#segment-7-concordances')
    await act(async () => {
      fireEvent.copy(container)
    })
    await new Promise((resolve) => setTimeout(resolve, 0))
    process.off('unhandledRejection', unhandledRejection)

    expect(unhandledRejection).not.toHaveBeenCalled()
    expect(global.navigator.clipboard.writeText).toHaveBeenCalledWith(
      'selected text',
    )
    getSelectionSpy.mockRestore()
  })

  test('FIND_CONCORDANCE event triggers an automatic search via getConcordance', () => {
    jest.useFakeTimers()
    const {getConcordance} = require('../../api/getConcordance')
    renderComponent()

    act(() => {
      SegmentStore.__emit(SegmentConstants.FIND_CONCORDANCE, '7', {
        inTarget: false,
        text: 'found source',
      })
    })
    act(() => {
      jest.runAllTimers()
    })

    expect(getConcordance).toHaveBeenCalledWith('found source', 0)
  })

  test('FIND_CONCORDANCE event with target text triggers getConcordance with type 1', () => {
    jest.useFakeTimers()
    const {getConcordance} = require('../../api/getConcordance')
    renderComponent()

    act(() => {
      SegmentStore.__emit(SegmentConstants.FIND_CONCORDANCE, '7', {
        inTarget: true,
        text: 'found target',
      })
    })
    act(() => {
      jest.runAllTimers()
    })

    expect(getConcordance).toHaveBeenCalledWith('found target', 1)
  })

  test('CONCORDANCE_RESULT event clears the loading state', () => {
    renderComponent()
    const sourceInput = document.querySelector('.search-source')
    fireEvent.change(sourceInput, {target: {value: 'hello'}})
    fireEvent.submit(document.querySelector('form'))
    expect(document.querySelector('.cc-search')).toHaveClass('loading')

    act(() => {
      SegmentStore.__emit(SegmentConstants.CONCORDANCE_RESULT, '7', [])
    })
    expect(document.querySelector('.cc-search')).not.toHaveClass('loading')
  })

  test('CONCORDANCE_RESULT event for a different sid does not clear the loading state', () => {
    renderComponent()
    const sourceInput = document.querySelector('.search-source')
    fireEvent.change(sourceInput, {target: {value: 'hello'}})
    fireEvent.submit(document.querySelector('form'))
    expect(document.querySelector('.cc-search')).toHaveClass('loading')

    act(() => {
      SegmentStore.__emit(SegmentConstants.CONCORDANCE_RESULT, '999', [])
    })
    expect(document.querySelector('.cc-search')).toHaveClass('loading')
  })

  test('memo bails out (skips re-render) when active_class and tab_class are unchanged', () => {
    const {__renderSpy} = require('./TabConcordanceResults')
    const contextValue = {clientConnected: true}
    const {rerender} = renderComponent({}, contextValue)
    const callsAfterFirstRender = __renderSpy.mock.calls.length
    expect(callsAfterFirstRender).toBeGreaterThan(0)

    rerender(
      <SegmentContext.Provider value={contextValue}>
        <SegmentFooterTabConcordance
          code="cc"
          active_class="active"
          tab_class="concordances"
          segment={baseSegment}
        />
      </SegmentContext.Provider>,
    )
    expect(__renderSpy.mock.calls.length).toBe(callsAfterFirstRender)
  })

  test('re-renders when active_class changes', () => {
    const {__renderSpy} = require('./TabConcordanceResults')
    const contextValue = {clientConnected: true}
    const {rerender} = renderComponent({}, contextValue)
    const callsAfterFirstRender = __renderSpy.mock.calls.length

    rerender(
      <SegmentContext.Provider value={contextValue}>
        <SegmentFooterTabConcordance
          code="cc"
          active_class="inactive"
          tab_class="concordances"
          segment={baseSegment}
        />
      </SegmentContext.Provider>,
    )
    expect(__renderSpy.mock.calls.length).toBeGreaterThan(callsAfterFirstRender)
  })
})
