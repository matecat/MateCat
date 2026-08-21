// Extra metadata shown next to a match, beyond the info core renders itself.
//
// This was a method on the matches tab component, so replacing it meant
// patching a prototype and reading `this.props`. A function component has no
// prototype, so it lives here as a plain function of an explicit context.
//
// Core shows nothing here. Overridden by plugin.

const matchInfo = {
  getMatchInfoMetadata: () => '',
}

export default matchInfo
