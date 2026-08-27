import React from 'react'
import {render, screen} from '@testing-library/react'

import SearchHighlight from './SearchHighLight.component'

test('highlights the current occurrence in a different color (matching by start)', () => {
  const occurrences = [
    {start: 0, end: 5, key: 'block-1', searchProgressiveIndex: 2},
  ]
  render(
    <SearchHighlight
      occurrences={occurrences}
      start={0}
      end={5}
      blockKey="block-1"
      currentIndex={2}
    >
      hello
    </SearchHighlight>,
  )

  expect(screen.getByText('hello').style.backgroundColor).toBe(
    'rgb(255, 210, 14)',
  )
})

test('highlights a matching but non-current occurrence in the default color (matching by end)', () => {
  const occurrences = [
    {start: 100, end: 5, key: 'block-1', searchProgressiveIndex: 2},
  ]
  render(
    <SearchHighlight
      occurrences={occurrences}
      start={0}
      end={5}
      blockKey="block-1"
      currentIndex={1}
    >
      hello
    </SearchHighlight>,
  )

  expect(screen.getByText('hello').style.backgroundColor).toBe(
    'rgb(255, 255, 0)',
  )
})

test('renders the default highlight color when there is no matching occurrence', () => {
  render(
    <SearchHighlight
      occurrences={[]}
      start={0}
      end={5}
      blockKey="block-1"
      currentIndex={1}
    >
      hello
    </SearchHighlight>,
  )

  expect(screen.getByText('hello').style.backgroundColor).toBe(
    'rgb(255, 255, 0)',
  )
})
