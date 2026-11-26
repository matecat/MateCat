import React, {memo, useCallback, useContext, useMemo} from 'react'
import PropTypes from 'prop-types'
import {
  DIFF_DELETE,
  DIFF_INSERT,
  applyMarks,
  diffTokens,
  segmentClipboardHtml,
  sliceSegment,
  tokenize,
} from './segmentModel'
import {tagSignatures} from '../../utils/segmentText/tagModel'
import {ApplicationWrapperContext} from '../common/ApplicationWrapper/ApplicationWrapperContext'
import {
  CHARACTER_TAGS,
  specialCharMark,
  specialCharsPattern,
} from '../../utils/segmentText/specialChars'
import {segmentViewTagHandlers} from './tagTooltip'

const DIFF_CLASS = {[DIFF_DELETE]: 'deleted', [DIFF_INSERT]: 'added'}

const isChip = (node) =>
  node.nodeType === Node.ELEMENT_NODE &&
  node.getAttribute('contenteditable') === 'false'

// Removed parts of a diff are not in `segment`: they count for nothing.
const isDeleted = (node, root) =>
  node.parentNode === root &&
  node.nodeType === Node.ELEMENT_NODE &&
  node.classList.contains(DIFF_CLASS[DIFF_DELETE])

/**
 * A selection boundary as an offset in `markableText(segment)`: text counts
 * its characters, a chip one. A boundary inside a chip moves before it
 * (`start`) or after it (`end`), so a partly selected chip counts whole; one
 * inside a removed part of a diff moves before that part.
 */
const boundaryOffset = (root, container, offset, side) => {
  for (let node = container; node && node !== root; node = node.parentNode) {
    if (isChip(node) || isDeleted(node, root)) {
      container = node.parentNode
      offset =
        Array.prototype.indexOf.call(container.childNodes, node) +
        (side === 'end' && isChip(node) ? 1 : 0)
    }
  }
  let count = 0
  const visit = (node) => {
    if (node === container && node.nodeType === Node.TEXT_NODE) {
      count += offset
      return true
    }
    if (node.nodeType === Node.TEXT_NODE) {
      count += node.length
      return false
    }
    if (node !== root && (isDeleted(node, root) || isChip(node))) {
      count += isChip(node) ? 1 : 0
      return false
    }
    const children = node.childNodes
    for (let i = 0; i < children.length; i++) {
      if (node === container && i === offset) return true
      if (visit(children[i])) return true
    }
    return node === container
  }
  visit(root)
  return count
}

// A line feed shows its mark and then breaks the line, as the editors do. A carriage return (a chip, as in the editor)
// breaks the line too, unless a line feed follows it (CRLF): that is one break.
const breaksLine = (tokens, i) =>
  tokens[i].type === 'tag' &&
  (tokens[i].name === 'lineFeed' ||
    (tokens[i].name === 'carriageReturn' && tokens[i + 1]?.name !== 'lineFeed'))

// The mark of a special character (utils/segmentText/specialChars.js), as the editor draws it: the character stays in
// the text, so offsets and copies count it as the character it is, and the drawing is child elements with no text.
const renderMark = (ch, mark, key) => (
  <span
    key={key}
    className={mark.className}
    aria-label={mark.title ?? undefined}
  >
    {mark.keepsWidth ? (
      ch
    ) : (
      <span className="special-char-mark-char">{ch}</span>
    )}
    {mark.label && (
      <span
        className="special-char-mark-label"
        aria-hidden="true"
        data-label={mark.label}
      />
    )}
    {mark.shape && (
      <span className={`special-char-mark-${mark.shape}`} aria-hidden="true" />
    )}
  </span>
)

// Text with its special characters as marks.
const renderText = (text, key, options) => {
  const parts = options.inChip ? [text] : text.split(options.specialChars)
  if (parts.length === 1)
    return <React.Fragment key={key}>{text}</React.Fragment>
  return (
    <React.Fragment key={key}>
      {parts.map((part, i) => {
        if (i % 2 === 0) return part
        const mark = specialCharMark(part, options)
        return part === '\n' ? (
          <React.Fragment key={i}>
            {renderMark(part, mark)}
            <br />
          </React.Fragment>
        ) : (
          renderMark(part, mark, i)
        )
      })}
    </React.Fragment>
  )
}

const renderTokens = (tokens, options) =>
  tokens.map((token, i) =>
    breaksLine(tokens, i) ? (
      <React.Fragment key={i}>
        {renderToken(token, i, options)}
        <br />
      </React.Fragment>
    ) : (
      renderToken(token, i, options)
    ),
  )

