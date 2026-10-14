### Cache eviction method names

A cache key is `md5(query . bind params)`, so the query is half the address: two reads can bind the
same values and differ only in their SQL. Name an eviction after the **read** it deletes — that
read's name, `find`/`get` prefix dropped — never after the parameters.

| tier    | name                     | takes          | clears               | visibility                      |
|---------|--------------------------|----------------|----------------------|---------------------------------|
| door    | `destroyCache`           | the struct     | every address        | public, one per DAO             |
| fan-out | `destroyCaches<Address>` | key components | one address, N reads | public only with a named caller |
| leaf    | `destroyCache<ReadName>` | bind values    | one read             | private by default              |

The door is the only tier taking a struct, which is what separates it from a leaf at a call site.
Leaves return `bool`, a door `void`. Never add a `For`, never keep the read's `find`. A DAO binding
a caller-supplied key (both `MetadataDao`) cannot have a door.
