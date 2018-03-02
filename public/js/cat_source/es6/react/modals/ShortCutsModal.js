
class ShortCutsModal extends React.Component {


    constructor(props) {
        super(props);
    }

    render() {
        return <div className="shortcuts-modal">
            <div className="matecat-modal-top">

            </div>
            <div className="matecat-modal-middle">
                <div className="shortcut-list">
                    <h2>Translate/Revise Page</h2>
                    <div className="shortcut-item-list">
                        <div className="shortcut-item">
                            <div className="shortcut-title">
                                Translate/Approve & go Next
                            </div>
                            <div className="shortcut-keys">
                                <div className="shortcuts mac">
                                    <div className="keys ctrl" />+
                                    <div className="keys shift" />+
                                    <div className="keys return" />
                                </div>
                            </div>
                        </div>
                        <div className="shortcut-item">
                            <div className="shortcut-title">
                                Translate/Approve & go Next
                            </div>
                            <div className="shortcut-keys">
                                <div className="shortcuts mac">
                                    <div className="keys ctrl" />+
                                    <div className="keys shift" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <div className="matecat-modal-bottom">

            </div>
        </div>
    }
}


export default ShortCutsModal ;