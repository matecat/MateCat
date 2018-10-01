/**
 * React Component .

 */
var React = require('react');
var SegmentStore = require('../../stores/SegmentStore');
var SegmentConstants = require('../../constants/SegmentConstants');


class SegmentSource extends React.Component {

    constructor(props) {
        super(props);
        this.state = {
            source : this.props.segment.decoded_source

        };
        this.originalSource = this.createEscapedSegment(this.props.segment.segment);
        this.createEscapedSegment = this.createEscapedSegment.bind(this);
        this.decodeTextSource = this.decodeTextSource.bind(this);
        this.replaceSource = this.replaceSource.bind(this);
        this.beforeRenderActions = this.beforeRenderActions.bind(this);
        this.afterRenderActions = this.afterRenderActions.bind(this);
        this.toggleTagLock = this.toggleTagLock.bind(this);
    }

    replaceSource(sid, source) {
        if (this.props.segment.sid == sid) {
            this.setState({
                source: source
            });
        }
    }

    toggleTagLock(sid, source) {
        this.setState({
            source: this.props.segment.decoded_source
        });
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
        if (!this.props.isReviewImproved) {
            var area = $("#segment-" + this.props.segment.sid + " .source");
            this.props.beforeRenderOrUpdate(area);
        }
    }

    afterRenderActions() {
        if (!this.props.isReviewImproved) {
            var area = $("#segment-" + this.props.segment.sid + " .source");
            this.props.afterRenderOrUpdate(area);
        }
    }

    onCopyEvent(e) {
        UI.handleSourceCopyEvent(e);
    }

    onDragEvent(e) {
        UI.handleDragEvent(e);
    }

    componentDidMount() {
        SegmentStore.addListener(SegmentConstants.REPLACE_SOURCE, this.replaceSource);
        SegmentStore.addListener(SegmentConstants.DISABLE_TAG_LOCK, this.toggleTagLock);
        SegmentStore.addListener(SegmentConstants.ENABLE_TAG_LOCK, this.toggleTagLock);
        this.afterRenderActions();
    }

    componentWillUnmount() {
        SegmentStore.removeListener(SegmentConstants.REPLACE_SOURCE, this.replaceSource);
        SegmentStore.removeListener(SegmentConstants.DISABLE_TAG_LOCK, this.toggleTagLock);
        SegmentStore.removeListener(SegmentConstants.ENABLE_TAG_LOCK, this.toggleTagLock);
    }
    componentWillMount() {
        this.beforeRenderActions();
    }
    componentWillUpdate() {
        this.beforeRenderActions();
    }

    componentDidUpdate() {
        this.afterRenderActions()
    }

    allowHTML(string) {
        return { __html: string };
    }

    render() {
        return (
            <div className={"source item"}
                 tabIndex={0}
                 id={"segment-" + this.props.segment.sid +"-source"}
                 data-original={this.originalSource}
                 dangerouslySetInnerHTML={ this.allowHTML(this.state.source) }
                 onCopy={this.onCopyEvent.bind(this)}
                 onDragStart={this.onDragEvent.bind(this)}
            />
        )
    }
}

export default SegmentSource;
