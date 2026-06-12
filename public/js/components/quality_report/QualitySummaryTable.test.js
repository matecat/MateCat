import React from 'react'
import {render, screen} from '@testing-library/react'
import Immutable from 'immutable'

import QualitySummaryTable from './QualitySummaryTable'

// Helper to build an Immutable qualitySummary prop from plain JS
const buildQualitySummary = (overrides = {}) => {
  const defaults = {
    revision_number: 1,
    feedback: null,
    score: 0,
    quality_overall: 'excellent',
    total_issues_weight: 0,
    total_reviewed_words_count: 100,
    passfail: '',
    categories: [
      {
        label: 'Typing',
        id: 1,
        severities: [
          {label: 'minor', penalty: 0.03, sort: 1},
          {label: 'major', penalty: 1, sort: 2},
        ],
        subcategories: [],
        options: [],
      },
      {
        label: 'Translation',
        id: 2,
        severities: [
          {label: 'minor', penalty: 0.03, sort: 1},
          {label: 'major', penalty: 1, sort: 2},
        ],
        subcategories: [],
        options: [],
      },
      {
        label: 'Style',
        id: 3,
        severities: [
          {label: 'minor', penalty: 0.03, sort: 1},
          {label: 'major', penalty: 1, sort: 2},
        ],
        subcategories: [],
        options: [],
      },
    ],
    revise_issues: {},
  }

  return Immutable.fromJS({...defaults, ...overrides})
}

const defaultJobInfo = Immutable.fromJS({
  id: 123,
  source: 'en-US',
  target: 'it-IT',
})

const renderComponent = (qualitySummaryOverrides = {}) => {
  const qualitySummary = buildQualitySummary(qualitySummaryOverrides)
  return render(
    <QualitySummaryTable
      qualitySummary={qualitySummary}
      jobInfo={defaultJobInfo}
    />,
  )
}

