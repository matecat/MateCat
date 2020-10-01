/**
 * React Component .

 */
import React from 'react';
import EditArea from './Editarea';
import TagsMenu from './TagsMenu';
import TagUtils from '../../utils/tagUtils';
import CursorUtils from '../../utils/cursorUtils';
import Customizations from '../../utils/customizations';
import SegmentConstants from '../../constants/SegmentConstants';
import SegmentStore from '../../stores/SegmentStore';
import SegmentButtons from './SegmentButtons';
import SegmentWarnings from './SegmentWarnings';
import QaBlacklist from './utils/qaCheckBlacklistUtils';
import LXQ from '../../utils/lxq.main';
import SearchUtils from '../header/cattol/search/searchUtils';

class SegmentTarget extends React.Component {
    constructor(props) {
        super(props);
        this.state = {
            originalTranslation: this.props.segment.original_translation
                ? this.props.segment.original_translation
                : this.props.segment.translation,
            showTagsMenu: false,
        };
        this.replaceTranslation = this.replaceTranslation.bind(this);
        this.setOriginalTranslation = this.setOriginalTranslation.bind(this);
        this.beforeRenderActions = this.beforeRenderActions.bind(this);
        this.afterRenderActions = this.afterRenderActions.bind(this);
        this.showTagsMenu = this.showTagsMenu.bind(this);
        this.hideTagsMenu = this.hideTagsMenu.bind(this);
        this.autoFillTagsInTarget = this.autoFillTagsInTarget.bind(this);
        this.storeTranslation = this.storeTranslation.bind(this);
    }

    replaceTranslation(sid, translation) {
        if (this.props.segment.sid == sid) {
            this.setState({
                translation: translation,
            });
        }
    }

    showTagsMenu(sid) {
        if (this.props.segment.sid == sid) {
            this.setState({
                showTagsMenu: true,
            });
        }
    }

    hideTagsMenu() {
        if (this.state.showTagsMenu) {
            this.setState({
                showTagsMenu: false,
            });
            //TODO Move it
            $('.tag-autocomplete-endcursor').remove();
        }
    }

    setOriginalTranslation(sid, translation) {
        if (this.props.segment.sid == sid) {
            this.setState({
                originalTranslation: translation,
            });
        }
    }

    beforeRenderActions() {
        this.props.beforeRenderOrUpdate(this.props.segment.translation);
    }

    afterRenderActions(prevProps) {
        let tagMismatchChanged =
            !_.isUndefined(prevProps) && prevProps.segImmutable.get('tagMismatch')
                ? !this.props.segImmutable.get('tagMismatch').equals(prevProps.segImmutable.get('tagMismatch'))
                : true;
        this.props.afterRenderOrUpdate(this.props.segment.translation, tagMismatchChanged);
    }

    onClickEvent(event) {
        if (this.props.readonly) {
            UI.handleClickOnReadOnly($(event.currentTarget).closest('section'));
        }
    }

    selectIssueText(event) {
        var selection = document.getSelection();
        var container = $(this.issuesHighlightArea).find('.errorTaggingArea');
        if (this.textSelectedInsideSelectionArea(selection, container)) {
            event.preventDefault();
            event.stopPropagation();
            selection = CursorUtils.getSelectionData(selection, container);
            SegmentActions.openIssuesPanel({ sid: this.props.segment.sid, selection: selection }, true);
            setTimeout(() => {
                SegmentActions.showIssuesMessage(this.props.segment.sid, 2);
            });
        } else {
            this.props.removeSelection();
            setTimeout(() => {
                SegmentActions.showIssuesMessage(this.props.segment.sid, 0);
            });
        }
    }

    textSelectedInsideSelectionArea(selection, container) {
        return (
            container.contents().text().indexOf(selection.focusNode.textContent) >= 0 &&
            container.contents().text().indexOf(selection.anchorNode.textContent) >= 0 &&
            selection.toString().length > 0
        );
    }

    lockEditArea(event) {
        event.preventDefault();
        if (!this.props.segment.edit_area_locked) {
            this.sendTranslationUpdate();
        } else {
            SegmentActions.showIssuesMessage(this.props.segment.sid, 0);
        }
        SegmentActions.lockEditArea(this.props.segment.sid, this.props.segment.fid);
    }

