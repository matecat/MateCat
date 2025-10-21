import React, {useRef} from 'react'
import Tooltip from '../common/Tooltip'

export const IcuHighlight = ({start, end, tokens, children, blockKey}) => {
  const token = tokens.find(
    (item) => item.start === start && item.end === end && item.key === blockKey,
  )
  const refToken = useRef()
  return (
    <div
      className={`icuItem ${token && token.type === 'error' ? 'icuItem-error' : ''}`}
    >
      {token && token.type === 'error' ? (
        <Tooltip
          content={
            <div className="icu-tooltip">
              <h3>ICU syntax error</h3>
              <p>{token.message}</p>
            </div>
          }
        >
          <span ref={refToken}>{children}</span>
        </Tooltip>
      ) : (
        <span>{children}</span>
      )}
    </div>
  )
}
