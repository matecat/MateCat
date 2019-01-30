import TagsUtils from "../../utils/textUtils"
import classnames from "classnames";
class SegmentQR extends React.Component {
    constructor(props) {
        super(props);
        this.state = {
            translateDiffOn: !_.isNull(this.props.segment.get('last_translation')) && _.isNull(this.props.segment.get('last_revision')),
            reviseDiffOn: !_.isNull(this.props.segment.get('last_revision')) && !_.isNull(this.props.segment.get('last_translation')),
            htmlDiff: "",
            automatedQaOpen: this.props.segment.get('issues').size === 0 && this.props.segment.get('warnings').get('total') > 0 ,
            humanQaOpen: this.props.segment.get('issues').size > 0
        };
        this.state.htmlDiff = this.initializeDiff();
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
    initializeDiff() {
        if ( this.state.translateDiffOn ) {
            return this.getDiffPatch(this.props.segment.get("suggestion"), this.props.segment.get("last_translation"));
        } else if ( this.state.reviseDiffOn ){
            let revise = this.props.segment.get("last_revision");
            return this.getDiffPatch(this.props.segment.get("last_translation"), revise);
        }
    }
    openAutomatedQa() {
        this.setState({
            automatedQaOpen: true,
            humanQaOpen: false
        });
    }
    openHumandQa() {
        this.setState({
            automatedQaOpen: false,
            humanQaOpen: true
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
        issues.map((issue, index)=>{
            let item = <div className="qr-issue human critical" key={issue.get('issue_id'+index)}>
                            <div className="qr-error">{issue.get('issue_category')}: </div>
                            {/*<div className="sub-type-error">Subtype </div>*/}
                            <div className="qr-severity"><b>[{issue.get('issue_severity')}]</b></div>
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
            let diffHtml = this.getDiffPatch(this.props.segment.get("suggestion"), this.props.segment.get("last_translation"));
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
            let diffHtml = this.getDiffPatch(this.props.segment.get("last_translation"), revise);
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
        };
        let time = parseInt(this.props.segment.get("secs_per_word"));
        let minutes = Math.floor( time / 60);
        let seconds = time - minutes * 60;
        if (minutes > 0) {
            return str_pad_left(minutes,'0',2)+"'"+str_pad_left(seconds,'0',2)+"''";
        } else {
            return str_pad_left(seconds,'0',2)+"''";
        }
    }
    getTimeToEdit() {
        let str_pad_left = function(string,pad,length) {
            return (new Array(length+1).join(pad)+string).slice(-length);
        }
        let time = parseInt(this.props.segment.get("time_to_edit")/1000);
        let hours = Math.floor(time / 3600);
        let minutes = Math.floor( time / 60);
        let seconds = parseInt(time - minutes * 60);
        if (hours > 0 ) {
            return str_pad_left(hours,'0',2)+''+str_pad_left(minutes,'0',2)+"'"+str_pad_left(seconds,'0',2)+"''";
        } else if (minutes > 0) {
            return str_pad_left(minutes,'0',2)+"'"+str_pad_left(seconds,'0',2)+"''";
        } else {
            return str_pad_left(seconds,'0',2)+"''";
        }

    }
    getDiffPatch(source, text) {
        return TagsUtils.getDiffHtml(source, text);
    }
    openTranslateLink() {
        window.open(this.props.urls.get("translate_url") + "#" + this.props.segment.get("sid"))
    }

    openReviseLink() {
        window.open(this.props.urls.get("revise_url") + "#" + this.props.segment.get("sid"))
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
        let suggestion = this.decodeTextAndTransformTags(this.props.segment.get("suggestion"));
        let target = this.decodeTextAndTransformTags(this.props.segment.get("last_translation"));
        let revise = this.decodeTextAndTransformTags(this.props.segment.get("last_revision"));
        let suggestionMatch = ( this.props.segment.get("match_type") === "ICE") ? 101 : parseInt(this.props.segment.get("suggestion_match"));
        let suggestionMatchClass = (suggestionMatch === 101)? 'per-blu': (suggestionMatch === 100)? 'per-green' : (suggestionMatch > 0 && suggestionMatch <=99)? 'per-orange' : '';
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
            "qr-segment-body shadow-1": true,
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
                        <div className="production word-speed">Secs/Word: <b>{this.getWordsSpeed()}</b></div>
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
                            <div className={ this.props.segment.get("suggestion_source") === "MT" ? ('per-yellow'): null}>
                                <b>{this.props.segment.get("suggestion_source")}</b>
                            </div>
                            {this.props.segment.get("suggestion_source") !== "MT" ? (
                                <div className={"tm-percent " + suggestionMatchClass}>{suggestionMatch}%</div>
                            ) : null}

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
                            { this.props.segment.get('ice_locked') === '0' || (this.props.segment.get('ice_locked') === '1' && this.props.segment.get('ice_modified')) ? (
                            <button className={(this.state.reviseDiffOn ? "active" : "")} onClick={this.showReviseDiff.bind(this)} title="Show Diff">
                                <i className="icon-eye2 icon" />
                            </button>
                            ) : null }
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

                {( this.state.automatedQaOpen || this.state.humanQaOpen ) ? (
                <div className="segment-container qr-issues">
                    <div className="segment-content qr-segment-title">
                        <b>QA</b>
                        <div className="ui basic mini buttons segment-production">

                            {this.props.segment.get('issues').size > 0 ? (
                                <div className={"ui button human-qa " + (this.state.humanQaOpen ? "active" : "") + " " + (this.props.segment.get('warnings').get('total') > 0 ? "" : "no-hover")}
                                     onClick={this.openHumandQa.bind(this)}>
                                    Human<b> ({this.props.segment.get('issues').size})</b></div>
                            ) : null}

                            {this.props.segment.get('warnings').get('total') > 0 ? (
                                <div className={"ui button automated-qa " + (this.state.automatedQaOpen ? "active" : "")+ " " + (this.props.segment.get('issues').size > 0 ? "" : "no-hover")}
                                     onClick={this.openAutomatedQa.bind(this)}>
                                    Automated<b> ({this.props.segment.get('warnings').get('total')})</b></div>
                            ) : null}

                        </div>
                    </div>
                    <div className="segment-content qr-text">

                        {this.state.automatedQaOpen ?

                            <div className="qr-issues-list">
                                {this.getAutomatedQaHtml()}
                            </div>

                            : (null)}

                        {this.state.humanQaOpen ?

                            <div className="qr-issues-list">
                                {this.getHumanQaHtml()}
                            </div>

                            : (null) }
                    </div>
                </div>
                ): null }
            </div>
        </div>
    }
}

export default SegmentQR ;