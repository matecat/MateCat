import {setTagSignatureMiddleware} from './tagModel'
import {transformTagsToHtml, transformTagsToText} from './tagUtils'

setTagSignatureMiddleware('space', () => false)

test('Tags and placeholders to html', () => {
  let text =
    'test <g id="1">tag</g> ph con &lt; &gt; &amp;lt; &amp;gt; <g id="2"/> <ph id="mtc_1" ctype="x-html" equiv-text="base64:Jmx0O3AmZ3Q7"/> <ph id="mtc_2" ctype="x-html" equiv-text="base64:Jmx0O3N0cm9uZyZndDs="/> <ph id="mtc_3" ctype="x-html" equiv-text="base64:Jmx0Oy9zdHJvbmcmZ3Q7"/>pippoL&apos; placeholder &nbsp; elle-même'

  let resultHtml =
    'test <span contenteditable="false" class="tag small tag-open">​1​</span>tag<span contenteditable="false" class="tag small tag-close">​1​</span> ph con &lt; &gt; &amp;lt; &amp;gt; <span contenteditable="false" class="tag small tag-open">​2​</span> <span contenteditable="false" class="tag small tag-selfclosed tag-ph">&lt;p&gt;</span> <span contenteditable="false" class="tag small tag-selfclosed tag-ph">&lt;strong&gt;</span> <span contenteditable="false" class="tag small tag-selfclosed tag-ph">&lt;/strong&gt;</span>pippoL&apos; placeholder &nbsp; elle-même'

  expect(transformTagsToHtml(text)).toBe(resultHtml)
})

test('Tags ph to text', () => {
  let text =
    'test <g id="1">tag</g> ph con &lt; &gt; &amp;lt; <g id="2"></g> &amp;gt; <ph id="mtc_1" ctype="x-html" equiv-text="base64:Jmx0O3AmZ3Q7"/> <ph id="mtc_2" ctype="x-html" equiv-text="base64:Jmx0O3N0cm9uZyZndDs="/> <ph id="mtc_3" ctype="x-html" equiv-text="base64:Jmx0Oy9zdHJvbmcmZ3Q7"/>pippoL&apos; placeholder &nbsp; elle-même'

  let resultText =
    'test <g id="1">tag</g> ph con &lt; &gt; &amp;lt; <g id="2"></g> &amp;gt; <p> <strong> </strong>pippoL&apos; placeholder &nbsp; elle-même'
  expect(transformTagsToText(text)).toBe(resultText)
})

test('Placeholders to text', () => {
  let text =
    'test tag ph con ##$_SPLIT$## &lt; &gt; ##$_0A$## &amp;lt; ##$_09$## &amp;gt; ##$_A0$## ##$_0D$## <ph id="mtc_1" ctype="x-html" equiv-text="base64:Jmx0O3AmZ3Q7"/> <ph id="mtc_2" ctype="x-html" equiv-text="base64:Jmx0O3N0cm9uZyZndDs="/> <ph id="mtc_3" ctype="x-html" equiv-text="base64:Jmx0Oy9zdHJvbmcmZ3Q7"/>pippoL&apos; placeholder &nbsp; ##$_A0$##elle-même'

  let resultText =
    'test tag ph con \uf03d &lt; &gt; \n &amp;lt; \u21E5 &amp;gt; \u00B0 \\r <p> <strong> </strong>pippoL&apos; placeholder &nbsp; \u00B0elle-même'

  expect(transformTagsToText(text)).toBe(resultText)
})
