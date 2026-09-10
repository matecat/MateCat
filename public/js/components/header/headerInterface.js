// Extra entries in the header's link list.
//
// Core adds none, so this returns nothing and the list renders as it stands.
// Overridden by plugin.

const headerInterface = {
  getMoreLinks: () => null,
}

export default headerInterface
