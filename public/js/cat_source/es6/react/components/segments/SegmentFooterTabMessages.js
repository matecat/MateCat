/**
 * React Component .

 */
let React = require('react');
let SegmentConstants = require('../../constants/SegmentConstants');
let SegmentStore = require('../../stores/SegmentStore');
let WrapperLoader =         require("../../common/WrapperLoader").default;
let showdown = require( "showdown" );
class SegmentFooterTabMessages extends React.Component {

    constructor(props) {
        super(props);
        this.state = {
            previews: null,
            currentIndexPreview: 0,
            loadingImage: false
        };
    }

    getNotes() {
        let notesHtml = [];
        let self = this;
        if (this.props.notes) {
            this.props.notes.forEach(function (item, index) {
                if (item.note && item.note !== "") {
                    let regExpUrl = /((([A-Za-z]{3,9}:(?:\/\/)?)(?:[-;:&=\+\$,\w]+@)?[A-Za-z0-9.-]+|(?:www.|[-;:&=\+\$,\w]+@)[A-Za-z0-9.-]+)((?:\/[\+~%\/$.\w-_]*)?\??(?:\S+)#?(?:[\w]*))?)/gmi;
                    let note = item.note.replace(regExpUrl, function ( match, text ) {
                        return '<a href="'+ text +'" target="_blank">' + text + '</a>';
                    });
                    let html = <div className="note" key={"note-" + index}>
                        <span className="note-label">Note: </span>
                        <span dangerouslySetInnerHTML={self.allowHTML(note)}/>
                    </div>;
                    notesHtml.push(html);
                } else if (item.json && typeof item.json === "object" && Object.keys(item.json).length > 0) {
                    Object.keys(item.json).forEach(function (key, index) {
                        let html = <div className="note" key={"note-json" + index}>
                            <span className="note-label">{key.toUpperCase()}: </span>
                            <span> {item.json[key]} </span>
                        </div>;
                        notesHtml.push(html);
                    });

                } else if (typeof item.json === "string") {
                    let converter = new showdown.Converter();
                    let text = converter.makeHtml( item.json );
                    let html = <div key={"note-json" + index} className="note" dangerouslySetInnerHTML={self.allowHTML(text)}/>;
                    notesHtml.push(html);
                }
            });
        }
        if (notesHtml.length === 0) {
            let html = <div className="note" key={"note-0"}>
                There are no notes available
            </div>;
            notesHtml.push(html);
        }
        return notesHtml;
    }

    renderPreview(sid, previewsData) {
        let self = this;
        if ( this.props.id_segment === sid && previewsData) {
            let segments = previewsData.segments;
            let segmentInfo = segments.find(function ( segment ) {
                return segment.segment === parseInt(self.props.id_segment)
            });
            if (segmentInfo && segmentInfo.previews && segmentInfo.previews.length > 0) {
                this.setState({
                    previews: segmentInfo.previews
                });
            }
        }
    }

    navigationBetweenPreviews(offset){
        let newIndex = this.state.currentIndexPreview + offset - this.state.previews.length * Math.floor((this.state.currentIndexPreview + offset) / this.state.previews.length);
        this.setState({
            currentIndexPreview: newIndex,
            loadingImage: true
        })
    }

    openPreview() {
        UI.openPreview(this.props.id_segment,this.state.previews[this.state.currentIndexPreview].file_index);
    }

    componentDidMount() {
        SegmentStore.addListener(SegmentConstants.RENDER_PREVIEW, this.renderPreview.bind(this));
    }

    componentWillUnmount() {
        SegmentStore.removeListener(SegmentConstants.RENDER_PREVIEW, this.renderPreview);
    }

    componentWillMount() {}

    allowHTML(string) {
        return { __html: string };
    }

    render() {
        let backgroundSrc = "",
            controls = "";
        if (this.state.previews && this.state.previews.length > 0) {
            let preview = this.state.previews[this.state.currentIndexPreview];
            backgroundSrc =  preview.path + preview.file_index ;

            controls =  <div className="tab-preview-screenshot">
                <button className="preview-button previous" onClick={this.navigationBetweenPreviews.bind(this,-1)}>
                    <i className="icon icon-chevron-left" /> </button>
                <div className="n-segments-available">{this.state.currentIndexPreview+1} / {this.state.previews.length}</div>
                <button className="preview-button next" onClick={this.navigationBetweenPreviews.bind(this,1)}>
                    <i className="icon icon-chevron-right" /></button>
                <div className="text-n-segments-available">available screens for this segment</div>
            </div>;
        }

        return  <div key={"container_" + this.props.code}
                    className={"tab sub-editor "+ this.props.active_class + " " + this.props.tab_class}
                    id={"segment-" + this.props.id_segment + " " + this.props.tab_class}>
                <div className="overflow">
                    {this.state.previews ? (
                        <div className="segments-preview-footer">
                            <div className="segments-preview-container" onClick={this.openPreview.bind(this)}>
                                <img src={backgroundSrc}/>
                                {this.state.loadingImage ? <WrapperLoader /> : null}
                            </div>

                            {this.state.previews.length > 1 ? controls : null}

                        </div>
                    ) : (null)}

                    <div className="segment-notes-container">
                        <div className="segment-notes-panel-body">
                            <div className="segments-notes-container">
                                {this.getNotes()}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    }

    componentDidUpdate(){
        let self = this;
        if(this.state.loadingImage){
            $('.segments-preview-container').imagesLoaded( function() {
                console.log('Image loaded');
                self.setState({
                    loadingImage: false
                })
            });
        }
    }
}

export default SegmentFooterTabMessages;