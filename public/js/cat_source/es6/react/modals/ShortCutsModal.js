
class ShortCutsModal extends React.Component {


    constructor(props) {
        super(props);
    }

    getShortcutsHtml() {
        let html = [];
        let self = this;
        let label = UI.isMac ? "mac" : "standard";
        _.each(this.props.shortcuts, function ( item, z ) {
            let keys = item.keystrokes[label].split('+');
            let keysHtml = [];
            keys.forEach(function ( key, i ) {
                let html = <div key={key} className={"keys " + key}/>;
                keysHtml.push(html);
                if (i < keys.length-1) {
                    keysHtml.push("+");
                }
            });
            let sh = <div key={z} className="shortcut-item">
                <div className="shortcut-title">
                    {item.label}
                </div>
                <div className="shortcut-keys">
                    <div className="shortcuts mac">
                        {keysHtml}
                    </div>
                </div>
            </div>;
            html.push(sh);
        });
        return html;
    }

    render() {
        let html = this.getShortcutsHtml();
        return <div className="shortcuts-modal">
            <div className="matecat-modal-top">

            </div>
            <div className="matecat-modal-middle">
                <div className="shortcut-list">
                    <h2>Translate/Revise Page</h2>
                    <div className="shortcut-item-list">
                        {html}
                        {/*<div className="shortcut-item">
                            <div className="shortcut-title">
                                Translate/Approve & go Next
                            </div>
                            <div className="shortcut-keys">
                                <div className="shortcuts mac">
                                    <div className="keys ctrl" />+
                                    <div className="keys shift" />
                                </div>
                            </div>
                        </div>*/}
                    </div>
                </div>

            </div>
            <div className="matecat-modal-bottom">

            </div>
        </div>
    }
}


export default ShortCutsModal ;