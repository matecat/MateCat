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

const fileWith = (metadata) => ({
  id: 1,
  file_name: 'foo.docx',
  metadata,
})

describe('JobMetadataModal', () => {
  // Instructions reach the modal from file_metadata and are injected with
  // dangerouslySetInnerHTML, so anything stored before the write path was sanitized — or written
  // by another client — has to be neutralised here.
  describe('escapes stored instructions', () => {
    test('strips event handler attributes', () => {
      render(
        <JobMetadataModal
          showCurrent={true}
          currentFile={1}
          files={[
            fileWith({instructions: '<img src="x" onerror="alert(1)">caption'}),
          ]}
        />,
      )

      expect(screen.getByRole('img')).not.toHaveAttribute('onerror')
      expect(screen.getByText('caption')).toBeInTheDocument()
    })

    test('strips javascript: hrefs', () => {
      render(
        <JobMetadataModal
          showCurrent={true}
          currentFile={1}
          files={[
            fileWith({
              instructions: '<a href="javascript:alert(1)">click</a>',
            }),
          ]}
        />,
      )

      expect(screen.queryByRole('link')).not.toBeInTheDocument()
      expect(screen.queryByText(/javascript:/)).not.toBeInTheDocument()
    })

    test('escapes script tags', () => {
      render(
        <JobMetadataModal
          showCurrent={true}
          currentFile={1}
          files={[fileWith({instructions: '<script>alert(1)</script>hello'})]}
        />,
      )

      // Escaped, the tag is shown as text instead of becoming an element.
      expect(
        screen.getByText('<script>alert(1)</script>hello'),
      ).toBeInTheDocument()
    })

    test('applies the same filtering to the project instructions', () => {
      render(
        <JobMetadataModal
          projectInfo='<img src="x" onerror="alert(1)">'
          files={[fileWith({instructions: null})]}
        />,
      )

      expect(screen.getByRole('img')).not.toHaveAttribute('onerror')
    })

    test('keeps benign markup', () => {
      render(
        <JobMetadataModal
          showCurrent={true}
          currentFile={1}
          files={[fileWith({instructions: '<b>Bold</b> and plain text'})]}
        />,
      )

      // An element of its own holds `Bold`: escaped, it would be part of one text with `<b>`.
      expect(screen.getByText('Bold')).toBeInTheDocument()
      expect(screen.queryByText(/<b>/)).not.toBeInTheDocument()
      expect(screen.getByText(/and plain text/)).toBeInTheDocument()
    })
  })

  // The write path stores anchors as anchors — which of them a translator may follow is decided
  // here, and core decides none of them (isAllowedLinkRedirect is `() => false`).
  describe('gates stored links through isAllowedLinkRedirect', () => {
    test('flattens instruction anchors to markdown text', () => {
      render(
        <JobMetadataModal
          showCurrent={true}
          currentFile={1}
          files={[
            fileWith({
              instructions:
                '**Job number**: <a href="https://cloud.memsource.com/web/job/Tsit/translate" target="_blank">Tsit</a>',
            }),
          ]}
        />,
      )

      expect(screen.queryByRole('link')).not.toBeInTheDocument()
      expect(
        screen.getByText(
          '[Tsit](https://cloud.memsource.com/web/job/Tsit/translate)',
          {exact: false},
        ),
      ).toBeInTheDocument()
    })

    test('gates the project instructions too', () => {
      render(
        <JobMetadataModal
          projectInfo='read <a href="https://example.com">this</a>'
          files={[fileWith({instructions: null})]}
        />,
      )

      expect(screen.queryByRole('link')).not.toBeInTheDocument()
      expect(
        screen.getByText('[this](https://example.com)', {exact: false}),
      ).toBeInTheDocument()
    })
  })

  // Regression: removeNotAllowedLinksFromHtml used to re-query the anchor by its href
  // (`[href="..."]`), so an href containing a quote produced an invalid selector and
  // querySelector threw "is not a valid selector", taking the whole modal down.
  test('renders references whose href contains a quote', () => {
    expect(() =>
      render(
        <JobMetadataModal
          showCurrent={true}
          currentFile={1}
          files={[
            fileWith({
              instructions: null,
              'mtc:references': "<a href='https://x.com/a\"b'>quoted</a>",
            }),
          ]}
        />,
      ),
    ).not.toThrow()
  })

  test('flattens references with duplicated hrefs', () => {
    render(
      <JobMetadataModal
        showCurrent={true}
        currentFile={1}
        files={[
          fileWith({
            instructions: null,
            'mtc:references':
              '<a href="https://dup.com">one</a> and <a href="https://dup.com">two</a>',
          }),
        ]}
      />,
    )

    // isAllowedLinkRedirect is false in core, so both anchors are flattened to markdown text.
    expect(
      screen.getByText('[one](https://dup.com) and [two](https://dup.com)', {
        exact: false,
      }),
    ).toBeInTheDocument()
  })

  describe('line breaks in plain-text instructions', () => {
    // The injected instructions are the only element carrying this class.
    const instructionsText = () =>
      screen.getByText(
        (_, element) =>
          element?.classList.contains('instructions-text') ?? false,
      )

    test('renders CRLF and LF newlines as line breaks', () => {
      render(
        <JobMetadataModal
          showCurrent={true}
          currentFile={1}
          files={[
            fileWith({
              instructions:
                '**Project domain**: UI\r\n\r\n**Owner**: Uber\nCost center',
            }),
          ]}
        />,
      )

      expect(instructionsText()).toContainHTML(
        '**Project domain**: UI<br><br>**Owner**: Uber<br>Cost center',
      )
    })

    test('keeps line breaks in the project instructions', () => {
      render(
        <JobMetadataModal
          files={[]}
          projectInfo={'Client: Freight\r\nDomain: UI'}
        />,
      )

      expect(instructionsText()).toContainHTML('Client: Freight<br>Domain: UI')
    })

    // Plugins render markdown to paragraphs first, with a newline between each block.
    test('does not add breaks between blocks of already rendered markup', () => {
      render(
        <JobMetadataModal
          showCurrent={true}
          currentFile={1}
          files={[fileWith({instructions: '<p>First</p>\n<p>Second</p>'})]}
        />,
      )

      expect(instructionsText()).not.toContainHTML('<br>')
      expect(screen.getByText('Second')).toBeInTheDocument()
    })
  })
})
