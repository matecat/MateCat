import React from 'react'
import {render, act} from '@testing-library/react'
import {TagEntityLite} from './TagEntityLite'

let mockCompressed = false
let mockStoreListeners = {}

jest.mock('../../../stores/CatToolStore', () => ({
  isPhTagsCompressed: () => mockCompressed,
  addListener: jest.fn((event, handler) => {
    mockStoreListeners[event] = handler
  }),
  removeListener: jest.fn((event) => {
    delete mockStoreListeners[event]
  }),
}))

jest.mock('../../../constants/CatToolConstants', () => ({
  TOGGLE_PH_TAGS_COMPRESSED: 'TOGGLE_PH_TAGS_COMPRESSED',
}))

jest.mock('../utils/DraftMatecatUtils/tagModel', () => ({
  tagSignatures: {
    ph: {style: 'tag-selfclosed tag-ph', styleRTL: null},
    g: {style: 'tag-open', styleRTL: 'tag-close'},
  },
}))

const mockContentState = (entityName, index) => ({
  getEntity: () => ({
    data: {name: entityName, index, placeholder: 'test'},
  }),
})

beforeEach(() => {
  mockCompressed = false
  mockStoreListeners = {}
})

describe('TagEntityLite', () => {
  test('renders ph tag with index counter when index >= 0', () => {
    const {container} = render(
      <TagEntityLite
        entityKey="1"
        contentState={mockContentState('ph', 0)}
        offsetkey="1-0-0"
        isRTL={false}
      >
        <span>{'<p>'}</span>
      </TagEntityLite>,
    )
    expect(container.querySelector('.index-counter')).toBeTruthy()
    expect(container.querySelector('.index-counter').textContent).toBe('1')
  })

  test('renders ph tag index as 1-based (index 0 -> display 1)', () => {
    const {container} = render(
      <TagEntityLite
        entityKey="1"
        contentState={mockContentState('ph', 2)}
        offsetkey="1-0-0"
        isRTL={false}
      >
        <span>text</span>
      </TagEntityLite>,
    )
    expect(container.querySelector('.index-counter').textContent).toBe('3')
  })

  test('does not render index counter for non-ph tags', () => {
    const {container} = render(
      <TagEntityLite
        entityKey="1"
        contentState={mockContentState('g', undefined)}
        offsetkey="1-0-0"
        isRTL={false}
      >
        <span>content</span>
      </TagEntityLite>,
    )
    expect(container.querySelector('.index-counter')).toBeNull()
  })

  test('shows children content when not compressed', () => {
    const {container} = render(
      <TagEntityLite
        entityKey="1"
        contentState={mockContentState('ph', 0)}
        offsetkey="1-0-0"
        isRTL={false}
      >
        <span data-testid="child">{'<p>'}</span>
      </TagEntityLite>,
    )
    const tagSpan = container.querySelector('.tag')
    expect(tagSpan.textContent).toContain('1')
    expect(tagSpan.textContent).toContain('<p>')
  })

  test('hides children content when compressed', () => {
    mockCompressed = true
    const {container} = render(
      <TagEntityLite
        entityKey="1"
        contentState={mockContentState('ph', 0)}
        offsetkey="1-0-0"
        isRTL={false}
      >
        <span>{'<p>'}</span>
      </TagEntityLite>,
    )
    const tagSpan = container.querySelector('.tag')
    expect(tagSpan.textContent).toBe('1')
    expect(tagSpan.classList.contains('tag-compressed')).toBe(true)
  })

  test('applies tag-compressed class when compressed and ph', () => {
    mockCompressed = true
    const {container} = render(
      <TagEntityLite
        entityKey="1"
        contentState={mockContentState('ph', 0)}
        offsetkey="1-0-0"
        isRTL={false}
      >
        <span>text</span>
      </TagEntityLite>,
    )
    expect(
      container.querySelector('.tag').classList.contains('tag-compressed'),
    ).toBe(true)
  })

  test('does not apply tag-compressed class for non-ph tags', () => {
    mockCompressed = true
    const {container} = render(
      <TagEntityLite
        entityKey="1"
        contentState={mockContentState('g', undefined)}
        offsetkey="1-0-0"
        isRTL={false}
      >
        <span>content</span>
      </TagEntityLite>,
    )
    expect(
      container.querySelector('.tag').classList.contains('tag-compressed'),
    ).toBe(false)
  })

  test('reacts to store toggle event', () => {
    const {container} = render(
      <TagEntityLite
        entityKey="1"
        contentState={mockContentState('ph', 0)}
        offsetkey="1-0-0"
        isRTL={false}
      >
        <span>{'<p>'}</span>
      </TagEntityLite>,
    )

    expect(
      container.querySelector('.tag').classList.contains('tag-compressed'),
    ).toBe(false)

    mockCompressed = true
    act(() => {
      mockStoreListeners['TOGGLE_PH_TAGS_COMPRESSED']?.()
    })

    expect(
      container.querySelector('.tag').classList.contains('tag-compressed'),
    ).toBe(true)
  })

  test('applies correct style class', () => {
    const {container} = render(
      <TagEntityLite
        entityKey="1"
        contentState={mockContentState('ph', 0)}
        offsetkey="1-0-0"
        isRTL={false}
      >
        <span>text</span>
      </TagEntityLite>,
    )
    expect(
      container.querySelector('.tag').classList.contains('tag-selfclosed'),
    ).toBe(true)
    expect(container.querySelector('.tag').classList.contains('tag-ph')).toBe(
      true,
    )
  })

  test('ph tag without index (index undefined) does not show counter', () => {
    const {container} = render(
      <TagEntityLite
        entityKey="1"
        contentState={mockContentState('ph', undefined)}
        offsetkey="1-0-0"
        isRTL={false}
      >
        <span>text</span>
      </TagEntityLite>,
    )
    expect(container.querySelector('.index-counter')).toBeNull()
  })
})
