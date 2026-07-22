import React from 'react'
import {render, screen} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import Immutable from 'immutable'

import SegmentQR from './SegmentQR'

// Mock dependencies
jest.mock('../../utils/textUtils', () => ({
  getDiffHtml: jest.fn((source, target) => `<diff>${target}</diff>`),
}))

jest.mock(
  '../segments/utils/DraftMatecatUtils',
  () => ({
    transformTagsToHtml: jest.fn((text) => text),
  }),
  {virtual: true},
)

beforeEach(() => {
  global.config = {
    ...global.config,
    isSourceRTL: false,
    isTargetRTL: false,
  }
  jest.spyOn(window, 'open').mockImplementation(() => {})
})

afterEach(() => {
  window.open.mockRestore()
})

const buildSegment = (overrides = {}) => {
  const defaults = {
    id: 100,
    segment: 'Hello world',
    suggestion: 'Ciao mondo',
    suggestion_match: '85',
    suggestion_source: 'MT',
    match_type: 'MT',
    last_translation: null,
    last_revisions: null,
    status: 'TRANSLATED',
    raw_word_count: '5.00',
    secs_per_word: 12,
    pee: 35,
    time_to_edit: 500,
    time_to_edit_translation: null,
    time_to_edit_revise: null,
    time_to_edit_revise_2: null,
    ice_locked: '0',
    ice_modified: false,
    is_pre_translated: false,
    issues: [],
    warnings: {
      total: 0,
      details: {
        issues_info: {
          ERROR: {Categories: {}},
          WARNING: {Categories: {}},
          INFO: {Categories: {}},
        },
      },
    },
  }
  return Immutable.fromJS({...defaults, ...overrides})
}

const defaultUrls = Immutable.fromJS({
  translate_url: 'https://example.com/translate/job',
  revise_urls: [
    {revision_number: 1, url: 'https://example.com/revise1/job'},
    {revision_number: 2, url: 'https://example.com/revise2/job'},
  ],
})

const renderComponent = (segmentOverrides = {}, extraProps = {}) => {
  const segment = buildSegment(segmentOverrides)
  return render(
    <SegmentQR
      segment={segment}
      urls={defaultUrls}
      secondPassReviewEnabled={false}
      revisionToShow={null}
      {...extraProps}
    />,
  )
}

