import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import JobMetadataModal from './JobMetadataModal'

global.ResizeObserver = class ResizeObserver {
  observe() {}
  unobserve() {}
  disconnect() {}
}

const files = [
  {
    id: 1,
    file_name: 'first.docx',
    metadata: {
      instructions: '<p>Follow these steps</p>',
      'mtc:references': '<a href="https://evil.example">bad link</a>',
    },
  },
  {
    id: 2,
    file_name: 'second.txt',
    metadata: {},
  },
]

test('renders project instructions when not showing a single current file', () => {
  render(
    <JobMetadataModal
      files={files}
      projectInfo="<p>Project level notes</p>"
      showCurrent={false}
    />,
  )

  expect(screen.getByText('Project instructions')).toBeInTheDocument()
  expect(screen.getByText('File instructions')).toBeInTheDocument()
  expect(screen.getByText('first.docx')).toBeInTheDocument()
})

test('does not render the File instructions section when no file has instructions or references', () => {
  render(
    <JobMetadataModal
      files={[{id: 3, file_name: 'plain.txt', metadata: {}}]}
      projectInfo={null}
      showCurrent={false}
    />,
  )

  expect(screen.queryByText('File instructions')).not.toBeInTheDocument()
  expect(screen.queryByText('Project instructions')).not.toBeInTheDocument()
})

test('renders a single current file with instructions and sanitized references', () => {
  const {container} = render(
    <JobMetadataModal files={files} currentFile={1} showCurrent={true} />,
  )

  expect(
    screen.getByText(
      'Please read the following notes and references carefully:',
    ),
  ).toBeInTheDocument()
  expect(container.querySelector('.instructions-container')).toHaveTextContent(
    'Follow these steps',
  )
  // isAllowedLinkRedirect always returns false in this codebase, so the
  // disallowed link is rewritten as plain markdown-style text instead of <a>.
  expect(container.querySelector('a')).not.toBeInTheDocument()
  expect(container.querySelector('.instructions-container')).toHaveTextContent(
    '[bad link](https://evil.example)',
  )
})

test('expanding a file accordion toggles the currentFile state', () => {
  render(<JobMetadataModal files={files} showCurrent={false} />)

  const title = screen.getByText('first.docx')
  fireEvent.click(title)

  expect(screen.getByText('Follow these steps')).toBeInTheDocument()
})
