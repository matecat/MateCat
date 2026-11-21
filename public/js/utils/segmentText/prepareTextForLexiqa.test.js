import {setTagSignatureMiddleware} from './tagModel'
import prepareTextForLexiqa from './prepareTextForLexiqa'

setTagSignatureMiddleware('space', () => false)

test('returns plain text untouched', () => {
  expect(prepareTextForLexiqa('hello world')).toBe('hello world')
})

test('converts a placeholder tag into its lexiqa-friendly text', () => {
  const text = 'value ##$_A0$## end'

  expect(prepareTextForLexiqa(text)).toMatchInlineSnapshot(`"value < > end"`)
})

test('converts a g tag pair into lexiqa placeholders', () => {
  const text = 'test <g id="1">tag</g> end'

  expect(prepareTextForLexiqa(text)).toMatchInlineSnapshot(
    `"test <1>tag<1> end"`,
  )
})
