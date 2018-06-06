class CopySourceModal extends React.Component {


    constructor(props) {
        super(props);

    }

    componentDidUpdate() {}

    componentDidMount() {}

    componentWillUnmount() {}



    render() {

        return <div className="copy-source-modal">
                <p className="text-container-top">Copy source to target for all new segments?
                    <b>This action cannot be undone.</b>
                </p>

            <p className="buttons-popup-container button-aligned-right">
                <input type="checkbox" id="popup-checkbox" className="confirm_checkbox"/>
                    <label>Confirm copy source to target</label>
                <a href="javascript:;" className="btn-cancel" data-callback="abortCopyAllSources">No</a>
                <a href="javascript:;" className="btn-ok disabled" disabled="disabled" data-callback-disabled="continueCopyAllSources">Yes</a>
            </p>
            <div className="boxed">
                <input type="checkbox" className="dont_show"/>
                    <label> Don't show this dialog again for the current job</label>
            </div>
        </div>;
    }
}


export default CopySourceModal ;