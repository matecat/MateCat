class SegmentQRLine extends React.Component {
    constructor(props) {
        super(props);
    }

    allowHTML(string) {
        return { __html: string };
    }
    render () {
        let suggestionMatch, suggestionMatchClass;
        if ( this.props.showSuggestionSource ) {
            suggestionMatch = ( this.props.segment.get("match_type") === "ICE") ? 101 : parseInt(this.props.segment.get("suggestion_match"));
            suggestionMatchClass = (suggestionMatch === 101)? 'per-blu': (suggestionMatch === 100)? 'per-green' : (suggestionMatch > 0 && suggestionMatch <=99)? 'per-orange' : '';
        }

        return <div className={this.props.classes}>
            { this.props.onClickLabel ? (
                <a className="segment-content qr-segment-title">
                    <b onClick={this.props.onClickLabel}>{this.props.label}</b>
                    { this.props.showDiffButton ? (
                        <button className={(this.props.diffActive ? "active" : "")} onClick={this.props.onClickDiff}  title="Show Diff">
                            <i className="icon-eye2 icon" />
                        </button>
                    ) : null}
                </a>
            ) : (
                <div className="segment-content qr-segment-title">
                    <b>{this.props.label}</b>
                </div>
            ) }

            <div className="segment-content qr-text" dangerouslySetInnerHTML={ this.allowHTML(this.props.text) }/>

            {this.props.showSegmentWords ? (
                <div className="segment-content qr-spec">
                    <div>Words:</div>
                    <div><b>{parseInt(this.props.segment.get("raw_word_count"))}</b></div>
                </div>
            ) :  null }

            {this.props.showSuggestionSource ? (
                <div className="segment-content qr-spec">
                    <div className={ this.props.segment.get("suggestion_source") === "MT" ? ('per-yellow'): null}>
                        <b>{this.props.segment.get("suggestion_source")}</b>
                    </div>
                    {this.props.segment.get("suggestion_source") !== "MT" ? (
                        <div className={"tm-percent " + suggestionMatchClass}>{suggestionMatch}%</div>
                    ) : null}

                </div>
            ) : null}

            { this.props.showIceMatchInfo ?  (
                <div className="segment-content qr-spec">
                    {this.props.segment.get('ice_locked') === '1' ? (
                        <div>
                            <b>ICE Match</b>
                        </div>
                    ) :  null}
                    {this.props.segment.get('ice_modified') ? (
                        <div>(Modified)</div>
                    ) : null}
                </div>
            ) : (
                null
                ) }
            { !this.props.showIceMatchInfo && !this.props.showSuggestionSource && !this.props.showSegmentWords ?  (
                <div className="segment-content qr-spec"/>
            ) : (null) }

        </div>
    }
}

SegmentQRLine.defaultProps = {
    showSegmentWords: false,
    showSuggestionSource: false,
    showIceMatchInfo: false,
    diffActive: false


};

export default SegmentQRLine ;