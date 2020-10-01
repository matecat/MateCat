/**
 * React Component .

 */
import React  from 'react';
import SegmentStore  from '../../stores/SegmentStore';
import SegmentActions  from '../../actions/SegmentActions';
import GlossaryUtils  from './utils/glossaryUtils';
import QACheckGlossary  from './utils/qaCheckGlossaryUtils';
import SearchUtils  from '../header/cattol/search/searchUtils';
import TextUtils  from '../../utils/textUtils';
import Shortcuts  from '../../utils/shortcuts';
import EventHandlersUtils  from './utils/eventsHandlersUtils';
import LXQ from '../../utils/lxq.main';


class SegmentSource extends React.Component {

    constructor(props) {
        super(props);

        this.originalSource = this.createEscapedSegment(this.props.segment.segment);
        this.createEscapedSegment = this.createEscapedSegment.bind(this);
        this.decodeTextSource = this.decodeTextSource.bind(this);
        this.beforeRenderActions = this.beforeRenderActions.bind(this);
        this.afterRenderActions = this.afterRenderActions.bind(this);
        this.openConcordance = this.openConcordance.bind(this);

        this.beforeRenderActions();
    }

    decodeTextSource(segment, source) {
        return this.props.decodeTextFn(segment, source);
    }

    createEscapedSegment(text) {
        if (!$.parseHTML(text).length) {
            text = text.replace(/<span(.*?)>/gi, '').replace(/<\/span>/gi, '');
        }

        let escapedSegment = TextUtils.htmlEncode(text.replace(/\"/g, "&quot;"));
        /* this is to show line feed in source too, because server side we replace \n with placeholders */
        escapedSegment = escapedSegment.replace( config.lfPlaceholderRegex, "\n" );
        escapedSegment = escapedSegment.replace( config.crPlaceholderRegex, "\r" );
        escapedSegment = escapedSegment.replace( config.crlfPlaceholderRegex, "\r\n" );
        return escapedSegment;
    }

    beforeRenderActions() {
        this.props.beforeRenderOrUpdate(this.props.segment.segment);

    }

    afterRenderActions(prevProps) {
        let tagMismatchChanged = (!_.isUndefined(prevProps) &&
            prevProps.segImmutable.get('tagMismatch')) ? !this.props.segImmutable.get('tagMismatch').equals(prevProps.segImmutable.get('tagMismatch')): true;
        this.props.afterRenderOrUpdate(this.props.segment.segment, tagMismatchChanged);
        let self = this;
        if ( this.splitContainer ) {
            $(this.splitContainer).on('mousedown', '.splitArea .splitpoint', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).remove();
                self.updateSplitNumber();
            })
        }
    }

    updateSplitNumber() {
        if (this.props.segment.splitted) return;
        let numSplits = $(this.splitContainer).find('.splitpoint').length + 1;
        let splitnum = $(this.splitContainer).find('.splitNum');
        $(splitnum).find('.num').text(numSplits);
        this.splitNum = numSplits;
        if (numSplits > 1) {
            $(splitnum).find('.plural').text('s');
            $(this.splitContainer).find('.btn-ok').removeClass('disabled');
        } else {
            $(splitnum).find('.plural').text('');
            splitnum.hide();
            $(this.splitContainer).find('.btn-ok').addClass('disabled');
        }
        $(this.splitContainer).find('.splitArea').blur();
    }

    onCopyEvent(e) {
        EventHandlersUtils.handleCopyEvent(e);
    }

    onDragEvent(e) {
        EventHandlersUtils.handleDragEvent(e);
    }

    addSplitPoint(event) {
        if(window.getSelection().type === 'Range') return false;
        TextUtils.pasteHtmlAtCaret('<span class="splitpoint"><span class="splitpoint-delete"/></span>');

        this.updateSplitNumber();
    }

