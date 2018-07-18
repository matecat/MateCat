import TagsUtils from "../../utils/textUtils"
import classnames from "classnames";
class SegmentQR extends React.Component {
    constructor(props) {
        super(props);
        this.state = {
            translateDiffOn: false,
            reviseDiffOn: false,
            htmlDiff: ""
        };
    }
    showTranslateDiff() {
        if (this.state.translateDiffOn) {
            this.setState({
                translateDiffOn: false,
            });
        } else {
            let diffHtml = this.getDiffPatch(this.props.segment.get("translation"));
            this.setState({
                translateDiffOn: true,
                reviseDiffOn: false,
                htmlDiff: diffHtml
            });
        }
    }
    showReviseDiff() {
        if (this.state.reviseDiffOn) {
            this.setState({
                reviseDiffOn: false,
            });
        } else {
            let diffHtml = this.getDiffPatch(this.props.segment.get("translation"));
            this.setState({
                translateDiffOn: false,
                reviseDiffOn: true,
                htmlDiff: diffHtml
            });
        }
    }
    decodeTextAndTransormTags(text) {
         let decodedText = TagsUtils.decodePlaceholdersToText(text);
         decodedText = TagsUtils.transformTextForLockTags(decodedText);
         return decodedText;
    }
    allowHTML(string) {
        return { __html: string };
    }
    getDiffPatch(text) {
        let suggestion = "&lt;g id=\"3521\"&gt;Sintesi: Informazioni sugli aspetti e i  ﻿principali in  all'aggiornamento a Project Server 2013.dd"
        return TagsUtils.getDiffHtml(suggestion, text);
    }
    render () {
        let source = this.decodeTextAndTransormTags(this.props.segment.get("segment"));
        let target = this.decodeTextAndTransormTags(this.props.segment.get("translation"));

        let segmentBodyClass = classnames({
            "qr-segment-body": true,
            "qr-diff-on": (this.state.translateDiffOn || this.state.reviseDiffOn),
        });
        let suggestionClasses = classnames({
            "segment-container": true,
            "qr-suggestion": true,
            "shadow-1" : (this.state.translateDiffOn || this.state.reviseDiffOn)
        });
        let translateClasses = classnames({
            "segment-container": true,
            "qr-translated": true,
            "shadow-1" : (this.state.translateDiffOn )
        });
        let revisedClasses = classnames({
            "segment-container": true,
            "qr-revised": true,
            "shadow-1" : (this.state.reviseDiffOn)
        });
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
                        <div className="production time-edit">Time to edit: <b>{this.props.segment.get("time_to_edit")}"</b></div>
                        <div className="production pee">PEE: <b>30%</b></div>
                    </div>
                </div>
                <div className="segment-status-container">
                    <div className="qr-label">Segment status</div>
                    <div className={"qr-info status-" + this.props.segment.get("status").toLowerCase()}><b>{this.props.segment.get("status")}</b></div>
                </div>
            </div>


            <div className={segmentBodyClass}>

                    <div className="segment-container qr-source">
                        <div className="segment-content qr-segment-title">
                            <b>Source</b>
                        </div>
                        <div className="segment-content qr-text" dangerouslySetInnerHTML={ this.allowHTML(source) }>
                            {/*Hi!*/}
                            {/*<span className="qr-tags start-tag">&lt;g id="1"&gt;</span> my name is <span className="qr-tags end-tag">&lt;/g&gt;</span>*/}
                            {/*Rubén Santillàn and I'm a*/}
                            {/*<span className="qr-tags start-tag violet-tag">&lt;g id="2"&gt;</span>Designer<span className="qr-tags end-tag violet-tag">&lt;/g&gt;</span>*/}
                        </div>
                        <div className="segment-content qr-spec">
                            <div>Words:</div>
                            <div><b>{parseInt(this.props.segment.get("raw_word_count"))}</b></div>
                        </div>
                    </div>

                    <div className={suggestionClasses}>
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

                    <div className={translateClasses}>
                        <div className="segment-content qr-segment-title">
                            <b>Translate</b>
                            <button onClick={this.showTranslateDiff.bind(this)}>
                                <i className="icon-eye2 icon" />
                            </button>
                        </div>
                        <div className="segment-content qr-text" dangerouslySetInnerHTML={ this.allowHTML(target) }>
                            {/*Hi! <span className="qr-diff clear"> my name</span> <span className="qr-diff add"> is Rubén</span> Santillàn*/}
                        </div>
                        <div className="segment-content qr-spec">
                            <div><b>ICE Match</b></div>
                            <div>(Modified)</div>
                        </div>
                    </div>

                    <div className={revisedClasses}>
                        <div className="segment-content qr-segment-title">
                            <b>Revised</b>
                            <button onClick={this.showReviseDiff.bind(this)}>
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