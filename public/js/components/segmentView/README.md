# SegmentView

Read-only rendering of a segment: text plus tag chips, as React elements. It
replaces `transformTagsToHtml()` + `dangerouslySetInnerHTML` wherever a segment
is only shown, not edited.

```jsx
import SegmentView from '../segmentView/SegmentView'

<SegmentView segment={translation} isRtl={config.isTargetRTL} />

// diff from an older version to this one
<SegmentView segment={revision} compareTo={translation} />
```

## Props

| Prop        | Type   | Default | Meaning                                                                                       |
|-------------|--------|---------|-----------------------------------------------------------------------------------------------|
| `segment`   | string | —       | The segment to show                                                                           |
| `isRtl`     | bool   | `false` | Right-to-left language: swaps the open/close tag styles, as the cattool does                  |
| `compareTo` | string | —       | Older text to diff from. Removed parts are wrapped in `span.deleted`, added parts in `span.added` |
| `className` | string | —       | Class of the root `span`                                                                      |

## Input format

The cattool segment string (layer 2): XLIFF tags as markup (`<g>`, `<x/>`,
`<bx/>`, `<ex/>`, `<ph equiv-text="base64:…"/>`), text HTML-encoded (`&amp;`,
`&lt;`…), special characters as `##$_XX$##` placeholders.

## Output

The same markup and classes as `transformTagsToHtml`, so `Tag.scss` applies:
`span.tag.small.<style>` per tag signature, numbered (compressible) tags with
`span.index-counter` and `span[data-text]`, `<g>` labelled with its id and each
`</g>` with its opener's id (`?` when orphan). `SegmentView.parity.test.js`
holds the two renderers to the same output.

Text is decoded and rendered by React as text: markup carried by the segment
can never become HTML. The diff works on tokens, so a tag is never split.

`segmentModel.js` exposes the pure functions behind it: `tokenize(segment,
{isRtl})`, `visibleText(segment)` and `diffTokens(oldTokens, newTokens)`.

## Rules

- Everything comes through props. No stores, actions, dispatcher or the global
  `config`: callers read those and pass values in.
- Only the Draft-free tag modules of `segments/utils/DraftMatecatUtils/` may be
  imported (`tagModel`, `compressibleTagUtils`), never that folder's `index.js`
  nor anything that pulls in draft-js.
- The tag signatures are shared with the cattool, including the user's
  show-whitespace switch (`initTagSignature`): the view follows it as
  `transformTagsToHtml` does.
