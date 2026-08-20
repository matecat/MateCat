import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import SegmentFooterMultiMatches from './SegmentFooterMultiMatches'
import {SegmentContext} from './SegmentContext'

jest.mock('../../actions/SegmentActions', () => ({
  setFocusOnEditArea: jest.fn(),
  disableTPOnSegment: jest.fn(),
  replaceEditAreaTextContent: jest.fn(),
}))

jest.mock('./utils/DraftMatecatUtils', () => ({
  __esModule: true,
  default: {
    transformTagsToHtml: jest.fn((text) => text),
  },
}))

beforeAll(() => {
  global.config = {
    ...global.config,
    isTargetRTL: false,
    isSourceRTL: false,
    source_code: 'en-US',
    mt_enabled: true,
  }
})

afterEach(() => {
  jest.clearAllMocks()
})

const baseSegment = {
  sid: '10',
  segment: 'Hello world',
  translation: 'Ciao mondo',
}

const renderComponent = (props = {}, contextValue = {clientConnected: true}) =>
  render(
    <SegmentContext.Provider value={contextValue}>
      <SegmentFooterMultiMatches
        code="cl"
        active_class="active"
        tab_class="cross-matches"
        segment={baseSegment}
        {...props}
      />
    </SegmentContext.Provider>,
  )

