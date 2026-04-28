import React from 'react'
import {render, screen, waitFor} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import Immutable from 'immutable'

import FilterSegments from './FilterSegments'

beforeEach(() => {
  global.config = {
    ...global.config,
    searchable_statuses: [
      {value: 'NEW', label: 'NEW'},
      {value: 'DRAFT', label: 'DRAFT'},
      {value: 'TRANSLATED', label: 'TRANSLATED'},
      {value: 'APPROVED', label: 'APPROVED'},
      {value: 'REJECTED', label: 'REJECTED'},
    ],
  }
})

const buildCategories = (overrides) => {
  const defaults = [
    {
      label: 'Typing',
      id: 1,
      severities: [
        {label: 'minor', penalty: 0.03},
        {label: 'major', penalty: 1},
      ],
      subcategories: [],
    },
    {
      label: 'Translation',
      id: 2,
      severities: [
        {label: 'minor', penalty: 0.03},
        {label: 'major', penalty: 1},
      ],
      subcategories: [],
    },
  ]
  return Immutable.fromJS(overrides || defaults)
}

const defaultProps = {
  applyFilter: jest.fn(),
  updateSegmentToFilter: jest.fn(),
  secondPassReviewEnabled: false,
  segmentToFilter: null,
}

const renderComponent = (propsOverrides = {}, categoriesOverrides) => {
  const props = {...defaultProps, ...propsOverrides}
  return render(
    <FilterSegments
      categories={buildCategories(categoriesOverrides)}
      {...props}
    />,
  )
}

describe('FilterSegments', () => {
  test('renders filter icon and input field', () => {
    renderComponent()

    expect(screen.getByPlaceholderText('Id Segment')).toBeInTheDocument()
  })

  test('renders segment status filter', () => {
    renderComponent()

    expect(screen.getByText('Segment status')).toBeInTheDocument()
  })

  test('renders issue category filter', () => {
    renderComponent()

    expect(screen.getByText('Issue category')).toBeInTheDocument()
  })

  test('renders issue severity filter', () => {
    renderComponent()

    expect(screen.getByText('Issue severity')).toBeInTheDocument()
  })

  test('does not render revision phase filter when secondPassReviewEnabled is false', () => {
    renderComponent()

    expect(screen.queryByText('Revision phase')).not.toBeInTheDocument()
  })

  test('renders revision phase filter when secondPassReviewEnabled is true', () => {
    renderComponent({secondPassReviewEnabled: true})

    expect(screen.getByText('Revision phase')).toBeInTheDocument()
  })

  test('shows id_segment input with initial value from segmentToFilter prop', () => {
    renderComponent({segmentToFilter: '42'})

    const input = screen.getByPlaceholderText('Id Segment')
    expect(input).toHaveValue('42')
  })

  test('id_segment input accepts user typing', async () => {
    const user = userEvent.setup()
    renderComponent()

    const input = screen.getByPlaceholderText('Id Segment')
    await user.type(input, '5')

    expect(input).toHaveValue('5')
  })

  test('filters REJECTED from status options', async () => {
    const user = userEvent.setup()
    renderComponent()

    // Open the status dropdown
    const statusPlaceholder = screen.getByText('Segment status')
    await user.click(statusPlaceholder)

    await waitFor(() => {
      expect(screen.getByText('NEW')).toBeInTheDocument()
    })
    expect(screen.queryByText('REJECTED')).not.toBeInTheDocument()
    expect(screen.getByText('TRANSLATED')).toBeInTheDocument()
    expect(screen.getByText('APPROVED')).toBeInTheDocument()
  })

  test('category options include "Any" plus all categories', async () => {
    const user = userEvent.setup()
    renderComponent()

    // Open the category dropdown
    const categoryPlaceholder = screen.getByText('Issue category')
    await user.click(categoryPlaceholder)

    await waitFor(() => {
      expect(screen.getByText('Any')).toBeInTheDocument()
    })
    expect(screen.getByText('Typing')).toBeInTheDocument()
    expect(screen.getByText('Translation')).toBeInTheDocument()
  })

  test('severity options are unique across categories', async () => {
    const user = userEvent.setup()
    renderComponent()

    // Open the severity dropdown
    const severityPlaceholder = screen.getByText('Issue severity')
    await user.click(severityPlaceholder)

    await waitFor(() => {
      expect(screen.getAllByText('minor').length).toBeGreaterThanOrEqual(1)
    })
    expect(screen.getAllByText('major').length).toBeGreaterThanOrEqual(1)
  })

  test('severity options collect from subcategories when present', async () => {
    const user = userEvent.setup()
    const categoriesWithSubs = [
      {
        label: 'Accuracy',
        id: 10,
        severities: [],
        subcategories: [
          {
            label: 'Mistranslation',
            id: 11,
            severities: [
              {label: 'critical', penalty: 5},
              {label: 'major', penalty: 1},
            ],
          },
        ],
      },
    ]
    renderComponent({}, categoriesWithSubs)

    // Open the severity dropdown
    const severityPlaceholder = screen.getByText('Issue severity')
    await user.click(severityPlaceholder)

    await waitFor(() => {
      expect(screen.getByText('critical')).toBeInTheDocument()
    })
  })

  test('renders Revise 1 and Revise 2 options when secondPassReviewEnabled', async () => {
    const user = userEvent.setup()
    renderComponent({secondPassReviewEnabled: true})

    // Open the revision phase dropdown
    const revisionPlaceholder = screen.getByText('Revision phase')
    await user.click(revisionPlaceholder)

    await waitFor(() => {
      expect(screen.getByText('Revise 1')).toBeInTheDocument()
    })
    expect(screen.getByText('Revise 2')).toBeInTheDocument()
  })
})
