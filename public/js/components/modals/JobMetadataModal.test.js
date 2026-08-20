import React from 'react'
import '../../extensions/extensionManifest'
import {render, screen, fireEvent, act} from '@testing-library/react'
import JobMetadataModal from './JobMetadataModal'
import CommonUtils from '../../utils/commonUtils'

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
  {
    id: 4,
    file_name: 'fourth.pdf',
    metadata: {
      instructions: '<p>Fourth file notes</p>',
    },
  },
]

// The Accordion's own title wrapper (the element carrying `data-expanded`) is
// the grandparent of the text node rendered by JobMetadataModal's title JSX
// (`<div className="title">{icon}<div>{file_name}</div></div>`).
const getAccordionWrapper = (fileLabelText) =>
  // eslint-disable-next-line testing-library/no-node-access
  screen.getByText(fileLabelText).parentElement.parentElement

afterEach(() => {
  jest.useRealTimers()
})

test('renders project instructions when not showing a single current file', () => {
  const {container} = render(
    <JobMetadataModal
      files={files}
      projectInfo="<p>Project level notes</p>"
      showCurrent={false}
    />,
  )

  expect(screen.getByText('Project instructions')).toBeInTheDocument()
  // eslint-disable-next-line testing-library/no-container, testing-library/no-node-access
  expect(container.querySelector('.instructions-container')).toHaveTextContent(
    'Project level notes',
  )
  expect(screen.getByText('File instructions')).toBeInTheDocument()
  expect(screen.getByText('first.docx')).toBeInTheDocument()
  // A file whose metadata has neither instructions nor a valid mtc:references
  // must not be rendered as an accordion entry.
  expect(screen.queryByText('second.txt')).not.toBeInTheDocument()
})

test('does not render the Project instructions heading when no projectInfo is provided', () => {
  render(
    <JobMetadataModal files={files} projectInfo={null} showCurrent={false} />,
  )

  expect(screen.queryByText('Project instructions')).not.toBeInTheDocument()
  // Files with instructions should still render the File instructions section.
  expect(screen.getByText('File instructions')).toBeInTheDocument()
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
  // eslint-disable-next-line testing-library/no-container, testing-library/no-node-access
  expect(container.querySelector('.instructions-container')).toHaveTextContent(
    'Follow these steps',
  )
  // isAllowedLinkRedirect always returns false in this codebase, so the
  // disallowed link is rewritten as plain markdown-style text instead of <a>.
  // eslint-disable-next-line testing-library/no-container, testing-library/no-node-access
  expect(container.querySelector('a')).not.toBeInTheDocument()
  // eslint-disable-next-line testing-library/no-container, testing-library/no-node-access
  expect(container.querySelector('.instructions-container')).toHaveTextContent(
    '[bad link](https://evil.example)',
  )
})

test('matches the current file across string/number id type mismatches via parseInt', () => {
  const {container} = render(
    <JobMetadataModal files={files} currentFile={'1'} showCurrent={true} />,
  )

  // eslint-disable-next-line testing-library/no-container, testing-library/no-node-access
  expect(container.querySelector('.instructions-container')).toHaveTextContent(
    'Follow these steps',
  )
})

test('does not render a reference paragraph when mtc:references is not a string', () => {
  const nonStringFiles = [
    {
      id: 30,
      file_name: 'obj.docx',
      metadata: {
        instructions: '<p>Has instructions</p>',
        'mtc:references': {foo: 'bar'},
      },
    },
  ]
  const {container} = render(
    <JobMetadataModal
      files={nonStringFiles}
      currentFile={30}
      showCurrent={true}
    />,
  )

  expect(container.innerHTML).not.toContain('Reference:')

  const undefinedRefFiles = [
    {
      id: 31,
      file_name: 'undef.docx',
      metadata: {instructions: '<p>Has instructions</p>'},
    },
  ]
  const {container: container2} = render(
    <JobMetadataModal
      files={undefinedRefFiles}
      currentFile={31}
      showCurrent={true}
    />,
  )

  expect(container2.innerHTML).not.toContain('Reference:')
})