    splitSegment(split) {
        let text = $(this.splitContainer).find('.splitArea').html();
        text = text.replace(/<span class=\"splitpoint\"><span class=\"splitpoint-delete\"><\/span><\/span>/gi, '##$_SPLIT$##');
        text = text.replace(/<span class=\"currentSplittedSegment\">(.*?)<\/span>/gi, '$1');
        text = TagUtils.prepareTextToSend(text);
        // let splitArray = text.split('##_SPLIT_##');
        SegmentActions.splitSegment(this.props.segment.original_sid, text, split);
    }

    markSource() {
        let source = this.props.segment.decoded_source;
        source = this.markSearch(source);
        source = this.markGlossary(source);
        source = this.markQaCheckGlossary(source);
        source = this.markLexiqa(source);
        return source;
    }

    markSearch(source) {
        if ( this.props.segment.inSearch ) {
            return SearchUtils.markText(source, true, this.props.segment.sid);
        }
        return source
    }

    markGlossary(source) {
        if ( this.props.segment.glossary && _.size(this.props.segment.glossary) > 0 ) {
            return GlossaryUtils.markGlossaryItemsInText(source, this.props.segment.glossary, this.props.segment.sid);
        }
        return source;
    }

    markQaCheckGlossary(source) {
        if (QACheckGlossary.enabled() && this.props.segment.qaCheckGlossary && this.props.segment.qaCheckGlossary.length > 0) {
            return QACheckGlossary.markGlossaryUnusedMatches(source, this.props.segment.qaCheckGlossary);
        }
        return source;
    }

    markLexiqa(source) {
        let searchEnabled = this.props.segment.inSearch;
        if (LXQ.enabled() && this.props.segment.lexiqa && this.props.segment.lexiqa.source && !searchEnabled) {
            source = LXQ.highLightText(source, this.props.segment.lexiqa.source, true, true, true );
        }
        return source;
    }

    openConcordance(e) {
        e.preventDefault();
        var selection = window.getSelection();
        if (selection.type === 'Range') { // something is selected
            var str = selection.toString().trim();
            if (str.length) { // the trimmed string is not empty
                SegmentActions.openConcordance(this.props.segment.sid, str, false);
            }
        }
    }

    componentDidMount() {
        this.$source = $(this.source);

        this.$source.on('click', 'mark.inGlossary',  ( e ) => {
            e.preventDefault();
            SegmentActions.activateTab(this.props.segment.sid, 'glossary');
        });

        this.afterRenderActions();

        this.$source.on('keydown', null, Shortcuts.cattol.events.searchInConcordance.keystrokes[Shortcuts.shortCutsKeyType], this.openConcordance);
    }

    componentWillUnmount() {
        this.$source.off('click', 'mark.inGlossary');
        $.powerTip.destroy($('.blacklistItem', this.$source));

        this.$source.on('keydown', this.openConcordance);
    }
    getSnapshotBeforeUpdate() {
        this.beforeRenderActions();
        return null;
    }

    componentDidUpdate(prevProps) {
        if ( QACheckGlossary.enabled() && this.props.segment.qaCheckGlossary &&  this.props.segment.qaCheckGlossary.length ) {
            $(this.source).find('.unusedGlossaryTerm').each((index, item)=>QACheckGlossary.powerTipFn(item, this.props.segment.qaCheckGlossary));
        }
        this.afterRenderActions(prevProps)
    }

    allowHTML(string) {
        return { __html: string };
    }

    render() {
        let source = this.markSource();

        let html = <div ref={(source)=>this.source=source}
                        className={"source item"}
                        tabIndex={0}
                        id={"segment-" + this.props.segment.sid +"-source"}
                        data-original={this.originalSource}
                        dangerouslySetInnerHTML={ this.allowHTML(source) }
                        onCopy={this.onCopyEvent.bind(this)}
                        onDragStart={this.onDragEvent.bind(this)}
        />;
        if ( this.props.segment.openSplit ) {
            if ( this.props.segment.splitted ) {
                let segmentsSplit = this.props.segment.split_group;
                let sourceHtml = '';
                segmentsSplit.forEach((sid, index)=>{
                    let segment = SegmentStore.getSegmentByIdToJS(sid);
                    if ( sid === this.props.segment.sid) {
                        sourceHtml += '<span class="currentSplittedSegment">'+TagUtils.transformPlaceholdersAndTags(segment.segment)+'</span>';
                    } else {
                        sourceHtml+= TagUtils.transformPlaceholdersAndTags(segment.segment);
                    }
                    if(index !== segmentsSplit.length - 1)
                        sourceHtml += '<span class="splitpoint"><span class="splitpoint-delete"></span></span>';
                });
                html =  <div className="splitContainer" ref={(splitContainer)=>this.splitContainer=splitContainer}>
                    <div className="splitArea" contentEditable = "false"
                         onClick={(e)=>this.addSplitPoint(e)}
                         dangerouslySetInnerHTML={this.allowHTML(sourceHtml)}/>
                    <div className="splitBar">
                        <div className="buttons">
                            <a className="ui button cancel-button cancel btn-cancel" onClick={()=>SegmentActions.closeSplitSegment()}>Cancel</a >
                            <a className = {"ui primary button done btn-ok pull-right" } onClick={()=>this.splitSegment()}> Confirm </a>
                        </div>
                        <div className="splitNum pull-right"> Split in <span className="num">1 </span> segment<span className="plural"/>
                        </div>
                    </div>
                </div >;
            } else {
                html =  <div className="splitContainer" ref={(splitContainer)=>this.splitContainer=splitContainer}>
                    <div className="splitArea" contentEditable = "false"
                         onClick={(e)=>this.addSplitPoint(e)}
                         dangerouslySetInnerHTML={this.allowHTML(TagUtils.transformPlaceholdersAndTags(this.props.segment.segment))}/>
                    <div className="splitBar">
                        <div className="buttons">
                            <a className="ui button cancel-button cancel btn-cancel" onClick={()=>SegmentActions.closeSplitSegment()}>Cancel</a >
                            <a className = {"ui primary button done btn-ok pull-right disabled" } onClick={()=>this.splitSegment()}> Confirm </a>
                        </div>
                        <div className="splitNum pull-right"> Split in <span className="num">1 </span> segment<span className="plural"/>
                        </div>
                    </div>
                </div >;
            }
        }
        return html;

    }
}

export default SegmentSource;
