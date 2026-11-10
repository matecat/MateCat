# SegmentView

Read-only rendering of a segment: text plus tag chips, as React elements. It
replaces `transformTagsToHtml()` + `dangerouslySetInnerHTML` wherever a segment
is only shown, not edited.

```jsx
import SegmentView from '../segmentView/SegmentView'

<SegmentView segment={translation} isRtl={config.isTargetRTL} />

// diff from an older version to this one
<SegmentView segment={revision} compareTo={translation} />

// highlight ranges of the text (search hits, a concordance term)
<SegmentView segment={source} marks={[{start: 0, end: 5, className: 'searchMarker'}]} />

// any other prop goes to the root element, `as` picks it
<SegmentView as="div" className="targetarea" lang="it-IT" segment={translation} />
```

## Props

| Prop        | Type   | Default | Meaning                                                                                       |
|-------------|--------|---------|-----------------------------------------------------------------------------------------------|
| `segment`   | string | —       | The segment to show                                                                           |
| `isRtl`     | bool   | `false` | Right-to-left language: swaps the open/close tag styles, as the cattool does                  |
| `compareTo` | string | —       | Older text to diff from. Removed parts are wrapped in `span.deleted`, added parts in `span.added` |
| `marks`     | array  | —       | `{start, end, className}` ranges of `markableText(segment)`, each wrapped in a `mark`. Ignored with `compareTo` |
| `showWhitespace` | bool | the user's switch | Marks every space. Without it, the view follows the switch held by the tag signatures (`initTagSignature`) |
| `as`        | elementType | `'span'` | Root element                                                                         |
| …           |        |         | Any other prop (`className`, `id`, `lang`, `data-*`…) goes to the root element                |

## Input format

The cattool segment string (layer 2): XLIFF tags as markup (`<g>`, `<x/>`,
`<bx/>`, `<ex/>`, `<ph equiv-text="base64:…"/>`), text HTML-encoded (`&amp;`,
`&lt;`…), special characters as `##$_XX$##` placeholders.

## Output

The same markup and classes as `transformTagsToHtml`, so `Tag.scss` applies:
`span.tag.small.<style>` per tag signature, numbered (compressible) tags with
`span.index-counter` and `span[data-text]`, the label of every other chip in
`span.tag-text`, `<g>` labelled with its id and each `</g>` with its opener's
id (`?` when orphan). `SegmentView.parity.test.js` holds the two renderers to
the same output, apart from the differences listed there.

Special characters are drawn with the display-only marks of the CodeMirror
editor, so a segment looks the same open and closed: one table
(`utils/segmentText/specialChars.js`) and one stylesheet
(`css/sass/components/segment/SpecialCharMarks.scss`, loaded by the cattool and
quality report pages). The nbsp, tab, word joiner and line feed tags are marks,
not chips (a line feed mark is followed by a `<br>`); so are the typographic
and zero-width spaces, the soft hyphen and the line/paragraph separators in the
text, and every space with `showWhitespace`. The carriage return stays a chip,
as in the editor. A mark keeps the character it stands for as its text (hidden
when it has no width of its own) and draws itself with child elements that hold
no text, so offsets, search marks and copies count it as that one character.
Inside the label of a chip nothing becomes a mark.

Text is decoded and rendered by React as text: markup carried by the segment
can never become HTML. The diff works on tokens, so a tag is never split.

`segmentModel.js` exposes the pure functions behind it: `tokenize(segment,
{isRtl})`, `visibleText(segment)` and `diffTokens(oldTokens, newTokens)`.

## Marks

Mark offsets refer to `markableText(segment, {isRtl})`: the decoded text,
with every tag as one character. Whitespace chips (space, nbsp, tab, line
breaks) stand for the character they show, every other tag for `OPAQUE_TAG`
(U+FFFC). Run a search on that string and its match offsets are marks: a hit
can never land inside a tag chip nor run across one, and one that runs across
a whitespace chip wraps it. Text is split at mark edges; a tag inside a mark is
wrapped whole.

`marksFromDelimiters(segment, open, close, {isRtl})` turns delimited parts
(the concordance service's `#{term}#`) into `{segment, marks}`.

## Copy

A copy (Ctrl/Cmd+C) of a selection that lies within one view writes:

- `text/html`: `<span data-matecat-segment="…">text</span>`, the attribute
  holding the base64 (UTF-8) of the **raw segment slice**: the selected part of
  `segment` as a segment string (see *Input format*). Tags are their markup, a
  partly selected chip counts whole; text is encoded as stored (`&amp;`,
  `&lt;`, `&gt;`); whitespace chips are their placeholders (`##$_A0$##`…).
- `text/plain`: the text as shown, except that whitespace chips are the
  character they stand for (space, nbsp, tab, line break), not their symbol.

With `compareTo` only the new side (`segment`) is copied: removed parts are
skipped. A selection that runs outside the view is left to the browser.

This clipboard format is the one format of every copy, cut and drag in
Matecat: the Draft editors write it too (`getSegmentSlice`, for the target and
the source) and read it on paste (`buildPastedEditorState`) and drop
(`resolveDrop`). Any editor that replaces them must write and read the same
format. `readSegmentClipboard(html)` decodes it
and validates it — the attribute can come from any page, so only markup that is
exactly one of the segment's tags stays a tag, the rest becomes text.
`sliceSegment(segment, start, end)` and `segmentClipboardHtml(slice)` write it.

## Rules

- Everything comes through props. No stores, actions, dispatcher or the global
  `config`: callers read those and pass values in.
- Only the Draft-free tag modules of `segments/utils/DraftMatecatUtils/` may be
  imported (`tagModel`, `compressibleTagUtils`), never that folder's `index.js`
  nor anything that pulls in draft-js.
- The tag signatures are shared with the cattool, including the user's
  show-whitespace switch (`initTagSignature`): without `showWhitespace` the view
  follows it. Pass the prop where the view must follow a change of the switch
  at once (closed segments do).
- Never import CodeMirror: the marks come from the shared table.
