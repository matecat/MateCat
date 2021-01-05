import TextUtils from '../../utils/textUtils';
import TagUtils from '../../utils/tagUtils';
import classnames from 'classnames';
import SegmentQRLine from './SegmentQRLine';
import SegmentQRIssue from './SegmentQRIssue';

class SegmentQR extends React.Component {
    constructor(props) {
        super(props);
        this.source = this.props.segment.get('segment');
        this.suggestion = this.props.segment.get('suggestion');
        this.target =
            !_.isNull(this.props.segment.get('last_translation')) && this.props.segment.get('last_translation');
        this.revise =
            !_.isNull(this.props.segment.get('last_revisions')) &&
            this.props.segment.get('last_revisions').find((value) => {
                return value.get('revision_number') === 1;
            });
        this.revise2 =
            !_.isNull(this.props.segment.get('last_revisions')) &&
            this.props.segment.get('last_revisions').find((value) => {
                return value.get('revision_number') === 2;
            });

        this.revise = this.revise && this.revise.size > 0 ? this.revise.get('translation') : false;
        this.revise2 = this.revise2 && this.revise2.size > 0 ? this.revise2.get('translation') : false;
        //If second pass separate the issues
        if (this.props.secondPassReviewEnabled) {
            this.issuesR1 = this.props.segment.get('issues').filter((value) => {
                return value.get('revision_number') === 1;
            });
            this.issuesR2 = this.props.segment.get('issues').filter((value) => {
                return value.get('revision_number') === 2;
            });
        }

        this.state = {
            translateDiffOn:
                !_.isNull(this.props.segment.get('last_translation')) &&
                _.isNull(this.props.segment.get('last_revisions')),
            reviseDiffOn:
                !_.isNull(this.props.segment.get('last_revisions')) &&
                this.revise &&
                !this.revise2 &&
                !_.isNull(this.props.segment.get('last_translation')),
            revise2DiffOn:
                !_.isNull(this.props.segment.get('last_revisions')) &&
                this.revise2 &&
                (this.revise || !_.isNull(this.props.segment.get('last_translation'))),
            htmlDiff: '',
            automatedQaOpen:
                this.props.segment.get('issues').size === 0 && this.props.segment.get('warnings').get('total') > 0,
            humanQaOpen: !this.props.secondPassReviewEnabled && this.props.segment.get('issues').size > 0,
            r1QaOpen: this.props.secondPassReviewEnabled && this.issuesR1 && this.issuesR1.size > 0,
            r2QaOpen:
                this.props.secondPassReviewEnabled &&
                this.issuesR2 &&
                this.issuesR2.size > 0 &&
                (_.isUndefined(this.issuesR1) || this.issuesR1.size === 0),
        };
        this.state.htmlDiff = this.initializeDiff();
        this.errorObj = {
            types: {
                TAGS: {
                    label: 'Tag mismatch',
                },
                MISMATCH: {
                    label: 'Character mismatch',
                },
            },
            icons: {
                ERROR: 'icon-cancel-circle icon red',
                WARNING: 'icon-warning2 icon orange',
                INFO: 'icon-info icon',
            },
        };
    }
    initializeDiff() {
        if (this.state.translateDiffOn) {
            return this.getDiffPatch(this.suggestion, this.target);
        } else if (this.state.reviseDiffOn) {
            let revise = this.revise;
            return this.getDiffPatch(this.target, revise);
        } else if (this.state.revise2DiffOn) {
            let source = this.revise ? this.revise : this.target;
            return this.getDiffPatch(source, this.revise2);
        }
    }
    openAutomatedQa() {
        this.setState({
            automatedQaOpen: true,
            humanQaOpen: false,
            r1QaOpen: false,
            r2QaOpen: false,
        });
    }
    openHumandQa() {
        this.setState({
            automatedQaOpen: false,
            humanQaOpen: true,
        });
    }
    openR1Qa() {
        this.setState({
            automatedQaOpen: false,
            r1QaOpen: true,
            r2QaOpen: false,
        });
    }
    openR2Qa() {
        this.setState({
            automatedQaOpen: false,
            r1QaOpen: false,
            r2QaOpen: true,
        });
    }
    getAutomatedQaHtml() {
        let html = [];
        let fnMap = (key, obj, type) => {
            let item = (
                <div className="qr-issue automated" key={key + type}>
                    <div className="box-icon">
                        <i className={this.errorObj.icons[type]} />
                    </div>
                    <div className="qr-error">
                        {this.errorObj.types[key].label} <b>({obj.size})</b>
                    </div>
                </div>
            );
            html.push(item);
        };
        let details = this.props.segment.get('warnings').get('details').get('issues_info');
        if (details.get('ERROR').get('Categories').size > 0) {
            details
                .get('ERROR')
                .get('Categories')
                .entrySeq()
                .forEach((item) => {
                    let key = item[0];
                    let value = item[1];
                    fnMap(key, value, 'ERROR');
                });
        }
        if (details.get('WARNING').get('Categories').size > 0) {
            details
                .get('WARNING')
                .get('Categories')
                .entrySeq()
                .forEach((item) => {
                    let key = item[0];
                    let value = item[1];
                    fnMap(key, value, 'WARNING');
                });
        }
        if (details.get('INFO').get('Categories').size > 0) {
            details
                .get('INFO')
                .get('Categories')
                .entrySeq()
                .forEach((item) => {
                    let key = item[0];
                    let value = item[1];
                    fnMap(key, value, 'INFO');
                });
        }

        return html;
    }
    getHumanQaHtml(issues) {
        let html = [];
        issues.map((issue, index) => {
            let item = <SegmentQRIssue key={index} index={index} issue={issue} />;
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
            let diffHtml = this.getDiffPatch(this.suggestion, this.target);
            this.setState({
                translateDiffOn: true,
                reviseDiffOn: false,
                revise2DiffOn: false,
                htmlDiff: diffHtml,
            });
        }
    }
    showReviseDiff() {
        if (this.state.reviseDiffOn) {
            this.setState({
                reviseDiffOn: false,
            });
        } else {
            let revise = this.revise;
            let textToDiff = this.target ? this.target : this.suggestion;
            let diffHtml = this.getDiffPatch(textToDiff, revise);
            this.setState({
                translateDiffOn: false,
                reviseDiffOn: true,
                revise2DiffOn: false,
                htmlDiff: diffHtml,
            });
        }
    }
    showRevise2Diff() {
        if (this.state.revise2DiffOn) {
            this.setState({
                revise2DiffOn: false,
            });
        } else {
            let revise2 = this.revise2;
            let textToDiff = this.revise ? this.revise : this.target ? this.target : this.suggestion;
            let diffHtml = this.getDiffPatch(textToDiff, revise2);
            this.setState({
                translateDiffOn: false,
                reviseDiffOn: false,
                revise2DiffOn: true,
                htmlDiff: diffHtml,
            });
        }
    }
    getWordsSpeed() {
        let str_pad_left = function (string, pad, length) {
            return (new Array(length + 1).join(pad) + string).slice(-length);
        };
        let time = parseInt(this.props.segment.get('secs_per_word'));
        let minutes = Math.floor(time / 60);
        let seconds = time - minutes * 60;
        if (minutes > 0) {
            return str_pad_left(minutes, '0', 2) + "'" + str_pad_left(seconds, '0', 2) + "''";
        } else {
            return str_pad_left(seconds, '0', 2) + "''";
        }
    }
    // getTimeToEdit() {
    //     let str_pad_left = function(string,pad,length) {
    //         return (new Array(length+1).join(pad)+string).slice(-length);
    //     };
    //     let time = parseInt(this.props.segment.get("time_to_edit")/1000);
    //     let hours = Math.floor(time / 3600);
    //     let minutes = Math.floor( time / 60);
    //     let seconds = parseInt(time - minutes * 60);
    //     if (hours > 0 ) {
    //         return str_pad_left(hours,'0',2)+''+str_pad_left(minutes,'0',2)+"'"+str_pad_left(seconds,'0',2)+"''";
    //     } else if (minutes > 0) {
    //         return str_pad_left(minutes,'0',2)+"'"+str_pad_left(seconds,'0',2)+"''";
    //     } else {
    //         return str_pad_left(seconds,'0',2)+"''";
    //     }
    //
    // }
    getDiffPatch(source, text) {
        return TextUtils.getDiffHtml(source, text);
    }
    openTranslateLink() {
        window.open(this.props.urls.get('translate_url') + '#' + this.props.segment.get('sid'));
    }

    openReviseLink(revise) {
        if (
            typeof this.props.urls.get('revise_url') === 'string' ||
            this.props.urls.get('revise_url') instanceof String
        ) {
            window.open(this.props.urls.get('revise_url') + '#' + this.props.segment.get('sid'));
        } else {
            let url = this.props.urls
                .get('revise_urls')
                .find((value) => {
                    return value.get('revision_number') === revise;
                })
                .get('url');
            window.open(url + '#' + this.props.segment.get('sid'));
        }
    }

    decodeTextAndTransformTags(text) {
        if (text) {
            let decodedText = TagUtils.matchTag(TagUtils.decodeHtmlInTag(TagUtils.decodePlaceholdersToTextSimple(text)));
            return decodedText;
        }
        return text;
    }
    allowHTML(string) {
        return { __html: string };
    }
    render() {
        let source = this.decodeTextAndTransformTags(this.source);
        let suggestion = this.decodeTextAndTransformTags(this.suggestion);
        let target = this.target && this.decodeTextAndTransformTags(this.target);
        let revise = this.revise && this.decodeTextAndTransformTags(this.revise);
        let revise2 = this.revise2 && this.decodeTextAndTransformTags(this.revise2);

        if (this.state.translateDiffOn) {
            target = this.decodeTextAndTransformTags(this.state.htmlDiff);
        }

        if (this.state.reviseDiffOn) {
            revise = this.decodeTextAndTransformTags(this.state.htmlDiff);
        }

        if (this.state.revise2DiffOn) {
            revise2 = this.decodeTextAndTransformTags(this.state.htmlDiff);
        }

        let sourceClass = classnames({
            'segment-container': true,
            'qr-source': true,
            'rtl-lang': config.source_rtl,
        });

        let segmentBodyClass = classnames({
            'qr-segment-body': true,
            'qr-diff-on': this.state.translateDiffOn || this.state.reviseDiffOn || this.state.revise2DiffOn,
        });
        let suggestionClasses = classnames({
            'segment-container': true,
            'qr-suggestion': true,
            'shadow-1':
                this.state.translateDiffOn ||
                (this.state.reviseDiffOn && !this.target) ||
                (this.state.revise2DiffOn && !this.revise && !this.target),
            'rtl-lang': config.target_rtl,
        });
        let translateClasses = classnames({
            'segment-container': true,
            'qr-translated': true,
            'shadow-1':
                this.state.translateDiffOn || this.state.reviseDiffOn || (this.state.revise2DiffOn && !this.revise),
            'rtl-lang': config.target_rtl,
        });
        let revisedClasses = classnames({
            'segment-container': true,
            'qr-revised': true,
            'shadow-1': this.state.reviseDiffOn || this.state.revise2DiffOn,
            'rtl-lang': config.target_rtl,
        });
        let revised2Classes = classnames({
            'segment-container': true,
            'qr-revised': true,
            'qr-revised-2ndpass': true,
            'shadow-1': this.state.revise2DiffOn,
            'rtl-lang': config.target_rtl,
        });
        return (
            <div className="qr-single-segment">
                <div className="qr-segment-head shadow-1">
                    <div className="segment-id">{this.props.segment.get('sid')}</div>
                    <div className="segment-production-container">
                        <div className="segment-production">
                            <div className="production word-speed">
                                Secs/Word: <b>{this.getWordsSpeed()}</b>
                            </div>
                            {/*<div className="production time-edit">Time to edit: <b>{this.getTimeToEdit()}</b></div>*/}
                            <div className="production pee">
                                PEE: <b>{this.props.segment.get('pee')}%</b>
                            </div>
                        </div>
                    </div>
                    <div className="segment-status-container">
                        <div className="qr-label">Segment status</div>
                        <div
                            className={classnames(
                                'qr-info',
                                'status-' + this.props.segment.get('status').toLowerCase(),
                                this.props.secondPassReviewEnabled &&
                                    this.props.segment.get('revision_number') &&
                                    'approved-r' + this.props.segment.get('revision_number')
                            )}
                        >
                            <b>{this.props.segment.get('status')}</b>
                        </div>
                    </div>
                </div>

                <div className={segmentBodyClass}>
                    <SegmentQRLine
                        segment={this.props.segment}
                        classes={sourceClass}
                        label={'Source'}
                        text={source}
                        showSegmentWords={true}
                    />
                    <SegmentQRLine
                        segment={this.props.segment}
                        classes={suggestionClasses}
                        label={'Suggestion'}
                        showSuggestionSource={true}
                        text={suggestion}
                    />

                    {this.props.segment.get('last_translation') ? (
                        <SegmentQRLine
                            segment={this.props.segment}
                            classes={translateClasses}
                            label={'Translation'}
                            onClickLabel={this.openTranslateLink.bind(this)}
                            text={target}
                            showDiffButton={true}
                            onClickDiff={this.showTranslateDiff.bind(this)}
                            diffActive={this.state.translateDiffOn}
                            showIceMatchInfo={true}
                            tte={this.props.segment.get('time_to_edit_translation')}
                            showIsPretranslated={this.props.segment.get('is_pre_translated')}
                        />
                    ) : null}
                    {!_.isNull(this.props.segment.get('last_revisions')) && revise ? (
                        <SegmentQRLine
                            segment={this.props.segment}
                            classes={revisedClasses}
                            label={'Revision'}
                            onClickLabel={this.openReviseLink.bind(this, 1)}
                            text={revise}
                            showDiffButton={true}
                            onClickDiff={this.showReviseDiff.bind(this)}
                            diffActive={this.state.reviseDiffOn}
                            showIceMatchInfo={_.isNull(target)}
                            tte={this.props.segment.get('time_to_edit_revise')}
                        />
                    ) : null}
                    {!_.isNull(this.props.segment.get('last_revisions')) && revise2 ? (
                        <SegmentQRLine
                            segment={this.props.segment}
                            classes={revised2Classes}
                            label={'2nd Revision'}
                            onClickLabel={this.openReviseLink.bind(this, 2)}
                            text={revise2}
                            showDiffButton={true}
                            onClickDiff={this.showRevise2Diff.bind(this)}
                            diffActive={this.state.revise2DiffOn}
                            showIceMatchInfo={_.isNull(target) && _.isNull(revise)}
                            tte={this.props.segment.get('time_to_edit_revise_2')}
                        />
                    ) : null}
                    {this.state.automatedQaOpen ||
                    this.state.humanQaOpen ||
                    this.state.r1QaOpen ||
                    this.state.r2QaOpen ? (
                        <div className="segment-container qr-issues">
                            <div className="segment-content qr-segment-title">
                                <b>QA</b>
                                <div className="ui basic mini buttons segment-production">
                                    {this.props.segment.get('issues').size > 0 &&
                                    !this.props.secondPassReviewEnabled ? (
                                        <div
                                            className={
                                                'ui button human-qa ' +
                                                (this.state.humanQaOpen ? 'active' : '') +
                                                ' ' +
                                                (this.props.segment.get('warnings').get('total') > 0 ? '' : 'no-hover')
                                            }
                                            onClick={this.openHumandQa.bind(this)}
                                        >
                                            Human<b> ({this.props.segment.get('issues').size})</b>
                                        </div>
                                    ) : null}

                                    {this.issuesR1 && this.issuesR1.size > 0 && this.props.secondPassReviewEnabled ? (
                                        <div
                                            className={'ui button human-qa ' + (this.state.r1QaOpen ? 'active' : '')}
                                            style={{ padding: '8px' }}
                                            onClick={this.openR1Qa.bind(this)}
                                        >
                                            R1<b> ({this.issuesR1.size})</b>
                                        </div>
                                    ) : null}

                                    {this.issuesR2 && this.issuesR2.size > 0 && this.props.secondPassReviewEnabled ? (
                                        <div
                                            className={'ui button human-qa ' + (this.state.r2QaOpen ? 'active' : '')}
                                            style={{ padding: '8px' }}
                                            onClick={this.openR2Qa.bind(this)}
                                        >
                                            R2<b> ({this.issuesR2.size})</b>
                                        </div>
                                    ) : null}

                                    {this.props.segment.get('warnings').get('total') > 0 ? (
                                        <div
                                            className={
                                                'ui button automated-qa ' +
                                                (this.state.automatedQaOpen ? 'active' : '') +
                                                ' ' +
                                                (this.props.segment.get('issues').size > 0 ? '' : 'no-hover')
                                            }
                                            onClick={this.openAutomatedQa.bind(this)}
                                        >
                                            Automated<b> ({this.props.segment.get('warnings').get('total')})</b>
                                        </div>
                                    ) : null}
                                </div>
                            </div>
                            <div
                                className="segment-content qr-text"
                                ref={(issueContainer) => (this.issuesContainer = issueContainer)}
                            >
                                {this.state.automatedQaOpen ? (
                                    <div className="qr-issues-list" key={'automated-qa'}>
                                        {this.getAutomatedQaHtml()}
                                    </div>
                                ) : null}

                                {this.state.humanQaOpen ? (
                                    <div className="qr-issues-list" key={'human-qa'}>
                                        {this.getHumanQaHtml(this.props.segment.get('issues'))}
                                    </div>
                                ) : null}

                                {this.state.r1QaOpen ? (
                                    <div className="qr-issues-list" key={'human-qa'}>
                                        {this.getHumanQaHtml(this.issuesR1)}
                                    </div>
                                ) : null}
                                {this.state.r2QaOpen ? (
                                    <div className="qr-issues-list" key={'human-qa'}>
                                        {this.getHumanQaHtml(this.issuesR2)}
                                    </div>
                                ) : null}
                            </div>
                        </div>
                    ) : null}
                </div>
            </div>
        );
    }
}

export default SegmentQR;
