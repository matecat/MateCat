import React from 'react'
import {render, screen, waitFor, within} from '@testing-library/react'
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

    expect(screen.getByPlaceholderText('ID segment')).toBeInTheDocument()
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

    const input = screen.getByPlaceholderText('ID segment')
    expect(input).toHaveValue('42')
  })

  test('id_segment input accepts user typing', async () => {
    const user = userEvent.setup()
    renderComponent()

    const input = screen.getByPlaceholderText('ID segment')
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

  test('shows the revision phase that was picked, not always Revise 2', async () => {
    const user = userEvent.setup()
    renderComponent({secondPassReviewEnabled: true})

    await user.click(screen.getByText('Revision phase'))
    await user.click(await screen.findByText('Revise 1'))

    await waitFor(() => {
      expect(screen.queryByText('Revision phase')).not.toBeInTheDocument()
    })
    expect(screen.getByText('Revise 1')).toBeInTheDocument()
    expect(screen.queryByText('Revise 2')).not.toBeInTheDocument()
  })

  describe('multiple selection', () => {
    // The label can also appear in the trigger once selected, so look inside the open list
    const clickOption = async (user, label) => {
      await user.click(within(screen.getByRole('list')).getByText(label))
    }

    const lastFilter = (applyFilter) =>
      applyFilter.mock.calls[applyFilter.mock.calls.length - 1][0]

    test('severity accumulates selections into a list', async () => {
      const user = userEvent.setup()
      const applyFilter = jest.fn()
      renderComponent({applyFilter})

      await user.click(screen.getByText('Issue severity'))
      await clickOption(user, 'minor')
      expect(lastFilter(applyFilter).severity).toEqual(['minor'])

      // The dropdown stays open, so the second option is clickable without reopening
      await clickOption(user, 'major')
      expect(lastFilter(applyFilter).severity).toEqual(['minor', 'major'])
    })

    test('severity deselection removes the item and an empty list becomes null', async () => {
      const user = userEvent.setup()
      const applyFilter = jest.fn()
      renderComponent({applyFilter})

      await user.click(screen.getByText('Issue severity'))
      await clickOption(user, 'minor')
      await clickOption(user, 'major')
      await clickOption(user, 'minor')
      expect(lastFilter(applyFilter).severity).toEqual(['major'])

      await clickOption(user, 'major')
      expect(lastFilter(applyFilter).severity).toBeNull()
    })

    test('selected severities mark the filter as filtered', async () => {
      const user = userEvent.setup()
      const {container} = renderComponent()
      // The class is a styling hook with no accessible equivalent
      // eslint-disable-next-line testing-library/no-container, testing-library/no-node-access
      const severityFilter = () => container.querySelector('.filter-severity')

      expect(severityFilter()).toHaveClass('not-filtered')
      await user.click(screen.getByText('Issue severity'))
      await clickOption(user, 'minor')

      expect(severityFilter()).toHaveClass('filtered')
    })

    test('severity and category lists combine in the same filter', async () => {
      const user = userEvent.setup()
      const applyFilter = jest.fn()
      renderComponent({applyFilter})

      await user.click(screen.getByText('Issue category'))
      await clickOption(user, 'Typing')
      await clickOption(user, 'Translation')
      expect(lastFilter(applyFilter).issue_category).toEqual(['1', '2'])

      await user.click(screen.getByText('Issue severity'))
      await clickOption(user, 'major')
      expect(lastFilter(applyFilter)).toMatchObject({
        issue_category: ['1', '2'],
        severity: ['major'],
      })
    })
  })
})
