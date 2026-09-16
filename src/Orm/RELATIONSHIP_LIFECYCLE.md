# Relationship lifecycle policies

Cardinality, dependency direction and lifecycle are separate concerns. A child relationship does not, by its name alone,
authorize deleting the target entity.

## One-to-many policy

Configure `orphanRemoval` on `HasMany`, `Relationships::hasMany()` or `Relationship\OneToMany`:

```php
#[Orm\HasMany(
    target: OrderLine::class,
    name: 'lines',
    columns: ['id' => 'order_id'],
    orphanRemoval: true,
)]
```

| Policy | Explicitly removed child |
| --- | --- |
| `false` | Clear its linking columns and save it. Required linking columns or primary-key columns prevent detachment and produce a `RelationException`. |
| `true` | Delete it through the ORM. |
| Omitted / `null` (before v2) | Preserve historical deletion of detached children. |

The omitted-policy default is a **deprecated compatibility behavior**. Set `orphanRemoval: true` explicitly to preserve
deletion when upgrading to v2. The v2 target default is `false`.

The shared lifecycle implementation is also the foundation for the parent-side scalar relation tracked in
[issue #135](https://github.com/hectororm/hectororm/issues/135). `HasOneChild` is not introduced by this change.

## Explicit collection changes

```php
$lines = $order->lines;
unset($lines[0]);            // Explicit removal, processed when saving the order.
$order->save();
```

Replacing an offset also removes its previous entity. Removing and then reattaching the same entity before saving does
not delete it. A transient child removed before persistence produces no SQL DELETE; a pending insert for that removed
child is cancelled when it has not already been persisted.

When the policy is **explicitly configured**, assigning another collection replaces the previously materialized members:

```php
$order->lines;                         // Materialize the relation.
$order->lines = new Collection([$kept]);
$order->save();                        // Process known members absent from the new collection.
```

- Only previously materialized members and explicitly tracked removals are candidates for removal.
- Assigning an empty collection to an **unloaded** relation does not clear unseen database rows.
- Replacing a filtered or limited collection never removes rows outside the loaded view.
- Assigning `null` with an explicit policy is equivalent to assigning an empty collection, with the same rules.
- Without an explicit policy, pre-v2 whole-collection assignment keeps its historical behavior (no inferred difference).
- Query hydration is not user replacement and never creates removal records.
- `getRelated()->unset('lines')` only invalidates the cache. Save pending removals before discarding their collection.

This is deliberately not a database-wide collection synchronization API. Query filters, grouping and limits do not define
ownership of all matching or missing rows. To remove children, load the intended entities and explicitly remove them.

For existing linked children whose non-link fields change, continue to use `save(cascade: true)`.

## Other relation types

A parent reference (`ManyToOne`, currently exposed by `HasOne`) cannot apply `orphanRemoval` to its parent. A many-to-many
relationship removes pivot links, not the shared target entities. Passing a non-null `orphanRemoval` option to unsupported
relationships throws a `RelationException`; it is not silently ignored.

Inverting a relationship does not copy its deletion policy to the opposite direction.

## Atomic writes and rollback

Saving a materialized graph containing a parent-child lifecycle relation runs its linking/removal writes and the parent
save in one transaction. Direct `OneToMany::linkNative()` calls also protect their child operations. Removed links are
released before new children are saved, allowing replacements under unique constraints.

On failure, the transaction restores the captured mapped properties (including generated identifiers and uninitialized
typed properties), original ORM data, entity statuses, relation caches and collection removal tracking. Pending user
edits and the desired replacement remain available for correction and retry. Custom non-mapped state or external side
effects of event listeners are not undone. After-save/delete events are not after-commit notifications. A vetoed child
removal or save aborts the lifecycle operation rather than silently discarding removal tracking.

The transaction scope is **one connection**. Cross-connection materialized graphs are rejected before their captured
entities are written; this feature does not provide distributed transactions. `persist()` batches containing lifecycle
relations likewise require a single connection and retain snapshots until the complete batch succeeds. SQL writes
performed outside the ORM cannot be tracked in memory.

When a caller already owns a transaction, a savepoint isolates a failing lifecycle operation. A successful savepoint
release is not a commit of the outer transaction. If the caller subsequently rolls back that outer transaction, discard
or reload the affected entity graph before reuse, as with other external database changes. Use a transactional storage
engine with savepoint support (for example InnoDB or SQLite); do not execute implicitly committing DDL inside callbacks.

Transactions do not implement optimistic versioning of relationship assignments. Applications performing concurrent
reparenting must coordinate their updates/locking. Keep FK and uniqueness constraints in the database.

## Parent deletion

`orphanRemoval` handles removal from an association, not deletion of its parent. Parent deletion remains governed by SQL
FK actions (`CASCADE`, `SET NULL`, `RESTRICT`) or explicit ORM deletes. SQL cascades do not dispatch child ORM events.

## Migration checklist

1. Declare `orphanRemoval: true` on existing `HasMany` relations that intentionally delete removed children.
2. Use `false` for detachable children; ensure all linking columns are nullable and are not primary-key columns.
3. Review whole-collection assignments when opting in: known missing members now follow the declared policy.
4. Keep many-to-many target deletion separate from pivot removal.
5. Retain explicit FK actions for parent deletion and uniqueness constraints for one-to-one associations.

Follow [#146](https://github.com/hectororm/hectororm/issues/146) for this foundation and
[#147](https://github.com/hectororm/hectororm/issues/147) for the v2 transition.