describe('SegmentQR', () => {
  test('renders segment ID', () => {
    renderComponent()
    expect(screen.getByText('100')).toBeInTheDocument()
  })

  test('renders segment status', () => {
    renderComponent()
    expect(screen.getByText('translated')).toBeInTheDocument()
  })

  test('renders production stats', () => {
    renderComponent()
    expect(screen.getByText('Machine Translation')).toBeInTheDocument()
    expect(screen.getByText("12''")).toBeInTheDocument()
    expect(screen.getByText('35%')).toBeInTheDocument()
  })

  test('renders source and suggestion lines', () => {
    renderComponent()
    expect(screen.getByText('Source')).toBeInTheDocument()
    expect(screen.getByText('Suggestion')).toBeInTheDocument()
  })

  test('does not render translation line when last_translation is null', () => {
    renderComponent()
    expect(screen.queryByText('Translation')).not.toBeInTheDocument()
  })

  test('renders translation line when last_translation is set', () => {
    renderComponent({last_translation: 'Ciao mondo tradotto'})
    expect(screen.getByText('Translation')).toBeInTheDocument()
  })

  test('does not render revision line when last_revisions is null', () => {
    renderComponent()
    expect(screen.queryByText('Revision')).not.toBeInTheDocument()
  })

  test('renders revision line when R1 revision exists', () => {
    renderComponent({
      last_translation: 'Tradotto',
      last_revisions: [{revision_number: 1, translation: 'Revisione 1'}],
    })
    expect(screen.getByText('Revision')).toBeInTheDocument()
  })

  test('renders 2nd revision line when R2 revision exists', () => {
    renderComponent({
      last_translation: 'Tradotto',
      last_revisions: [
        {revision_number: 1, translation: 'Revisione 1'},
        {revision_number: 2, translation: 'Revisione 2'},
      ],
    })
    expect(screen.getByText('Revision')).toBeInTheDocument()
    expect(screen.getByText('2nd Revision')).toBeInTheDocument()
  })

  test('renders words per second with minutes and seconds', () => {
    renderComponent({secs_per_word: 125})
    expect(screen.getByText("02'05''")).toBeInTheDocument()
  })

  test('renders words per second with only seconds', () => {
    renderComponent({secs_per_word: 8})
    expect(screen.getByText("08''")).toBeInTheDocument()
  })

  test('diff button toggles diff view on translation', async () => {
    const user = userEvent.setup()
    renderComponent({
      last_translation: 'Ciao mondo tradotto',
      suggestion: 'Ciao mondo',
    })

    const diffButtons = screen.getAllByTitle('Show Diff')
    expect(diffButtons.length).toBeGreaterThan(0)

    // Click the diff button for translation
    await user.click(diffButtons[0])
  })

  test('renders automated QA when warnings exist and no issues', () => {
    renderComponent({
      warnings: {
        total: 2,
        details: {
          issues_info: {
            ERROR: {
              Categories: {
                TAGS: ['tag1', 'tag2'],
              },
            },
            WARNING: {Categories: {}},
            INFO: {Categories: {}},
          },
        },
      },
    })

    expect(screen.getByText('QA')).toBeInTheDocument()
    expect(screen.getByText(/Automated/)).toBeInTheDocument()
    expect(screen.getByText('Tag mismatch')).toBeInTheDocument()
  })

  test('renders human QA when issues exist', () => {
    renderComponent({
      issues: [
        {
          issue_id: 1,
          issue_category: 'Accuracy',
          issue_severity: 'Major',
          revision_number: 1,
          comments: [],
          target_text: '',
        },
      ],
    })

    expect(screen.getByText('QA')).toBeInTheDocument()
    expect(screen.getByText(/Human/)).toBeInTheDocument()
    expect(screen.getByText(/Accuracy/)).toBeInTheDocument()
  })

  test('renders R1 and R2 QA tabs when secondPassReviewEnabled', () => {
    renderComponent(
      {
        issues: [
          {
            issue_id: 1,
            issue_category: 'Accuracy',
            issue_severity: 'Major',
            revision_number: 1,
            comments: [],
            target_text: '',
          },
          {
            issue_id: 2,
            issue_category: 'Fluency',
            issue_severity: 'Minor',
            revision_number: 2,
            comments: [],
            target_text: '',
          },
        ],
      },
      {secondPassReviewEnabled: true, revisionToShow: '1'},
    )

    expect(screen.getByText(/R1/)).toBeInTheDocument()
    expect(screen.getByText(/R2/)).toBeInTheDocument()
  })

  test('clicking R2 tab shows R2 issues', async () => {
    const user = userEvent.setup()
    renderComponent(
      {
        issues: [
          {
            issue_id: 1,
            issue_category: 'Accuracy',
            issue_severity: 'Major',
            revision_number: 1,
            comments: [],
            target_text: '',
          },
          {
            issue_id: 2,
            issue_category: 'Fluency',
            issue_severity: 'Minor',
            revision_number: 2,
            comments: [],
            target_text: '',
          },
        ],
      },
      {secondPassReviewEnabled: true, revisionToShow: '1'},
    )

    const r2Button = screen.getByText(/R2/)
    await user.click(r2Button)
    expect(screen.getByText(/Fluency/)).toBeInTheDocument()
  })

  test('opens translate link on label click', async () => {
    const user = userEvent.setup()
    renderComponent({last_translation: 'Tradotto'})

    await user.click(screen.getByText('Translation'))
    expect(window.open).toHaveBeenCalledWith(
      'https://example.com/translate/job#100',
    )
  })

  test('opens revise link with revise_urls list', async () => {
    const user = userEvent.setup()
    renderComponent({
      last_translation: 'Tradotto',
      last_revisions: [{revision_number: 1, translation: 'Revisione'}],
    })

    await user.click(screen.getByText('Revision'))
    expect(window.open).toHaveBeenCalledWith(
      'https://example.com/revise1/job#100',
    )
  })

  test('renders APPROVED2 status as approved', () => {
    renderComponent({status: 'APPROVED2'})
    expect(screen.getByText('approved')).toBeInTheDocument()
  })

  test('does not render QA section when no issues and no warnings', () => {
    renderComponent()
    expect(screen.queryByText('QA')).not.toBeInTheDocument()
  })

  test('renders automated QA with WARNING categories', () => {
    renderComponent({
      warnings: {
        total: 1,
        details: {
          issues_info: {
            ERROR: {Categories: {}},
            WARNING: {
              Categories: {
                MISMATCH: ['mismatch1'],
              },
            },
            INFO: {Categories: {}},
          },
        },
      },
    })

    expect(screen.getByText('Character mismatch')).toBeInTheDocument()
  })
})
