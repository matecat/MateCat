import Filters from "./FilterSegments";
import FileDetails from "./FileDetails"

class SegmentsDetails extends React.Component {

    getFiles() {
        let files = [];
        if ( this.props.files ) {
            this.props.files.keySeq().forEach(( key, index ) => {
                let file = <FileDetails key={key} file={this.props.files.get(key)}/>
                files.push(file)
            });
        }
        return files;
    }

    render () {

        return <div className="qr-segment-details-container">
            <div className="qr-segments-summary">
                <h3>Segment details</h3>
                <Filters/>
                {this.getFiles()}
            </div>
        </div>
    }
}

export default SegmentsDetails ;