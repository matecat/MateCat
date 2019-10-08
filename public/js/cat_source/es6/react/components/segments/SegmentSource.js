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
import EventHandlersUtils  from './utils/eventsHandlersUtils';

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

        let escapedSegment = htmlEncode(text.replace(/\"/g, "&quot;"));
        /* this is to show line feed in source too, because server side we replace \n with placeholders */
        escapedSegment = escapedSegment.replace( config.lfPlaceholderRegex, "\n" );
        escapedSegment = escapedSegment.replace( config.crPlaceholderRegex, "\r" );
        escapedSegment = escapedSegment.replace( config.crlfPlaceholderRegex, "\r\n" );
        return escapedSegment;
    }

    beforeRenderActions() {
        this.props.beforeRenderOrUpdate(this.props.segment.segment);

    }

    afterRenderActions() {
        this.props.afterRenderOrUpdate(this.props.segment.segment);
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
        pasteHtmlAtCaret('<span class="splitpoint"><span class="splitpoint-delete"/></span>');

        this.updateSplitNumber();
    }

    splitSegment(split) {
        let text = $(this.splitContainer).find('.splitArea').html();
        text = text.replace(/<span class=\"splitpoint\"><span class=\"splitpoint-delete\"><\/span><\/span>/, '##$_SPLIT$##');
        text = text.replace(/<span class=\"currentSplittedSegment\">(.*?)<\/span>/gi, '$1');
        text = TextUtils.prepareTextToSend(text);
        // let splitArray = text.split('##_SPLIT_##');
        SegmentActions.splitSegment(this.props.segment.original_sid, text, split);
    }

    markSource() {
        let source = this.props.segment.decoded_source;
        source = this.markGlossary(source);
        source = this.markQaCheckGlossary(source);
        source = this.markLexiqa(source);
        source = this.markSearch(source);
        return source;
    }

    markSearch(source) {
        if ( this.props.segment.search && Object.size(this.props.segment.search) > 0 && this.props.segment.search.source) {
            source = SearchUtils.markText(source, this.props.segment.search, true, this.props.segment.sid);
        }
        return source;
    }

    markGlossary(source) {
        if ( this.props.segment.glossary && Object.size(this.props.segment.glossary) > 0 ) {
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
        if (LXQ.enabled() && this.props.segment.lexiqa && this.props.segment.lexiqa.source) {
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

        this.$source.on('keydown', null, UI.shortcuts.cattol.events.searchInConcordance.keystrokes.mac, this.openConcordance);
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

    componentDidUpdate() {
        if ( QACheckGlossary.enabled() && this.props.segment.qaCheckGlossary &&  this.props.segment.qaCheckGlossary.length ) {
            $(this.source).find('.unusedGlossaryTerm').each((index, item)=>QACheckGlossary.powerTipFn(item, this.props.segment.qaCheckGlossary));
        }
        this.afterRenderActions()
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
                        sourceHtml += '<span class="currentSplittedSegment">'+UI.transformPlaceholdersAndTags(segment.segment)+'</span>';
                    } else {
                        sourceHtml+= UI.transformPlaceholdersAndTags(segment.segment);
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
                            <a className="cancel btn-cancel" onClick={()=>SegmentActions.closeSplitSegment()}>Cancel</a >
                            <a className = {"done btn-ok pull-right" } onClick={()=>this.splitSegment()}> Confirm </a>
                        </div>
                        <div className="splitNum pull-right"> Split in <span className="num">1 </span> segment<span className="plural"/>
                        </div>
                    </div>
                </div >;
            } else {
                html =  <div className="splitContainer" ref={(splitContainer)=>this.splitContainer=splitContainer}>
                    <div className="splitArea" contentEditable = "false"
                         onClick={(e)=>this.addSplitPoint(e)}
                         dangerouslySetInnerHTML={this.allowHTML(UI.transformPlaceholdersAndTags(this.props.segment.segment))}/>
                    <div className="splitBar">
                        <div className="buttons">
                            <a className="cancel btn-cancel" onClick={()=>SegmentActions.closeSplitSegment()}>Cancel</a >
                            <a className = {"done btn-ok pull-right disabled" } onClick={()=>this.splitSegment()}> Confirm </a>
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
