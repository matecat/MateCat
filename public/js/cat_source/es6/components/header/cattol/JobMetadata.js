import JobMetadataModal from '../../modals/JobMetadataModal';
import SegmentStore from '../../../stores/SegmentStore';
class JobMetadata extends React.Component {
    constructor(props) {
        super(props);

        this.state = {};
    }
    openModal() {
        let currentSegment = SegmentStore.getCurrentSegment();
        let props = {
            currentFile: currentSegment ? currentSegment.id_file : null,
            files: this.props.files,
            projectInfo: this.props.projectInfo
        };
        let styleContainer = {
            minWidth: 600,
            minHeight: 400,
            maxWidth: 900,
        };
        APP.ModalWindow.showModalComponent(
            JobMetadataModal,
            props,
            'Job instructions and references',
            styleContainer
        );
    }

    render() {
        return (
            <div onClick={() => this.openModal()}>
                <svg width="28" height="30" viewBox="0 0 33 35" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path
                        d="M0 10.5V0H2.33333V10.5C2.33333 12.433 3.90034 14 5.83333 14C7.76633 14 9.33333 12.433 9.33333 10.5V3.5C9.33333 2.85567 8.811 2.33333 8.16667 2.33333C7.52234 2.33333 7 2.85567 7 3.5V11.6667H4.66667V3.5C4.66667 1.567 6.23367 0 8.16667 0C10.0997 0 11.6667 1.567 11.6667 3.5V10.5C11.6667 13.7217 9.05499 16.3333 5.83333 16.3333C2.61167 16.3333 0 13.7217 0 10.5Z"
                        fill="#F2F2F2"
                    />
                    <path
                        fillRule="evenodd"
                        clipRule="evenodd"
                        d="M29.1667 0H14V10.5C14 15.0103 10.3437 18.6667 5.83333 18.6667H2.33333V31.5C2.33333 33.433 3.90034 35 5.83333 35H29.1667C31.0997 35 32.6667 33.433 32.6667 31.5V3.5C32.6667 1.567 31.0997 0 29.1667 0ZM25.6667 9.33333H16.3333V11.6667H25.6667V9.33333ZM25.6667 16.3333H16.3333V18.6667H25.6667V16.3333ZM9.33333 23.3333H25.6667V25.6667H9.33333V23.3333Z"
                        fill="#F2F2F2"
                    />
                </svg>
            </div>
        );
    }
}

export default JobMetadata;
