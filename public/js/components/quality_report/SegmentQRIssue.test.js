import {render} from '@testing-library/react'
import {screen} from '@testing-library/dom'
import userEvent from '@testing-library/user-event'
import React from 'react'
import {fromJS} from 'immutable'
import SegmentQRIssue from './SegmentQRIssue'

test('renders category and severity with no comments', () => {
  render(
    <SegmentQRIssue
      index={0}
      issue={fromJS({issue_category: 'Accuracy', issue_severity: 'Major'})}
    />,
  )
  expect(screen.getByText(/Accuracy/)).toBeInTheDocument()
  expect(screen.getByText('[Major]')).toBeInTheDocument()
})

test('renders comment lines from a translator and a reviewer', async () => {
  const user = userEvent.setup()
  render(
    <SegmentQRIssue
      index={1}
      issue={fromJS({
        issue_category: 'Fluency',
        issue_severity: 'Minor',
        comments: [
          {
            id: 1,
            create_date: '2026-01-01T10:00:00Z',
            source_page: 1,
            comment: 'from translator',
          },
          {
            id: 2,
            create_date: '2026-01-02T10:00:00Z',
            source_page: 2,
            comment: 'from reviewer',
          },
        ],
      })}
    />,
  )
  // Comment lines render inside the Tooltip's interactive content, which only
  // mounts after the trigger is hovered (Tooltip.js delays showing by 300ms).
  await user.hover(screen.getByTitle('Comments'))
  expect(await screen.findByText('from translator')).toBeInTheDocument()
  expect(await screen.findByText('from reviewer')).toBeInTheDocument()
})

test('renders selected target text line when present', async () => {
  const user = userEvent.setup()
  render(
    <SegmentQRIssue
      index={2}
      issue={fromJS({
        issue_id: 3,
        issue_category: 'Style',
        issue_severity: 'Minor',
        target_text: 'selected phrase',
      })}
    />,
  )
  await user.hover(screen.getByTitle('Comments'))
  expect(await screen.findByText('selected phrase')).toBeInTheDocument()
})
