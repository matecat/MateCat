import SegmentQR from "./SegmentQR";

class FileDetails extends React.Component {

    render () {

        return <div className="qr-segments">
            <div className="document-name top-30">FILE Test_Project_For_New_QR.html</div>
            <div className="qr-segments-list">
                <SegmentQR/>
                <SegmentQR/>
            </div>
        </div>

    }
}

export default FileDetails ;