import React from 'react'
import {render, screen, fireEvent, act} from '@testing-library/react'
import {TagsCompressButton} from './TagsCompressButton'

const mockToggle = jest.fn()
let mockCompressed = false
let mockStoreListeners = {}

jest.mock('../../actions/CatToolActions', () => ({
  togglePhTagsCompressed: () => mockToggle(),
}))

jest.mock('../../stores/CatToolStore', () => ({
  isPhTagsCompressed: () => mockCompressed,
  addListener: jest.fn((event, handler) => {
    mockStoreListeners[event] = handler
  }),
  removeListener: jest.fn((event) => {
    delete mockStoreListeners[event]
  }),
}))

jest.mock('../../constants/CatToolConstants', () => ({
  TOGGLE_PH_TAGS_COMPRESSED: 'TOGGLE_PH_TAGS_COMPRESSED',
}))

jest.mock('../common/Button/Button', () => ({
  Button: ({children, onClick, title, active, ...rest}) => (
    <button
      data-testid="tags-compress-btn"
      data-active={active}
      title={title}
      onClick={onClick}
    >
      {children}
    </button>
  ),
  BUTTON_MODE: {OUTLINE: 'outline'},
  BUTTON_SIZE: {ICON_SMALL: 'icon-small'},
}))

beforeEach(() => {
  mockCompressed = false
  mockToggle.mockClear()
  mockStoreListeners = {}
})

describe('TagsCompressButton', () => {
  test('renders with "Compress ph tags" title when not compressed', () => {
    render(<TagsCompressButton />)
    expect(screen.getByTestId('tags-compress-btn')).toHaveAttribute(
      'title',
      'Compress ph tags',
    )
  })

  test('renders with "Expand ph tags" title when compressed', () => {
    mockCompressed = true
    render(<TagsCompressButton />)
    expect(screen.getByTestId('tags-compress-btn')).toHaveAttribute(
      'title',
      'Expand ph tags',
    )
  })

  test('calls togglePhTagsCompressed on click', () => {
    render(<TagsCompressButton />)
    fireEvent.click(screen.getByTestId('tags-compress-btn'))
    expect(mockToggle).toHaveBeenCalledTimes(1)
  })

  test('active prop reflects compressed state', () => {
    mockCompressed = true
    render(<TagsCompressButton />)
    expect(screen.getByTestId('tags-compress-btn')).toHaveAttribute(
      'data-active',
      'true',
    )
  })

  test('updates when store emits change', () => {
    render(<TagsCompressButton />)
    expect(screen.getByTestId('tags-compress-btn')).toHaveAttribute(
      'title',
      'Compress ph tags',
    )

    mockCompressed = true
    act(() => {
      mockStoreListeners['TOGGLE_PH_TAGS_COMPRESSED']?.()
    })

    expect(screen.getByTestId('tags-compress-btn')).toHaveAttribute(
      'title',
      'Expand ph tags',
    )
  })

  test('cleans up listener on unmount', () => {
    const CatToolStore = require('../../stores/CatToolStore')
    const {unmount} = render(<TagsCompressButton />)
    unmount()
    expect(CatToolStore.removeListener).toHaveBeenCalledWith(
      'TOGGLE_PH_TAGS_COMPRESSED',
      expect.any(Function),
    )
  })

  test('renders SVG icon', () => {
    const {container} = render(<TagsCompressButton />)
    expect(container.querySelector('svg')).toBeTruthy()
  })
})
