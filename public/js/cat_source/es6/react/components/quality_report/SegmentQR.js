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
        this.errorObj = {
            'types' : {
                'TAGS': {
                    label: 'Tag mismatch'
                },
                'MISMATCH': {
                    label: 'Tag mismatch'
                }
            },
            'icons' : {
                'ERROR' : 'icon-cancel-circle icon red',
                'WARNING' : 'icon-warning2 icon orange',
                'INFO' : ''
            }
        }

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
    getAutomatedQaHtml() {
        let html = [];
        let fnMap = (key, obj, type) => {
            let item = <div className="qr-issue automated" key={key+type}>
                            <div className="box-icon">
                                <i className={this.errorObj.icons[type]} />
                            </div>
                            <div className="qr-error">{this.errorObj.types[key].label} <b>({obj.size})</b></div>
                        </div>;
            html.push(item);
        };
        let details = this.props.segment.get('warnings').get('details').get('issues_info');
        if (details.get('ERROR').get('Categories').size > 0) {
            details.get('ERROR').get('Categories').entrySeq().forEach( (item)=> {
                let key = item[0];
                let value = item[1];
                fnMap(key, value, 'ERROR');
            });
        }
        if (details.get('WARNING').get('Categories').size > 0) {
            details.get('WARNING').get('Categories').entrySeq().forEach( (item)=> {
                let key = item[0];
                let value = item[1];
                fnMap(key, value, 'WARNING');
            });
        }
        if (details.get('INFO').get('Categories').size > 0) {
            details.get('INFO').get('Categories').entrySeq().forEach( (item)=> {
                let key = item[0];
                let value = item[1];
                fnMap(key, value, 'INFO');
            });
        }

        return html;
    }
    getHumanQaHtml() {
        let html = [];
        let issues = this.props.segment.get('issues');
        issues.map((issue)=>{
            let item = <div className="qr-issue human critical" key={issue.get('issue_id')}>
                            <div className="qr-error"><b>{issue.get('issue_category')}</b></div>
                            <div className="sub-type-error">Subtype </div>
                            <div className="severity"><b>{issue.get('issue_severity')}</b></div>
                        </div>;
            html.push(item);
        });

        return html;
    }
    showTranslateDiff() {
        if (this.state.translateDiffOn) {
            this.setState({
                translateDiffOn: false,
            });
        } else {
            let diffHtml = this.getDiffPatch(TagsUtils.htmlEncode(this.props.segment.get("suggestion")), TagsUtils.htmlEncode(this.props.segment.get("last_translation")));
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
            let revise = this.props.segment.get("last_revision");
            let diffHtml = this.getDiffPatch(TagsUtils.htmlEncode(this.props.segment.get("last_translation")), revise);
            this.setState({
                translateDiffOn: false,
                reviseDiffOn: true,
                htmlDiff: diffHtml
            });
        }
    }
    getWordsSpeed() {
        let str_pad_left = function(string,pad,length) {
            return (new Array(length+1).join(pad)+string).slice(-length);
        }
        let time = parseInt(this.props.segment.get("secs_per_word"));
        let minutes = Math.floor( time / 60);
        let seconds = time - minutes * 60;
        return str_pad_left(minutes,'0',2)+':'+str_pad_left(seconds,'0',2);
    }
    getTimeToEdit() {
        let str_pad_left = function(string,pad,length) {
            return (new Array(length+1).join(pad)+string).slice(-length);
        }
        let time = parseInt(this.props.segment.get("time_to_edit")/1000);
        let hours = Math.floor(time / 3600);
        let minutes = Math.floor( time / 60);
        let seconds = parseInt(time - minutes * 60);
        return str_pad_left(hours,'0',2)+':'+str_pad_left(minutes,'0',2)+':'+str_pad_left(seconds,'0',2);
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
        if (text) {
            let decodedText = TagsUtils.decodePlaceholdersToText(text);
            decodedText = TagsUtils.transformTextForLockTags(decodedText);
            return decodedText;
        }
        return text;
    }
    allowHTML(string) {
        return { __html: string };
    }
    render () {
        let source = this.decodeTextAndTransformTags(this.props.segment.get("segment"));
        let suggestion = this.decodeTextAndTransformTags(TagsUtils.htmlEncode(this.props.segment.get("suggestion")));
        let target = this.decodeTextAndTransformTags(TagsUtils.htmlEncode(this.props.segment.get("last_translation")));
        let revise = this.decodeTextAndTransformTags(this.props.segment.get("last_revision"));

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

                        {this.props.segment.get('warnings').get('total') > 0 ? (
                            <div className={"ui basic button tiny automated-qa " + (this.state.automatedQaOpen ? "active" : "")} onClick={this.openAutomatedQa.bind(this)}>
                                Automated QA<b> ({this.props.segment.get('warnings').get('total')})</b></div>
                        ) : null}

                        {this.props.segment.get('issues').size > 0 ? (
                            <div className={"ui basic button tiny human-qa " + (this.state.humanQaOpen ? "active" : "")} onClick={this.openHumandQa.bind(this)}>
                                Human QA<b> ({this.props.segment.get('issues').size})</b></div>
                        ) : null}

                    </div>
                    <div className="segment-production">
                        <div className="production word-speed">Words speed: <b>{this.getWordsSpeed()}</b></div>
                        <div className="production time-edit">Time to edit: <b>{this.getTimeToEdit()}</b></div>
                        <div className="production pee">PEE: <b>{this.props.segment.get("pee")}%</b></div>
                    </div>
                </div>
                <div className="segment-status-container">
                    <div className="qr-label">Segment status</div>
                    <div className={"qr-info status-" + this.props.segment.get("status").toLowerCase()}><b>{this.props.segment.get("status")}</b></div>
                </div>
            </div>


            <div className={segmentBodyClass}>

                    <div className={sourceClass}>
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
                            <div><b>{this.props.segment.get("suggestion_source")}</b></div>
                            <div className="tm-percent">{this.props.segment.get("suggestion_match")}%</div>
                        </div>
                    </div>
                {this.props.segment.get('last_translation') ? (
                    <div className={translateClasses}>
                        <a className="segment-content qr-segment-title">
                            <b onClick={this.openTranslateLink.bind(this)}>Translate</b>
                            <button className={(this.state.translateDiffOn ? "active" : "")} onClick={this.showTranslateDiff.bind(this)}  title="Show Diff">
                                <i className="icon-eye2 icon" />
                            </button>
                        </a>
                        <div className="segment-content qr-text" dangerouslySetInnerHTML={ this.allowHTML(target) }/>

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
                    </div>
                ) : null}
                {this.props.segment.get('last_revision') ? (
                    <div className={revisedClasses}>
                        <a className="segment-content qr-segment-title">
                            <b onClick={this.openReviseLink.bind(this)}>Revised</b>
                            <button className={(this.state.reviseDiffOn ? "active" : "")} onClick={this.showReviseDiff.bind(this)} title="Show Diff">
                                <i className="icon-eye2 icon" />
                            </button>
                        </a>
                        <div className="segment-content qr-text" dangerouslySetInnerHTML={ this.allowHTML(revise) } >
                        </div>
                        <div className="segment-content qr-spec">
                            { (this.props.segment.get('ice_locked') === '1' && !this.props.segment.get('ice_modified')) ? (
                                <div>
                                    <b>ICE Match</b>
                                </div>
                            ) :  null}
                        </div>
                    </div>
                ) : null}


                {this.state.automatedQaOpen ?
                    <div className="segment-container qr-issues">
                        <div className="segment-content qr-segment-title">
                            <b>Atomated QA</b>
                        </div>
                        <div className="segment-content qr-text">

                            <div className="qr-issues-list">
                                {this.getAutomatedQaHtml()}
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
                                {this.getHumanQaHtml()}
                            </div>
                        </div>
                    </div>
                    : (null) }
            </div>
        </div>
    }
}

export default SegmentQR ;