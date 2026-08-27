import React from 'react'
import {render, screen} from '@testing-library/react'

import TooltipInfo from './TooltipInfo.component'

test('renders plain text content when not a tag', () => {
  render(<TooltipInfo text="hello world" isTag={false} />)

  expect(screen.getByText('hello world')).toBeInTheDocument()
  expect(
    document.querySelector('.tooltip-error-category'),
  ).toBeInTheDocument()
})

test('renders tag-styled content when isTag is true', () => {
  render(<TooltipInfo text="<ph/>" isTag={true} tagStyle="tag-ph" />)

  const tagSpan = document.querySelector('.tag')
  expect(tagSpan).toBeInTheDocument()
  expect(tagSpan.classList.contains('tag-ph')).toBe(true)
  expect(screen.getByText('<ph/>')).toBeInTheDocument()
})
