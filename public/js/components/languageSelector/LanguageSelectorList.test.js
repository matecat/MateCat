import React from 'react'
import {render, screen, fireEvent, act} from '@testing-library/react'
import LanguageSelectorList, {chunk, buildRangeArray} from './LanguageSelectorList'

const languagesList = [
  {code: 'it-IT', id: 'it-IT', name: 'Italian', direction: 'ltr'},
  {code: 'fr-FR', id: 'fr-FR', name: 'French', direction: 'ltr'},
  {code: 'de-DE', id: 'de-DE', name: 'German', direction: 'ltr'},
  {code: 'es-ES', id: 'es-ES', name: 'Spanish', direction: 'ltr'},
]

const renderComponent = (overrides = {}) => {
  const ref = React.createRef()
  const props = {
    languagesList,
    selectedLanguages: [],
    querySearch: '',
    onToggleLanguage: jest.fn(),
    onResetResults: jest.fn(),
    ...overrides,
  }
  const utils = render(<LanguageSelectorList ref={ref} {...props} />)
  return {...utils, ref, props}
}

describe('chunk', () => {
  test('splits an array into chunks of the given size', () => {
    expect(chunk([1, 2, 3, 4, 5], 2)).toEqual([[1, 2], [3, 4], [5]])
  })

  test('wraps a single leftover element in its own chunk', () => {
    expect(chunk([1], 2)).toEqual([[1]])
  })
})

describe('buildRangeArray', () => {
  test('builds a numeric range array of the given length', () => {
    expect(buildRangeArray(4)).toEqual([0, 1, 2, 3])
  })

  test('returns an empty array for zero items', () => {
    expect(buildRangeArray(0)).toEqual([])
  })
})

describe('LanguageSelectorList', () => {
  test('renders all languages spread across 4 columns', () => {
    const {container} = renderComponent()
    expect(container.querySelectorAll('.dropdown__list').length).toBe(4)
    expect(container.querySelectorAll('.lang-item').length).toBe(4)
  })

  test('marks a selected language with the selected class', () => {
    const {container} = renderComponent({selectedLanguages: [languagesList[0]]})
    const italian = screen.getByText('Italian').closest('.lang-item')
    expect(italian).toHaveClass('selected')
  })

  test('clicking a language calls onToggleLanguage with that language', () => {
    const {props} = renderComponent()
    fireEvent.click(screen.getByText('French').closest('.lang-item'))
    expect(props.onToggleLanguage).toHaveBeenCalledWith(languagesList[1])
  })

  test('filters languages by querySearch matching name or id', () => {
    const {container} = renderComponent({querySearch: 'it'})
    expect(container.querySelectorAll('.lang-item').length).toBe(1)
    expect(screen.getByText('Italian')).toBeInTheDocument()
    expect(screen.queryByText('French')).not.toBeInTheDocument()
  })

  test('renders no languages and keeps 4 empty columns when nothing matches', () => {
    const {container} = renderComponent({querySearch: 'zzz-no-match'})
    expect(container.querySelectorAll('.dropdown__list').length).toBe(4)
    expect(container.querySelectorAll('.lang-item').length).toBe(0)
  })

  test('highlights the first filtered result when a query is active', () => {
    const {container} = renderComponent({querySearch: 'an'})
    const hovered = container.querySelectorAll('.lang-item.hover')
    expect(hovered.length).toBe(1)
    expect(hovered[0]).toHaveTextContent('Italian')
  })

  test('ArrowDown/ArrowUp move the hover position across filtered results', () => {
    const {container, ref} = renderComponent({querySearch: 'an'})
    // matches Italian, German, Spanish (in that order) - French has no "an"

    act(() => {
      ref.current.navigateLanguagesList({keyCode: 40, preventDefault: jest.fn()})
    })
    let hovered = container.querySelectorAll('.lang-item.hover')
    expect(hovered.length).toBe(1)
    expect(hovered[0]).toHaveTextContent('German')

    act(() => {
      ref.current.navigateLanguagesList({keyCode: 38, preventDefault: jest.fn()})
    })
    hovered = container.querySelectorAll('.lang-item.hover')
    expect(hovered.length).toBe(1)
    expect(hovered[0]).toHaveTextContent('Italian')
  })

  test('Enter toggles the only filtered language and resets the search', () => {
    const {props, ref} = renderComponent({querySearch: 'ital'})

    act(() => {
      ref.current.navigateLanguagesList({
        keyCode: 13,
        preventDefault: jest.fn(),
        stopPropagation: jest.fn(),
      })
    })

    expect(props.onToggleLanguage).toHaveBeenCalledWith(languagesList[0])
    expect(props.onResetResults).toHaveBeenCalled()
  })

  test('Enter does nothing when no language is filtered', () => {
    const {props, ref} = renderComponent({querySearch: 'zzz-no-match'})

    act(() => {
      ref.current.navigateLanguagesList({
        keyCode: 13,
        preventDefault: jest.fn(),
        stopPropagation: jest.fn(),
      })
    })

    expect(props.onToggleLanguage).not.toHaveBeenCalled()
    expect(props.onResetResults).not.toHaveBeenCalled()
  })

  test('resets hover position to 0 when querySearch prop changes', () => {
    const {container, ref, rerender, props} = renderComponent({querySearch: 'an'})

    act(() => {
      ref.current.navigateLanguagesList({keyCode: 40, preventDefault: jest.fn()})
    })
    expect(
      container.querySelectorAll('.lang-item.hover')[0],
    ).toHaveTextContent('German')

    rerender(
      <LanguageSelectorList ref={ref} {...props} querySearch="ital" />,
    )

    expect(
      container.querySelectorAll('.lang-item.hover')[0],
    ).toHaveTextContent('Italian')
  })
})
