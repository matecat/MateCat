import Filters from "./FilterSegments";
import FileDetails from "./FileDetails"
import QualityReportActions from "./../../actions/QualityReportActions"

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

    scrollDebounceFn() {
        let self = this;
        return _.debounce(function() {
            self.onScroll();
        }, 200)
    }

    onScroll(){
        // (window.innerHeight + window.scrollY) >= (document.body.offsetHeight - 500)
        if ( $(window).scrollTop() + $(window).height() > $(document).height() - 200)  {
            console.log("Load More Segments!");
            QualityReportActions.getMoreQRSegments();
        }
    }

    componentDidMount() {
        window.addEventListener('scroll', this.scrollDebounceFn(), false);
    }

    componentWillUnmount() {
        window.removeEventListener('scroll', this.scrollDebounceFn(), false);
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