import TagsUtils from "../../utils/textUtils"
import classnames from "classnames";
class SegmentQR extends React.Component {
    constructor(props) {
        super(props);
        this.state = {
            translateDiffOn: false,
            reviseDiffOn: false,
            htmlDiff: "",
            automatedQaOpen: false,
            humanQaOpen:false
        };
    }
    openAutomatedQa() {
        this.setState({
            automatedQaOpen: !this.state.automatedQaOpen
        });
    }
    openHumandQa() {
        this.setState({
            humanQaOpen: !this.state.humanQaOpen
        });
    }
    showTranslateDiff() {
        if (this.state.translateDiffOn) {
            this.setState({
                translateDiffOn: false,
            });
        } else {
            let suggestion = "&lt;g id=\"3521\"&gt; " + this.props.segment.get("translation") + "&lt;g id=\"3521\"&gt;";
            let diffHtml = this.getDiffPatch(suggestion, this.props.segment.get("translation"));
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
            let revise = "Text added " + this.props.segment.get("translation") + " Text added";
            let diffHtml = this.getDiffPatch(this.props.segment.get("translation"), revise);
            this.setState({
                translateDiffOn: false,
                reviseDiffOn: true,
                htmlDiff: diffHtml
            });
        }
    }
    getDiffPatch(source, text) {
        return TagsUtils.getDiffHtml(source, text);
    }
    openTranslateLink() {
        window.open("/translate/project_name/langs/" + config.id_job + "-" + config.password + "#" + this.props.segment.get("sid"))
    }

    openReviseLink() {
        window.open("/revise/project_name/langs/" + config.id_job + "-" + config.password + "#" + this.props.segment.get("sid"))
    }
    decodeTextAndTransformTags( text) {
         let decodedText = TagsUtils.decodePlaceholdersToText(text);
         decodedText = TagsUtils.transformTextForLockTags(decodedText);
         return decodedText;
    }
    allowHTML(string) {
        return { __html: string };
    }
    render () {
        let source = this.decodeTextAndTransformTags(this.props.segment.get("segment"));
        let suggestion = this.decodeTextAndTransformTags("&lt;g id=\"3521\"&gt; " + this.props.segment.get("translation") + "&lt;g id=\"3521\"&gt;");
        let target = this.decodeTextAndTransformTags(this.props.segment.get("translation"));
        let revise = "Text added " + this.props.segment.get("translation") + " Text added";

        if (this.state.translateDiffOn) {
            target = this.decodeTextAndTransformTags(this.state.htmlDiff);
        }

        if (this.state.reviseDiffOn) {
            revise = this.decodeTextAndTransformTags(this.state.htmlDiff);
        }

        let sourceClass = classnames({
            "segment-container": true,
            "qr-source": true,
            "rtl-lang" : config.source_rtl
        });

        let segmentBodyClass = classnames({
            "qr-segment-body": true,
            "qr-diff-on": (this.state.translateDiffOn || this.state.reviseDiffOn),
        });
        let suggestionClasses = classnames({
            "segment-container": true,
            "qr-suggestion": true,
            "shadow-1" : (this.state.translateDiffOn),
            "rtl-lang" : config.target_rtl
        });
        let translateClasses = classnames({
            "segment-container": true,
            "qr-translated": true,
            "shadow-1" : (this.state.translateDiffOn || this.state.reviseDiffOn ),
            "rtl-lang" : config.target_rtl
        });
        let revisedClasses = classnames({
            "segment-container": true,
            "qr-revised": true,
            "shadow-1" : (this.state.reviseDiffOn),
            "rtl-lang" : config.target_rtl
        });
        return <div className="qr-single-segment">

            <div className="qr-segment-head shadow-1">
                <div className="segment-id">{this.props.segment.get("sid")}</div>
                <div className="segment-production-container">
                    <div className="segment-production">
                        <div className={"ui basic button tiny automated-qa " + (this.state.automatedQaOpen ? "active" : "")} onClick={this.openAutomatedQa.bind(this)}>
                            Automated QA<b> (7)</b></div>
                        <div className={"ui basic button tiny human-qa " + (this.state.humanQaOpen ? "active" : "")} onClick={this.openHumandQa.bind(this)}>
                            Human QA<b> (7)</b></div>
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
                        <div className="segment-content qr-text" dangerouslySetInnerHTML={ this.allowHTML(source) }/>
                        <div className="segment-content qr-spec">
                            <div>Words:</div>
                            <div><b>{parseInt(this.props.segment.get("raw_word_count"))}</b></div>
                        </div>
                    </div>

                    <div className={suggestionClasses}>
                        <div className="segment-content qr-segment-title">
                            <b>Suggestion</b>
                        </div>
                        <div className="segment-content qr-text" dangerouslySetInnerHTML={ this.allowHTML(suggestion) }/>
                        <div className="segment-content qr-spec">
                            <div>Public <b>TM</b></div>
                            <div className="tm-percent">101%</div>
                        </div>
                    </div>

                    <div className={translateClasses}>
                        <div className="segment-content qr-segment-title">
                            <b onClick={this.openTranslateLink.bind(this)}>Translate</b>
                            <button className={(this.state.translateDiffOn ? "active" : "")} onClick={this.showTranslateDiff.bind(this)}  title="Show Diff">
                                <i className="icon-eye2 icon" />
                            </button>
                        </div>
                        <div className="segment-content qr-text" dangerouslySetInnerHTML={ this.allowHTML(target) }/>
                        <div className="segment-content qr-spec">
                            <div><b>ICE Match</b></div>
                            <div>(Modified)</div>
                        </div>
                    </div>

                    <div className={revisedClasses}>
                        <div className="segment-content qr-segment-title">
                            <b onClick={this.openReviseLink.bind(this)}>Revised</b>
                            <button className={(this.state.reviseDiffOn ? "active" : "")} onClick={this.showReviseDiff.bind(this)} title="Show Diff">
                                <i className="icon-eye2 icon" />
                            </button>
                        </div>
                        <div className="segment-content qr-text" dangerouslySetInnerHTML={ this.allowHTML(revise) } >
                        </div>
                        <div className="segment-content qr-spec">

                        </div>
                    </div>

                {this.state.automatedQaOpen ?
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
                     : (null)}

                {this.state.humanQaOpen ?
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
                    : (null) }
            </div>
        </div>
    }
}

export default SegmentQR ;