let Immutable = require('immutable');
class ModifyOrganization extends React.Component {


    constructor(props) {
        super(props);
        this.state = {
            organization: this.props.organization
        }
    }

    componentDidMount () {
        $('.menu .item').tab();
        $('.fixed-organization-modal .ui.checkbox').checkbox();
    }

    removeUser(user, index) {
        let newOrganization = this.state.organization.set('users', this.state.organization.get('users').delete(index));
        this.setState({
            organization: newOrganization
        });
    }

    handleKeyPress(e) {
        e.stopPropagation();
        if (e.key === 'Enter' ) {
            e.preventDefault();
            if (this.inputNewUSer.value.length > 4) {

                let newUser = {
                    id: Math.floor((Math.random() * 100) + 1),
                    userMail: '@translated.net',
                    userFullName: this.inputNewUSer.value,
                    userShortName: 'XX'

                };
                this.setState({
                    organization: this.state.organization.set('users', this.state.organization.get('users').push(Immutable.fromJS(newUser)))
                });

                this.inputNewUSer.value = '';
            }
        }
        return false;
    }

    getUserList() {
        return this.state.organization.get('users').map((user, i) => (
            <div className="item"
               key={'user' + user.get('userShortName') + user.get('id')}>
                <div className="right floated content">
                    <div className="ui button" onClick={this.removeUser.bind(this, user, i)}>Remove</div>
                </div>
                <div className="ui avatar image initials green">{user.get('userShortName')}</div>
                <div className="content">
                    {user.get('userFullName')}
                </div>
            </div>

        ));

    }

    render() {
        let userlist = this.getUserList();
        return <div className="modify-organization-modal">
            <div className="matecat-modal-top">
                <div className="ui one column grid left aligned">
                    <div className="column">
                        <h3>Change Organization Name</h3>
                        <div className="ui large fluid icon input">
                            <input type="text" value="Organization Name"/>
                            <i className="icon-pencil icon"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div className="matecat-modal-middle">
                <div className="ui one column grid left aligned">
                    <div className="column">
                        <h3>Add member</h3>
                        <div className="ui large fluid icon input">
                            <input type="text" defaultValue="emails"
                                   onKeyPress={this.handleKeyPress.bind(this)}
                                   ref={(inputNewUSer) => this.inputNewUSer = inputNewUSer}/>
                        </div>
                    </div>
                    <div className="column">
                        <div className="ui segment members-list">
                            <div className="ui middle aligned divided list">
                                {userlist}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>;
    }
}


export default ModifyOrganization ;