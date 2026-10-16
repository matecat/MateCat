import React from 'react'
import {render, screen} from '@testing-library/react'
import {FilesCard, getFilesTitle} from './FilesCard'

const file = (id, name) => ({
  id,
  name,
  totals: {raw: 10, industry: null, equivalent: 8},
  matches: [{type: 'new', raw: 10, equivalent: 8}],
})

const renderCard = (props = {}) =>
  render(
    <FilesCard
      files={[file(1, 'docs/manual.docx'), file(2, 'guide.docx')]}
      workflowType="standard"
      done={true}
      {...props}
    />,
  )

describe('FilesCard', () => {
  test('lists the files once the analysis is complete', () => {
    renderCard()
    expect(screen.getByRole('heading', {name: 'Files (2)'})).toBeInTheDocument()
    expect(screen.getByText('manual.docx')).toBeInTheDocument()
    expect(screen.getByText('guide.docx')).toBeInTheDocument()
  })

  test('one file is "File"', () => {
    renderCard({files: [file(1, 'guide.docx')]})
    expect(screen.getByRole('heading', {name: 'File'})).toBeInTheDocument()
  })

  test('explains the wait while the analysis runs', () => {
    renderCard({done: false})
    expect(screen.queryByText('manual.docx')).not.toBeInTheDocument()
    expect(
      screen.getByText(
        'The words per file appear when the analysis is complete.',
      ),
    ).toBeInTheDocument()
  })

  test('getFilesTitle uses a comma separator', () => {
    expect(getFilesTitle(1200)).toBe('Files (1,200)')
  })

  test('a page that does not refresh asks for a reload', () => {
    renderCard({done: false, reloadWhenDone: true})
    expect(
      screen.getByText(
        'The analysis is still running. Reload the page once it is complete to see the words per file.',
      ),
    ).toBeInTheDocument()
  })
})
