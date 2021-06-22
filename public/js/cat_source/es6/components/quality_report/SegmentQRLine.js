import React from 'react'

class SegmentQRLine extends React.Component {
  allowHTML(string) {
    return {__html: string}
  }
  getTimeToEdit(tte) {
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
  render() {
    let suggestionMatch, suggestionMatchClass
    if (this.props.showSuggestionSource) {
      suggestionMatch =
        this.props.segment.get('match_type') === 'ICE'
          ? 101
          : parseInt(this.props.segment.get('suggestion_match'))
      suggestionMatchClass =
        suggestionMatch === 101
          ? 'per-blu'
          : suggestionMatch === 100
          ? 'per-green'
          : suggestionMatch > 0 && suggestionMatch <= 99
          ? 'per-orange'
          : ''
    }

    return (
      <div className={this.props.classes}>
        {this.props.onClickLabel ? (
          <a className="segment-content qr-segment-title">
            <b onClick={this.props.onClickLabel}>{this.props.label}</b>
            {this.props.showDiffButton ? (
              <button
                className={this.props.diffActive ? 'active' : ''}
                onClick={this.props.onClickDiff}
                title="Show Diff"
              >
                <i className="icon-eye2 icon" />
              </button>
            ) : null}
          </a>
        ) : (
          <div className="segment-content qr-segment-title">
            <b>{this.props.label}</b>
          </div>
        )}

        <div
          className="segment-content qr-text"
          dangerouslySetInnerHTML={this.allowHTML(this.props.text)}
        />

        {this.props.showSegmentWords ? (
          <div className="segment-content qr-spec">
            <div>Words:</div>
            <div>
              <b>{parseInt(this.props.segment.get('raw_word_count'))}</b>
            </div>
          </div>
        ) : null}

        {this.props.showSuggestionSource ? (
          <div className="segment-content qr-spec">
            <div
              className={
                this.props.segment.get('suggestion_source') === 'MT'
                  ? 'per-yellow'
                  : null
              }
            >
              <b>{this.props.segment.get('suggestion_source')}</b>
            </div>
            {this.props.segment.get('suggestion_source') &&
            this.props.segment.get('suggestion_source') !== 'MT' ? (
              <div className={'tm-percent ' + suggestionMatchClass}>
                {suggestionMatch}%
              </div>
            ) : null}
          </div>
        ) : null}

        {this.props.showIceMatchInfo &&
        this.props.segment.get('ice_locked') === '1' ? (
          <div className="segment-content qr-spec">
            {this.props.segment.get('ice_locked') === '1' ? (
              <div>
                <b>ICE Match</b>
                {this.props.segment.get('ice_modified') ? (
                  <div>(Modified)</div>
                ) : null}
              </div>
            ) : null}

            {this.props.tte ? (
              <div className={'tte-container'}>
                <b>TTE:</b>
                <div>
                  <div>{this.getTimeToEdit(this.props.tte)}</div>
                </div>
              </div>
            ) : null}
          </div>
        ) : this.props.tte ? (
          <div className="segment-content qr-spec tte">
            <b>TTE:</b>
            <div>
              <div>{this.getTimeToEdit(this.props.tte)}</div>
            </div>
          </div>
        ) : null}

        {this.props.showIsPretranslated ? (
          <div className="segment-content qr-spec">
            <div>
              <b>Pre-Translated</b>
            </div>
          </div>
        ) : null}
        {!(
          this.props.showIceMatchInfo &&
          this.props.segment.get('ice_locked') === '1'
        ) &&
        !this.props.showSuggestionSource &&
        !this.props.showSegmentWords &&
        !this.props.tte &&
        !this.props.showIsPretranslated ? (
          <div className="segment-content qr-spec" />
        ) : null}
      </div>
    )
  }
}

SegmentQRLine.defaultProps = {
  showSegmentWords: false,
  showSuggestionSource: false,
  showIceMatchInfo: false,
  diffActive: false,
}

export default SegmentQRLine
