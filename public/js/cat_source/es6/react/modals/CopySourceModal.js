class CopySourceModal extends React.Component {


    constructor(props) {
        super(props);

    }

    copyAllSources() {
        this.props.confirmCopyAllSources();
        this.checkCheckbox();
        APP.ModalWindow.onCloseModal();
    }

    copySegmentOnly() {
        this.props.abortCopyAllSources();
        this.checkCheckbox();
        APP.ModalWindow.onCloseModal();
    }

    checkCheckbox() {
        var checked = this.checkbox.checked;
        if ( checked ) {
            Cookies.set('source_copied_to_target-' + config.id_job +"-" + config.password,
                '0',
                //expiration: 1 day
                { expires: 30 });
        }
        else {
            Cookies.set('source_copied_to_target-' + config.id_job +"-" + config.password,
                null,
                //set expiration date before the current date to delete the cookie
                {expires: new Date(1)});
        }
    }

    componentDidUpdate() {}

    componentDidMount() {}

    componentWillUnmount() {}



    render() {

        return <div className="copy-source-modal">
                <h3 className="text-container-top">
                    Do you really want to copy source to target for all new segments?
                </h3>

            <div className="buttons-popup-container">
                <label>Copy source to target for:</label>
                <button className="btn-cancel" onClick={this.copyAllSources.bind(this)}>ALL new segments</button>
                <button className="btn-ok" onClick={this.copySegmentOnly.bind(this)}>This segment only</button>
                <div className="notes-action"><b>Note</b>: This action cannot be undone.</div>
            </div>
            <div className="boxed">
                <input type="checkbox" className="dont_show" ref={(checkbox)=>this.checkbox=checkbox}/>
                    <label> Don't show this dialog again for the current job</label>
            </div>
        </div>;
    }
}


export default CopySourceModal ;