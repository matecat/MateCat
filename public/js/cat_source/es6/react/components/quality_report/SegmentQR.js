
class SegmentQR extends React.Component {

    render () {

        return <div className="qr-single-segment">

            <div className="qr-segment-head shadow-1">
                <div className="segment-id">{this.props.segment.get("sid")}</div>
                <div className="segment-production-container">
                    <div className="segment-production">
                        <div className="ui basic button tiny automated-qa">Automated QA<b> (7)</b></div>
                        <div className="ui basic button tiny human-qa">Human QA<b> (7)</b></div>
                    </div>
                    <div className="segment-production">
                        <div className="production word-speed">Words speed: <b>7"</b></div>
                        <div className="production time-edit">Time to edit: <b>53"</b></div>
                        <div className="production pee">PEE: <b>30%</b></div>
                    </div>
                </div>
                <div className="segment-status-container">
                    <div className="qr-label">Segment status</div>
                    <div className="qr-info status-translated"><b>Translated</b></div>
                </div>
            </div>


            <div className="qr-segment-body ">

                    <div className="segment-container qr-source">
                        <div className="segment-content qr-segment-title">
                            <b>Source</b>
                        </div>
                        <div className="segment-content qr-text">
                            Hi!
                            <span className="qr-tags start-tag">&lt;g id="1"&gt;</span> my name is <span className="qr-tags end-tag">&lt;/g&gt;</span>
                            Rubén Santillàn and I'm a
                            <span className="qr-tags start-tag violet-tag">&lt;g id="2"&gt;</span>Designer<span className="qr-tags end-tag violet-tag">&lt;/g&gt;</span>
                        </div>
                        <div className="segment-content qr-spec">
                            <div>Words:</div>
                            <div><b>10</b></div>
                        </div>
                    </div>

                    <div className="segment-container qr-suggestion">
                        <div className="segment-content qr-segment-title">
                            <b>Suggestion</b>
                        </div>
                        <div className="segment-content qr-text">
                            Hi! my name is Rubén Santillàn
                        </div>
                        <div className="segment-content qr-spec">
                            <div>Public <b>TM</b></div>
                            <div className="tm-percent">101%</div>
                        </div>
                    </div>

                    <div className="segment-container qr-translated">
                        <div className="segment-content qr-segment-title">
                            <b>Translate</b>
                            <button>
                                <i className="icon-eye2 icon" />
                            </button>
                        </div>
                        <div className="segment-content qr-text">
                            Hi! <span className="qr-diff clear"> my name</span> <span className="qr-diff add"> is Rubén</span> Santillàn
                        </div>
                        <div className="segment-content qr-spec">
                            <div><b>ICE Match</b></div>
                            <div>(Modified)</div>
                        </div>
                    </div>

                    <div className="segment-container qr-revised">
                        <div className="segment-content qr-segment-title">
                            <b>Revised</b>
                            <button>
                                <i className="icon-eye2 icon" />
                            </button>
                        </div>
                        <div className="segment-content qr-text">
                            Hi! my name is Rubén Santillàn, Hi! my name is Rubén Santillàn, Hi! my name is Rubén Santillàn, Hi! my name is Rubén Santillàn, Hi! my name is Rubén Santillàn
                        </div>
                        <div className="segment-content qr-spec">

                        </div>
                    </div>

                    <div className="segment-container qr-issues">
                        <div className="segment-content qr-segment-title">
                            <b>Atomated QA</b>
                        </div>
                        <div className="segment-content qr-text">
                            <div className="qr-issues-list">
                                <div className="qr-issue automated">
                                    <div className="box-icon">
                                        <i className="icon-cancel-circle icon red" />
                                    </div>
                                    <div className="qr-error">Tag mismatch <b>(2)</b></div>
                                </div>
                                <div className="qr-issue automated">
                                    <div className="box-icon">
                                        <i className="icon-warning2 icon orange" />
                                    </div>
                                    <div className="qr-error">Tag mismatch <b>(2)</b></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="segment-container qr-issues">
                        <div className="segment-content qr-segment-title">
                            <b>Human QA</b>
                        </div>
                        <div className="segment-content qr-text">
                            <div className="qr-issues-list">

                                <div className="qr-issue human critical">
                                    <div className="qr-error"><b>Language quality</b></div>
                                    <div className="sub-type-error">Subtype </div>
                                    <div className="severity"><b>Critical</b></div>
                                </div>
                                <div className="qr-issue human major">
                                    <div className="qr-error"><b>Language quality</b></div>
                                    <div className="sub-type-error">Subtype </div>
                                    <div className="severity"><b>Major</b></div>
                                </div>
                                <div className="qr-issue human enhacement">
                                    <div className="qr-error"><b>Language quality</b></div>
                                    <div className="sub-type-error">Subtype </div>
                                    <div className="severity"><b>Enhacement</b></div>
                                </div>

                            </div>
                        </div>
                    </div>

            </div>
        </div>
    }
}

export default SegmentQR ;