/* eslint-disable testing-library/no-node-access --
   The CodeMirror marks are plain DOM with no roles or labels: they are found by class. */
import {render, screen} from '@testing-library/react'
import {EditorView} from '@codemirror/view'
import {createSegmentState} from '../core/createSegmentEditor'
import {setLayer} from '../core/layers'
import {act} from 'react'
import {fireEvent} from '@testing-library/react'
import {createSegmentEditor} from '../core/createSegmentEditor'
import {serialize} from '../core/docModel'
import {HOVER_TIME} from '../core/layerHover'
import {
  layerTooltipExtensions,
  createLayerTooltip,
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

  test('a blacklisted term says what it is a forbidden translation of', () => {
    render(
      tooltipContent([
        range('blacklist', {
          term: {source: {term: 'inexpensive'}, target: {term: 'cheap'}},
        }),
      ]).element,
    )
    expect(
      screen.getByText(
        'cheap is flagged as a forbidden translation for inexpensive',
      ),
    ).toBeInTheDocument()
  })

  test('an ICU error lists its messages under its title', () => {
    render(
      tooltipContent([
        range('icu', {messages: ['Missing few', 'Missing many']}),
      ]).element,
    )
    expect(screen.getByText('ICU syntax error')).toBeInTheDocument()
    expect(screen.getByText('Missing many')).toBeInTheDocument()
  })

  test('a Lexiqa suggestion is handed to onReplace with the warning it belongs to', () => {
    const onReplace = jest.fn()
    const warning = range('lexiqa', {
      messages: [{type: 'suggestion', msg: 'two', start: 0, end: 3}],
    })
    render(tooltipContent([warning], {onReplace}).element)
    screen.getByText('two').click()
    expect(onReplace).toHaveBeenCalledWith(warning, 'two')
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

describe('the Lexiqa tooltip of the target (Editarea.replaceWordAt, C4)', () => {
  const suggestion = [{type: 'suggestion', msg: 'two', start: 0, end: 3}]
  let editor

  const mountTarget = (value, warnings, options) => {
    editor = createSegmentEditor({
      parent: document.body.appendChild(document.createElement('div')),
      value,
      mode: 'target',
      ...options,
    })
    editor.view.dispatch({
      effects: setLayer.of({
        name: 'lexiqa',
        volatile: true,
        ranges: warnings.map(({from, to}) => ({
          from,
          to,
          className: 'lexiqahighlight',
          data: {type: 'lexiqa', messages: suggestion},
        })),
      }),
    })
  }

  beforeEach(() => jest.useFakeTimers())
  afterEach(() => {
    act(() => {
      editor.destroy()
      jest.runOnlyPendingTimers()
    })
    jest.useRealTimers()
    document.body.innerHTML = ''
  })

  test('hovering a warning of the second line and picking the suggestion replaces that word, not one on the caret line', () => {
    const value = 'tow one##$_0A$##line tow'
    const second = 'tow one\nline '.length
    mountTarget(value, [{from: second, to: second + 3}], {
      extensions: layerTooltipExtensions({editable: true}),
    })
    editor.view.dispatch({selection: {anchor: 0}})
    const mark = editor.view.contentDOM.querySelector('.lexiqahighlight')
    fireEvent.mouseOver(mark)
    act(() => jest.advanceTimersByTime(HOVER_TIME))
    act(() => screen.getByText('two').click())
    expect(serialize(editor.view.state)).toBe('tow one##$_0A$##line two')
  })

  test('the tooltip is the cattool tooltip, interactive, above the warning', () => {
    mountTarget('a tow', [{from: 2, to: 5}])
    let shown
    act(() => {
      shown = createLayerTooltip({editable: true})(editor.view, {
        ranges: [
          {
            name: 'lexiqa',
            from: 2,
            to: 5,
            data: {type: 'lexiqa', messages: suggestion},
          },
        ],
        rect: {top: 100, left: 40, width: 20, height: 16},
        close: () => {},
      })
    })
    const tooltip = shown.dom.firstChild
    expect(tooltip).toHaveStyle({top: `${100 - 7}px`, left: '50px'})
    expect(shown.dom).toHaveTextContent('two')
    shown.destroy()
    act(() => jest.runAllTimers())
    expect(shown.dom).toBeEmptyDOMElement()
  })

  test('in the source a suggestion changes nothing', () => {
    mountTarget('a tow', [{from: 2, to: 5}], {mode: 'source'})
    const close = jest.fn()
    let shown
    act(() => {
      shown = createLayerTooltip({editable: false})(editor.view, {
        ranges: [
          {
            name: 'lexiqa',
            from: 2,
            to: 5,
            data: {type: 'lexiqa', messages: suggestion},
          },
        ],
        rect: {top: 0, left: 0, width: 0, height: 0},
        close,
      })
    })
    document.body.appendChild(shown.dom)
    act(() => screen.getByText('two').click())
    expect(serialize(editor.view.state)).toBe('a tow')
    expect(close).not.toHaveBeenCalled()
  })

  test('ranges with nothing to explain get no tooltip', () => {
    mountTarget('a tow', [])
    expect(
      createLayerTooltip({editable: true})(editor.view, {
        ranges: [{name: 'icu', from: 0, to: 1}],
        rect: {},
        close: () => {},
      }),
    ).toBeNull()
  })
})