const renderToken = (token, i, options) => {
  if (token.type === 'text') return renderText(token.text, i, options)
  if (token.type === 'mark') {
    return (
      <mark key={i} className={token.className}>
        {renderTokens(token.tokens, options)}
      </mark>
    )
  }
  // Inside the label of a chip nothing is a mark (Tag.scss reverts every span of a chip): the label stays as it was.
  if (!options.inChip) {
    // A space is text, shown as a mark only with the show whitespace option, as in the editor.
    if (token.name === 'space') return renderText(token.raw, i, options)
    // Tags that stand for one character are that character's mark, never a chip, as in the editor.
    const ch = CHARACTER_TAGS[token.name]
    if (ch) return renderMark(ch, specialCharMark(ch), i)
  }
  const content = token.children
    ? renderTokens(token.children, {...options, inChip: true})
    : token.label
  // The tag the chip shows, read by the tag tooltip (tagTooltip.js) when the chip is hovered.
  const tagData = {'data-tag-name': token.name, 'data-tag-role': token.role}
  if (token.index !== undefined && token.index !== null) {
    return (
      <span
        key={i}
        contentEditable={false}
        className={token.className}
        {...tagData}
        data-tag-index={token.index}
      >
        <span className="index-counter">{token.index + 1}</span>
        {token.role !== 'close' && <span data-text="true">{content}</span>}
      </span>
    )
  }
  // The label in its own element, as the numbered chips have it: that is what Tag.scss trims with an ellipsis.
  return (
    <span
      key={i}
      contentEditable={false}
      className={token.className}
      {...tagData}
    >
      <span className="tag-text">{content}</span>
    </span>
  )
}

/**
 * Read-only view of a segment string: text plus tag chips, with the markup
 * and classes of the cattool tags, and special characters drawn with the
 * marks of the editor. `showWhitespace` marks spaces too; without it the
 * view follows the user's switch from the application context, or the one held by the tag signatures when there is none. With
 * `compareTo`, shows the diff from `compareTo` (old) to `segment` (new),
 * removed parts in `span.deleted` and added parts in `span.added`. Without it, `marks` wraps ranges of the text
 * in `mark` elements. Other props go to the root element.
 */
const SegmentView = ({
  segment,
  isRtl = false,
  compareTo,
  marks,
  showWhitespace,
  as: Root = 'span',
  ...rootProps
}) => {
  const {onCopy, onMouseOver, onMouseOut, onMouseDown} = rootProps

  // Copy the selected part as a segment slice, so that a paste in the editor
  // gets real tags. Only a selection that lies within this view.
  const handleCopy = useCallback(
    (event) => {
      onCopy?.(event)
      const root = event.currentTarget
      const selection = root.ownerDocument.getSelection()
      if (event.defaultPrevented || !selection?.rangeCount) return
      const range = selection.getRangeAt(0)
      if (
        range.collapsed ||
        !root.contains(range.startContainer) ||
        !root.contains(range.endContainer)
      ) {
        return
      }
      const start = boundaryOffset(
        root,
        range.startContainer,
        range.startOffset,
        'start',
      )
      const end = boundaryOffset(
        root,
        range.endContainer,
        range.endOffset,
        'end',
      )
      const slice = sliceSegment(segment, start, end, {isRtl})
      if (!slice.raw) return
      event.clipboardData.setData('text/plain', slice.text)
      event.clipboardData.setData('text/html', segmentClipboardHtml(slice))
      event.preventDefault()
    },
    [segment, isRtl, onCopy],
  )

  // Without the prop, the user's switch from the application context (a change re-renders the view at once);
  // with no user there, the switch as the tag signatures hold it (see initTagSignature).
  const {userInfo} = useContext(ApplicationWrapperContext)
  const userSwitch = userInfo
    ? userInfo.metadata?.show_whitespace === 1
    : undefined
  const whitespace = showWhitespace ?? userSwitch ?? !!tagSignatures.space

  const content = useMemo(() => {
    const options = {
      showWhitespace: whitespace,
      specialChars: new RegExp(
        `(${specialCharsPattern({showWhitespace: whitespace})})`,
      ),
    }
    const tokens = tokenize(segment, {isRtl})
    if (compareTo === undefined || compareTo === null) {
      return renderTokens(
        marks?.length ? applyMarks(tokens, marks) : tokens,
        options,
      )
    }
    return diffTokens(tokenize(compareTo, {isRtl}), tokens).map(
      ({op, tokens: chunk}, i) => {
        const diffClass = DIFF_CLASS[op]
        return diffClass ? (
          <span key={i} className={diffClass}>
            {renderTokens(chunk, options)}
          </span>
        ) : (
          <React.Fragment key={i}>
            {renderTokens(chunk, options)}
          </React.Fragment>
        )
      },
    )
  }, [segment, isRtl, compareTo, marks, whitespace])

  // One delegated handler pair per view shows the full text of a trimmed or compressed chip: no state per chip.
  const tagHandlers = useMemo(
    () => ({
      onMouseOver: (event) => {
        onMouseOver?.(event)
        segmentViewTagHandlers.onMouseOver(event)
      },
      onMouseOut: (event) => {
        onMouseOut?.(event)
        segmentViewTagHandlers.onMouseOut(event)
      },
      onMouseDown: (event) => {
        onMouseDown?.(event)
        segmentViewTagHandlers.onMouseDown(event)
      },
    }),
    [onMouseOver, onMouseOut, onMouseDown],
  )

  return (
    <Root {...rootProps} {...tagHandlers} onCopy={handleCopy}>
      {content}
    </Root>
  )
}

SegmentView.propTypes = {
  segment: PropTypes.string,
  isRtl: PropTypes.bool,
  compareTo: PropTypes.string,
  marks: PropTypes.arrayOf(
    PropTypes.shape({
      start: PropTypes.number.isRequired,
      end: PropTypes.number.isRequired,
      className: PropTypes.string,
    }),
  ),
  showWhitespace: PropTypes.bool,
  as: PropTypes.elementType,
  className: PropTypes.string,
}

export default memo(SegmentView)