test('filters disallowed markup out of mtc:references while preserving safe text', () => {
  const maliciousFiles = [
    {
      id: 10,
      file_name: 'malicious.docx',
      metadata: {
        instructions: '<p>Read carefully</p>',
        'mtc:references':
          '<script>alert(1)</script><img src=x onerror="alert(2)">safe reference text',
      },
    },
  ]
  const {container} = render(
    <JobMetadataModal
      files={maliciousFiles}
      currentFile={10}
      showCurrent={true}
    />,
  )

  // filterXSS must actually run: no <script> element and no onerror handler
  // should survive into the rendered DOM.
  // eslint-disable-next-line testing-library/no-container, testing-library/no-node-access
  expect(container.querySelector('script')).not.toBeInTheDocument()
  expect(container.innerHTML).not.toContain('onerror')
  // eslint-disable-next-line testing-library/no-container, testing-library/no-node-access
  expect(container.querySelector('.instructions-container')).toHaveTextContent(
    'safe reference text',
  )
})

test('rewrites disallowed redirect links to plain text but preserves allowed links', () => {
  const spy = jest.spyOn(CommonUtils, 'isAllowedLinkRedirect')
  spy.mockImplementation((href) => href === 'https://allowed.example')

  const linkFiles = [
    {
      id: 20,
      file_name: 'links.docx',
      metadata: {
        instructions: '<p>Notes</p>',
        'mtc:references':
          '<a href="https://allowed.example">good link</a> and <a href="https://blocked.example">bad link</a>',
      },
    },
  ]
  const {container} = render(
    <JobMetadataModal files={linkFiles} currentFile={20} showCurrent={true} />,
  )

  // eslint-disable-next-line testing-library/no-container, testing-library/no-node-access
  const links = container.querySelectorAll('a')
  expect(links).toHaveLength(1)
  expect(links[0]).toHaveAttribute('href', 'https://allowed.example')
  // eslint-disable-next-line testing-library/no-container, testing-library/no-node-access
  expect(container.querySelector('.instructions-container')).toHaveTextContent(
    '[bad link](https://blocked.example)',
  )

  spy.mockRestore()
})

test('accordion expand state toggles on click, collapses back to undefined, and only one file is expanded at a time', () => {
  render(<JobMetadataModal files={files} showCurrent={false} />)

  const firstWrapper = getAccordionWrapper('first.docx')
  const fourthWrapper = getAccordionWrapper('fourth.pdf')

  expect(firstWrapper).not.toHaveAttribute('data-expanded')
  expect(fourthWrapper).not.toHaveAttribute('data-expanded')

  fireEvent.click(screen.getByText('first.docx'))
  expect(firstWrapper).toHaveAttribute('data-expanded', 'true')
  expect(fourthWrapper).not.toHaveAttribute('data-expanded')
  expect(screen.getByText('Follow these steps')).toBeInTheDocument()

  // Clicking a different file switches the expanded one (only one at a time).
  fireEvent.click(screen.getByText('fourth.pdf'))
  expect(firstWrapper).not.toHaveAttribute('data-expanded')
  expect(fourthWrapper).toHaveAttribute('data-expanded', 'true')

  // Clicking the already-expanded file collapses it back to undefined.
  fireEvent.click(screen.getByText('fourth.pdf'))
  expect(fourthWrapper).not.toHaveAttribute('data-expanded')
})

test('scrolls the active title into view 200ms after mount when present', () => {
  jest.useFakeTimers()
  document.body.innerHTML = '<div class="title current active"></div>'
  const scrollIntoViewMock = jest.fn()
  // eslint-disable-next-line testing-library/no-node-access
  document.querySelector('.title.current.active').scrollIntoView =
    scrollIntoViewMock

  render(<JobMetadataModal files={[]} showCurrent={false} />)

  act(() => {
    jest.advanceTimersByTime(200)
  })

  expect(scrollIntoViewMock).toHaveBeenCalledWith({behavior: 'smooth'})

  document.body.innerHTML = ''
})

test('does not throw 200ms after mount when no active title element exists', () => {
  jest.useFakeTimers()

  render(<JobMetadataModal files={[]} showCurrent={false} />)

  expect(() => {
    act(() => {
      jest.advanceTimersByTime(200)
    })
  }).not.toThrow()
})

test('expanding a file accordion toggles the currentFile state', () => {
  render(<JobMetadataModal files={files} showCurrent={false} />)

  const title = screen.getByText('first.docx')
  fireEvent.click(title)

  expect(screen.getByText('Follow these steps')).toBeInTheDocument()
})

