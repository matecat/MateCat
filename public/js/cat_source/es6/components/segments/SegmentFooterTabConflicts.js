/**
 * React Component .

 */
import React from 'react';
import Immutable from 'immutable';
import TagUtils from '../../utils/tagUtils';
import TextUtils from '../../utils/textUtils';

class SegmentFooterTabConflicts extends React.Component {
    constructor(props) {
        super(props);
    }

    chooseAlternative(text) {
        SegmentActions.setFocusOnEditArea();
        SegmentActions.disableTPOnSegment(this.props.segment);
        setTimeout(() => {
            SegmentActions.replaceEditAreaTextContent(this.props.segment.sid, this.props.segment.id_file, text);
            SegmentActions.highlightEditarea(this.props.segment.sid);
            SegmentActions.modifiedTranslation(this.props.segment.sid, this.props.segment.id_file, true);
        });
    }

    renderAlternatives(alternatives) {
        let segment = this.props.segment;
        let segment_id = this.props.segment.sid;
        let escapedSegment = TagUtils.decodePlaceholdersToText(segment.segment);
        // Take the .editarea content with special characters (Ex: ##$_0A$##) and transform the placeholders
        let mainStr = TextUtils.htmlEncode(TagUtils.prepareTextToSend(segment.decoded_translation)).replace(
            /&amp;/g,
            '&'
        );
        let html = [];
        let self = this;
        let replacementsMap;
        $.each(alternatives.editable, function (index) {
            // Decode the string from the server
            let transDecoded = this.translation;
            // Make the diff between the text with the same codification

            [mainStr, transDecoded, replacementsMap] = TagUtils._treatTagsAsBlock(mainStr, transDecoded, []);

            let diff_obj = TextUtils.execDiff(mainStr, transDecoded);

            //replace the original string in the diff object by the character placeholder
            if (replacementsMap.length > 0) {
                Object.keys(diff_obj).forEach((element) => {
                    if (replacementsMap[diff_obj[element][1]]) {
                        diff_obj[element][1] = replacementsMap[diff_obj[element][1]];
                    } else {
                        Object.keys(replacementsMap).forEach((replaceElem) => {
                            if (diff_obj[element][1].indexOf(replaceElem) !== -1) {
                                diff_obj[element][1] = diff_obj[element][1].replace(
                                    replaceElem,
                                    replacementsMap[replaceElem]
                                );
                            }
                        });
                    }
                });
            }

            let translation = TagUtils.decodePlaceholdersToText(
                TagUtils.transformTextForLockTags(TextUtils.diffMatchPatch.diff_prettyHtml(diff_obj))
            );
            let source = TagUtils.decodePlaceholdersToText(TagUtils.transformTextForLockTags(escapedSegment));
            html.push(
                <ul
                    className="graysmall"
                    data-item={index + 1}
                    key={'editable' + index}
                    onDoubleClick={() => self.chooseAlternative(this.translation)}
                >
                    <li className="sugg-source">
                        <span
                            id={segment_id + '-tm-' + this.id + '-source'}
                            className="suggestion_source"
                            dangerouslySetInnerHTML={self.allowHTML(source)}
                        />
                    </li>
                    <li className="b sugg-target">
                        {/*<span className="graysmall-message">{'CTRL' + (index + 1)}</span>*/}
                        <span className="translation" dangerouslySetInnerHTML={self.allowHTML(translation)} />
                        <span className="realData hide" dangerouslySetInnerHTML={self.allowHTML(this.translation)} />
                    </li>
                    <li className="goto">
                        <a
                            data-goto={this.involved_id[0]}
                            onClick={() => SegmentActions.openSegment(this.involved_id[0])}
                        >
                            Go to
                        </a>
                    </li>
                </ul>
            );
        });

        $.each(alternatives.not_editable, function (index1) {
            let diff_obj = TextUtils.execDiff(mainStr, this.translation);
            let translation = TagUtils.transformTextForLockTags(TextUtils.diffMatchPatch.diff_prettyHtml(diff_obj));

            html.push(
                <ul
                    className="graysmall notEditable"
                    data-item={index1 + alternatives.editable.length + 1}
                    key={'not-editable' + index1}
                    onDoubleClick={() => self.chooseAlternative(escapedSegment)}
                >
                    <li className="sugg-source">
                        <span
                            id={segment_id + '-tm-' + this.id + '-source'}
                            className="suggestion_source"
                            dangerouslySetInnerHTML={self.allowHTML(escapedSegment)}
                        />
                    </li>
                    <li className="b sugg-target">
                        {/*<span className="graysmall-message">{'CTRL+' + (index1 + alternatives.data.editable.length + 1)}</span>*/}
                        <span className="translation" dangerouslySetInnerHTML={self.allowHTML(translation)} />
                        <span className="realData hide" dangerouslySetInnerHTML={self.allowHTML(this.translation)} />
                    </li>
                    <li className="goto">
                        <a
                            data-goto={this.involved_id[0]}
                            onClick={() => SegmentActions.openSegment(this.involved_id[0])}
                        >
                            Go to
                        </a>
                    </li>
                </ul>
            );
        });
        return html;
    }

    componentDidMount() {}

    componentWillUnmount() {}

    allowHTML(string) {
        return { __html: string };
    }

    shouldComponentUpdate(nextProps, nextState) {
        return (
            this.props.active_class !== nextProps.active_class ||
            this.props.tab_class !== nextProps.tab_class ||
            ((!_.isUndefined(nextProps.segment.alternatives) || !_.isUndefined(this.props.segment.alternatives)) &&
                ((!_.isUndefined(nextProps.segment.alternatives) && _.isUndefined(this.props.segment.alternatives)) ||
                    !Immutable.fromJS(this.props.segment.alternatives).equals(
                        Immutable.fromJS(nextProps.segment.alternatives)
                    )))
        );
    }

    render() {
        let html;
        if (this.props.segment.alternatives && _.size(this.props.segment.alternatives) > 0) {
            html = this.renderAlternatives(this.props.segment.alternatives);
            return (
                <div
                    key={'container_' + this.props.code}
                    className={'tab sub-editor ' + this.props.active_class + ' ' + this.props.tab_class}
                    id={'segment-' + this.props.id_segment + '-' + this.props.tab_class}
                >
                    <div className="overflow">{html}</div>
                </div>
            );
        } else {
            return '';
        }
    }
}

export default SegmentFooterTabConflicts;
