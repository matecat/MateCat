import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import TagSuggestion from './TagSuggestion'

beforeAll(() => {
  global.config.isTargetRTL = false
})

describe('TagSuggestion', () => {
  test('renders the placeholder and tag style for a known tag name', () => {
    const suggestion = {
      data: {name: 'ph', placeholder: 'PH_1'},
    }
    render(
      <TagSuggestion
        suggestion={suggestion}
        isFocused={false}
        tabIndex={0}
        onTagClick={jest.fn()}
      />,
    )
    expect(screen.getByText('PH_1')).toBeInTheDocument()
  })

  test('renders an index counter badge when index is defined', () => {
    const suggestion = {
      data: {name: 'ph', placeholder: 'PH_1', index: 2, pcRole: 'open'},
    }
    render(
      <TagSuggestion
        suggestion={suggestion}
        isFocused={false}
        tabIndex={0}
        onTagClick={jest.fn()}
      />,
    )
    expect(screen.getByText('3')).toBeInTheDocument()
  })

  test('applies the active class and bold style when focused', () => {
    const suggestion = {data: {name: 'g', placeholder: 'G_1'}}
    const {container} = render(
      <TagSuggestion
        suggestion={suggestion}
        isFocused={true}
        tabIndex={0}
        onTagClick={jest.fn()}
      />,
    )
    const outer = container.querySelector('.tag-menu-suggestion')
    expect(outer.className).toContain('active')
  })

  test('invokes onTagClick with the suggestion on mouse down', () => {
    const onTagClick = jest.fn()
    const suggestion = {data: {name: 'g', placeholder: 'G_1'}}
    const {container} = render(
      <TagSuggestion
        suggestion={suggestion}
        isFocused={false}
        tabIndex={0}
        onTagClick={onTagClick}
      />,
    )
    fireEvent.mouseDown(container.querySelector('.tag-menu-suggestion'))
    expect(onTagClick).toHaveBeenCalledWith(suggestion)
  })

  // NOTE: the `props.suggestion ? (...) : 'No tags'` branch is unreachable in
  // practice — `props.suggestion.data.name` is read unconditionally above it,
  // so a falsy `suggestion` throws before that ternary is ever evaluated.
})
