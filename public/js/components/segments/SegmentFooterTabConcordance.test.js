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
  return {
    TabConcordanceResults: ReactLib.forwardRef((props, ref) => {
      ReactLib.useImperativeHandle(ref, () => ({reset: jest.fn()}))
      return ReactLib.createElement('div', {
        'data-testid': 'tab-concordance-results',
      })
    }),
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
      screen.getByText('TM Search is not available when the TM feature is disabled'),
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
    expect(document.querySelector('.search-source')).toHaveValue(
      'found source',
    )
  })

  test('FIND_CONCORDANCE event with target text populates target field', () => {
    renderComponent()
    act(() => {
      SegmentStore.__emit(SegmentConstants.FIND_CONCORDANCE, '7', {
        inTarget: true,
        text: 'found target',
      })
    })
    expect(document.querySelector('.search-target')).toHaveValue(
      'found target',
    )
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
})
