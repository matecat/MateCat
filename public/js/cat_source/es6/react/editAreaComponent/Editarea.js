/**
 * React Component for the editare to Matecat.
 
 */
    

class Editarea extends React.Component {


    constructor(props) {
        super(props);
        this.state = {

        };
    }

    renderEditArea(segment) {
        var decoded_translation;
        this.props.segment = segment;
        this.classes = this.createClassAttribute(segment);

    }
    
    createClassAttribute(segment) {
        var editarea_classes = ['targetarea', 'invisible'];
        if ( segment.readonly ) {
            editarea_classes.push( 'area' );
        } else {
            editarea_classes.push( 'editarea' );
        }
        Speech2Text.enabled() && editarea_classes.push( 'micActive' ) ;
    }

    render() {
        if (this.props.segment)
        return (
            <div class="{{editarea_classes_string}}"
                {{#if readonly}} contenteditable="false" {{/if}}
                 spellcheck="true"
                 lang="{{lang}}"
                 id="segment-{{segment.sid}}-editarea"
                 data-sid="{{segment.sid}}"
            >{{#if segment.translation }}{{{ decoded_translation }}}{{/if}}</div>

        );
    }
}



export default Editarea ;

