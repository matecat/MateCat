import {Base64} from 'js-base64'
import {setTagSignatureMiddleware} from './tagModel'
import {
  findTagMatches,
  numberTagsInDocumentOrder,
  replaceTagMatches,
  scanPairedTags,
  scanTags,
} from './tagScan'

// Blanks are tags only with the show-whitespace switch on
beforeEach(() => {
  setTagSignatureMiddleware('space', () => false)
})

const ph = (text, attributes = 'id="1"') =>
  `<ph ${attributes} equiv-text="base64:${Base64.encode(text)}"/>`

describe('scanTags', () => {
  test('gives every tag its offset, markup, id and shown text', () => {
    const tags = scanTags(`a ${ph('%s')} <bx id="9"/>`)

    expect(tags).toEqual([
      {
        offset: 2,
        length: ph('%s').length,
        name: 'ph',
        encodedText: ph('%s'),
        id: '1',
        placeholder: '%s',
        decodedText: '%s',
      },
      expect.objectContaining({
        offset: 3 + ph('%s').length,
        name: 'bx',
        id: '9',
      }),
    ])
  })

  test('finds the tags of each signature in the string as stored, even inside the text another one shows', () => {
    // The equiv-text of this ph is a <g> once decoded, but the stored string holds it in base64: no g tag
    const names = scanTags(ph('<g id="2">')).map(({name}) => name)

    expect(names).toEqual(['ph'])
  })

  test('reads the signatures at call time, so the show-whitespace switch applies at once', () => {
    expect(scanTags('a b')).toEqual([])

    setTagSignatureMiddleware('space', () => true)
    expect(scanTags('a b').map(({name}) => name)).toEqual(['space'])
  })

  test('scans only the signatures accepted', () => {
    const tags = scanTags('<g id="1">x</g>##$_A0$##', (name) => name === 'nbsp')

    expect(tags.map(({name}) => name)).toEqual(['nbsp'])
  })
})

test('keeps every tag of a segment made of more single-character tags than a call can take as arguments', () => {
  const tags = scanTags('⁠'.repeat(150000), (name) => name === 'wordJoiner')
  expect(tags).toHaveLength(150000)
})

describe('scanPairedTags', () => {
  test('gives a closing tag the id and placeholder of its opening tag', () => {
    const tags = scanPairedTags('test <g id="1">tag</g> end')
    const closing = tags.find(({name}) => name === 'gCl')

    expect(closing).toMatchObject({id: '1', placeholder: '1'})
  })

  test('pairs each closing tag with the nearest opening tag still open', () => {
    const tags = scanPairedTags('<g id="1">a<g id="2">b</g>c</g></g>')
    const closings = tags.filter(({name}) => name === 'gCl')

    expect(closings.map(({offset, id}) => [offset, id])).toEqual([
      [22, '2'],
      [27, '1'],
      [31, ''],
    ])
    expect(closings[2].placeholder).toBe('?')
  })

  test('shows an orphan closing tag as "?"', () => {
    const [closing] = scanPairedTags('test </g> end')

    expect(closing).toMatchObject({name: 'gCl', placeholder: '?'})
  })

  test('lists the opening tags from the last, then the closing and the self-closing ones in order', () => {
    const tags = scanPairedTags(
      '<g id="1">a</g> ##$_A0$## <g id="2">b</g> <x id="3"/>',
    )

    expect(tags.map(({name, offset}) => [name, offset])).toEqual([
      ['g', 26],
      ['g', 0],
      ['gCl', 11],
      ['gCl', 37],
      ['x', 42],
      ['nbsp', 16],
    ])
  })
})

describe('findTagMatches', () => {
  test('matches x tags whatever their case, but the views only as the placeholder regex reads them', () => {
    expect(findTagMatches('<X id="1"/>', 'x')).toHaveLength(1)
    expect(findTagMatches('<X id="1"/>', 'x', {display: true})).toEqual([])
  })

  test('finds the blanks of a long segment in linear time', () => {
    setTagSignatureMiddleware('space', () => true)
    const bestTime = (text) => {
      let best = Infinity
      for (let i = 0; i < 5; i++) {
        const start = performance.now()
        findTagMatches(text, 'space')
        best = Math.min(best, performance.now() - start)
      }
      return best
    }
    const unit = 'a <g id="1"> b '
    bestTime(unit.repeat(512))
    // Eight times the text: linear code takes about eight times as long, quadratic code about sixty-four. The
    // limit sits well between the two, so the load of a full parallel run cannot fail it, and the sizes are large
    // enough that the timings are not mostly noise.
    const ratio = bestTime(unit.repeat(32768)) / bestTime(unit.repeat(4096))

    expect(ratio).toBeLessThan(24)
  })
})

test('finds nothing for a tag name no signature has', () => {
  expect(findTagMatches('<g id="1">a</g>', 'notATag')).toEqual([])
})

describe('replaceTagMatches', () => {
  test('replaces each match with what the callback returns, captures included', () => {
    const text = replaceTagMatches(
      'a <g id="1">b</g> <g id="2">c</g>',
      'g',
      ({1: id}) => `[${id}]`,
    )

    expect(text).toBe('a [1]b</g> [2]c</g>')
  })

  test('leaves the text as it is when the signature is switched off', () => {
    expect(replaceTagMatches('a b', 'space', () => '_')).toBe('a b')
  })
})

describe('numberTagsInDocumentOrder', () => {
  test('numbers the compressible tags across signatures in document order', () => {
    const open = ph('<pc id="1">', 'id="mtc_1" ctype="x-original_pc_open"')
    const close = ph('</pc>', 'id="mtc_2" ctype="x-original_pc_close"')
    const g = '<g id="5" equiv-text="base64:' + Base64.encode('<b>') + '">'
    const queues = numberTagsInDocumentOrder(`${g}x</g>${open}y${close}`)

    expect(queues).toEqual({
      ph: [
        {index: 1, role: 'open'},
        {index: 1, role: 'close'},
      ],
      g: [{index: 0, role: 'open'}],
      gCl: [{index: 0, role: 'close'}],
    })
  })
})
