import React, {memo, useMemo} from 'react'
import PropTypes from 'prop-types'
import {DIFF_DELETE, DIFF_INSERT, diffTokens, tokenize} from './segmentModel'

const DIFF_CLASS = {[DIFF_DELETE]: 'deleted', [DIFF_INSERT]: 'added'}

const renderTokens = (tokens) =>
  tokens.map((token, i) => {
    if (token.type === 'text') {
      return <React.Fragment key={i}>{token.text}</React.Fragment>
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
  })

/**
 * Read-only view of a segment string: text plus tag chips, with the markup
 * and classes of the cattool tags. With `compareTo`, shows the diff from
 * `compareTo` (old) to `segment` (new), removed parts in `span.deleted` and
 * added parts in `span.added`.
 */
const SegmentView = ({segment, isRtl = false, compareTo, className}) => {
  const content = useMemo(() => {
    const tokens = tokenize(segment, {isRtl})
    if (compareTo === undefined || compareTo === null) {
      return renderTokens(tokens)
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
  }, [segment, isRtl, compareTo])

  return <span className={className}>{content}</span>
}

SegmentView.propTypes = {
  segment: PropTypes.string,
  isRtl: PropTypes.bool,
  compareTo: PropTypes.string,
  className: PropTypes.string,
}

export default memo(SegmentView)
