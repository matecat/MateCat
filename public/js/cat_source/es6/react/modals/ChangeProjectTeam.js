
class ChangeProjectTeam extends React.Component {


    constructor(props) {
        super(props);
    }

    render() {
        return <div className="success-modal">

            <div className="image content">
                <div className="description">
                    <form className="ui form">
                        <div className="field">
                            <label>Change Project Name</label>
                            <input type="text" name="ChangeProjectName" placeholder="Change the actual name" />
                        </div>
                    </form>
                    <div className="ui tabular menu">
                          <div className="item" data-tab="tab-name">Tab Name</div>
                          <div className="item" data-tab="tab-name2">Tab Name 2</div>
                    </div>
                    <div className="ui tab" data-tab="tab-name">
                        {/*Tab Content*/}
                        <p>primo contenuto</p>
                    </div>
                    <div className="ui tab" data-tab="tab-name2">
                        {/*Tab Content*/}
                        <p>secondo contenuto</p>
                    </div>
                </div>
            </div>
            <div className="matecat-modal-footer">
                <div className="actions">
                    <div className="ui positive right labeled icon button" >
                        Si Crea Team
                        <i className="checkmark icon"/>
                    </div>
                </div>
            </div>
        </div>;
    }
}


export default ChangeProjectTeam ;
