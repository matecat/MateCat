import {render, screen} from '@testing-library/react'
import React from 'react'
import SimpleEditor from './SimpleEditor'
import {setTagSignatureMiddleware} from './utils/DraftMatecatUtils/tagModel'

setTagSignatureMiddleware('space', () => false)

test('Test input string', () => {
  let text = 'Cia&apos;o &amp;nbsp; &amp;nbsp; &amp;lt; &amp;lt;come stai? '
  const result = "Cia'o &nbsp; &nbsp; &lt; &lt;come stai? "
  render(<SimpleEditor text={text} />)

  expect(screen.getByTestId('simple-editor-test').textContent).toBe(result)
})

test('Test input string', () => {
  let text =
    'test <g id="1">tag</g> ph con &lt; &gt; &amp;lt; <g id="2"></g> &amp;gt; <ph id="mtc_1" ctype="x-html" equiv-text="base64:Jmx0O3AmZ3Q7"/> <ph id="mtc_2" ctype="x-html" equiv-text="base64:Jmx0O3N0cm9uZyZndDs="/> <ph id="mtc_3" ctype="x-html" equiv-text="base64:Jmx0Oy9zdHJvbmcmZ3Q7"/>pippoL&apos; placeholder &nbsp; elle-même'

  const result =
    "test \u200B1\u200Btag\u200B1\u200B ph con < > &lt; \u200B2\u200B\u200B2\u200B &gt; 1<p> 2<strong> 3</strong>pippoL' placeholder \u00A0 elle-m\u00EAme"
  render(<SimpleEditor text={text} />)

  expect(screen.getByTestId('simple-editor-test').textContent).toEqual(result)
})
