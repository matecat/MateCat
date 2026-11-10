/* eslint-disable testing-library/no-node-access --
   The CodeMirror marks are plain DOM with no roles or labels: they are found by class. */
import {render, screen} from '@testing-library/react'
import {EditorView} from '@codemirror/view'
import {createSegmentState} from '../core/createSegmentEditor'
import {setLayer} from '../core/layers'
import {act} from 'react'
import {
  layerTooltipAt,
  layerTooltipExtensions,
  tooltipContent,
} from './layerTooltip'

const mockHighlightGlossaryTerm = jest.fn()
jest.mock('../../../actions/segmentDispatchActions', () => ({
  highlightGlossaryTerm: (...args) => mockHighlightGlossaryTerm(...args),
}))

const range = (type, extra = {}) => ({
  name: type,
  from: 0,
  to: 3,
  data: {type, ...extra},
})

describe('tooltipContent (GlossaryHighlight / QaCheckGlossaryHighlight / LexiqaHighlight)', () => {
  test('a glossary term says it is a termbase entry', () => {
    render(tooltipContent([range('glossary')]).element)
    expect(screen.getByText('Termbase entry')).toBeInTheDocument()
  })

  test('a missing term says its translation is not in the target', () => {
    render(tooltipContent([range('check')]).element)
    expect(
      screen.getByText('Termbase translation not found in target'),
    ).toBeInTheDocument()
  })

  test('a Lexiqa warning shows its messages and wins over a glossary term on the same text', () => {
    const content = tooltipContent([
      range('glossary'),
      range('lexiqa', {messages: [{type: 'error', msg: 'Spelling mistake'}]}),
    ])
    render(content.element)
    expect(screen.getByText('Spelling mistake')).toBeInTheDocument()
    expect(content.range.name).toBe('lexiqa')
  })

  test('a Lexiqa suggestion click does nothing to the read-only source', () => {
    const content = tooltipContent([
      range('lexiqa', {messages: [{type: 'suggestion', msg: 'two'}]}),
    ])
    render(content.element)
    expect(() => screen.getByText('two').click()).not.toThrow()
  })

  test('nothing to show for a warning without messages, an ICU token or no range', () => {
    expect(tooltipContent([range('lexiqa')])).toBeNull()
    expect(tooltipContent([{name: 'icu', from: 0, to: 1}])).toBeNull()
    expect(tooltipContent([])).toBeNull()
  })
})

describe('glossary click', () => {
  const mount = (ranges) => {
    const parent = document.createElement('div')
    document.body.appendChild(parent)
    const view = new EditorView({
      parent,
      state: createSegmentState('guest room', {
        mode: 'source',
        extensions: layerTooltipExtensions(),
      }),
    })
    view.dispatch({effects: setLayer.of({name: 'glossary', ranges})})
    return view
  }

  beforeEach(() => mockHighlightGlossaryTerm.mockReset())

  test('clicking a term opens its entry in the footer glossary tab', () => {
    const view = mount([
      {
        from: 0,
        to: 5,
        className: 'glossaryItem',
        data: {type: 'glossary', sid: '9', termId: 42},
      },
    ])
    view.contentDOM.querySelector('.glossaryItem').click()
    expect(mockHighlightGlossaryTerm).toHaveBeenCalledWith({
      sid: '9',
      termId: 42,
      type: 'glossary',
    })
    view.destroy()
  })

  test('clicking plain text, or a term with no entry, opens nothing', () => {
    const view = mount([
      {
        from: 6,
        to: 10,
        className: 'qaCheckGlossaryItem',
        data: {type: 'check', sid: '9'},
      },
    ])
    view.contentDOM.querySelector('.cm-line').click()
    view.contentDOM.querySelector('.qaCheckGlossaryItem').click()
    expect(mockHighlightGlossaryTerm).not.toHaveBeenCalled()
    view.destroy()
  })
})

describe('hover tooltip', () => {
  test('hovering a term shows its tooltip over the whole term, and closing it unmounts the content', async () => {
    jest.useFakeTimers()
    const parent = document.createElement('div')
    document.body.appendChild(parent)
    const view = new EditorView({
      parent,
      state: createSegmentState('guest room', {mode: 'source'}),
    })
    view.dispatch({
      effects: setLayer.of({
        name: 'glossary',
        ranges: [
          {from: 0, to: 5, className: 'glossaryItem', data: {type: 'glossary'}},
        ],
      }),
    })
    const tooltip = layerTooltipAt(view, 2)
    expect([tooltip.pos, tooltip.end]).toEqual([0, 5])
    let shown
    act(() => {
      shown = tooltip.create(view)
    })
    expect(shown.dom).toHaveTextContent('Termbase entry')
    shown.destroy()
    act(() => jest.runAllTimers())
    expect(shown.dom).toBeEmptyDOMElement()
    expect(layerTooltipAt(view, 8)).toBeNull()
    view.destroy()
    jest.useRealTimers()
  })
})
