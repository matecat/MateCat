import React from 'react'
import {render, screen} from '@testing-library/react'
import TagBox from './TagBox'

beforeAll(() => {
  global.config.isTargetRTL = false
})

const makeTag = (name, placeholder) => ({data: {name, placeholder}})

describe('TagBox', () => {
  test('renders only the "all" section when there are no missing tags', () => {
    const suggestions = {
      sourceTags: [makeTag('g', 'G_1')],
    }
    render(
      <TagBox
        popoverPosition={{top: 0, left: 0}}
        displayPopover={true}
        suggestions={suggestions}
        onTagClick={jest.fn()}
        focusedTagIndex={0}
      />,
    )
    expect(screen.queryByText('Missing source', {exact: false})).not.toBeInTheDocument()
    expect(screen.getByText('G_1')).toBeInTheDocument()
  })

  test('renders the missing-tags section when missingTags is non-empty', () => {
    const suggestions = {
      missingTags: [makeTag('g', 'MISSING_1')],
      sourceTags: [makeTag('g', 'ALL_1')],
    }
    render(
      <TagBox
        popoverPosition={{top: 0, left: 0}}
        displayPopover={true}
        suggestions={suggestions}
        onTagClick={jest.fn()}
        focusedTagIndex={0}
      />,
    )
    expect(screen.getByText('MISSING_1')).toBeInTheDocument()
    expect(screen.getByText('ALL_1')).toBeInTheDocument()
  })

  test('renders neither list when suggestions has no missingTags/sourceTags', () => {
    const {container} = render(
      <TagBox
        popoverPosition={{top: 0, left: 0}}
        displayPopover={false}
        suggestions={{}}
        onTagClick={jest.fn()}
        focusedTagIndex={0}
      />,
    )
    expect(container.querySelectorAll('.tag-menu-suggestion')).toHaveLength(0)
  })

  test('scrollElementIntoViewIfNeeded scrolls the focused item into view on update', () => {
    const suggestions = {
      sourceTags: [makeTag('g', 'A'), makeTag('g', 'B'), makeTag('g', 'C')],
    }
    const scrollTo = jest.fn()
    Element.prototype.scrollTo = scrollTo
    Element.prototype.getBoundingClientRect = function () {
      // the focused element (index 1) reports itself as below the container
      // so the "activeElementClientRect.bottom > tabBoxClientRect.bottom" branch fires
      if (this.className.includes('tag-box-inner')) {
        return {top: 0, bottom: 100}
      }
      return {top: 50, bottom: 200}
    }

    const {rerender} = render(
      <TagBox
        popoverPosition={{top: 0, left: 0}}
        displayPopover={true}
        suggestions={suggestions}
        onTagClick={jest.fn()}
        focusedTagIndex={0}
      />,
    )
    rerender(
      <TagBox
        popoverPosition={{top: 0, left: 0}}
        displayPopover={true}
        suggestions={suggestions}
        onTagClick={jest.fn()}
        focusedTagIndex={1}
      />,
    )

    expect(scrollTo).toHaveBeenCalled()
  })

  test('scrolls to top when the newly focused item is index 0', () => {
    const suggestions = {
      sourceTags: [makeTag('g', 'A'), makeTag('g', 'B')],
    }
    const scrollTo = jest.fn()
    Element.prototype.scrollTo = scrollTo
    Element.prototype.getBoundingClientRect = function () {
      if (this.className.includes('tag-box-inner')) {
        return {top: 0, bottom: 10}
      }
      return {top: 50, bottom: 200}
    }

    const {rerender} = render(
      <TagBox
        popoverPosition={{top: 0, left: 0}}
        displayPopover={true}
        suggestions={suggestions}
        onTagClick={jest.fn()}
        focusedTagIndex={1}
      />,
    )
    rerender(
      <TagBox
        popoverPosition={{top: 0, left: 0}}
        displayPopover={true}
        suggestions={suggestions}
        onTagClick={jest.fn()}
        focusedTagIndex={0}
      />,
    )

    expect(scrollTo).toHaveBeenCalledWith({top: 0, left: 0, behavior: 'smooth'})
  })

  test('does not scroll when focusedTagIndex is unchanged between updates', () => {
    const suggestions = {sourceTags: [makeTag('g', 'A')]}
    const scrollTo = jest.fn()
    Element.prototype.scrollTo = scrollTo

    const {rerender} = render(
      <TagBox
        popoverPosition={{top: 0, left: 0}}
        displayPopover={true}
        suggestions={suggestions}
        onTagClick={jest.fn()}
        focusedTagIndex={0}
      />,
    )
    rerender(
      <TagBox
        popoverPosition={{top: 5, left: 5}}
        displayPopover={true}
        suggestions={suggestions}
        onTagClick={jest.fn()}
        focusedTagIndex={0}
      />,
    )

    expect(scrollTo).not.toHaveBeenCalled()
  })
})
