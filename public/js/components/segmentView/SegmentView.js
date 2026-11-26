import React, {memo, useCallback, useMemo} from 'react'
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

// A line break shows its chip and then breaks the line, as the editors do. A carriage return followed by a line
// feed (CRLF) is one break: only the line feed breaks.
const breaksLine = (tokens, i) =>
  tokens[i].type === 'tag' &&
  (tokens[i].name === 'lineFeed' ||
    (tokens[i].name === 'carriageReturn' && tokens[i + 1]?.name !== 'lineFeed'))

const renderTokens = (tokens) =>
  tokens.map((token, i) =>
    breaksLine(tokens, i) ? (
      <React.Fragment key={i}>
        {renderToken(token, i)}
        <br />
      </React.Fragment>
    ) : (
      renderToken(token, i)
    ),
  )

const renderToken = (token, i) => {
  if (token.type === 'text') {
    return <React.Fragment key={i}>{token.text}</React.Fragment>
  }
  if (token.type === 'mark') {
    return (
      <mark key={i} className={token.className}>
        {renderTokens(token.tokens)}
      </mark>
    )
  }
  const content = token.children ? renderTokens(token.children) : token.label
  if (token.index !== undefined && token.index !== null) {
    return (
      <span key={i} contentEditable={false} className={token.className}>
        <span className="index-counter">{token.index + 1}</span>
        {token.role !== 'close' && <span data-text="true">{content}</span>}
      </span>
    )
  }
  return (
    <span key={i} contentEditable={false} className={token.className}>
      {content}
    </span>
  )
}

/**
 * Read-only view of a segment string: text plus tag chips, with the markup
 * and classes of the cattool tags. With `compareTo`, shows the diff from
 * `compareTo` (old) to `segment` (new), removed parts in `span.deleted` and
 * added parts in `span.added`. Without it, `marks` wraps ranges of the text
 * in `mark` elements. Other props go to the root element.
 */
const SegmentView = ({
  segment,
  isRtl = false,
  compareTo,
  marks,
  as: Root = 'span',
  ...rootProps
}) => {
  const {onCopy} = rootProps

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

  const content = useMemo(() => {
    const tokens = tokenize(segment, {isRtl})
    if (compareTo === undefined || compareTo === null) {
      return renderTokens(marks?.length ? applyMarks(tokens, marks) : tokens)
    }
    return diffTokens(tokenize(compareTo, {isRtl}), tokens).map(
      ({op, tokens: chunk}, i) => {
        const diffClass = DIFF_CLASS[op]
        return diffClass ? (
          <span key={i} className={diffClass}>
            {renderTokens(chunk)}
          </span>
        ) : (
          <React.Fragment key={i}>{renderTokens(chunk)}</React.Fragment>
        )
      },
    )
  }, [segment, isRtl, compareTo, marks])

  return (
    <Root {...rootProps} onCopy={handleCopy}>
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
  as: PropTypes.elementType,
  className: PropTypes.string,
}

export default memo(SegmentView)
