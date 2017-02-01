class ModifyWorkspace extends React.Component {


    constructor(props) {
        super(props);
    }

    componentDidMount () {
    }

    render() {

        return <div className="modify-organization-modal">

            <div className="image content">
                <div className="description">
                    <div className="fixed-organization-modal">
                        <div className="">
                            {/*Tab Content*/}
                            <form className="ui form">
                                <div className="required field">
                                    <label>Change workspace name</label>
                                    <input type="text" name="Project Name" defaultValue={this.props.workspace.get('name')} />
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <div className="matecat-modal-footer">
                <div className="actions">
                    <div className="ui positive right labeled icon button" >
                        Salva Cambiamenti workspace
                        <i className="checkmark icon"/>
                    </div>
                </div>
            </div>
        </div>;
    }
}


export default ModifyWorkspace ;