    allowHTML(string) {
        return { __html: string };
    }

    sendTranslationUpdate() {
        if (this.editArea && this.props.segment.modified) {
            let textToSend = this.editArea.editAreaRef.innerHTML;
            let sid = this.props.segment.sid;
            SegmentActions.replaceEditAreaTextContent(sid, null, textToSend);
        }
    }

    sendTranslationWithoutUpdate(force) {
        if (this.editArea && (this.props.segment.modified || force)) {
            let textToSend = this.editArea.editAreaRef.innerHTML;
            let sid = this.props.segment.sid;
            SegmentActions.updateTranslation(sid, textToSend);
        }
    }
    storeTranslation(sid) {
        if (sid === this.props.segment.sid) {
            this.sendTranslationWithoutUpdate();
        }
    }
    getAllIssues() {
        let issues = [];
        if (this.props.segment.versions) {
            this.props.segment.versions.forEach(function (version) {
                if (!_.isEmpty(version.issues)) {
                    issues = issues.concat(version.issues);
                }
            });
        }
        return issues;
    }

    formatSelection(action) {
        UI.formatSelection(action);
        SegmentActions.modifiedTranslation(this.props.segment.sid, null, true);
        this.sendTranslationWithoutUpdate();
    }

    getTargetArea(translation) {
        var textAreaContainer = '';
        let issues = this.getAllIssues();
        if (this.props.segment.edit_area_locked) {
            let currentTranslationVersion = TagUtils.decodeText(
                this.props.segment,
                this.props.segment.versions[0].translation
            );
            textAreaContainer = (
                <div className="segment-text-area-container" data-mount="segment_text_area_container">
                    <div
                        className="textarea-container"
                        onClick={this.onClickEvent.bind(this)}
                        onMouseUp={this.selectIssueText.bind(this)}
                        ref={(div) => (this.issuesHighlightArea = div)}
                    >
                        <div
                            className="targetarea issuesHighlightArea errorTaggingArea"
                            dangerouslySetInnerHTML={this.allowHTML(currentTranslationVersion)}
                        />
                    </div>
                    <div className="toolbar">
                        {config.isReview && ReviewExtended.enabled() ? (
                            <a
                                href="#"
                                className="revise-lock-editArea active"
                                onClick={this.lockEditArea.bind(this)}
                                title="Highlight text and assign an issue to the selected text."
                            />
                        ) : null}
                    </div>
                </div>
            );
        } else {
            var s2tMicro = '';
            var tagModeButton = '';
            var tagCopyButton = '';
            var tagLockCustomizable;
            if (this.props.segment.segment.match(/\&lt;.*?\&gt;/gi) && config.tagLockCustomizable) {
                tagLockCustomizable = UI.tagLockEnabled ? (
                    <a
                        className="tagLockCustomize icon-lock"
                        title="Toggle Tag Lock"
                        onClick={() => SegmentActions.disableTagLock()}
                    />
                ) : (
                    <a
                        className="tagLockCustomize icon-unlocked3"
                        title="Toggle Tag Lock"
                        onClick={() => SegmentActions.enableTagLock()}
                    />
                );
            }

            //Speeche2Text
            var s2t_enabled = this.props.speech2textEnabledFn();
            if (s2t_enabled) {
                s2tMicro = (
                    <div className="micSpeech" title="Activate voice input" data-segment-id="{{originalId}}">
                        <div className="micBg"></div>
                        <div className="micBg2">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                version="1.1"
                                width="20"
                                height="20"
                                viewBox="0 0 20 20"
                            >
                                <g
                                    className="svgMic"
                                    transform="matrix(0.05555509,0,0,0.05555509,-3.1790007,-3.1109739)"
                                    fill="#737373"
                                >
                                    <path d="m 290.991,240.991 c 0,26.392 -21.602,47.999 -48.002,47.999 l -11.529,0 c -26.4,0 -48.002,-21.607 -48.002,-47.999 l 0,-136.989 c 0,-26.4 21.602,-48.004 48.002,-48.004 l 11.529,0 c 26.4,0 48.002,21.604 48.002,48.004 l 0,136.989 z" />
                                    <path d="m 342.381,209.85 -8.961,0 c -4.932,0 -8.961,4.034 -8.961,8.961 l 0,8.008 c 0,50.26 -37.109,91.001 -87.361,91.001 -50.26,0 -87.109,-40.741 -87.109,-91.001 l 0,-8.008 c 0,-4.927 -4.029,-8.961 -8.961,-8.961 l -8.961,0 c -4.924,0 -8.961,4.034 -8.961,8.961 l 0,8.008 c 0,58.862 40.229,107.625 96.07,116.362 l 0,36.966 -34.412,0 c -4.932,0 -8.961,4.039 -8.961,8.971 l 0,17.922 c 0,4.923 4.029,8.961 8.961,8.961 l 104.688,0 c 4.926,0 8.961,-4.038 8.961,-8.961 l 0,-17.922 c 0,-4.932 -4.035,-8.971 -8.961,-8.971 l -34.43,0 0,-36.966 c 55.889,-8.729 96.32,-57.5 96.32,-116.362 l 0,-8.008 c 0,-4.927 -4.039,-8.961 -8.961,-8.961 z" />
                                </g>
                            </svg>
                        </div>
                    </div>
                );
            }

            //Tag Mode Buttons

            if (this.props.tagModesEnabled && !this.props.enableTagProjection && UI.tagLockEnabled) {
                var buttonClass = $('body').hasClass('tagmode-default-extended') ? 'active' : '';
                tagModeButton = (
                    <a
                        className={'tagModeToggle ' + buttonClass}
                        alt="Display full/short tags"
                        onClick={() => Customizations.toggleTagsMode()}
                        title="Display full/short tags"
                    >
                        <span className="icon-chevron-left" />
                        <span className="icon-tag-expand" />
                        <span className="icon-chevron-right" />
                    </a>
                );
            }
            if (this.props.tagModesEnabled && UI.tagLockEnabled) {
                tagCopyButton = (
                    <a
                        className="autofillTag"
                        alt="Copy missing tags from source to target"
                        title="Copy missing tags from source to target"
                        onClick={() => this.autoFillTagsInTarget()}
                    />
                );
            }

            //Text Area
            textAreaContainer = (
                <div className="textarea-container">
                    <EditArea
                        ref={(ref) => (this.editArea = ref)}
                        segment={this.props.segment}
                        translation={translation}
                        locked={this.props.locked}
                        readonly={this.props.readonly}
                        sendTranslationWithoutUpdate={this.sendTranslationWithoutUpdate.bind(this)}
                    />
                    {this.state.showTagsMenu ? (
                        <TagsMenu segment={this.props.segment} height={this.props.height} />
                    ) : null}
                    {s2tMicro}
                    <div
                        className="original-translation"
                        style={{ display: 'none' }}
                        dangerouslySetInnerHTML={this.allowHTML(this.state.originalTranslation)}
                    />
                    <div className="toolbar">
                        {config.isReview && ReviewExtended.enabled() ? (
                            <a
                                href="#"
                                className="revise-lock-editArea"
                                onClick={this.lockEditArea.bind(this)}
                                title="Highlight text and assign an issue to the selected text."
                            />
                        ) : null}
                        {ReviewExtended.enabled() && (issues.length > 0 || config.isReview) ? (
                            <a
                                className="revise-qr-link"
                                title="Segment Quality Report."
                                target="_blank"
                                href={
                                    '/revise-summary/' +
                                    config.id_job +
                                    '-' +
                                    config.password +
                                    '?revision_type=' +
                                    (config.revisionNumber ? config.revisionNumber : 1) +
                                    '&id_segment=' +
                                    this.props.segment.sid
                                }
                            >
                                QR
                            </a>
                        ) : null}
                        {tagLockCustomizable}
                        {tagModeButton}
                        {tagCopyButton}
                        <ul className="editToolbar">
                            <li
                                className="uppercase"
                                title="Uppercase"
                                onMouseDown={() => this.formatSelection('uppercase')}
                            />
                            <li
                                className="lowercase"
                                title="Lowercase"
                                onMouseDown={() => this.formatSelection('lowercase')}
                            />
                            <li
                                className="capitalize"
                                title="Capitalized"
                                onMouseDown={() => this.formatSelection('capitalize')}
                            />
                        </ul>
                    </div>
                </div>
            );
        }
        return textAreaContainer;
    }