describe('QualitySummaryTable', () => {
  test('renders the header with category and severity columns', () => {
    renderComponent()

    expect(screen.getByText('Categories')).toBeInTheDocument()
    expect(screen.getByText('Severities')).toBeInTheDocument()
    expect(screen.getByText('Error Points')).toBeInTheDocument()
  })

  test('renders all category labels', () => {
    renderComponent()

    expect(screen.getByText('Typing')).toBeInTheDocument()
    expect(screen.getByText('Translation')).toBeInTheDocument()
    expect(screen.getByText('Style')).toBeInTheDocument()
  })

  test('renders total line', () => {
    renderComponent()

    expect(screen.getByText('Total')).toBeInTheDocument()
  })

  test('renders severity weight labels with penalty multiplier', () => {
    renderComponent()

    expect(screen.getByText('(x0.03)')).toBeInTheDocument()
    expect(screen.getByText('(x1)')).toBeInTheDocument()
  })

  test('displays issue counts when revise_issues has data', () => {
    renderComponent({
      revise_issues: {
        1: {
          name: 'Typing',
          founds: {minor: 3, major: 1},
        },
        2: {
          name: 'Translation',
          founds: {minor: 0, major: 2},
        },
      },
      total_issues_weight: 2.09,
    })

    // Typing: minor=3, major=1 => error points = 3*0.03 + 1*1 = 1.09
    expect(screen.getByText('3')).toBeInTheDocument()
    // Translation: major=2 => error points = 2*1 = 2
    expect(screen.getByText('2.09')).toBeInTheDocument()
  })

  test('renders zero total when no issues exist', () => {
    renderComponent({total_issues_weight: 0})

    const totalElements = screen.getAllByText('0')
    expect(totalElements.length).toBeGreaterThan(0)
    expect(screen.getByText('Total')).toBeInTheDocument()
  })

  test('renders with subcategories when present', () => {
    // Note: getCategorySeverities checks `cat.get('severities')` for truthiness.
    // An empty Immutable List is truthy, so parent categories should either omit
    // severities or include the same severities as subcategories.
    const categoriesData = Immutable.fromJS([
      {
        label: 'Accuracy',
        id: 10,
        severities: [
          {label: 'minor', penalty: 1, sort: 1},
          {label: 'major', penalty: 5, sort: 2},
        ],
        subcategories: [
          {
            label: 'Mistranslation',
            id: 11,
            severities: [
              {label: 'minor', penalty: 1, sort: 1},
              {label: 'major', penalty: 5, sort: 2},
            ],
          },
          {
            label: 'Omission',
            id: 12,
            severities: [
              {label: 'minor', penalty: 1, sort: 1},
              {label: 'major', penalty: 5, sort: 2},
            ],
          },
        ],
        options: [],
      },
      {
        label: 'Fluency',
        id: 20,
        severities: [
          {label: 'minor', penalty: 1, sort: 1},
          {label: 'major', penalty: 5, sort: 2},
        ],
        subcategories: [
          {
            label: 'Grammar',
            id: 21,
            severities: [
              {label: 'minor', penalty: 1, sort: 1},
              {label: 'major', penalty: 5, sort: 2},
            ],
          },
        ],
        options: [],
      },
    ])

    // Build revise_issues with integer keys so .get(sub.id) works
    const reviseIssues = Immutable.Map().set(
      11,
      Immutable.fromJS({name: 'Mistranslation', founds: {minor: 2, major: 1}}),
    )

    const qualitySummary = Immutable.fromJS({
      revision_number: 1,
      feedback: null,
      score: 0,
      quality_overall: 'excellent',
      total_issues_weight: 7,
      total_reviewed_words_count: 100,
      passfail: '',
    })
      .set('categories', categoriesData)
      .set('revise_issues', reviseIssues)

    render(
      <QualitySummaryTable
        qualitySummary={qualitySummary}
        jobInfo={defaultJobInfo}
      />,
    )

    // Parent categories should be displayed
    expect(screen.getByText('Accuracy')).toBeInTheDocument()
    expect(screen.getByText('Fluency')).toBeInTheDocument()
    // Subcategory issues aggregated under Accuracy: minor=2, major=1
    expect(screen.getByText('2')).toBeInTheDocument()
    expect(screen.getByText('1')).toBeInTheDocument()
  })

  test('renders Kudos section when Kudos category exists', () => {
    renderComponent({
      categories: [
        {
          label: 'Typing',
          id: 1,
          severities: [
            {label: 'minor', penalty: 0.03, sort: 1},
            {label: 'major', penalty: 1, sort: 2},
          ],
          subcategories: [],
          options: [],
        },
        {
          label: 'Kudos',
          id: 99,
          severities: [{label: 'Neutral', penalty: 0, sort: 0}],
          subcategories: [],
          options: [],
        },
      ],
      revise_issues: {
        99: {name: 'Kudos', founds: {Neutral: 5}},
      },
    })

    expect(screen.getByText('Kudos')).toBeInTheDocument()
    expect(screen.getByText('5')).toBeInTheDocument()
  })

  test('renders Kudos with 0 when no neutral issues found', () => {
    renderComponent({
      categories: [
        {
          label: 'Typing',
          id: 1,
          severities: [
            {label: 'minor', penalty: 0.03, sort: 1},
            {label: 'major', penalty: 1, sort: 2},
          ],
          subcategories: [],
          options: [],
        },
        {
          label: 'Kudos',
          id: 99,
          severities: [{label: 'Neutral', penalty: 0, sort: 0}],
          subcategories: [],
          options: [],
        },
      ],
      revise_issues: {},
    })

    expect(screen.getByText('Kudos')).toBeInTheDocument()
    // Kudos value should be 0 — find within the Kudos container
    const kudosEl = screen.getByText('Kudos').closest('.qr-kudos')
    expect(kudosEl).toHaveTextContent('0')
  })

  test('severities are sorted by sort property when defined', () => {
    const qualitySummary = buildQualitySummary({
      categories: [
        {
          label: 'Typing',
          id: 1,
          severities: [
            {label: 'critical', penalty: 5, sort: 3},
            {label: 'minor', penalty: 0.03, sort: 1},
            {label: 'major', penalty: 1, sort: 2},
          ],
          subcategories: [],
          options: [],
        },
      ],
    })

    render(
      <QualitySummaryTable
        qualitySummary={qualitySummary}
        jobInfo={defaultJobInfo}
      />,
    )

    // Verify the severity labels appear in sorted order (minor, major, critical)
    const minorEl = screen.getByText('minor', {exact: false})
    const majorEl = screen.getByText('major', {exact: false})
    const criticalEl = screen.getByText('critical', {exact: false})

    // Compare document order via compareDocumentPosition
    expect(
      minorEl.compareDocumentPosition(majorEl) &
        Node.DOCUMENT_POSITION_FOLLOWING,
    ).toBeTruthy()
    expect(
      majorEl.compareDocumentPosition(criticalEl) &
        Node.DOCUMENT_POSITION_FOLLOWING,
    ).toBeTruthy()
  })

  test('renders with different severity sets across categories', () => {
    const qualitySummary = buildQualitySummary({
      categories: [
        {
          label: 'Typing',
          id: 1,
          severities: [
            {label: 'minor', penalty: 0.03, sort: 1},
            {label: 'major', penalty: 1, sort: 2},
          ],
          subcategories: [],
          options: [],
        },
        {
          label: 'Style',
          id: 3,
          severities: [
            {label: 'minor', penalty: 0.1, sort: 1},
            {label: 'critical', penalty: 5, sort: 3},
          ],
          subcategories: [],
          options: [],
        },
      ],
    })

    render(
      <QualitySummaryTable
        qualitySummary={qualitySummary}
        jobInfo={defaultJobInfo}
      />,
    )

    // Should render without errors even with different severity groups
    expect(screen.getByText('Typing')).toBeInTheDocument()
    expect(screen.getByText('Style')).toBeInTheDocument()
    // Both severity groups should show penalty multipliers
    expect(screen.getByText('(x0.03)')).toBeInTheDocument()
    expect(screen.getByText('(x0.1)')).toBeInTheDocument()
  })
})