describe('SegmentFooterMultiMatches', () => {
  test('renders loader when clientConnected and no matches yet', () => {
    renderComponent({segment: baseSegment})
    expect(document.querySelector('.loader.loader_on')).toBeInTheDocument()
  })

  test('renders error component when clientConnected is false', () => {
    renderComponent({segment: baseSegment}, {clientConnected: false})
    expect(
      screen.getByText(/unable to provide access to language resources/i),
    ).toBeInTheDocument()
  })

  test('renders "no matches" message when mt_enabled is true and matches is empty', () => {
    global.config.mt_enabled = true
    renderComponent({
      segment: {...baseSegment, cl_contributions: {matches: []}},
    })
    expect(
      screen.getByText(/There are no matches for this segment/i),
    ).toBeInTheDocument()
    expect(screen.getByText('support@matecat.com')).toBeInTheDocument()
  })

  test('renders "no matches" message without support link when mt_enabled is false', () => {
    global.config.mt_enabled = false
    renderComponent({
      segment: {...baseSegment, cl_contributions: {matches: []}},
    })
    expect(
      screen.getByText(
        'There are no matches for this segment in the languages you have selected.',
      ),
    ).toBeInTheDocument()
    global.config.mt_enabled = true
  })

  test('renders match items when cl_contributions has matches', () => {
    renderComponent({
      segment: {
        ...baseSegment,
        cl_contributions: {
          matches: [
            {
              id: '1',
              segment: 'source text',
              translation: 'target text',
              created_by: 'MyMemory',
              match: '95',
              source: 'en-US',
              target: 'it-IT',
              last_update_date: '2024-01-01',
            },
          ],
        },
      },
    })
    expect(document.querySelector('.suggestion-item')).toBeInTheDocument()
    expect(screen.getByText('MyMemory')).toBeInTheDocument()
  })

  test('skips matches with empty segment/translation', () => {
    renderComponent({
      segment: {
        ...baseSegment,
        cl_contributions: {
          matches: [
            {
              id: '1',
              segment: '',
              translation: 'target text',
              created_by: 'MyMemory',
              match: '95',
              source: 'en-US',
              target: 'it-IT',
            },
          ],
        },
      },
    })
    expect(document.querySelector('.suggestion-item')).not.toBeInTheDocument()
    // Falls through to loader branch since matches array ends up empty
    expect(document.querySelector('.loader.loader_on')).toBeInTheDocument()
  })

  test('double click on suggestion triggers SegmentActions', () => {
    const SegmentActions = require('../../actions/SegmentActions')
    renderComponent({
      segment: {
        ...baseSegment,
        cl_contributions: {
          matches: [
            {
              id: '1',
              segment: 'source text',
              translation: 'target text',
              created_by: 'MyMemory',
              match: '95',
              source: 'en-US',
              target: 'it-IT',
            },
          ],
        },
      },
    })
    fireEvent.doubleClick(document.querySelector('.suggestion-item'))
    expect(SegmentActions.setFocusOnEditArea).toHaveBeenCalled()
    expect(SegmentActions.disableTPOnSegment).toHaveBeenCalled()
  })

  test('renders quality info for MT match with sentence_confidence', () => {
    renderComponent({
      segment: {
        ...baseSegment,
        cl_contributions: {
          matches: [
            {
              id: '1',
              segment: 'source text',
              translation: 'target text',
              created_by: 'MT',
              match: 'MT',
              sentence_confidence: 0.85,
              source: 'en-US',
              target: 'it-IT',
            },
          ],
        },
      },
    })
    expect(document.querySelector('.graysmall-details')).toBeInTheDocument()
  })

  test('applies yellow variant class when source differs from source_code', () => {
    renderComponent({
      segment: {
        ...baseSegment,
        cl_contributions: {
          matches: [
            {
              id: '1',
              segment: 'source text',
              translation: 'target text',
              created_by: 'MyMemory',
              match: '95',
              source: 'fr-FR',
              target: 'it-IT',
            },
          ],
        },
      },
    })
    expect(document.querySelector('.per-yellow-variant')).toBeInTheDocument()
  })

  test('renders diff sourceDiff for fuzzy match between 75 and 99', () => {
    renderComponent({
      segment: {
        ...baseSegment,
        cl_contributions: {
          matches: [
            {
              id: '1',
              segment: 'source text updated',
              translation: 'target text',
              created_by: 'MyMemory',
              match: '80',
              source: 'en-US',
              target: 'it-IT',
            },
          ],
        },
      },
    })
    expect(document.querySelector('.suggestion-item')).toBeInTheDocument()
  })

  test('memo bails out (skips re-render) when cl_contributions is deep-equal but a new object reference', () => {
    const DraftMatecatUtils = require('./utils/DraftMatecatUtils').default
    const match = {
      id: '1',
      segment: 'source text',
      translation: 'target text',
      created_by: 'MyMemory',
      match: '95',
      source: 'en-US',
      target: 'it-IT',
    }
    const segment = {...baseSegment, cl_contributions: {matches: [{...match}]}}
    const sameShapeNewReference = {
      ...baseSegment,
      cl_contributions: {matches: [{...match}]},
    }
    const contextValue = {clientConnected: true}

    const {rerender} = renderComponent({segment}, contextValue)
    const callsAfterFirstRender =
      DraftMatecatUtils.transformTagsToHtml.mock.calls.length
    expect(callsAfterFirstRender).toBeGreaterThan(0)

    rerender(
      <SegmentContext.Provider value={contextValue}>
        <SegmentFooterMultiMatches
          code="cl"
          active_class="active"
          tab_class="cross-matches"
          segment={sameShapeNewReference}
        />
      </SegmentContext.Provider>,
    )
    expect(DraftMatecatUtils.transformTagsToHtml.mock.calls.length).toBe(
      callsAfterFirstRender,
    )
  })

  test('re-renders when cl_contributions content actually changes', () => {
    const DraftMatecatUtils = require('./utils/DraftMatecatUtils').default
    const segment = {
      ...baseSegment,
      cl_contributions: {
        matches: [
          {
            id: '1',
            segment: 'source text',
            translation: 'target text',
            created_by: 'MyMemory',
            match: '95',
            source: 'en-US',
            target: 'it-IT',
          },
        ],
      },
    }
    const changed = {
      ...baseSegment,
      cl_contributions: {
        matches: [
          {
            id: '1',
            segment: 'different source text',
            translation: 'target text',
            created_by: 'MyMemory',
            match: '95',
            source: 'en-US',
            target: 'it-IT',
          },
        ],
      },
    }
    const contextValue = {clientConnected: true}

    const {rerender} = renderComponent({segment}, contextValue)
    const callsAfterFirstRender =
      DraftMatecatUtils.transformTagsToHtml.mock.calls.length

    rerender(
      <SegmentContext.Provider value={contextValue}>
        <SegmentFooterMultiMatches
          code="cl"
          active_class="active"
          tab_class="cross-matches"
          segment={changed}
        />
      </SegmentContext.Provider>,
    )
    expect(
      DraftMatecatUtils.transformTagsToHtml.mock.calls.length,
    ).toBeGreaterThan(callsAfterFirstRender)
  })

  test('re-renders when active_class changes even if cl_contributions is unchanged', () => {
    const DraftMatecatUtils = require('./utils/DraftMatecatUtils').default
    const segment = {
      ...baseSegment,
      cl_contributions: {
        matches: [
          {
            id: '1',
            segment: 'source text',
            translation: 'target text',
            created_by: 'MyMemory',
            match: '95',
            source: 'en-US',
            target: 'it-IT',
          },
        ],
      },
    }
    const contextValue = {clientConnected: true}

    const {rerender} = renderComponent({segment}, contextValue)
    const callsAfterFirstRender =
      DraftMatecatUtils.transformTagsToHtml.mock.calls.length

    rerender(
      <SegmentContext.Provider value={contextValue}>
        <SegmentFooterMultiMatches
          code="cl"
          active_class="inactive"
          tab_class="cross-matches"
          segment={segment}
        />
      </SegmentContext.Provider>,
    )
    expect(
      DraftMatecatUtils.transformTagsToHtml.mock.calls.length,
    ).toBeGreaterThan(callsAfterFirstRender)
  })
})
