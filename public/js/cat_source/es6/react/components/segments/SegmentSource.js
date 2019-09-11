/**
 * React Component .

 */
var React = require('react');
var SegmentStore = require('../../stores/SegmentStore');
var SegmentConstants = require('../../constants/SegmentConstants');


class SegmentSource extends React.Component {

    constructor(props) {
        super(props);
        this.originalSource = this.createEscapedSegment(this.props.segment.segment);
        this.createEscapedSegment = this.createEscapedSegment.bind(this);
        this.beforeRenderActions = this.beforeRenderActions.bind(this);
        this.afterRenderActions = this.afterRenderActions.bind(this);
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
        var area = $("#segment-" + this.props.segment.sid + " .source");
        this.props.beforeRenderOrUpdate(area);

    }

    afterRenderActions() {
        var area = $("#segment-" + this.props.segment.sid + " .source");
        this.props.afterRenderOrUpdate(area);
    }

    onCopyEvent(e) {
        UI.handleCopyEvent(e);
    }

    onDragEvent(e) {
        UI.handleDragEvent(e);
    }

    componentDidMount() {
        this.afterRenderActions();
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
                 dangerouslySetInnerHTML={ this.allowHTML(this.props.segment.decoded_source) }
                 onCopy={this.onCopyEvent.bind(this)}
                 onDragStart={this.onDragEvent.bind(this)}
            />
        )
    }
}

export default SegmentSource;
