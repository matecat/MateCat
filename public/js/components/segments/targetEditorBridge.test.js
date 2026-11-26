import {
  charactersCounter,
  tagIdentities,
  targetEditorBridge,
  translationUpdate,
} from './targetEditorBridge'
import {setTagSignatureMiddleware} from './utils/DraftMatecatUtils/tagModel'
import {chipsWithIdentity} from '../editor/matecat/SourceEditor'
import {createSegmentState} from '../editor/core/createSegmentEditor'

setTagSignatureMiddleware('space', () => false)

const SOURCE = 'Hello <g id="1">world</g> <x id="2"/>'
const sourceTagMap = translationUpdate(SOURCE).tagMap

describe('translationUpdate', () => {
  test('gives the store what Editarea gives it: plain text, tag map, the source tags missing, the Lexiqa text', () => {
    const update = translationUpdate('Ciao <g id="1">mondo</g>', sourceTagMap)
    expect(update.plainText).toBe('Ciao 1mondo1')
    expect(update.tagMap.map((tag) => tag.data.encodedText)).toEqual([
      '<g id="1">',
      '</g>',
    ])
    expect(update.missingTags.map((tag) => tag.data.encodedText)).toEqual([
      '<x id="2"/>',
    ])
    expect(typeof update.lxqDecodedTranslation).toBe('string')
  })

  test('with every source tag in the target nothing is missing', () => {
    const {missingTags} = translationUpdate(
      '<x id="2"/>Ciao <g id="1">mondo</g>',
      sourceTagMap,
    )
    expect(missingTags).toEqual([])
  })

  test('without a source tag map no tag is missing', () => {
    expect(translationUpdate('Ciao', undefined).missingTags).toEqual([])
  })
})

describe('charactersCounter', () => {
  test('leaves tags out, or counts the tags other than g, bx, ex and x as their text when the project says so', () => {
    const text = 'ab<x id="2"/><ph id="1" equiv-text="base64:JXM="/>'
    expect(charactersCounter(text, false)).toBe(2)
    expect(charactersCounter(text, true)).toBe(4)
  })

  test('counts an nbsp placeholder as one character', () => {
    expect(charactersCounter('a##$_A0$##b', false)).toBe(3)
  })
})

describe('tagIdentities', () => {
  test('lists the Draft id and placeholder of each tag in document order, with its markup', () => {
    expect(tagIdentities('<x id="2"/>a<g id="1">b</g>', sourceTagMap)).toEqual([
      {raw: '<x id="2"/>', id: '2', placeholder: expect.any(String)},
      {raw: '<g id="1">', id: '1', placeholder: '1'},
      {raw: '</g>', id: '1', placeholder: '1'},
    ])
  })

  test('word joiners, characters in the CodeMirror target, do not shift the Draft identity of its chips', () => {
    const translation = '\u2060<x id="2"/>a\u2060<g id="1">b\u2060</g>\u2060'
    const chips = chipsWithIdentity(
      createSegmentState(translation),
      tagIdentities(translation, sourceTagMap),
      'target',
    )
    expect(chips.map(({token, identity}) => [token.raw, identity?.id])).toEqual(
      [
        ['<x id="2"/>', '2'],
        ['<g id="1">', '1'],
        ['</g>', '1'],
      ],
    )
  })

  test('the bridge also hands out the case change of the toolbar', () => {
    expect(targetEditorBridge.formatText('ciao mondo', 'capitalize')).toBe(
      'Ciao Mondo',
    )
  })
})