test('strips event handler attributes from file instructions', () => {
  const maliciousFiles = [
    {
      id: 40,
      file_name: 'onerror.docx',
      metadata: {
        instructions: '<img src="x" onerror="alert(1)">caption',
      },
    },
  ]
  const {container} = render(
    <JobMetadataModal
      files={maliciousFiles}
      currentFile={40}
      showCurrent={true}
    />,
  )

  // eslint-disable-next-line testing-library/no-container, testing-library/no-node-access
  expect(container.querySelector('[onerror]')).not.toBeInTheDocument()
  expect(container.innerHTML).not.toContain('onerror')
  expect(container).toHaveTextContent('caption')
})

test('strips javascript: hrefs from file instructions', () => {
  const maliciousFiles = [
    {
      id: 41,
      file_name: 'javascript-href.docx',
      metadata: {
        instructions: '<a href="javascript:alert(1)">click me</a>',
      },
    },
  ]
  const {container} = render(
    <JobMetadataModal
      files={maliciousFiles}
      currentFile={41}
      showCurrent={true}
    />,
  )

  expect(container.innerHTML).not.toContain('javascript:')
})

test('escapes script tags in file instructions', () => {
  const maliciousFiles = [
    {
      id: 42,
      file_name: 'script.docx',
      metadata: {
        instructions: '<script>alert(1)</script>',
      },
    },
  ]
  const {container} = render(
    <JobMetadataModal
      files={maliciousFiles}
      currentFile={42}
      showCurrent={true}
    />,
  )

  // eslint-disable-next-line testing-library/no-container, testing-library/no-node-access
  expect(container.querySelector('script')).not.toBeInTheDocument()
})

test('applies the same sanitization to project instructions', () => {
  const {container} = render(
    <JobMetadataModal
      files={files}
      projectInfo='<img src="x" onerror="alert(1)">project notes'
      showCurrent={false}
    />,
  )

  expect(container.innerHTML).not.toContain('onerror')
})

test('keeps benign markup and real links intact', () => {
  const spy = jest.spyOn(CommonUtils, 'isAllowedLinkRedirect')
  spy.mockImplementation((href) => href === 'https://example.com')

  const benignFiles = [
    {
      id: 43,
      file_name: 'benign.docx',
      metadata: {
        instructions:
          '<b>Bold notes</b> and <a href="https://example.com">a link</a>',
      },
    },
  ]
  const {container} = render(
    <JobMetadataModal
      files={benignFiles}
      currentFile={43}
      showCurrent={true}
    />,
  )

  // eslint-disable-next-line testing-library/no-container, testing-library/no-node-access
  expect(container.querySelector('b')).toBeInTheDocument()
  expect(
    // eslint-disable-next-line testing-library/no-container, testing-library/no-node-access
    container.querySelector('a[href="https://example.com"]'),
  ).toBeInTheDocument()

  spy.mockRestore()
})

test('renders references whose href contains a quote without throwing', () => {
  const quotedHrefFiles = [
    {
      id: 44,
      file_name: 'quoted-href.docx',
      metadata: {
        instructions: '<p>Notes</p>',
        'mtc:references': "<a href='https://x.com/a\"b'>quoted</a>",
      },
    },
  ]

  expect(() => {
    render(
      <JobMetadataModal
        files={quotedHrefFiles}
        currentFile={44}
        showCurrent={true}
      />,
    )
  }).not.toThrow()
})

test('flattens references with duplicated hrefs into independent replacements', () => {
  const duplicatedHrefFiles = [
    {
      id: 45,
      file_name: 'duplicated-href.docx',
      metadata: {
        instructions: '<p>Notes</p>',
        'mtc:references':
          '<a href="https://dup.com">one</a> and <a href="https://dup.com">two</a>',
      },
    },
  ]
  const {container} = render(
    <JobMetadataModal
      files={duplicatedHrefFiles}
      currentFile={45}
      showCurrent={true}
    />,
  )

  // eslint-disable-next-line testing-library/no-container, testing-library/no-node-access
  expect(container.querySelector('.instructions-container')).toHaveTextContent(
    '[one](https://dup.com) and [two](https://dup.com)',
  )
})
