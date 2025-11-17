import React, {useRef} from 'react'
import Tooltip from '../common/Tooltip'

export const IcuHighlight = ({
  start,
  end,
  tokens,
  children,
  blockKey,
  isTarget,
}) => {
  const token = tokens.find(
    (item) =>
      item.start === start && item.end === end && item.blockKey === blockKey,
  )
  const refToken = useRef()
  return (
    <div
      className={`icuItem ${token && token.type === 'error' && isTarget ? 'icuItem-error' : ''}`}
    >
      {token && token.type === 'error' && isTarget ? (
        <Tooltip
          content={
            <div className="icu-tooltip">
              <h3>ICU syntax error</h3>
              {token.message.map((t) => (
                <p key={t}>{t}</p>
              ))}
            </div>
          }
          isInteractiveContent={true}
        >
          <span ref={refToken}>{children}</span>
        </Tooltip>
      ) : (
        <span>{children}</span>
      )}
    </div>
  )
}