    autoFillTagsInTarget(sid) {
        if (_.isUndefined(sid) || sid === this.props.segment.sid) {
            let newTranslation = TagUtils.autoFillTagsInTarget(this.props.segment);
            //lock tags and run again getWarnings
            setTimeout(() => {
                SegmentActions.replaceEditAreaTextContent(
                    this.props.segment.sid,
                    this.props.segment.id_file,
                    newTranslation
                );
                UI.segmentQA(UI.getSegmentById(this.props.segment.sid));
            }, 100);
        }
    }

    markTranslation(translation) {
        let searchEnabled = this.props.segment.inSearch;
        if (LXQ.enabled() && this.props.segment.lexiqa && this.props.segment.lexiqa.target && !searchEnabled) {
            translation = LXQ.highLightText(translation, this.props.segment.lexiqa.target, true, false, true);
        }
        if (
            QaBlacklist.enabled() &&
            this.props.segment.qaBlacklistGlossary &&
            this.props.segment.qaBlacklistGlossary.length
        ) {
            translation = QaBlacklist.markBlacklistItemsInSegment(translation, this.props.segment.qaBlacklistGlossary);
        }
        if (searchEnabled) {
            translation = SearchUtils.markText(translation, false, this.props.segment.sid);
        }

        return translation;
    }

