import {render} from '@testing-library/react'
import React from 'react'

import JobMetadataModal from './JobMetadataModal'

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
      const {container} = render(
        <JobMetadataModal
          showCurrent={true}
          currentFile={1}
          files={[
            fileWith({instructions: '<img src="x" onerror="alert(1)">caption'}),
          ]}
        />,
      )

      expect(container.querySelector('[onerror]')).toBeNull()
      expect(container.innerHTML).not.toContain('onerror')
      expect(container.textContent).toContain('caption')
    })

    test('strips javascript: hrefs', () => {
      const {container} = render(
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

      expect(container.innerHTML).not.toContain('javascript:')
    })

    test('escapes script tags', () => {
      const {container} = render(
        <JobMetadataModal
          showCurrent={true}
          currentFile={1}
          files={[fileWith({instructions: '<script>alert(1)</script>hello'})]}
        />,
      )

      expect(container.querySelector('script')).toBeNull()
    })

    test('applies the same filtering to the project instructions', () => {
      const {container} = render(
        <JobMetadataModal
          projectInfo='<img src="x" onerror="alert(1)">'
          files={[fileWith({instructions: null})]}
        />,
      )

      expect(container.innerHTML).not.toContain('onerror')
    })

    test('keeps benign markup and real links', () => {
      const {container} = render(
        <JobMetadataModal
          showCurrent={true}
          currentFile={1}
          files={[
            fileWith({
              instructions:
                '<b>Bold</b> and <a href="https://example.com">a link</a>',
            }),
          ]}
        />,
      )

      expect(container.querySelector('b')).not.toBeNull()
      expect(
        container.querySelector('a[href="https://example.com"]'),
      ).not.toBeNull()
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
    const {container} = render(
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
    expect(container.textContent).toContain('[one](https://dup.com)')
    expect(container.textContent).toContain('[two](https://dup.com)')
  })
})
