/**
 * React Component for the editarea.
 
 */
class Editarea extends React.Component {

    constructor(props) {
        super(props);
        this.state = {

        };
    }
    
    createClassAttribute(segment) {
        /*var editarea_classes = ['targetarea', 'invisible'];
        if ( segment.readonly ) {
            editarea_classes.push( 'area' );
        } else {
            editarea_classes.push( 'editarea' );
        }
        Speech2Text.enabled() && editarea_classes.push( 'micActive' );
        return true;*/
    }

    render() {
        if (this.props.segment)
        return (
            <div className={'editarea'}
                 id={'segment-' + this.props.segment.sid + '-editarea'}
                 data-sid={this.props.segment.sid}
            >ciao</div>

        );
    }
}
export default Editarea ;

