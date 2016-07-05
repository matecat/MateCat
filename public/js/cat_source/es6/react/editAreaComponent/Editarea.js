/**
 * React Component for the editarea.
 
 */
var SegmentConstants = require('../constants/SegmentConstants');
class Editarea extends React.Component {

    constructor(props) {
        super(props);
        this.state = {
            editareaClasses : ['targetarea', 'invisible']

        };
        this.hightlightEditarea = this.hightlightEditarea.bind(this);
    }
    componentWillMount() {
        var decoded_translation;
        var segment = this.props.segment;
        if (UI.enableTagProjection && (UI.getSegmentStatus(segment) === 'draft' || UI.getSegmentStatus(segment) === 'new')
            && !UI.checkXliffTagsInText(segment.translation) ) {
            decoded_translation = UI.removeAllTags(segment.translation);
        } else {
            decoded_translation = segment.translation;
        }
        decoded_translation = UI.decodePlaceholdersToText(decoded_translation || '');
        this.translation = decoded_translation;

        var editareaClasses = this.state.editareaClasses;
        if ((this.props.segment.readonly == 'true')||(UI.body.hasClass('archived'))) {
            editareaClasses.push('area')
        } else {
            editareaClasses.push('editarea')
        }
        this.setState({
            editareaClasses: editareaClasses
        });

    }

    allowHTML(string) {
        return { __html: string };
    }

    componentDidMount() {
        SegmentStore.addListener(SegmentConstants.HIGHLIGHT_EDITAREA, this.hightlightEditarea);
    }
    hightlightEditarea(sid) {
        if (this.props.segment.sid == sid) {
            var editareaClasses = this.state.editareaClasses;
            editareaClasses.push('highlighted1');
            this.setState({
                editareaClasses: editareaClasses
            });
        }
        /*segment = seg || this.currentSegment;
        segment.addClass('highlighted1');
        setTimeout(function() {
            $('.highlighted1').addClass('modified highlighted2');
            segment.trigger('modified');
        }, 300);
        setTimeout(function() {
            $('.highlighted1, .highlighted2').removeClass('highlighted1 highlighted2');
        }, 2000);*/
    }
    render() {
        if (this.props.segment){
            return (
                <div className={this.state.editareaClasses.join(' ')}
                     id={'segment-' + this.props.segment.sid + '-editarea'}
                     data-sid={this.props.segment.sid}
                     dangerouslySetInnerHTML={ this.allowHTML(this.translation) }
                ></div>

            );
        }
    }
}

export default Editarea ;

