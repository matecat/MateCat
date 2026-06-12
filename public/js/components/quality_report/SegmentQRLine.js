import React, {useRef} from 'react'
import {Badge, BADGE_TYPE} from '../common/Badge'
const SegmentQRLine = ({
  showSuggestionSource = false,
  segment,
  classes,
  onClickLabel,
  label,
  showDiffButton,
  diffActive = false,
  onClickDiff,
  text,
  showSegmentWords = false,
  showIceMatchInfo = false,
  tte,
  showIsPretranslated,
  rev,
}) => {
  const textRef = useRef()
  const allowHTML = (string) => {
    return {__html: string}
  }
  const getTimeToEdit = (tte) => {
    let str_pad_left = function (string, pad, length) {
      return (new Array(length + 1).join(pad) + string).slice(-length)
    }
    let time = parseInt(tte / 1000)
    let hours = Math.floor(time / 3600)
    let minutes = Math.floor(time / 60)
    let seconds = parseInt(time - minutes * 60)
    if (hours > 0) {
      return (
        str_pad_left(hours, '0', 2) +
        'h' +
        str_pad_left(minutes, '0', 2) +
        "'" +
        str_pad_left(seconds, '0', 2) +
        "''"
      )
    } else if (minutes > 0) {
      return (
        str_pad_left(minutes, '0', 2) +
        "'" +
        str_pad_left(seconds, '0', 2) +
        "''"
      )
    } else {
      return str_pad_left(seconds, '0', 2) + "''"
    }
  }
  let suggestionMatch, suggestionMatchClass
  if (showSuggestionSource) {
    suggestionMatch =
      segment.get('match_type').toUpperCase() === 'ICE'
        ? 101
        : parseInt(segment.get('suggestion_match'))
    suggestionMatchClass =
      segment.get('suggestion_source') === 'MT'
        ? BADGE_TYPE.YELLOW
        : suggestionMatch === 101
          ? BADGE_TYPE.PRIMARY
          : suggestionMatch === 100
            ? BADGE_TYPE.GREEN
            : suggestionMatch > 0 && suggestionMatch <= 99
              ? BADGE_TYPE.ORANGE
              : suggestionMatch === 0
                ? BADGE_TYPE.RED
                : ''
  }

  const copyText = async (e) => {
    const internalClipboard = document.getSelection()
    if (internalClipboard) {
      e.preventDefault()
      // Get plain text form internalClipboard fragment
      const plainText = internalClipboard
        .toString()
        .replace(new RegExp(String.fromCharCode(parseInt('200B', 16)), 'g'), '')
        .replace(/·/g, ' ')
      return await navigator.clipboard.writeText(plainText)
    }
  }

  return (
    <div className={classes}>
      {onClickLabel ? (
        <a className="segment-content qr-segment-title">
          <b onClick={onClickLabel}>{label}</b>
          {showDiffButton ? (
            <button
              className={diffActive ? 'active' : ''}
              onClick={onClickDiff}
              title="Show Diff"
            >
              <i className="icon-eye2 icon" />
            </button>
          ) : null}
        </a>
      ) : (
        <div className="segment-content qr-segment-title">
          <b>{label}</b>
        </div>
      )}

      <div
        className="segment-content qr-text"
        ref={textRef}
        onCopy={copyText}
        onCut={copyText}
        dangerouslySetInnerHTML={allowHTML(text)}
      />

      {showSegmentWords ? (
        <div className="segment-content qr-spec">
          <div>Words:</div>
          <div>
            <b>{parseInt(segment.get('raw_word_count'))}</b>
          </div>
        </div>
      ) : null}

      {showSuggestionSource ? (
        <div className="segment-content qr-spec">
          <div className="tm-percent">
            <Badge type={suggestionMatchClass}>
              {!segment.get('suggestion_source')
                ? suggestionMatch + '%'
                : segment.get('suggestion_source') !== 'MT'
                  ? `${segment.get('suggestion_source')} - ${suggestionMatch}%`
                  : segment.get('suggestion_source')}
            </Badge>
          </div>
        </div>
      ) : null}

      {tte ? (
        <div className="segment-content qr-spec tte">
          <b>TTE:</b>
          <div>
            <div>{getTimeToEdit(tte)}</div>
          </div>
        </div>
      ) : null}

      {showIsPretranslated && !rev ? (
        <div className="segment-content qr-spec">
          <div>
            <b>Pre-Translated</b>
          </div>
        </div>
      ) : showIsPretranslated && rev ? (
        <div className="segment-content qr-spec">
          <div>
            <b>Pre-Approved</b>
          </div>
        </div>
      ) : null}
      {!(showIceMatchInfo && segment.get('ice_locked')) &&
      !showSuggestionSource &&
      !showSegmentWords &&
      !tte &&
      !showIsPretranslated ? (
        <div className="segment-content qr-spec" />
      ) : null}
    </div>
  )
}

export default SegmentQRLine