    componentDidMount() {
        SegmentStore.addListener(SegmentConstants.REPLACE_TRANSLATION, this.replaceTranslation);
        SegmentStore.addListener(SegmentConstants.OPEN_TAGS_MENU, this.showTagsMenu);
        SegmentStore.addListener(SegmentConstants.CLOSE_TAGS_MENU, this.hideTagsMenu);
        SegmentStore.addListener(SegmentConstants.SET_SEGMENT_ORIGINAL_TRANSLATION, this.setOriginalTranslation);
        SegmentStore.addListener(SegmentConstants.FILL_TAGS_IN_TARGET, this.autoFillTagsInTarget);
        SegmentStore.addListener(SegmentConstants.STORE_TRANSLATION, this.storeTranslation);
        this.afterRenderActions();
    }

    componentWillUnmount() {
        SegmentStore.removeListener(SegmentConstants.REPLACE_TRANSLATION, this.replaceTranslation);
        SegmentStore.removeListener(SegmentConstants.OPEN_TAGS_MENU, this.showTagsMenu);
        SegmentStore.removeListener(SegmentConstants.CLOSE_TAGS_MENU, this.hideTagsMenu);
        SegmentStore.removeListener(SegmentConstants.SET_SEGMENT_ORIGINAL_TRANSLATION, this.setOriginalTranslation);
        SegmentStore.removeListener(SegmentConstants.FILL_TAGS_IN_TARGET, this.autoFillTagsInTarget);
        SegmentStore.removeListener(SegmentConstants.STORE_TRANSLATION, this.storeTranslation);
    }

    componentDidUpdate(prevProps) {
        this.afterRenderActions(prevProps);
        if (
            QaBlacklist.enabled() &&
            this.props.segment.qaBlacklistGlossary &&
            this.props.segment.qaBlacklistGlossary.length
        ) {
            $(this.target)
                .find('.blacklistItem')
                .each((index, item) => QaBlacklist.powerTipFn(item, this.props.segment.qaCheckGlossary));
        }
    }

    getSnapshotBeforeUpdate(prevProps, prevState) {
        this.beforeRenderActions();
        return null;
    }

    render() {
        let translation = this.props.segment.decoded_translation.replace(/(<\/span\>\s)$/gi, '</span><br class="end">');
        let buttonsDisabled = false;
        translation = this.markTranslation(translation);

        if (translation.trim().length === 0) {
            buttonsDisabled = true;
        }

        return (
            <div
                className="target item"
                id={'segment-' + this.props.segment.sid + '-target'}
                ref={(target) => (this.target = target)}
            >
                {this.getTargetArea(translation)}
                <p className="warnings" />

                <SegmentButtons
                    disabled={buttonsDisabled}
                    {...this.props}
                    updateTranslation={this.sendTranslationUpdate.bind(this)}
                />

                {this.props.segment.warnings ? <SegmentWarnings warnings={this.props.segment.warnings} /> : null}
            </div>
        );
    }
}

export default SegmentTarget;
